<?php
/**
 * @copyright   Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     Mozilla Public License, version 2.0
 * @link        https://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PluginUninstall extends Command
{
    protected function configure()
    {
        $this->setName('plugin:uninstall')
             ->setDescription('Used for uninstalling plugins, i.e. wordpress console command bundles')
             ->addArgument('package', InputArgument::REQUIRED, 'The composer package containing the plugin to uninstall');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $plugins = $this->getApplication()->getPlugins();
        $path    = $this->getApplication()->getPluginPath();

        $package = $input->getArgument('package');

        $result = `command -v composer >/dev/null 2>&1 || { echo >&2 "false"; }`;

        if ($result == 'false')
        {
            $output->writeln('<error>Composer was not found. It is either not installed or globally available.</error>');
            return;
        }

        if (!array_key_exists($package, $plugins))
        {
            $output->writeln('<error>Error:</error>The package "' . $package . '" is not installed');
            return;
        }

        passthru("composer --working-dir=$path --update-with-dependencies remove $package");
    }
}
