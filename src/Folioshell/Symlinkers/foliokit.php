<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/foliolabs/folioshell for the canonical source repository
 */

use Folioshell\Command\Extension;
use Symfony\Component\Console\Output\OutputInterface;

Extension\Symlink::registerDependencies('foliokit', ['kodekit']);

/**
 * Kodekit custom symlinker
 */
Extension\Symlink::registerSymlinker(function($project, $destination, $name, $projects, OutputInterface $output) {
    if (!is_file($project.'/composer.json')) {
        return false;
    }

    $manifest = json_decode(file_get_contents($project.'/composer.json'));

    if (!isset($manifest->name) || $manifest->name != 'timble/kodekit') {
        return false;
    }

    $from = $project.'/code';
    $to   = $destination.'/wp-content/plugins/foliokit/library';

    if (!is_dir(dirname($to))) {
        throw new Exception('Foliokit is not symlinked');
    }

    if (!file_exists($to))
    {
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln(" * creating link `$to` -> $from");
        }

        `ln -sf $from $to`;
    }

    return true;
});