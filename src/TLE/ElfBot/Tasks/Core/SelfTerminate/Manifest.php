<?php

namespace TLE\ElfBot\Tasks\Core\SelfTerminate;

use TLE\ElfBot\Task\AbstractManifest;
use Psr\Log\LoggerInterface;

class Manifest extends AbstractManifest
{
    public function execute(LoggerInterface $logger, array $options)
    {
        posix_kill(getmypid(), SIGTERM);
    }
}
