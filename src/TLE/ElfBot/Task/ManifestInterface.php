<?php

namespace TLE\ElfBot\Task;

use Guzzle\Http\Client;
use Psr\Log\LoggerInterface;

interface ManifestInterface
{
    /**
     * @param LoggerInterface $logger
     * @param Client $guzzle
     * @param mixed[string] $options
     * @return void
     */
    public function execute(LoggerInterface $logger, array $options);
}
