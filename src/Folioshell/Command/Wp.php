<?php
/**
 * @copyright   Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     Mozilla Public License, version 2.0
 * @link        https://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Wp extends AbstractSite
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
            static::$wp = self::_setWPPath();
        }

        $wp = static::$wp;

        return `{$wp} {$arguments}`;
    }

    protected function configure()
    {
        parent::configure();

        $this->setName('wp')
            ->setDescription('Run WP CLI commands with the syntax "folioshell wp sitename -- config list"')
            ->addArgument('arguments', InputArgument::IS_ARRAY, 'Original arguments');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $arguments = $input->getArgument('arguments');

        $has_path = false;
        foreach ($arguments as $arg) {
            if (strpos($arg, '--path') === 0) {
                $has_path = true;
                break;
            }
        }

        if (is_array($arguments)) {
            $arguments = implode(' ', $arguments);
        }

        if (!$has_path) {
            $arguments = '--path='.$this->target_dir.' '.$arguments;
        }

        $output->writeln(static::call($arguments));

        return 0;
    }

    protected static function _setWPPath()
    {
        $dirs = explode(DIRECTORY_SEPARATOR, __DIR__);

        for ($i = count($dirs); $i >= 0; $i--)
        {
            $dir = implode(DIRECTORY_SEPARATOR, array_slice($dirs, 0, $i));

            $binary   = $dir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR .'wp';
            $vendored = $dir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR .'wp';

            if (file_exists($binary)) {
                return $binary;
            }
            elseif (file_exists($vendored)) {
                return $vendored;
            }
        }

        throw new \Exception('Unable to locate bin/wp');
    }
}