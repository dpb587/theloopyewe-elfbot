<?php

namespace TLE\ElfBot\Task;

use Psr\Log\LoggerInterface;
use Pimple\Container;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

class TaskFactory
{
    protected $container;

    protected $configProcessor;

    protected $taskDefinitions = [];

    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->configProcessor = new Processor();
        $this->registerBuiltins();
    }

    protected function registerBuiltins()
    {
        $this
            ->register('self.display_dialog', 'TLE\ElfBot\Task\ElfBot\DisplayDialog\Task')
            ->register('self.log', 'TLE\ElfBot\Task\ElfBot\Log\Task')
            ->register('self.terminate', 'TLE\ElfBot\Task\ElfBot\Terminate\Task')
            ;
    }

    public function register($name, $class, array $options = [])
    {
        list($serviceDefinition) = $this->getDefinition($class);

        $processedOptions = $this->configProcessor->process($serviceDefinition, [ $options ]);

        $this->container['task.' . $name] = function ($c) use ($class, $processedOptions) {
            return new $class($c, $processedOptions);
        };

        return $this;
    }

    public function execute(LoggerInterface $logger, $name, array $options = [])
    {
        $task = $this->container['task.' . $name];

        list(, $taskDefinition) = $this->getDefinition(get_class($task));

        $processedOptions = $this->configProcessor->process(
            $taskDefinition,
            [
                $options,
            ]
        );

        $task->execute($logger, $processedOptions);
    }

    public function getDefinition($class)
    {
        if (!isset($this->taskDefinitions[$class])) {
            $serviceDefinition = new TreeBuilder();
            $serviceDefinitionRoot = $serviceDefinition->root('task_service');

            $taskDefinition = new TreeBuilder();
            $taskDefinitionRoot = $taskDefinition->root('task');

            call_user_func(
                [ $class, 'getDefinition' ],
                $serviceDefinitionRoot,
                $taskDefinitionRoot
            );

            $this->taskDefinitions[$class] = [
                $serviceDefinition->buildTree(),
                $taskDefinition->buildTree(),
            ];
        }

        return $this->taskDefinitions[$class];
    }
}
