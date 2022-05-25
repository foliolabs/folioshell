<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Folioshell\Command\Vhost;

use Folioshell\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Command\AbstractSite
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('vhost:create')
            ->setDescription('Creates a new Apache2 virtual host')
            ->addOption(
                'http-port',
                null,
                InputOption::VALUE_REQUIRED,
                'The HTTP port the virtual host should listen to',
                80
            )
            ->addOption(
                'ssl-port',
                null,
                InputOption::VALUE_REQUIRED,
                'The HTTPS port the virtual host should listen to',
                443
            )
            ->addOption(
                'template',
                null,
                InputOption::VALUE_REQUIRED,
                'Custom file to use as the Apache vhost configuration. Make sure to include HTTP and SSL directives if you need both.',
                null
            )
            ->addOption('folder',
                null,
                InputOption::VALUE_REQUIRED,
                'The Apache2 vhost folder',
                '/etc/apache2/sites-enabled'
            )
            ->addOption('filename',
                null,
                InputOption::VALUE_OPTIONAL,
                'The Apache2 vhost file name',
                null,
            )
            ->addOption('restart-command',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full command for restarting Apache2',
                null
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }

        $target = $this->_getVhostPath($input);

        $variables = $this->_getVariables($input);

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }

        if (is_dir(dirname($target)))
        {
            $template = $this->_getTemplate($input);
            $template = str_replace(array_keys($variables), array_values($variables), $template);

            file_put_contents($target, $template);

            if ($command = $input->getOption('restart-command')) {
                `$command`;
            }
        }

        return 0;
    }

    protected function _getVhostPath($input) 
    {
        $folder = str_replace('[site]', $this->site, $input->getOption('folder'));
        $file = $input->getOption('filename') ?? $input->getArgument('site').'.conf';

        return $folder.'/'.$file;
    }

    protected function _getVariables(InputInterface $input)
    {
        $documentroot = $this->target_dir;

        $variables = array(
            '%site%'       => $input->getArgument('site'),
            '%root%'       => $documentroot,
            '%http_port%'  => $input->getOption('http-port'),
            '%ssl_port%'  => $input->getOption('ssl-port'),
        );

        return $variables;
    }

    protected function _getTemplate(InputInterface $input)
    {
        if ($template = $input->getOption('template'))
        {
            if (file_exists($template))
            {
                $file = basename($template);
                $path = dirname($template);
            }
            else throw new \Exception(sprintf('Template file %s does not exist.', $template));
        }
        else
        {
            $path = realpath(__DIR__.'/../../../../bin/.files/vhosts');

            $file = 'apache.conf';
        }

        $template = file_get_contents(sprintf('%s/%s', $path, $file));

        return $template;
    }
}