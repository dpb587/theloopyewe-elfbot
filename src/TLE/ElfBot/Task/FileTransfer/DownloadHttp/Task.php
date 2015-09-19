<?php

namespace TLE\ElfBot\Task\FileTransfer\DownloadHttp;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use TLE\ElfBot\Task\AbstractTask;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;

class Task extends AbstractTask
{
    static public function getDefinition(NodeDefinition $service, NodeDefinition $task)
    {
        $service->children()
            ->scalarNode('target_dir')
                ->info('Target directory')
                ->defaultValue('./')
                ->beforeNormalization()
                    ->always()
                    ->then(function ($value) {
                        $realpath = realpath($value);

                        if (null == $realpath) {
                            throw new \UnexpectedValueException('Target directory does not resolve: ' . $value);
                        }

                        return $realpath;
                    })
                    ->end()
                ->end()
            ->end()
            ;

        $task->children()
            ->scalarNode('source_http')
                ->info('HTTP Client for retrieval')
                ->defaultValue('app')
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
            ->scalarNode('target_path')
                ->info('Target path')
                ->isRequired()
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        // @todo this allows directory traversal
        $realpath= $this->options['target_dir'] . '/' . $options['target_path'];
        
        $tmppath = dirname($realpath) . '/.' . basename($realpath);
        $fh = fopen($tmppath, 'w');

        $sourceOptions = isset($options['source_options']) ? $options['source_options'] : [];
        $sourceOptions['sink'] = $fh;

        $logger->debug('downloading');

        try {
            $this->container['http.' . $options['source_http']]->request(
                $options['source_method'],
                isset($options['source_url']) ? $options['source_url'] : null,
                $sourceOptions
            );
        } catch (\Exception $e) {
            fclose($fh);
            unlink($tmppath);

            throw $e;
        }

        $logger->debug('downloaded ' . filesize($tmppath) . ' bytes');

        rename($tmppath, $realpath);
    }
}
