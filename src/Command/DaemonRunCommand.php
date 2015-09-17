<?php

namespace PHPFastCGI\FastCGIDaemon\Command;

use PHPFastCGI\FastCGIDaemon\DaemonOptions;
use PHPFastCGI\FastCGIDaemon\Driver\DriverContainerInterface;
use PHPFastCGI\FastCGIDaemon\KernelInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

class DaemonRunCommand extends Command
{
    const DEFAULT_NAME        = 'run';
    const DEFAULT_DESCRIPTION = 'Run the FastCGI daemon';

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var DriverContainerInterface
     */
    private $driverContainer;

    /**
     * Constructor.
     *
     * @param KernelInterface          $kernel          The kernel to be given to the daemon
     * @param DriverContainerInterface $driverContainer The driver container
     * @param string                   $name            The name of the daemon run command
     * @param string                   $description     The description of the daemon run command
     */
    public function __construct(KernelInterface $kernel, DriverContainerInterface $driverContainer, $name = null, $description = null)
    {
        $this->kernel          = $kernel;
        $this->driverContainer = $driverContainer;

        $name        = $name        ?: self::DEFAULT_NAME;
        $description = $description ?: self::DEFAULT_DESCRIPTION;

        parent::__construct($name);

        $this
            ->setDescription($description)
            ->addOption('port',          null, InputOption::VALUE_OPTIONAL, 'TCP port to listen on (if not present, daemon will listen on FCGI_LISTENSOCK_FILENO)')
            ->addOption('host',          null, InputOption::VALUE_OPTIONAL, 'TCP host to listen on')
            ->addOption('request-limit', null, InputOption::VALUE_OPTIONAL, 'The maximum number of requests to handle before shutting down')
            ->addOption('memory-limit',  null, InputOption::VALUE_OPTIONAL, 'The memory limit on the daemon instance before shutting down')
            ->addOption('time-limit',    null, InputOption::VALUE_OPTIONAL, 'The time limit on the daemon in seconds before shutting down')
            ->addOption('driver',        null, InputOption::VALUE_OPTIONAL, 'The implementation of the FastCGI protocol to use', 'userland');
    }

    /**
     * Retrieves the daemon configuration from the Symfony command input and
     * output objects.
     * 
     * @param InputInterface  $input The  Symfony command input
     * @param OutputInterface $output The Symfony command output
     * 
     * @return DaemonOptions The daemon configuration
     */
    private function getDaemonOptions(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output);

        $requestLimit = $input->getOption('request-limit') ?: DaemonOptions::NO_LIMIT;
        $memoryLimit  = $input->getOption('memory-limit')  ?: DaemonOptions::NO_LIMIT;
        $timeLimit    = $input->getOption('time-limit')    ?: DaemonOptions::NO_LIMIT;

        return new DaemonOptions($logger, $requestLimit, $memoryLimit, $timeLimit);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $port = $input->getOption('port');
        $host = $input->getOption('host');

        $daemonOptions = $this->getDaemonOptions($input, $output);

        $driver        = $input->getOption('driver');
        $daemonFactory = $this->driverContainer->getFactory($driver);

        if (null !== $port) {
            // If we have the port, create a TCP daemon
            $daemon = $daemonFactory->createTcpDaemon($this->kernel, $daemonOptions, $host ?: 'localhost', $port);
        } elseif (null !== $host) {
            // If we have the host but not the port, we cant create a TCP daemon - throw exception
            throw new \InvalidArgumentException('TCP port option must be set if host option is set');
        } else {
            // With no host or port, listen on FCGI_LISTENSOCK_FILENO (default)
            $daemon = $daemonFactory->createDaemon($this->kernel, $daemonOptions);
        }

        $daemon->run();
    }
}
