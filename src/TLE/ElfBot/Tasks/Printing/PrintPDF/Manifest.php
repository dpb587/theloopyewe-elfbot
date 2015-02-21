<?php

namespace TLE\ElfBot\Tasks\Printing\PrintPDF;

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
        $options->setRequired([
            'queue_name',
        ]);

        $options->setDefaults(
            [
                'executable' => function (Options $options) {
                    $exec = (new ExecutableFinder())->find('lp');

                    if (null === $exec) {
                        throw new RuntimeException('Failed to find lp');
                    }

                    return $exec;
                },
            ]
        );
    }

    public function setTaskOptions(OptionsResolverInterface $options)
    {
        $options->setRequired([
            'pdf_url',
        ]);
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $logger->debug('retrieving pdf');

        $res = $this->application->getHttpClient()->get($options['pdf_url'])->send();

        file_put_contents(
            $r1 = (uniqid('/tmp/tle-r1-') . '.pdf'),
            $res->getBody(true)
        );


        $logger->debug('printing pdf');

        $p = new Process(
            sprintf(
                'lp -d %s %s',
                escapeshellarg($options['queue_name']),
                escapeshellarg($r1)
            )
        );

        $p->mustRun(
            function ($type, $bytes) use ($logger) {
                $logger->debug($type . ': ' . $bytes);
            }
        );


        $logger->debug('removing pdf');

        unlink($r1);
    }
}
