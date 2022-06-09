<?php
namespace Folioshell;

use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;

class Application extends \Symfony\Component\Console\Application
{
    /**
     * Application version
     *
     * @var string
     */
    const VERSION = '2.0.0';

    /**
     * Application name
     *
     * @var string
     */
    const NAME = 'FolioShell - WordPress Console tools';

    /**
     * The path to the plugin directory
     * 
     * @var string
     */
    protected $_plugin_path;

    /**
     * Reference to the Output\ConsoleOutput object
     */
    protected $_output;

    /**
     * Reference to the Input\ArgvInput object
     */
    protected $_input;

    /**
     * List of installed plugins
     */
    protected $_plugins;

    /**
     * @inheritdoc
     */
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        parent::__construct(self::NAME, self::VERSION);
    }

    /**
     * Runs the current application.
     *
     * @param Input\InputInterface  $input  An Input instance
     * @param Output\OutputInterface $output An Output instance
     *
     * @return int 0 if everything went fine, or an error code
     *
     * @throws \Exception When doRun returns Exception
     */
    public function run(Input\InputInterface $input = null, Output\OutputInterface $output = null)
    {
        if (null === $input) {
            $input = new Input\ArgvInput();
        }

        if (null === $output) {
            $output = new Output\ConsoleOutput();
        }

        $this->_input  = $input;
        $this->_output = $output;

        $this->configureIO($this->_input, $this->_output);

        $this->_setup();

        $this->_loadPlugins();

        $this->_loadExtraCommands();

        parent::run($this->_input, $this->_output);
    }

    /**
     * Get the home directory path
     *
     * @return string Path to the Joomlatools Console home directory
     */
    public function getConsoleHome()
    {
        $home       = getenv('HOME');
        $customHome = getenv('FOLIOSHELL_HOME');

        if (!empty($customHome)) {
            $home = $customHome;
        }

        return rtrim($home, '/') . '/.foliolabs/folioshell';
    }

    /**
     * Get the plugin path
     *
     * @return string Path to the plugins directory
     */
    public function getPluginPath()
    {
        if (empty($this->_plugin_path)) {
            $this->_plugin_path = $this->getConsoleHome() . '/plugins';
        }

        return $this->_plugin_path;
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return \Symfony\Component\Console\Command\Command[] An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        $commands = array_merge($commands, array(
            new Command\Wp(),

            new Command\DatabaseDrop(), 
            new Command\DatabaseExport(), 
            
            new Command\SiteCreate(),
            new Command\SiteDelete(),
            new Command\SiteExport(),
            new Command\SiteList(),

            new Command\Extension\Symlink(),

            new Command\Extension\Install(),
            new Command\Extension\InstallFile(),

            new Command\Vhost\Create(),
            new Command\Vhost\Remove(),
        ));

        return $commands;
    }

    /**
     * Get the list of installed plugin packages.
     *
     * @return array Array of package names as key and their version as value
     */
    public function getPlugins()
    {
        if (!$this->_plugins) {

            $manifest = $this->_plugin_path . '/composer.json';

            if (!file_exists($manifest)) {
                return array();
            }

            $contents = file_get_contents($manifest);

            if ($contents === false) {
                return array();
            }

            $data = json_decode($contents);

            if (!isset($data->require)) {
                return array();
            }

            $this->_plugins = array();

            foreach ($data->require as $package => $version)
            {
                $file = $this->_plugin_path . '/vendor/' . $package . '/composer.json';

                if (file_exists($file))
                {
                    $json     = file_get_contents($file);
                    $manifest = json_decode($json);

                    if (is_null($manifest)) {
                        continue;
                    }

                    if (isset($manifest->type) && $manifest->type == 'folioshell-plugin') {
                        $this->_plugins[$package] = $version;
                    }
                }
            }
        }

        return $this->_plugins;
    }

    /**
     * Loads extra commands from the ~/.foliolabs/folioshell/commands/ folder 
     * 
     * Each PHP file in the folder is included and if the class in the file extends the base Symfony command
     * it's instantiated and added to the app. 
     *
     * @return void
     */
    protected function _loadExtraCommands()
    {
        $path = $this->getConsoleHome().'/commands';

        if (\is_dir($path)) 
        {
            $iterator = new \DirectoryIterator($path);

            foreach ($iterator as $file)
            {
                if ($file->getExtension() == 'php') 
                {
                    require $file->getPathname();

                    $className  = $file->getBasename('.php');

                    if (\class_exists($className)) 
                    {
                        $reflection = new \ReflectionClass($className);
    
                        if (!$reflection->isSubclassOf('\Symfony\Component\Console\Command\Command')) {
                            continue;
                        }
                        
                        $command = new $className();
    
                        if (!$command instanceof \Symfony\Component\Console\Command\Command) {
                            continue;
                        }
    
                        $this->add($command);
                    }
                }
            }
        }
    }

    /**
     * Set up environment
     */
    protected function _setup()
    {
        $home = $this->getConsoleHome();

        if (!file_exists($home))
        {
            $result = @mkdir($home, 0775, true);

            if (!$result) {
                $this->_output->writeln(sprintf('<error>Unable to create home directory: %s. Please check write permissions.</error>', $home));
            }
        }
    }

    /**
     * Loads plugins into the application.
     */
    protected function _loadPlugins()
    {
        $autoloader = $this->_plugin_path . '/vendor/autoload.php';

        if (file_exists($autoloader)) {
            require_once $autoloader;
        }

        $plugins = $this->getPlugins();

        $classes = array();
        foreach ($plugins as $package => $version)
        {
            $path        = $this->_plugin_path . '/vendor/' . $package;
            $directories = glob($path.'/*/Console/Command', GLOB_ONLYDIR);

            foreach ($directories as $directory)
            {
                $vendor   = substr($directory, strlen($path) + 1, strlen('/Console/Command') * -1);
                $iterator = new \DirectoryIterator($directory);

                foreach ($iterator as $file)
                {
                    if ($file->getExtension() == 'php') {
                        $classes[] = sprintf('%s\Console\Command\%s', $vendor, $file->getBasename('.php'));
                    }
                }
            }
        }

        foreach ($classes as $class)
        {
            if (class_exists($class))
            {
                $command = new $class();

                if (!$command instanceof \Symfony\Component\Console\Command\Command) {
                    continue;
                }

                $name = $command->getName();

                if(!$this->has($name)) {
                    $this->add($command);
                }
                else $this->_output->writeln("<fg=yellow;options=bold>Notice:</fg=yellow;options=bold> The '$class' command wants to register the '$name' command but it already exists, ignoring.");
            }
        }
    }

}