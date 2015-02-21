<?php

namespace TLE\ElfBot\Tasks\DYMOLabel\PrintLabel;

use TLE\ElfBot\Task\AbstractManifest;
use Guzzle\Http\Client;
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
                    return (new ExecutableFinder())->find('osascript');
                },
            ]
        );
    }

    public function setTaskOptions(OptionsResolverInterface $options)
    {
        $options->setRequired(
            [
                'label_url',
            ]
        );

        $options->setDefaults(
            [
                'print' => 1,
            ]
        );
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $res = $this->application->getHttpClient()->get($options['label_url'])->send();

        file_put_contents(
            $r1 = (uniqid('/tmp/tle-r1-') . '.applescript'),
            file_get_contents(__DIR__ . '/script.applescript')
        );

        file_put_contents(
            $r2 = (uniqid('/tmp/tle-r2-') . '.label'),
            $res->getBody(true)
        );

        $p = new Process(
            sprintf(
                '%s %s %s %s',
                $this->options['executable'],
                $r1,
                $r2,
                $options['print']
            )
        );

        $p->run(
            function ($type, $bytes) use ($logger) {
                $logger->debug($type . ': ' . $bytes);
            }
        );

        unlink($r1);
        unlink($r2);
    }
}
