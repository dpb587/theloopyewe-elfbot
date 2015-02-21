<?php

namespace TLE\ElfBot\Tasks\Core\LogMessage;

use TLE\ElfBot\Task\AbstractManifest;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class Manifest extends AbstractManifest
{
    public function setTaskOptions(OptionsResolverInterface $options)
    {
        $options->setRequired(
            [
                'message',
            ]
        );
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $logger->emergency($options['message']);
    }
}
