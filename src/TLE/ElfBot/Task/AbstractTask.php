<?php

namespace TLE\ElfBot\Task;

use TLE\ElfBot\Task\ManifestInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use TLE\ElfBot\Console\Application;
use Pimple\Container;

abstract class AbstractTask implements TaskInterface
{
    /**
     * @var mixed[string]
     */
    protected $options;

    /**
     * @var Container
     */
    protected $container;

    public function __construct(Container $container, array $options)
    {
        $this->container = $container;
        $this->options = $options;
    }

    static public function getDefinition(NodeDefinition $service, NodeDefinition $task)
    {
        // nop
    }
}
