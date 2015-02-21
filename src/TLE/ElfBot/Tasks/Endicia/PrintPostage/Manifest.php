<?php

namespace TLE\ElfBot\Tasks\Endicia\PrintPostage;

use RuntimeException;
use TLE\ElfBot\Task\AbstractManifest;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Psr\Log\LoggerInterface;

class Manifest extends AbstractManifest
{
    static public function setManifestOptions(OptionsResolverInterface $options)
    {
        $options->setDefaults(
            [
                'executable' => function (Options $options) {
                    $exec = (new ExecutableFinder())->find('endiciatool');

                    if (null === $exec) {
                        throw new RuntimeException('Failed to find endiciatool');
                    }

                    return $exec;
                },
            ]
        );
    }

    public function setTaskOptions(OptionsResolverInterface $options)
    {
        $options->setRequired(
            [
                'dazzlexml',
                'manifestxml',
            ]
        );
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $logger->debug('retrieving dazzle xml');

        $res = $this->application->getHttpClient()->get($options['dazzlexml'])->send();

        $logger->debug($res->getBody(true));


        $logger->debug('sending dazzle to endicia');

        $p = new Process(
            $this->options['executable'],
            null,
            null,
            $res->getBody(true),
            300
        );

        $p->mustRun(
            function ($type, $bytes) use ($logger) {
                $logger->debug($type . ': ' . $bytes);
            }
        );


        $logger->debug('uploading endicia response');

        $this->application->getHttpClient()->send(
            $this->application->getHttpClient()->put(
                $options['manifestxml'],
                [],
                $p->getOutput()
            )
        );
    }
}
