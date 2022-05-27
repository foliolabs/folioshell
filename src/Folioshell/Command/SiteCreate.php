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

class SiteCreate extends AbstractSite
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
    
    protected $symlinked_projects;

    /**
     * WP_CLI executable path
     *
     * @var string
     */
    protected $wp;

    protected function configure()
    {
        parent::configure();

        if (!self::$files) {
            self::$files = realpath(__DIR__.'/../../../bin/.files');
        }

        $this
            ->setName('site:create')
            ->setDescription('Create a WordPress site')
            ->addOption(
                'release',
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
                80
            )
            ->addOption(
                'ssl-port',
                null,
                InputOption::VALUE_OPTIONAL,
                'The port on which the server will listen for SSL requests',
                '443'
            )
            ->addOption(
                'vhost', 
                null,
                InputOption::VALUE_NEGATABLE,
                'Create an Apache vhost for the site',
                true
            )
            ->addOption(
                'vhost-template',
                null,
                InputOption::VALUE_REQUIRED,
                'Custom file to use as the Apache vhost configuration. Make sure to include HTTP and SSL directives if you need both.',
                null
            )
            ->addOption(
                'vhost-folder',
                null,
                InputOption::VALUE_REQUIRED,
                'The Apache2 vhost folder',
                null
            )
            ->addOption(
                'vhost-filename',
                null,
                InputOption::VALUE_OPTIONAL,
                'The Apache2 vhost file name',
                null,
            )
            ->addOption(
                'vhost-restart-command',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full command for restarting Apache2',
                null
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->version = $input->getOption('release');

        $this->symlink = $input->getOption('symlink');
        if (is_string($this->symlink)) {
            $this->symlink = explode(',', $this->symlink);
        }

        $this->check($input, $output);
        $this->createFolder($input, $output);
        $this->createDatabase($input, $output);
        $this->modifyConfiguration($input, $output);
        $this->installWordPress($input, $output);

        if ($input->getOption('vhost')) {
            $this->addVirtualHost($input, $output);
        }

        $this->symlinkProjects($input, $output);
        $this->installExtensions($input, $output);

        /*
         * Run all site:create:* commands after site creation
         */
        try {
            $commands = $this->getApplication()->all('site:create');
            
            foreach ($commands as $command) {
                $arguments = array(
                    $command->getName(),
                    'site'   => $this->site,
                    '--www'  => $this->www
                );
                $command->setApplication($this->getApplication());
                $command->run(new ArrayInput($arguments), $output);
            }
        }
        catch (\Symfony\Component\Console\Exception\NamespaceNotFoundException $e) {}
        catch (\Symfony\Component\Console\Exception\CommandNotFoundException $e) {}
        
        $output->writeln("Your new <info>WordPress $this->version</info> site has been created.");
        $output->writeln("It was installed using the domain name <info>$this->site.test</info>.");
        $output->writeln("You can login using the following username and password combination: <info>admin</info>/<info>admin</info>.");

        return 0;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (file_exists($this->target_dir) && !is_dir($this->target_dir)) {
            throw new \RuntimeException(sprintf('A file named \'%s\' already exists', $this->site));
        }

        if (is_dir($this->target_dir) && count(scandir($this->target_dir)) > 2) {
            throw new \RuntimeException(sprintf('Site directory \'%s\' is not empty.', $this->site));
        }

        $result = $this->_executeSQL(sprintf("SHOW DATABASES LIKE \"%s\"", $this->target_db));

        if (!empty($result)) { // Table exists
            throw new \RuntimeException(sprintf('A database with name %s already exists', $this->target_db));
        }
    }

    public function createFolder(InputInterface $input, OutputInterface $output)
    {
        $version = $input->getOption('release');

        `mkdir -p $this->target_dir`;
        $output->writeln(WP::call("core download --path=$this->target_dir --version=$version"));
    }

    public function createDatabase(InputInterface $input, OutputInterface $output)
    {
        $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);
        exec(
            sprintf(
                "echo 'CREATE DATABASE `%s` CHARACTER SET utf8' | mysql -u'%s' %s",
                $this->target_db, $this->mysql->user, $password
            )
        );
    }

    public function modifyConfiguration(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(WP::call("config create --path={$this->target_dir} --dbname={$this->target_db} --dbuser={$this->mysql->user} --dbpass={$this->mysql->password}"));
    }

    public function installWordPress(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(WP::call("core install --url=$this->site.test --path=$this->target_dir --title=$this->site --admin_user=admin --admin_password=admin --admin_email=admin@$this->site.test"));
        $output->writeln(WP::call("user update admin --role=administrator --path=$this->target_dir"));

        $roles = ['author', 'contributor', 'editor', 'subscriber'];

        foreach ($roles as $role) {
            $command = "user create {$role} {$role}@{$this->site}.test --user_pass={$role} --role={$role} --path={$this->target_dir}";
            $output->writeln(WP::call($command));
        }

    }

    public function addVirtualHost(InputInterface $input, OutputInterface $output)
    {
        $command_input = array(
            'vhost:create',
            'site'          => $this->site,
            '--http-port'   => $input->getOption('http-port'),
            '--ssl-port'    => $input->getOption('ssl-port'),
            '--www'         => $input->getOption('www'),
        );

        foreach (array('template', 'folder', 'filename', 'restart-command') as $vhostkey) {
            if ($input->getOption('vhost-'.$vhostkey) !== null) {
                $command_input['--'.$vhostkey] = $input->getOption('vhost-'.$vhostkey);
            }
        }

        $command = new Vhost\Create();
        $command->run(new ArrayInput($command_input), $output);
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
            $installer = new Extension\Install();

            $installer->run($plugin_input, $output);
        }
    }
}
