<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		https://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Folioshell\Command\Symlink\Iterator;

class Symlink extends Command
{
    protected function configure()
    {
        $this
            ->setName('symlink')
            ->setDescription('Symlink plugins into WordPress sites for easier development')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Source dir (usually from an IDE workspace)'
            )
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Target dir (A valid WordPress installation)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source = realpath($input->getArgument('source'));
        $target = realpath($input->getArgument('target'));

        if ($source === false || $target === false) {
            throw new \InvalidArgumentException('Invalid folders passed');
        }

        $iterator = new Iterator($source, $target);

        while ($iterator->valid()) {
            $iterator->next();
        }
    }
}