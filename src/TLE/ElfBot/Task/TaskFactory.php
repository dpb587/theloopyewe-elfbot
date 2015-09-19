<?php

namespace TLE\ElfBot\Task;

use Psr\Log\LoggerInterface;
use Pimple\Container;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class TaskFactory
{
    protected $container;

    protected $taskDefinitions = [];

    public function __construct(Container $container)
    {
        $this->container = $container;

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

        $processedOptions = $this->container['config_processor']->process($serviceDefinition, [ $options ]);

        $this->container['task.' . $name] = function ($c) use ($class, $processedOptions) {
            return new $class($c, $processedOptions);
        };

        return $this;
    }

    public function execute(LoggerInterface $logger, $name, array $options = [])
    {
        $task = $this->getTask($name);

        list(, $taskDefinition) = $this->getDefinition(get_class($task));

        $processedOptions = $this->container['config_processor']->process(
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

    public function getTask($taskName)
    {
        return $this->container['task.' . $taskName];
    }

    public function getTaskNames()
    {
        $tasks = array_map(
            function ($key) {
                return substr($key, 5);
            },
            array_filter(
                $this->container->keys(),
                function ($key) {
                    return 'task.' == substr($key, 0, 5);
                }
            )
        );

        sort($tasks);

        return $tasks;
    }
}
