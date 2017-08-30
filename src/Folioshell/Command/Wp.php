<?php
/**
 * @copyright   Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     Mozilla Public License, version 2.0
 * @link        https://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Wp extends Command
{
    protected static $wp;

    /**
     * Call WP CLI with arguments
     *
     * @param $arguments
     * @return mixed
     */
    public static function call($arguments)
    {
        if (!static::$wp) {
            static::$wp = realpath(__DIR__ . '/../../../vendor/bin/wp');
        }

        $wp = static::$wp;

        return `{$wp} {$arguments}`;
    }

    protected function configure()
    {
        parent::configure();

        $this->setName('wp')
            ->setDescription('Run WP CLI commands with the syntax "folioshell box wp -- plugin activate"')
            ->addArgument('arguments', InputArgument::IS_ARRAY, 'Original arguments');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $arguments = $input->getArgument('arguments');

        if (is_array($arguments)) {
            $arguments = implode(' ', $arguments);
        }

        $output->writeln(static::call($arguments));
    }
}