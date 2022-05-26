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
    const VERSION = '1.1.0';

    /**
     * Application name
     *
     * @var string
     */
    const NAME = 'FolioShell - WordPress Console tools';

    /**
     * Reference to the Output\ConsoleOutput object
     */
    protected $_output;

    /**
     * Reference to the Input\ArgvInput object
     */
    protected $_input;

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
            $this->_input = new Input\ArgvInput();
        }

        if (null === $output) {
            $this->_output = new Output\ConsoleOutput();
        }

        $this->configureIO($this->_input, $this->_output);

        return parent::run($this->_input, $this->_output);
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

}