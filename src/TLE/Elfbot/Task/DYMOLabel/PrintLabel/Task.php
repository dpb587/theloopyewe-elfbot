<?php

namespace TLE\Elfbot\Task\DYMOLabel\PrintLabel;

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
                        return ('/' == $value[0]) ? $value : (new ExecutableFinder())->find($value);
                    })
                    ->end()
                ->end()
            ->end()
            ;

        $task->children()
            ->scalarNode('label_url')
                ->info('URL to download the label file')
                ->isRequired()
                ->end()
            ->scalarNode('print')
                ->info('Number of copies of the label to print')
                ->defaultValue(1)
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $res = $this->container['http.app']->request('GET', $options['label_url']);

        file_put_contents(
            $r1 = (uniqid('/tmp/tle-r1-') . '.applescript'),
            file_get_contents(__DIR__ . '/script.applescript')
        );

        file_put_contents(
            $r2 = (uniqid('/tmp/tle-r2-') . '.label'),
            $res->getBody()->getContents()
        );

        $p = new Process(
            sprintf(
                '%s %s %s %s',
                $this->options['osascript'],
                $r1,
                $r2,
                $options['print']
            )
        );

        $p->run(
            function ($type, $bytes) use ($logger) {
                $logger->debug($type . ': ' . $bytes);
            }
        );

        unlink($r1);
        unlink($r2);
    }
}
