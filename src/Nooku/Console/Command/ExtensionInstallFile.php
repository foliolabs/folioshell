<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/nooku/wordpress-console for the canonical source repository
 */

namespace Nooku\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Nooku\Console\Joomla\Bootstrapper;

class ExtensionInstallFile extends SiteAbstract
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
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function install(InputInterface $input, OutputInterface $output)
    {
        $wp_cli = realpath(__DIR__.'/../../../../vendor/bin/wp');

        foreach ($this->plugin as $package)
        {
            $result = `$wp_cli plugin install $package --activate --path=$this->target_dir`;

            if(preg_match('/Plugin installed successfully/i', $result)) {
                $output->writeln("Success! Plugin <info>$package</info> Installed and Activated!");
            }
            else $output->writeln("Plugin <info>$package</info> cannot be installed.");
        }
    }
}
