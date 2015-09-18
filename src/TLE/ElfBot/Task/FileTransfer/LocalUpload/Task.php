<?php

namespace TLE\ElfBot\Task\FileTransfer\LocalUpload;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use TLE\ElfBot\Task\AbstractTask;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;

class Task extends AbstractTask
{
    static public function getDefinition(NodeDefinition $service, NodeDefinition $task)
    {
        $service->children()
            ->scalarNode('source_dir')
                ->info('Source directory')
                ->defaultValue('./')
                ->beforeNormalization()
                    ->always()
                    ->then(function ($value) {
                        $realpath = realpath($value);

                        if (null == $realpath) {
                            throw new \UnexpectedValueException('Source directory does not resolve: ' . $value);
                        }

                        return $realpath;
                    })
                    ->end()
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

        $task->children()
            ->scalarNode('source_path')
                ->info('Source path')
                ->isRequired()
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $path = $options['source_path'];

        if ('/' != $path[0]) {
            $path = $this->options['source_dir'] . '/' . $path;
        }

        $path = realpath($path);

        if (!$path) {
            throw new \UnexpectedValueException('Source file does not exist');
        }

        if (substr($path, 0, strlen($this->options['source_dir'])) != $this->options['source_dir']) {
            throw new \RuntimeException('Source path is not within configured source directory');
        }

        $spl = new \SplFileInfo($path);
        $fh = $spl->openFile();

        $targetOptions = isset($this->options['target_options']) ? $this->options['target_options'] : [];
        $targetOptions['body'] = $fh;
        $targetOptions['headers']['x-mime-type'] = MimeTypeGuesser::getInstance()->guess($path);
        $targetOptions['headers']['x-file-name'] = $spl->getFilename();
        $targetOptions['headers']['x-file-perms'] = $spl->getPerms();
        $targetOptions['headers']['x-file-ctime'] = $spl->getCTime();
        $targetOptions['headers']['x-file-mtime'] = $spl->getCTime();

        $group = posix_getgrgid($spl->getGroup());

        if ($group) {
            $targetOptions['headers']['x-file-group'] = $group['name'];
        }

        $owner = posix_getpwuid($spl->getOwner());

        if ($owner) {
            $targetOptions['headers']['x-file-owner'] = $owner['name'];
        }

        $logger->debug('uploading ' . $spl->getSize() . ' bytes');

        try {
            $this->container['http.' . $this->options['target_http']]->request(
                $this->options['target_method'],
                isset($this->options['target_url']) ? $this->options['target_url'] : null,
                $targetOptions
            );
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
