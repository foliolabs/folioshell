<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/wordplugs/wp-console for the canonical source repository
 */

namespace Nooku\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExtensionSymlink extends SiteAbstract
{
    protected $symlink = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:symlink')
            ->setDescription('Symlink projects into a site')
            ->addArgument(
                'symlink',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'A list of folders to symlink from projects folder'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                sprintf('%s/Projects', trim(`echo ~`))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->symlink = $input->getArgument('symlink');

        $this->check($input, $output);
        $this->symlinkProjects($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function symlinkProjects(InputInterface $input, OutputInterface $output)
    {
        $project_folder = $input->getOption('projects-dir');

        $projects = array();
        foreach ($this->symlink as $symlink)
        {
            $projects[] = $symlink;
            $projects   = array_merge($projects, $this->_getDependencies($symlink));
        }

        foreach ($projects as $project)
        {
            $root = $project_folder.'/'.$project;

            if (!is_dir($root)) {
                continue;
            }

            // TODO: We still need to figure out where to place the core Nooku Framework.
            if ($this->_isNookuFramework($root)) {
                `ln -sf $root $this->target_dir/wp-content/plugins/nooku`;
            }
            else
            {
                $target = $this->target_dir.'/wp-content';

                if(!is_dir($target)) {
                    throw new \InvalidArgumentException('Invalid WordPress folder '.$this->target_dir);
                }

                if (is_dir($root.'/code')) {
                    $root = $root.'/code';
                }

                $iterator = new Symlink\Iterator($root, $target);

                while ($iterator->valid()) {
                    $iterator->next();
                }
            }
        }
    }

    protected function _isNookuFramework($folder)
    {
        return is_file($folder.'/code/koowa.php');
    }
}