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
use Symfony\Component\Console\Output\OutputInterface;

class InstallFile extends Command\AbstractSite
{
    protected $plugin = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:installfile')
            ->setDescription('Install packaged plugins for file or directory into a site')
            ->addArgument(
                'extension',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'A list of full paths to plugin packages (package file or url) to install'
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
        foreach ($this->plugin as $package)
        {
            $result = Command\WP::call("plugin install $package --activate --path=$this->target_dir");

            if(preg_match('/Plugin installed successfully/i', $result)) {
                $output->writeln("Success! Plugin <info>$package</info> Installed and Activated!");
            }
            else $output->writeln("Plugin <info>$package</info> cannot be installed.");
        }
    }
}
