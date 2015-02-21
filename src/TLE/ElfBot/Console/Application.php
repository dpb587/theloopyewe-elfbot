<?php

namespace TLE\ElfBot\Console;

use TLE\ElfBot\Manifest;
use TLE\ElfBot\Console\Command as ConsoleCommand;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Formatter\LineFormatter;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Cookie\CookieJar\ArrayCookieJar;
use Aws\Sqs\SqsClient;
use Guzzle\Http\Client;

class Application extends BaseApplication
{
    /**
     * @var array[int]
     */
    protected $runScope = [];

    /**
     * @var LineFormatter
     */
    protected $loggerFormatter;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var mixed[string]
     */
    protected $config;

    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var SqsClient
     */
    protected $queueClient;

    public function __construct()
    {
        parent::__construct(Manifest::NAME, Manifest::VERSION);

        $this->getDefinition()->addOption(new InputOption('config-file', 'c', InputOption::VALUE_REQUIRED, 'Load a configuration file.'));
    }

    public function getLongVersion()
    {
        if (Manifest::isReleaseVersion()) {
            return sprintf(
                '<info>%s</info> version <comment>%s</comment> (<comment>%s</comment>)',
                $this->getName(),
                $this->getVersion(),
                Manifest::COMMIT
            );
        }

        return '<info>' . $this->getName() . '</info> (dev)';
    }

    /**
     *
     * @param string|null $scope
     * @param int|null $level
     * @param int|null $fingersCrossed
     * @return Logger
     */
    public function getLogger($scope = null, $level = null, $fingersCrossed = null)
    {
        if (null === $this->loggerFormatter) {
            $this->loggerFormatter = new LineFormatter("%datetime% %channel% %level_name% %message%\n", 'c');
        }

        $level = ($level === null) ? (500 - (100 * $this->runScope[0][2]->getVerbosity())) : $level;
        $handler = new StreamHandler(fopen('php://stderr', 'w'), $level);

        $handler->setFormatter($this->loggerFormatter);

        if (0 < $fingersCrossed) {
            $handler = new FingersCrossedHandler($handler, min($level, $fingersCrossed));
        }

        $logger = new Logger($this->getName() . (isset($this->runScope[0][0]) ? ('/' . $this->runScope[0][0]->getName()) : '') . ((null !== $scope) ? ( '/' . $scope ) : ''));
        $logger->pushHandler($handler);

        return $logger;
    }

    public function configureIO(InputInterface $input, OutputInterface $output)
    {
        parent::configureIO($input, $output);

        if (null === $this->logger) {
            // need our application logger
            array_unshift($this->runScope, [ null , $input , $output ]);

            $this->logger = $this->getLogger();

            array_shift($this->runScope);
        }
    }

    public function doRunCommand(Command $command, InputInterface $input, OutputInterface $output)
    {
        if (0 == count($this->runScope) && $input->hasParameterOption([ '--config-file' , '-c' ])) {
            $configFile = realpath($input->getParameterOption([ '--config-file' , '-c' ]));

            $this->config = json_decode(file_get_contents($configFile), true);

            $this->logger->info('loading config file "' . $configFile . '"');

            if (isset($this->config['http'])) {
                $this->httpClient = new Client(
                    'https://' . $this->config['http']['host']
                );

                if (isset($this->config['http']['auth']['basic'])) {
                    $this->httpClient->setDefaultOption(
                        'auth',
                        [
                            $this->config['http']['auth']['basic']['username'],
                            $this->config['http']['auth']['basic']['password'],
                            'Basic'
                        ]
                    );
                }
            }

            if (isset($this->config['queue'])) {
                $this->httpClient->addSubscriber(
                    new CookiePlugin(
                        new ArrayCookieJar()
                    )
                );

                $this->queueClient = SqsClient::factory(
                    [
                        'key' => $this->config['queue']['aws_sqs']['key'],
                        'secret' => $this->config['queue']['aws_sqs']['secret'],
                        'region' => $this->config['queue']['aws_sqs']['region'],
                    ]
                );
            }
        }

        array_unshift($this->runScope, [ $command, $input, $output ]);

        if ($command instanceof ConsoleCommand\AbstractLoggerAwareCommand) {
            $command->setLogger($this->getLogger());
        }

        try {
            $exitCode = parent::doRunCommand($command, $input, $output);
        } catch (\Exception $e) {
            array_shift($this->runScope);

            throw $e;
        }

        array_shift($this->runScope);

        return $exitCode;
    }

    /**
     * @return mixed[string]
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return Client
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * @return SqsClient
     */
    public function getQueueClient()
    {
        return $this->queueClient;
    }
    
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        $commands[] = new ConsoleCommand\InstallLaunchdCommand();
        $commands[] = new ConsoleCommand\RunCommand();
        $commands[] = new ConsoleCommand\SelfUpdateCommand();

        return $commands;
    }
}
