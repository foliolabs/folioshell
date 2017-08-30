<?php
/**
 * @copyright   Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     Mozilla Public License, version 2.0
 * @link        https://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command;

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
     * WP_CLI executable path
     *
     * @var string
     */
    protected $wp;

    protected function configure()
    {
        parent::configure();

        $this->wp  = realpath(__DIR__.'/../../../vendor/bin/wp');

        if (!self::$files) {
            self::$files = realpath(__DIR__.'/../../../bin/.files');
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
            ->addOption(
                'http-port',
                null,
                InputOption::VALUE_REQUIRED,
                'The HTTP port the virtual host should listen to',
                8080
            )
            ->addOption(
                'disable-ssl',
                null,
                InputOption::VALUE_NONE,
                'Disable SSL for this site'
            )
            ->addOption(
                'ssl-crt',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full path to the signed cerfificate file',
                '/etc/apache2/ssl/server.crt'
            )
            ->addOption(
                'ssl-key',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full path to the private cerfificate file',
                '/etc/apache2/ssl/server.key'
            )
            ->addOption(
                'ssl-port',
                null,
                InputOption::VALUE_OPTIONAL,
                'The port on which the server will listen for SSL requests',
                '443'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->symlink = $input->getOption('symlink');
        if (is_string($this->symlink)) {
            $this->symlink = explode(',', $this->symlink);
        }

        $this->check($input, $output);
        $this->createFolder($input, $output);
        $this->createDatabase($input, $output);
        $this->modifyConfiguration($input, $output);
        $this->installWordPress($input, $output);
        $this->addVirtualHost($input, $output);
        $this->symlinkProjects($input, $output);
        $this->installExtensions($input, $output);

        $output->writeln("Your new <info>WordPress $this->version</info> site has been created.");
        $output->writeln("It was installed using the domain name <info>$this->site.dev</info>.");
        $output->writeln("Don't forget to add <info>$this->site.dev</info> to your <info>/etc/hosts</info>");
        $output->writeln("You can login using the following username and password combination: <info>admin</info>/<info>admin</info>.");
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('A site with name %s already exists', $this->site));
        }

        $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);
        $result = exec(sprintf(
                "echo 'SHOW DATABASES LIKE \"%s\"' | mysql -u'%s' %s",
                $this->target_db, $this->mysql->user, $password
            )
        );

        if (!empty($result)) { // Table exists
            throw new \RuntimeException(sprintf('A database with name %s already exists', $this->target_db));
        }
    }

    public function createFolder(InputInterface $input, OutputInterface $output)
    {
        $version = $input->getOption('wordpress');

        `mkdir -p $this->target_dir`;
        `{$this->wp} core download --path=$this->target_dir --version=$version`;
    }

    public function createDatabase(InputInterface $input, OutputInterface $output)
    {
        $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);
        $result = exec(
            sprintf(
                "echo 'CREATE DATABASE `%s` CHARACTER SET utf8' | mysql -u'%s' %s",
                $this->target_db, $this->mysql->user, $password
            )
        );
    }

    public function modifyConfiguration(InputInterface $input, OutputInterface $output)
    {
        `{$this->wp} config create --path={$this->target_dir} --dbname={$this->target_db} --dbuser={$this->mysql->user} --dbpass={$this->mysql->password} --extra-php="define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', true );"`;
    }

    public function installWordPress(InputInterface $input, OutputInterface $output)
    {
        `{$this->wp} core install --url=$this->site.dev --path=$this->target_dir --title=$this->site --admin_user=admin --admin_password=admin --admin_email=admin@$this->site.dev`;
        `{$this->wp} user update admin --role=administrator --path=$this->target_dir`;
    }

    public function addVirtualHost(InputInterface $input, OutputInterface $output)
    {
        if (is_dir('/etc/apache2/sites-available'))
        {
            $tmp = self::$files.'/.vhost.tmp';

            $template = file_get_contents(self::$files.'/vhost.conf');

            file_put_contents($tmp, sprintf($template, $this->site, $this->target_dir, $input->getOption('http-port')));

            if (!$input->getOption('disable-ssl'))
            {
                $ssl_crt = $input->getOption('ssl-crt');
                $ssl_key = $input->getOption('ssl-key');
                $ssl_port = $input->getOption('ssl-port');

                if (file_exists($ssl_crt) && file_exists($ssl_key))
                {
                    $template = "\n\n" . file_get_contents(self::$files . '/vhost.ssl.conf');
                    file_put_contents($tmp, sprintf($template, $this->site, $this->target_dir, $ssl_port, $ssl_crt, $ssl_key), FILE_APPEND);
                }
                else $output->writeln('<comment>SSL was not enabled for the site. One or more certificate files are missing.</comment>');
            }

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
                'extension:symlink',
                'site'    => $input->getArgument('site'),
                'symlink' => $this->symlink,
                '--www'   => $this->www,
                '--projects-dir' => $input->getOption('projects-dir')
            ));
            $symlink = new Extension\Symlink();

            $symlink->run($symlink_input, $output);

            $this->symlinked_projects = $symlink->getProjects();
        }
    }

    public function installExtensions(InputInterface $input, OutputInterface $output)
    {
        if ($this->symlinked_projects)
        {
            $plugin_input = new ArrayInput(array(
                'extension:install',
                'site'           => $input->getArgument('site'),
                'extension'      => $this->symlinked_projects,
                '--www'          => $this->www,
                '--projects-dir' => $input->getOption('projects-dir')
            ));
            $installer = new ExtensionInstall();

            $installer->run($plugin_input, $output);
        }
    }
}
