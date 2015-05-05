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
                'A list of plugins(slug) to install downloaded from the official WordPress Plugin Repo.'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                sprintf('%s/Projects', trim(`echo ~`))
            );
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
        $wp_cli          = realpath(__DIR__.'/../../../../vendor/bin/wp');
        $plugins         = array();
        $projects_dir = $input->getOption('projects-dir');

        foreach($this->extension as $plugin )
        {
            $plugins[] = $plugin;
            $plugins   = array_merge($plugins, $this->_getDependencies($plugin));
        }

        $project_plugins   = $this->getProjectPlugins($plugins, $projects_dir);
        $wordpress_plugins = $this->getWordPressPlugins($plugins);

        // Always Activate Koowa first
        if(in_array('koowa', $project_plugins)) {
            `$wp_cli plugin activate --path=$this->target_dir koowa`;
            $output->writeln("<info>Nooku Framework</info> has been activated.");
        }

        // Install Plugins from the Projects Folder
        foreach($project_plugins as $plugin)
        {
            if($plugin != 'koowa')
            {
                `$wp_cli plugin activate --path=$this->target_dir $plugin`;
                $output->writeln("Plugin <info>$plugin</info> has been activated.");
            }
        }

        // Install Plugins from the Projects Folder
        foreach($wordpress_plugins as $plugin => $title)
        {
            $output->writeln("Installing <info>$title</info> from the WordPress Repostory.");
            `$wp_cli plugin install $plugin --activate --path=$this->target_dir`;
            $output->writeln("Success! Plugin <info>$title</info> Installed and Activated!");
        }

        if(count($plugins))
        {
            $leftover = implode(', ', $plugins);
            $output->writeln("Cannot find plugin/s <info>$leftover</info> from $projects_dir or at the WordPress Repository.");
        }
    }

    protected function getWordPressPlugins(&$plugins)
    {
        $wp_cli  = realpath(__DIR__.'/../../../../vendor/bin/wp');
        $results = array();

        foreach((array) $plugins as $index => $plugin)
        {
            $result = `$wp_cli plugin search $plugin --format=json --path=$this->target_dir`;
            $result = json_decode(substr($result, strpos($result, '[')));

            if(is_array($result))
            {
                // Install only the result whose slug is equal to the plugin being installed
                $result = array_filter($result, function($data) use($plugin){
                    if($data->slug == $plugin) {
                        return true;
                    }
                });

                if(count($result) == 1)
                {
                    $result = array_pop($result);
                    $results[$result->slug] = $result->name;
                    unset($plugins[$index]);
                }
            }
        }

        return $results;
    }

    protected function getProjectPlugins(&$projects, $projects_dir)
    {
        $plugins = array();

        foreach((array) $projects as $index => $project)
        {
            $plugins_folder = $projects_dir.'/'.$project.'/code/plugins';

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

            if(is_dir($projects_dir.'/'.$project)) {
                unset($projects[$index]);
            }
        }

        return $plugins;
    }
}