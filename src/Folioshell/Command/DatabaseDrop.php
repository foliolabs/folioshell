<?php
/**
 * Joomlatools Console backup plugin - https://github.com/joomlatools/joomlatools-console-backup
 *
 * @copyright	Copyright (C) 2011 - 2013 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-console-backup for the canonical source repository
 */

namespace Folioshell\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Backup plugin class.
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Console
 */
class DatabaseDrop extends AbstractSite
{
    protected function configure()  
    {
        parent::configure();

        $this
            ->setName('database:drop')
            ->setDescription('Drop the site\'s database');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $result = $this->_executeSQL(sprintf("DROP DATABASE IF EXISTS `%s`", $this->target_db));

        if (!empty($result)) {
            throw new \RuntimeException(sprintf('Cannot drop database %s. Error: %s', $this->target_db, $result));
        }

        return 0;
    }
}