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
class DatabaseDump extends AbstractSite
{
    protected function configure()  
    {
        parent::configure();

        $this->setName('database:dump')
            ->addOption(
                'folder',
                null,
                InputOption::VALUE_REQUIRED,
                "Target folder where the backup should be stored. Defaults to site folder",
                null
            )
            ->addOption(
                'filename',
                null,
                InputOption::VALUE_REQUIRED,
                "File name for the backup. Defaults to sitename_date.format",
                null
            )
            ->setDescription('Dump the database of a site');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check();

        $path = $input->getOption('folder') ?? $this->target_dir;
        $path .= '/'.($input->getOption('filename') ?? $this->site.'_database_'.date('Y-m-d').'.sql');

        $this->_backupDatabase($path);

        return 0;
    }

    public function check()
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('The site %s does not exist', $this->site));
        }
    }
}