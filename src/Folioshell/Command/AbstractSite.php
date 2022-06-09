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
        $this->_executeMysqldump(sprintf("--skip-dump-date --skip-extended-insert --no-tablespaces %s > %s", $this->target_db, $target_file));
    }

    protected function _executePDO($query, $database = null) {
        $database = $database ?: $this->target_db;
        $connectionString = "mysql:host={$this->mysql->host}:{$this->mysql->port};dbname={$database};charset=utf8mb4";
        $pdoDB = new \PDO($connectionString, $this->mysql->user, $this->mysql->password);
        $pdoDB->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdoDB->query($query);
    }

    protected function _executeSQL($query, $database = '')
    {
        return $this->_executeMysqlWithCredentials(function($path) use($query, $database) {
            return "echo '$query' | mysql --defaults-extra-file=$path $database";
        });
    }

    protected function _executeMysql($command)
    {
        return $this->_executeMysqlWithCredentials(function($path) use($command) {
            return "mysql --defaults-extra-file=$path $command";
        });
    }
    
    protected function _executeMysqldump($command)
    {
        return $this->_executeMysqlWithCredentials(function($path) use($command) {
            return "mysqldump --defaults-extra-file=$path $command";
        });
    }

    /**
     * Write a temporary --defaults-extra-file file and execute a Mysql command given from the callback
     *
     * @param callable $callback Receives a single string with the path to the --defaults-extra-file path
     * @return void
     */
    private function _executeMysqlWithCredentials(callable $callback)
    {
        try {
            $file = tmpfile();
            $path = stream_get_meta_data($file)['uri'];

            $contents = <<<STR
[client]
user={$this->mysql->user}
password={$this->mysql->password}
host={$this->mysql->host}
port={$this->mysql->port}
STR;

            fwrite($file, $contents);


            return exec($callback($path));
        }
        finally {
            if (\is_resource($file)) {
                \fclose($file);
            }
        }
    }

}
