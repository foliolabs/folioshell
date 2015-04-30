<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/nooku/wordpress-console for the canonical source repository
 */

namespace Nooku\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Nooku\Console\Joomla\Bootstrapper;

class ExtensionInstall extends SiteAbstract
{
    protected $extension = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:install')
            ->setDescription('Install extensions into a site')
            ->addArgument(
                'extension',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'A list of extensions to install to the site using discover install'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                sprintf('%s/Projects', trim(`echo ~`))
            );;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->extension = $input->getArgument('extension');

        $this->check($input, $output);
        $this->install($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function install(InputInterface $input, OutputInterface $output)
    {
        $wp_cli   = realpath(__DIR__.'/../../../../vendor/bin/wp');
        $projects = array();

        foreach($this->extension as $plugin )
        {
            $projects[] = $plugin;
            $projects   = array_merge($projects, $this->_getDependencies($plugin));
        }

        $plugins = $this->getProjectPlugins($projects, $input->getOption('projects-dir'));

        // Always Activate Koowa first
        if(in_array('koowa', $plugins)) {
            `$wp_cli plugin activate --path=$this->target_dir koowa`;
            $output->writeln("<info>Nooku Framework</info> has been activated.");
        }

        foreach($plugins as $plugin) {
            if($plugin != 'koowa') {
                `$wp_cli plugin activate --path=$this->target_dir $plugin`;
                $output->writeln("Plugin <info>$plugin</info> has been activated.");
            }
        }
    }

    protected function getProjectPlugins($projects, $project_folder)
    {
        $plugins = array();

        foreach((array) $projects as $project)
        {
            $plugins_folder = $project_folder.'/'.$project.'/code/plugins';

            if(is_dir($plugins_folder))
            {
                $dir = new \RecursiveDirectoryIterator($plugins_folder);

                foreach($dir as $plugin)
                {
                    if($plugin->isDir() && is_file($plugin.'/'.$plugin->getFilename().'.php')) {
                        $plugins[] = $plugin->getFilename();
                    }
                }
            }
        }

        return $plugins;
    }
}