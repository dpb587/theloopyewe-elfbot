<?php

namespace TLE\Elfbot;

use GuzzleHttp\Client;
use Pimple\Container as PimpleContainer;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

class ContainerProvider implements ServiceProviderInterface
{
    protected $configFiles = [];

    public function __construct(array $configFiles)
    {
        $this->configFiles = $configFiles;
    }

    public function register(PimpleContainer $pimple)
    {
        $configFiles = [];

        foreach ($this->configFiles as $configFile) {
            $resolvedConfigFile = $pimple['filesystem']->realpath($configFile);

            if (!$resolvedConfigFile) {
                $pimple['logger']->debug(sprintf('skipped config %s', $configFile));

                continue;
            }

            $configType = pathinfo($configFile, PATHINFO_EXTENSION);
            $configData = file_get_contents($resolvedConfigFile);

            if (in_array($configType, [ 'yml', 'yaml' ])) {
                $config = Yaml::parse($configData);
            } elseif ('json' == $configType) {
                $config = json_decode($configData, true);
            } else {
                throw new \LogicException('Unsupported config file type: ' . $configType);
            }

            $configFiles[] = $config;

            $pimple['logger']->debug(sprintf('loaded config %s', $resolvedConfigFile));
        }

        $config = $pimple['config_processor']->process($this->getConfiguration(), $configFiles);

        if (isset($config['tasks'])) {
            foreach ($config['tasks'] as $task => $taskConfig) {
                $pimple['task_factory']->register(
                    $task,
                    $taskConfig['class'],
                    $taskConfig['options']
                );
            }
        }

        if (isset($config['worker'])) {
            if (isset($config['worker']['aws_sqs'])) {
                $workerConfig = $config['worker']['aws_sqs'];

                $pimple['worker'] = function ($c) use ($workerConfig) {
                    return new Worker\AwsSqs\Worker($c, $workerConfig);
                };
            }
        }

        if (isset($config['worker_events'])) {
            $workerEvents = $config['worker_events'];

            $pimple['worker_events'] = function ($c) use ($workerEvents) {
                $class = $workerEvents['class'];

                return new $class($c, $workerEvents['options']);
            };
        } else {
            $pimple['worker_events'] = function () {
                return new Worker\AlwaysEvents();
            };
        }

        if (isset($config['http'])) {
            foreach ($config['http'] as $http => $httpConfig) {
                $pimple['http.' . $http] = function () use ($httpConfig) {
                    return new Client($httpConfig);
                };
            }
        }
    }

    public function getConfiguration()
    {
        $definition = new TreeBuilder();

        $definition->root('config')
            ->children()
                ->arrayNode('tasks')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('class')
                                ->isRequired()
                                ->end()
                            ->arrayNode('options')
                                ->normalizeKeys(false)
                                ->useAttributeAsKey('key')
                                ->prototype('variable')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->arrayNode('worker')
                    ->children()
                        ->arrayNode('aws_sqs')
                            ->children()
                                ->scalarNode('key')
                                    ->isRequired()
                                    ->end()
                                ->scalarNode('secret')
                                    ->isRequired()
                                    ->end()
                                ->scalarNode('region')
                                    ->defaultValue('us-east-1')
                                    ->isRequired()
                                    ->end()
                                ->scalarNode('queue_url')
                                    ->end()
                                ->scalarNode('queue_name')
                                    ->end()
                                ->end()
                            ->beforeNormalization()
                                ->ifTrue(function ($v) {
                                    return !isset($v['queue_url']) && !isset($v['queue_name']);
                                })
                                ->thenInvalid('Either queue_url or queue_name must be configured')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->arrayNode('worker_events')
                    ->children()
                        ->scalarNode('class')
                            ->isRequired()
                            ->end()
                        ->arrayNode('options')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('key')
                            ->prototype('variable')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->arrayNode('http')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->normalizeKeys(false)
                        ->useAttributeAsKey('key')
                        ->prototype('variable')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ;

        return $definition->buildTree();
    }
}
