<?php

namespace TLE\ElfBot\Tasks\ShopWebcam\CaptureUploadImage;

use Guzzle\Http\Client;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use TLE\ElfBot\Task\AbstractManifest;
use Psr\Log\LoggerInterface;

class Manifest extends AbstractManifest
{
    static public function setManifestOptions(OptionsResolverInterface $options)
    {
        $options->setRequired(
            [
                'source',
                'endpoint',
            ]
        );
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $webcam = new Client($this->options['source']);

        $logger->debug('downloading');

        $download = $webcam->get()->send();

        
        $logger->debug('uploading');

        $this->application->getHttpClient()->put(
            $this->options['endpoint'],
            [],
            $download->getBody()
        )->send();
    }
}
