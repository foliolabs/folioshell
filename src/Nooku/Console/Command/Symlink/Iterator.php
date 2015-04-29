<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/nooku/wordpress-console for the canonical source repository
 */

namespace Nooku\Console\Command\Symlink;

class Iterator extends \RecursiveIteratorIterator
{
    protected $source;
    protected $target;

    /**
     * @param string $source Source dir (usually from an IDE workspace)
     * @param string $target Target dir (usually where a wordpress installation resides)
     */
    public function __construct($source, $target)
    {
        if(!is_dir($target.'/wp-content')) {
            throw new \InvalidArgumentException('Invalid WordPress folder passed');
        }

        $this->source = $source;
        $this->target = $target.'/wp-content';

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
        $target = $this->target.$target;

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
        if (!file_exists($target)) {
            `ln -sf $source $target`;
        }
    }
}