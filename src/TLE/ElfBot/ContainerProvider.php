<?php

namespace TLE\ElfBot;

use GuzzleHttp\Client;
use Pimple\Container as PimpleContainer;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Yaml\Yaml;

class ContainerProvider implements ServiceProviderInterface
{
    protected $configFiles = [];

    public function __construct(array $configFiles)
    {
        $this->configFiles = $configFiles;
    }

    public function register(PimpleContainer $pimple)
    {
        foreach ($this->configFiles as $configFile) {
            $resolvedConfigFile = $pimple['filesystem']->realpath($configFile);

            if (!$resolvedConfigFile) {
                $pimple['logger']->debug(sprintf('skipped config %s', $configFile));

                continue;
            }

            $configType = pathinfo($configFile, PATHINFO_EXTENSION);
            $configData = file_get_contents($configFile);

            if (in_array($configType, [ 'yml', 'yaml' ])) {
                $config = Yaml::parse($configData);
            } elseif ('json' == $configType) {
                $config = json_decode($configData, true);
            } else {
                throw new \LogicException('Unsupported config file type: ' . $configType);
            }

            if (isset($config['tasks'])) {
                foreach ($config['tasks'] as $task => $taskConfig) {
                    $pimple['task_factory']->register(
                        $task,
                        $taskConfig['class'],
                        isset($taskConfig['options']) ? $taskConfig['options'] : []
                    );
                }
            }

            if (isset($config['queue'])) {
                if (isset($config['queue']['aws_sqs'])) {
                    $workerConfig = $config['queue']['aws_sqs'];

                    $pimple['worker'] = function ($c) use ($workerConfig) {
                        return new Worker\AwsSqs\Worker($c, $workerConfig);
                    };
                }
            }

            if (isset($config['http'])) {
                foreach ($config['http'] as $http => $httpConfig) {
                    $pimple['http.' . $http] = function () use ($httpConfig) {
                        return new Client($httpConfig);
                    };
                }
            }

            $pimple['logger']->debug(sprintf('loaded config %s', $resolvedConfigFile));
        }
    }
}
