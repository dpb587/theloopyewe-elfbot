<?php

namespace TLE\Elfbot\Task\Endicia\PurchasePostage;

use TLE\Elfbot\Task\AbstractTask;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Psr\Log\LoggerInterface;

class Task extends AbstractTask
{
    static public function getDefinition(NodeDefinition $service, NodeDefinition $task)
    {
        $service->children()
            ->scalarNode('osascript')
                ->info('The path to osascript')
                ->defaultValue('osascript')
                ->beforeNormalization()
                    ->always()
                    ->then(function ($value) {
                        return (new ExecutableFinder())->find($value);
                    })
                    ->end()
                ->end()
            ->end()
            ;

        $task->children()
            ->scalarNode('amount')
                ->info('The amount of postage to purchase')
                ->isRequired()
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        file_put_contents(
            $r1 = (uniqid('/tmp/tle-r1-') . '.applescript'),
            file_get_contents(__DIR__ . '/script.applescript')
        );

        $p = new Process(
            sprintf(
                '%s %s %s',
                $this->options['osascript'],
                $r1,
                $options['amount']
            )
        );

        $p->run(
            function ($type, $bytes) use ($logger) {
                $logger->debug($type . ': ' . $bytes);
            }
        );

        unlink($r1);
    }
}
