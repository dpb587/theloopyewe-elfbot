<?php

namespace TLE\Elfbot\Task\Elfbot\Update;

use TLE\Elfbot\Task\AbstractTask;
use Guzzle\Http\Client;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest as UpdateManifest;
use Herrera\Version\Parser;
use Psr\Log\LoggerInterface;

class Task extends AbstractTask
{
    static public function getDefinition(NodeDefinition $service, NodeDefinition $task)
    {
        $service->children()
            ->scalarNode('manifest_url')
                ->info('The version manifest file')
                ->isRequired()
                ->end()
            ->end()
            ;

        $task->children()
            ->booleanNode('major')
                ->info('True to upgrade to a new major version')
                ->defaultFalse()
                ->end()
            ->booleanNode('pre')
                ->info('True to upgrade to a new pre-release version')
                ->defaultFalse()
                ->end()
            ->booleanNode('terminate')
                ->info('True to self-terminate after upgrade')
                ->defaultTrue()
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $httpClient = new Client();

        $runtimeVersion = Parser::toVersion(ltrim($this->container['runtime.version'], 'v'));

        $logger->debug(sprintf('runtime version: %s', $runtimeVersion));

        $manifest = UpdateManifest::load($httpClient->get($this->options['manifest_url'])->send()->getBody(true));

        $update = $manifest->findRecent($runtimeVersion, $options['major'], $options['pre']);

        $logger->debug(sprintf('update version: %s', $update->getVersion()));

        if (null === $update) {
            $logger->info('no update is available');

            return;
        }

        $logger->warn('updating from ' . $runtimeVersion . ' to ' . $update->getVersion());

        if (!$this->container['runtime.is_phar']) {
            throw new \RuntimeException('Cannot update: not running as a PHAR.');
        }

        $manager = new Manager($manifest);

        $manager->update($runtimeVersion, $options['major'], $options['pre']);

        if ($options['terminate']) {
            posix_kill(getmypid(), SIGTERM);
        }
    }
}
