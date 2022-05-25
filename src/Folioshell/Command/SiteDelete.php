<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		https://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;

class SiteDelete extends AbstractSite
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:delete')
            ->setDescription('Delete a site')
            ->addOption(
                'skip-database',
                null,
                InputOption::VALUE_NONE,
                'Leave the database intact'
            )
            ->addOption(
                'skip-vhost',
                null,
                InputOption::VALUE_NONE,
                'Leave the virtual host intact'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check($input, $output);
        $this->deleteFolder($input, $output);
        $this->deleteVirtualHost($input, $output);
        $this->deleteDatabase($input, $output);

        return 0;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if ((strpos(getcwd(), $this->target_dir) === 0) && (getcwd() !== $this->www)) {
            throw new \RuntimeException('You are currently in the directory you are trying to delete. Aborting');
        }

        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('The site %s does not exist!', $this->site));
        }
    }

    public function deleteFolder(InputInterface $input, OutputInterface $output)
    {
        `rm -rf $this->target_dir`;
    }

    public function deleteDatabase(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('skip-database')) {
            return;
        }

        $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);
        $command  = sprintf("echo 'DROP DATABASE IF EXISTS `$this->target_db`' | mysql -u'%s' %s", $this->mysql->user, $password);

        $result   = exec($command);

        if (!empty($result)) { // MySQL returned an error
            throw new \RuntimeException(sprintf('Cannot delete database %s. Error: %s', $this->target_db, $result));
        }
    }

    public function deleteVirtualHost(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('skip-vhost')) {
            return;
        }

        $command_input = new ArrayInput(array(
            'vhost:remove',
            'site' => $this->site
        ));

        $command = new Vhost\Remove();
        $command->run($command_input, $output);
    }
}
