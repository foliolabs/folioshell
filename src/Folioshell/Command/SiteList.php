<?php

namespace Folioshell\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SiteList extends AbstractSite
{
    protected function configure()
    {
        $this
            ->setName('site:list')
            ->setDescription('List Wordpress sites')
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'The output format (txt or json)',
                'txt'
            )
            ->addOption(
                'www',
                null,
                InputOption::VALUE_REQUIRED,
                "Web server root",
                '/var/www'
            )
            ->setHelp('List Wordpress sites running on this machine');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $docroot = $input->getOption('www');

        if (!file_exists($docroot)) {
            throw new \RuntimeException(sprintf('Web server root \'%s\' does not exist.', $docroot));
        }

        $dir = new \DirectoryIterator($docroot);
        $sites = array();

        foreach ($dir as $fileinfo)
        {
            if ($fileinfo->isDir() && !$fileinfo->isDot())
            {
                $version = WP::call(sprintf('core version --quiet --path=%s 2>/dev/null', $fileinfo->getPathname()));

                if (!empty($version))
                {
                    $sites[] = (object) array(
                        'name'    => $fileinfo->getFilename(),
                        'docroot' => $docroot . '/' . $fileinfo->getFilename() . '/',
                        'type'    => 'wordpress',
                        'version' => trim($version)
                    );
                }
            }
        }

        if (!in_array($input->getOption('format'), array('txt', 'json'))) {
            throw new \InvalidArgumentException(sprintf('Unsupported format "%s".', $input->getOption('format')));
        }

        switch ($input->getOption('format'))
        {
            case 'json':
                $result = new \stdClass();
                $result->command = $input->getArgument('command');
                $result->data    = $sites;

                $options = (version_compare(phpversion(),'5.4.0') >= 0 ? JSON_PRETTY_PRINT : 0);
                $string  = json_encode($result, $options);
                break;
            case 'txt':
            default:
                $lines = array();
                foreach ($sites as $i => $site) {
                    $lines[] = sprintf("<info>%s. %s</info> (%s %s)", ($i+1), $site->name, $site->type, $site->version);
                }

                $string = implode("\n", $lines);
                break;
        }

        $output->writeln($string);

        return 0;
    }
}
