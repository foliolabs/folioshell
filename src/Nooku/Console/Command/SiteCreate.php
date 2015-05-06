<?php
/**
 * @copyright   Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     Mozilla Public License, version 2.0
 * @link        http://github.com/nooku/wordpress-console for the canonical source repository
 */

namespace Nooku\Console\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SiteCreate extends SiteAbstract
{
    /**
     * File cache
     *
     * @var string
     */
    protected static $files;

    /**
     * Downloaded WordPress tarball
     *
     * @var
     */
    protected $source_tarball;

    /**
     * Path to database export in WordPress tarball
     *
     * @va

    /**
     * Sample data to install
     *
     * @var string
     */
    protected $sample_data;

    /**
     * Clear cache before fetching versions
     * @var bool
     */
    protected $clear_cache = false;

    protected $template;

    /**
     * WordPress version to install
     *
     * @var string
     */
    protected $version;

    /**
     * Projects to symlink
     * @var array
     */
    protected $symlink = array();

    /**
     * @var Versions
     */
    protected $versions;

    /**
     *
     */

    protected function configure()
    {
        parent::configure();

        if (!self::$files) {
            self::$files = realpath(__DIR__.'/../../../../bin/.files');
        }

        $this
            ->setName('site:create')
            ->setDescription('Create a WordPress site')
            ->addOption(
                'wordpress',
                null,
                InputOption::VALUE_REQUIRED,
                "WordPress version. Can be a release number (3.2, 4.2.1, ..) or branch name. Run `wordpress versions` for a full list.\nUse \"none\" for an empty virtual host.",
                'latest'
            )
            ->addOption(
                'symlink',
                null,
                InputOption::VALUE_REQUIRED,
                'A comma separated list of folders to symlink from projects folder'
            )
            ->addOption(
                'clear-cache',
                null,
                InputOption::VALUE_NONE,
                'Update the list of available tags and branches from the WordPress repository'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                sprintf('%s/Projects', trim(`echo ~`))
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->versions = new Versions();

        if ($input->getOption('clear-cache')) {
            $this->versions->refresh();
        }

        $this->setVersion($input->getOption('wordpress'));

        $this->symlink = $input->getOption('symlink');
        if (is_string($this->symlink)) {
            $this->symlink = explode(',', $this->symlink);
        }

        $this->check($input, $output);
        $this->createFolder($input, $output);
        $this->createDatabase($input, $output);
        $this->modifyConfiguration();
        $this->installWordPress($input, $output);
        $this->addVirtualHost($input, $output);
        $this->symlinkProjects($input, $output);
        $this->installPlugins($input, $output);

        if ($this->version)
        {
            $output->writeln("Your new <info>WordPress $this->version</info> site has been created.");
            $output->writeln("It was installed using the domain name <info>$this->site.dev</info>.");
            $output->writeln("Don't forget to add <info>$this->site.dev</info> to your <info>/etc/hosts</info>");
            $output->writeln("You can login using the following username and password combination: <info>admin</info>/<info>admin</info>.");
        }
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('A site with name %s already exists', $this->site));
        }

        if ($this->version)
        {
            $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);
            $result = exec(sprintf(
                    "echo 'SHOW DATABASES LIKE \"%s\"' | mysql -u'%s' %s",
                    $this->target_db, $this->mysql->user, $password
                )
            );

            if (!empty($result)) { // Table exists
                throw new \RuntimeException(sprintf('A database with name %s already exists', $this->target_db));
            }

            $this->source_tarball = $this->getTarball($this->version, $output);
            if(!file_exists($this->source_tarball)) {
                throw new \RuntimeException(sprintf('File %s does not exist', $this->source_tarball));
            }
        }
    }

    public function createFolder(InputInterface $input, OutputInterface $output)
    {
        `mkdir -p $this->target_dir`;

        if ($this->version)
        {
            `cd $this->target_dir; tar xzf $this->source_tarball --strip 1`;

            if ($this->versions->isBranch($this->version)) {
                unlink($this->source_tarball);
            }
        }
    }

    public function createDatabase()
    {
        if (!$this->version) {
            return;
        }

        $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);
        $result = exec(
            sprintf(
                "echo 'CREATE DATABASE `%s` CHARACTER SET utf8' | mysql -u'%s' %s",
                $this->target_db, $this->mysql->user, $password
            )
        );
    }

    public function installWordPress()
    {
        $wp_cli = realpath(__DIR__.'/../../../../vendor/bin/wp');
        `$wp_cli core install --url=$this->site.dev --path=$this->target_dir --title=$this->site --admin_user=admin --admin_password=admin --admin_email=admin@$this->site.dev`;
    }

    public function modifyConfiguration()
    {
        if (!$this->version) {
            return;
        }

        $source   = $this->target_dir.'/wp-config-sample.php';
        $target   = $this->target_dir.'/wp-config.php';

        $contents = file_get_contents($source);

        $random   = function($length = 50) {
            $charset ='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+';
            $string  = '';
            $count   = strlen($charset);

            while ($length--) {
                $string .= $charset[mt_rand(0, $count-1)];
            }

            return $string;
        };

        $replacements = array(
            'database_name_here'           => $this->target_db,
            'username_here'                => $this->mysql->user,
            'password_here'                => $this->mysql->password,
            'define(\'WP_DEBUG\', false);' => 'define(\'WP_DEBUG\', true);'."\n".'define(\'WP_USE_EXT_MYSQL\', false);',
            'put your unique phrase here'  => $random()
        );

        foreach($replacements as $key => $value) {
            $contents = str_replace($key, $value, $contents);
        }

        file_put_contents($target, $contents);
        chmod($target, 0644);

        `rm $source`;
    }

    public function addVirtualHost(InputInterface $input, OutputInterface $output)
    {
        if (is_dir('/etc/apache2/sites-available'))
        {
            $template = file_get_contents(self::$files.'/vhost.conf');
            $contents = sprintf($template, $this->site);

            $tmp = self::$files.'/.vhost.tmp';

            file_put_contents($tmp, $contents);

            `sudo tee /etc/apache2/sites-available/1-$this->site.conf < $tmp`;
            `sudo a2ensite 1-$this->site.conf`;
            `sudo /etc/init.d/apache2 restart > /dev/null 2>&1`;

            @unlink($tmp);
        }
    }

    public function symlinkProjects(InputInterface $input, OutputInterface $output)
    {
        if ($this->symlink)
        {
            $symlink_input = new ArrayInput(array(
                'site:symlink',
                'site'    => $input->getArgument('site'),
                'symlink' => $this->symlink,
                '--www'   => $this->www,
                '--projects-dir' => $input->getOption('projects-dir')
            ));
            $symlink = new PluginSymlink();

            $symlink->run($symlink_input, $output);
        }
    }

    public function installPlugins(InputInterface $input, OutputInterface $output)
    {
        if ($this->symlink)
        {
            $plugin_input = new ArrayInput(array(
                'plugin:install',
                'site'           => $input->getArgument('site'),
                'plugin'      => $this->symlink,
                '--www'          => $this->www,
                '--projects-dir' => $input->getOption('projects-dir')
            ));
            $installer = new PluginInstall();

            $installer->run($plugin_input, $output);
        }
    }

    public function setVersion($version)
    {
        $result = $version;

        if (strtolower($version) === 'latest') {
            $result = $this->versions->getLatestRelease();
        }
        else
        {
            $length = strlen($version);
            $format = is_numeric($version) || preg_match('/^\d\.\d+$/im', $version);

            if ( ($length == 1 || $length == 3) && $format)
            {
                $result = $this->versions->getLatestRelease($version);

                if($result == '0.0.0') {
                    $result = $version.($length == 1 ? '.0.0' : '.0');
                }
            }
        }

        $this->version = $result;
    }

    public function getTarball($version, OutputInterface $output)
    {
        $tar   = $this->version.'.tar.gz';
        $cache = self::$files.'/cache/'.$tar;

        if(file_exists($cache) && !$this->versions->isBranch($this->version)) {
            return $cache;
        }

        if ($this->versions->isBranch($version)) {
            $url = 'http://github.com/WordPress/WordPress/tarball/'.$version;
        }
        else {
            $url = 'https://github.com/WordPress/WordPress/archive/'.$version.'.tar.gz';
        }

        $output->writeln("<info>Downloading WordPress $this->version - this could take a few minutes...</info>");
        $bytes = file_put_contents($cache, fopen($url, 'r'));
        if ($bytes === false || $bytes == 0) {
            throw new \RuntimeException(sprintf('Failed to download %s', $url));
        }

        return $cache;
    }
}
