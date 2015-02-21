<?php

namespace TLE\ElfBot\Console\Command;

use Symfony\Component\Console\Command\Command;
use Psr\Log\LoggerInterface;

abstract class AbstractLoggerAwareCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }
}
