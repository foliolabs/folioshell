<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/foliolabs/folioshell for the canonical source repository
 */

use Folioshell\Command\Extension;

Extension\Symlink::registerDependencies('foliolabs-todo', ['foliokit', 'kodekit']);
Extension\Symlink::registerDependencies('foliolabs-docman', ['foliokit', 'kodekit']);
Extension\Symlink::registerDependencies('foliolabs-fileman', ['foliokit', 'kodekit']);
Extension\Symlink::registerDependencies('foliolabs-logman', ['foliokit', 'kodekit']);
Extension\Symlink::registerDependencies('foliolabs-textman', ['foliokit', 'kodekit']);