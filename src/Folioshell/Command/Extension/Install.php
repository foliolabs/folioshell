<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		https://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command\Extension;

use Folioshell\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Command\AbstractSite
{
    protected $plugin = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:install')
            ->setDescription('Install plugins into a site')
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

        $this->plugin = $input->getArgument('extension');

        $this->check($input, $output);
        $this->install($input, $output);

        return 0;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function install(InputInterface $input, OutputInterface $output)
    {
        // Always Activate Koowa first
        if(in_array('foliokit', $this->plugin)) {
            $output->writeln(Command\Wp::call("plugin activate --path=$this->target_dir foliokit"));
            $output->writeln("<info>FolioKit</info> has been activated.");
        }

        // Install Plugins from the Projects Folder
        foreach($this->plugin as $plugin)
        {
            if(!in_array($plugin, ['foliokit', 'kodekit']))
            {
                $output->writeln(Command\Wp::call("plugin activate --path=$this->target_dir $plugin"));
                $output->writeln("Plugin <info>$plugin</info> has been activated.");
            }
        }
    }
}