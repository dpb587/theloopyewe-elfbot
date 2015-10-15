<?php

namespace TLE\Elfbot\Task\Elfbot\Terminate;

use TLE\Elfbot\Task\AbstractTask;
use Psr\Log\LoggerInterface;

class Task extends AbstractTask
{
    public function execute(LoggerInterface $logger, array $options)
    {
        posix_kill(getmypid(), SIGTERM);
    }
}
