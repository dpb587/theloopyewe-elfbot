<?php

namespace TLE\ElfBot\Task\ElfBot\Log;

use TLE\ElfBot\Task\AbstractTask;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

class Task extends AbstractTask
{
    static public function getDefinition(NodeDefinition $service, NodeDefinition $task)
    {
        $task->children()
            ->enumNode('level')
                ->info('The level to log at')
                ->defaultValue('emergency')
                ->values([
                    'emergency',
                    'alert',
                    'critical',
                    'error',
                    'warning',
                    'notice',
                    'info',
                    'debug',
                ])
                ->end()
            ->scalarNode('message')
                ->info('The message to log')
                ->isRequired()
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $logger->log($options['level'], $options['message']);
    }
}
