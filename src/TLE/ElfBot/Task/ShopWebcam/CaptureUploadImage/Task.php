<?php

namespace TLE\ElfBot\Task\ShopWebcam\CaptureUploadImage;

use Guzzle\Http\Client;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use TLE\ElfBot\Task\AbstractTask;
use Psr\Log\LoggerInterface;

class Task extends AbstractTask
{
    static public function getDefinition(NodeDefinition $service, NodeDefinition $task)
    {
        $service->children()
            ->scalarNode('source')
                ->info('URL to retrieve')
                ->isRequired()
                ->end()
            ->scalarNode('endpoint')
                ->info('URL to upload to')
                ->isRequired()
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $webcam = new Client($this->options['source']);

        $logger->debug('downloading');

        $download = $webcam->get()->send();

        
        $logger->debug('uploading');

        $this->container['http.app']->put(
            $this->options['endpoint'],
            [],
            $download->getBody()
        )->send();
    }
}
