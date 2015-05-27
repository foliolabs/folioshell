<?php
/**
 * @copyright   Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     Mozilla Public License, version 2.0
 * @link        http://github.com/nooku/wordpress-console for the canonical source repository
 */

namespace Nooku\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginList extends Command
{
    protected function configure()
    {
        $this->setName('plugin:list')
             ->setDescription('List installed plugins');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $plugins = $this->getApplication()->getPlugins();

        $packages = array_keys($plugins);
        $versions = array_values($plugins);

        $combine = function($a, $b) {
            return array($a, $b);
        };

        $rows = array_map($combine, $packages, $versions);

        $headers = array('Plugin package', 'Version');

        $this->getHelperSet()->get('table')
            ->setHeaders($headers)
            ->setRows($rows)
            ->render($output);
    }
}