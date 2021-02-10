<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		https://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class SiteAbstract extends Configurable
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
            'mysql',
            null,
            InputOption::VALUE_REQUIRED,
            "MySQL credentials in the form of user:password",
            'root:root'
        )
        ->addOption(
            'mysql_db_prefix',
            null,
            InputOption::VALUE_OPTIONAL,
            "MySQL database prefix (default: sites_)",
            'sites_'
        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->target_db_prefix = $input->getOption('mysql_db_prefix');
        $this->site             = $input->getArgument('site');
        $this->www              = $input->getOption('www');

        $this->target_db  = $this->target_db_prefix.$this->site;
        $this->target_dir = $this->www.'/'.$this->site;

        $credentials = explode(':', $input->getOption('mysql'), 2);
        $this->mysql = (object) array('user' => $credentials[0], 'password' => $credentials[1]);
    }
}
