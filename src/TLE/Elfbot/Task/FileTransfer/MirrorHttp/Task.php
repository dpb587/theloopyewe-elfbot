<?php

namespace TLE\Elfbot\Task\FileTransfer\MirrorHttp;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use TLE\Elfbot\Task\AbstractTask;
use Psr\Log\LoggerInterface;

class Task extends AbstractTask
{
    static public function getDefinition(NodeDefinition $service, NodeDefinition $task)
    {
        $service->children()
            ->scalarNode('source_http')
                ->info('HTTP Client for retrieval')
                ->defaultValue('default')
                ->end()
            ->scalarNode('source_method')
                ->info('HTTP Method for retrieval')
                ->defaultValue('GET')
                ->end()
            ->scalarNode('source_url')
                ->info('HTTP URL for retrieval')
                ->end()
            ->arrayNode('source_options')
                ->info('HTTP Client options for retrieval')
                ->end()
            ->scalarNode('target_http')
                ->info('HTTP Client for retrieval')
                ->defaultValue('app')
                ->end()
            ->scalarNode('target_method')
                ->info('HTTP Method for retrieval')
                ->defaultValue('PUT')
                ->end()
            ->scalarNode('target_url')
                ->info('HTTP URL for retrieval')
                ->end()
            ->arrayNode('target_options')
                ->info('HTTP Client options for retrieval')
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $logger->debug('downloading');

        $download = $this->container['http.' . $this->options['source_http']]->request(
            $this->options['source_method'],
            isset($this->options['source_url']) ? $this->options['source_url'] : null,
            isset($this->options['source_options']) ? $this->options['source_options'] : []
        );
        
        $logger->debug('uploading');

        $this->container['http.' . $this->options['target_http']]->request(
            $this->options['target_method'],
            isset($this->options['target_url']) ? $this->options['target_url'] : null,
            array_merge(
                isset($this->options['target_options']) ? $this->options['target_options'] : [],
                [
                    'body' => $download->getBody(),
                ]
            )
        );
    }
}
