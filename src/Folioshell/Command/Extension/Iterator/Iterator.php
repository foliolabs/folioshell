<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command\Extension\Iterator;

use Symfony\Component\Console\Output\OutputInterface;

class Iterator extends \RecursiveIteratorIterator
{
    protected $source;
    protected $target;

    protected $_output;

    /**
     * @param string $source Source dir (usually from an IDE workspace)
     * @param string $target Target dir (usually where a joomla installation resides)
     */
    public function __construct($source, $target)
    {
        $this->source = $source;
        $this->target = $target;

        parent::__construct(new \RecursiveDirectoryIterator($source));
    }

    public function callHasChildren()
    {
        $filename = $this->getFilename();
        if ($filename[0] == '.') {
            return false;
        }

        $source = $this->key();

        $target = str_replace($this->source, '', $source);
        $target = str_replace('/site', '', $target);
        $target = trim(str_replace($this->target, '', $target), '/');
        $target = rtrim($this->target, '/').'/'.$target;

        if (is_link($target)) {
            unlink($target);
        }

        if (!is_dir($target))
        {
            $this->createLink($source, $target);
            return false;
        }

        return parent::callHasChildren();
    }

    public function createLink($source, $target)
    {
        if (!file_exists($target))
        {
            if (!is_null($this->_output) && $this->_output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $this->_output->writeln(" * creating link: `$target` -> `$source`");
            }

            `ln -sf $source $target`;
        }
    }

    public function setOutput(OutputInterface $output)
    {
        $this->_output = $output;
    }
}