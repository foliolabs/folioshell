<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		https://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractSite extends Configurable
{
    protected $site;
    protected $www;

    protected $target_dir;
    protected $target_db;
    protected $target_db_prefix = 'sites_';

    protected $mysql;

    protected function configure()
    {
        $this->addArgument(
            'site',
            InputArgument::REQUIRED,
            'Alphanumeric site name. Also used in the site URL with .test domain'
        )->addOption(
            'www',
            null,
            InputOption::VALUE_REQUIRED,
            "Web server root",
            '/var/www'
        )
        ->addOption(
            'mysql-login',
            'L',
            InputOption::VALUE_REQUIRED,
            "MySQL credentials in the form of user:password",
            'root:root'
        )
        ->addOption(
            'mysql-host',
            'H',
            InputOption::VALUE_REQUIRED,
            "MySQL host",
            'localhost'
        )
        ->addOption(
            'mysql-port',
            'P',
            InputOption::VALUE_REQUIRED,
            "MySQL port",
            3306
        )
        ->addOption(
            'mysql-db-prefix',
            null,
            InputOption::VALUE_REQUIRED,
            sprintf("MySQL database name prefix. Defaults to `%s`", $this->target_db_prefix),
            $this->target_db_prefix
        )
        ->addOption(
            'mysql-database',
            'db',
            InputOption::VALUE_REQUIRED,
            "MySQL database name. If set, the --mysql-db-prefix option will be ignored."
        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->site             = $input->getArgument('site');
        $this->www              = $input->getOption('www');

        $this->target_dir = $this->www.'/'.$this->site;

        $db_name = $input->getOption('mysql-database');
        if (empty($db_name))
        {
            $this->target_db_prefix = $input->getOption('mysql-db-prefix');
            $this->target_db        = $this->target_db_prefix.$this->site;
        }
        else
        {
            $this->target_db_prefix = '';
            $this->target_db        = $db_name;
        }
        
        $credentials = explode(':', $input->getOption('mysql-login'), 2);

        $this->mysql = (object) array(
            'user'     => $credentials[0],
            'password' => $credentials[1],
            'host'     => $input->getOption('mysql-host'),
            'port'     => (int) $input->getOption('mysql-port'),
        );


        return 0;
    }

    protected function _backupDatabase($target_file)
    {
        $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);

        exec(sprintf("mysqldump --host=%s --port=%u -u'%s' %s %s > %s", $this->mysql->host, $this->mysql->port, $this->mysql->user, $password, $this->target_db, $target_file));

        if (!file_exists($target_file)) {
            throw new \RuntimeException(sprintf('Failed to backup database "%s"!', $this->target_db));
        }
    }

    protected function _executeSQL($query)
    {
        $password = empty($this->mysql->password) ? '' : sprintf("--password='%s'", $this->mysql->password);
        $cmd      = sprintf("echo '$query' | mysql --host=%s --port=%u --user='%s' %s", $this->mysql->host, $this->mysql->port, $this->mysql->user, $password);

        return exec($cmd);
    }

}
