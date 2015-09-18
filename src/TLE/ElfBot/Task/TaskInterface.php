<?php

namespace TLE\ElfBot\Task;

use Guzzle\Http\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

interface TaskInterface
{
    static public function getDefinition(NodeDefinition $service, NodeDefinition $task);

    /**
     * @param LoggerInterface $logger
     * @param Client $guzzle
     * @param mixed[string] $options
     * @return void
     */
    public function execute(LoggerInterface $logger, array $options);
}
