<?php

namespace TLE\Elfbot\Task\Endicia\PrintPostage;

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
            ->scalarNode('endiciatool')
                ->info('The path to endiciatool')
                ->defaultValue('endiciatool')
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
            ->scalarNode('dazzlexml')
                ->info('URL to load the DAZzle XML postage data')
                ->isRequired()
                ->end()
            ->scalarNode('manifestxml')
                ->info('URL to post the postage result')
                ->isRequired()
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $logger->debug('retrieving dazzle xml');

        $res = $this->container['http.app']->request('GET', $options['dazzlexml']);

        $logger->debug($res->getBody()->getContents());


        $logger->debug('sending dazzle to endicia');

        $p = new Process(
            $this->options['endiciatool'],
            null,
            null,
            $res->getBody(true),
            300
        );

        $p->mustRun(
            function ($type, $bytes) use ($logger) {
                $logger->debug($type . ': ' . $bytes);
            }
        );


        $logger->debug('uploading endicia response');

        $this->container['http.app']->request(
            'PUT',
            $options['manifestxml'],
            [
                'body' => $p->getOutput(),
            ]
        );
    }
}
