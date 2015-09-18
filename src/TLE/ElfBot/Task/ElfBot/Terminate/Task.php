<?php

namespace TLE\ElfBot\Task\ElfBot\Terminate;

use TLE\ElfBot\Task\AbstractTask;
use Psr\Log\LoggerInterface;

class Task extends AbstractTask
{
    public function execute(LoggerInterface $logger, array $options)
    {
        posix_kill(getmypid(), SIGTERM);
    }
}
