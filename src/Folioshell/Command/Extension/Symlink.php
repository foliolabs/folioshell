<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command\Extension;

use Folioshell\Command\SiteAbstract;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Folioshell\Command\Extension\Iterator\Iterator;

class Symlink extends SiteAbstract
{
    protected $symlink  = array();
    protected $projects = array();

    protected static $_symlinkers = array();

    protected static $_dependencies = array();

    public static function registerDependencies($project, array $dependencies)
    {
        static::$_dependencies[$project] = $dependencies;
    }

    public static function registerSymlinker($symlinker)
    {
        array_unshift(static::$_symlinkers, $symlinker);
    }

    /**
     * @return array
     */
    public function getProjects()
    {
        return $this->projects;
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:symlink')
            ->setDescription('Symlink projects into a site')
            ->setHelp(<<<EOL
This command will symlink the directories from the <comment>--projects-dir</comment> directory into the given site. This is ideal for testing your custom extensions while you are working on them.
You can either put your plugin files into a code/ folder or your source code should resemble the Wordpress directory structure for symlinking to work well. For example, the directory structure of your plugin should look like this:

* wp-content/plugins/foobar

To symlink <comment>foobar</comment> into your tesite:

    <info>folioshell extension:symlink testsite foobar</info>

You can now use the <comment>extension:install</comment> command to make your component available to Wordpress.

Note that you can use the <comment>site:create</comment> command to both create a new site and symlink your projects into it using the <comment>--symlink</comment> flag.
EOL
            )
            ->addArgument(
                'symlink',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'A list of directories to symlink from projects directory. Use \'all\' to symlink every directory.'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                sprintf('%s/Projects', trim(`echo ~`))
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $path = dirname(dirname(dirname(__FILE__))).'/Symlinkers';

        if (file_exists($path))
        {
            foreach (glob($path.'/*.php') as $symlinker) {
                require_once $symlinker;
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->symlink = $input->getArgument('symlink');

        if (count($this->symlink) == 1 && $this->symlink[0] == 'all')
        {
            $this->symlink = array();
            $source = $input->getOption('projects-dir') . '/*';

            foreach(glob($source, GLOB_ONLYDIR) as $directory) {
                $this->symlink[] = basename($directory);
            }
        }

        $this->projects = array();
        foreach ($this->symlink as $symlink)
        {
            $this->projects[] = $symlink;
            $this->projects   = array_unique(array_merge($this->projects, $this->_getDependencies($symlink)));
        }

        $this->check($input, $output);
        $this->symlinkProjects($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }

        $project_dir = $input->getOption('projects-dir');
        foreach ($this->projects as $project)
        {
            $root =  $project_dir . '/' . $project;

            if (!is_dir($root)) {
                throw new \RuntimeException(sprintf('`%s` could not be found in %s', $project, $project_dir));
            }
        }
    }

    public function symlinkProjects(InputInterface $input, OutputInterface $output)
    {
        $project_directory = $input->getOption('projects-dir');

        foreach ($this->projects as $project)
        {
            $result = false;
            $root   = $project_directory.'/'.$project;

            if (!is_dir($root)) {
                continue;
            }

            foreach (static::$_symlinkers as $symlinker)
            {
                $result = call_user_func($symlinker, $root, $this->target_dir, $project, $this->projects, $output);

                if ($result === true) {
                    break;
                }
            }

            if (!$result) {
                $this->_symlink($root, $this->target_dir, $output);
            }
        }
    }

    /**
     * Default symlinker
     *
     * @param $project
     * @param $destination
     * @param $output
     * @return bool
     */
    protected function _symlink($project, $destination, OutputInterface $output)
    {
        if (is_dir($project.'/code')) {
            $project .= '/code';
        }

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln("Symlinking `$project` into `$destination`");
        }

        /**
         * Special case: Plugin code exists in the root
         */
        if (!is_dir($project.'/wp-content'))
        {
            $plugin_name = null;

            foreach (glob($project.'/*.php') as $file)
            {
                if (preg_match('#Plugin Name: ([a-z0-9\-_\.]+)\b#i', file_get_contents($file), $matches)) {
                    $plugin_name = pathinfo($file, PATHINFO_FILENAME);
                    break;
                }
            }

            if ($plugin_name === null) {
                return false;
            }

            $code_destination = $destination.'/wp-content/plugins/'.$plugin_name;

            if (!file_exists($code_destination))
            {
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln(" * creating link `$code_destination` -> $project");
                }

                `ln -sf $project $code_destination`;
            }

            return true;
        }

        $iterator = new Iterator($project, $destination);
        $iterator->setOutput($output);

        while ($iterator->valid()) {
            $iterator->next();
        }

        return true;
    }

    /**
     * Look for the dependencies of the dependency
     *
     * @param  string $project      The directory name of Project
     * @return array                An array of dependencies
     */
    protected function _getDependencies($project)
    {
        $projects     = array();
        $dependencies = static::$_dependencies;

        if(array_key_exists($project, $dependencies) && is_array($dependencies[$project]))
        {
            $projects = $dependencies[$project];

            foreach ($projects as $dependency) {
                $projects = array_merge($projects, $this->_getDependencies($dependency));
            }
        }

        return $projects;
    }
}