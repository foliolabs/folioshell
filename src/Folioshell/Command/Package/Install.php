<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		https://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace Folioshell\Command\Package;

use Folioshell\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Command\AbstractSite
{
    protected $packages = array();
    protected $projects_dir;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('package:install')
            ->setDescription('Install a built plugin package zip into a site')
            ->setHelp(<<<EOL
Install a package you have already built. Packages live in the extension folder
under <comment>--projects-dir</comment> (default <comment>~/Projects</comment>):

    <info>folioshell package:install testsite foliolabs</info>

When that folder contains more than one zip, the newest (by modification time)
is used. Pass a version to pick a specific build:

    <info>folioshell package:install testsite foliolabs:1.2.3</info>

Several packages can be given; they are installed in order:

    <info>folioshell package:install testsite foliolabs otherplugin</info>

A filesystem path to a zip is also accepted:

    <info>folioshell package:install testsite /path/to/plugin.zip</info>

<comment>site:create --install=</comment> runs this command after the site is
created. That list is comma-separated, the same shape as <comment>--symlink</comment>:

    <info>folioshell site:create testsite --install=foliolabs</info>
    <info>folioshell site:create testsite --install=foliolabs:1.2.3,otherplugin</info>

This is not <comment>extension:install</comment>, which activates symlinked
plugins, and not <comment>extension:installfile</comment>, which takes an
explicit zip or URL and does not look the file up in the project folder.
EOL
            )
            ->addArgument(
                'package',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Plugin name (latest zip), name:version, or a path to a zip'
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

        $this->packages     = (array) $input->getArgument('package');
        $this->projects_dir = $input->getOption('projects-dir');

        $this->check($input, $output);
        $this->install($input, $output);

        return 0;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function install(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->packages as $spec)
        {
            $zip = $this->resolvePackage($spec, $this->projects_dir);
            $output->writeln(sprintf('Installing <info>%s</info> from <comment>%s</comment>', $spec, $zip));
            $this->installZip($zip, $output);
        }
    }

    /**
     * Resolve a plugin name, name:version spec, or filesystem path to a zip.
     *
     * @param string $spec
     * @param string $projectsDir
     * @return string Absolute path to the zip
     */
    public function resolvePackage($spec, $projectsDir)
    {
        $expanded = $this->expandHome($spec);

        if (is_file($expanded)) {
            return realpath($expanded);
        }

        $name    = $spec;
        $version = null;

        if (strpos($spec, ':') !== false)
        {
            list($name, $version) = explode(':', $spec, 2);

            if ($version === '' || strcasecmp($version, 'latest') === 0) {
                $version = null;
            }
        }

        $dir = rtrim($projectsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($dir)) {
            throw new \RuntimeException(sprintf('Package directory not found: %s', $dir));
        }

        $zips = array();

        foreach (glob($dir . '/*.zip') ?: array() as $zip)
        {
            if (!is_file($zip)) {
                continue;
            }

            if (strpos(basename($zip), '._') === 0) {
                continue;
            }

            $zips[] = $zip;
        }

        if ($version !== null)
        {
            $zips = array_values(array_filter($zips, function ($zip) use ($version) {
                return $this->zipMatchesVersion($zip, $version);
            }));
        }

        if (!$zips)
        {
            if ($version !== null) {
                throw new \RuntimeException(sprintf('No zip matching version %s in %s', $version, $dir));
            }

            throw new \RuntimeException(sprintf('No zip files in %s', $dir));
        }

        usort($zips, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $zips[0];
    }

    protected function zipMatchesVersion($zip, $version)
    {
        $version = ltrim($version, 'vV');
        $base    = basename($zip);

        return (bool) preg_match('/(^|[^0-9])v?' . preg_quote($version, '/') . '([^0-9]|$)/', $base);
    }

    protected function expandHome($path)
    {
        if ($path === '~') {
            return getenv('HOME');
        }

        if (isset($path[0]) && $path[0] === '~' && isset($path[1]) && $path[1] === '/') {
            return getenv('HOME') . substr($path, 1);
        }

        return $path;
    }

    protected function installZip($zip, OutputInterface $output)
    {
        $result = Command\WP::call(sprintf(
            'plugin install %s --activate --path=%s',
            escapeshellarg($zip),
            escapeshellarg($this->target_dir)
        ));

        if ($result !== '') {
            $output->writeln(trim($result));
        }

        if (!preg_match('/Plugin installed successfully/i', $result)) {
            throw new \RuntimeException(sprintf('Failed to install package: %s', $zip));
        }
    }
}
