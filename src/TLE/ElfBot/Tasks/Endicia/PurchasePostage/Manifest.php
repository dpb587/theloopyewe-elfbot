<?php

namespace TLE\ElfBot\Tasks\Endicia\PurchasePostage;

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
                    return (new ExecutableFinder())->find('osascript');
                },
            ]
        );
    }

    public function setTaskOptions(OptionsResolverInterface $options)
    {
        $options->setDefaults(
            [
                'amount' => 100,
            ]
        );
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        file_put_contents(
            $r1 = (uniqid('/tmp/tle-r1-') . '.applescript'),
            file_get_contents(__DIR__ . '/script.applescript')
        );

        $p = new Process(
            sprintf(
                '%s %s %s',
                $this->options['executable'],
                $r1,
                $options['amount']
            )
        );

        $p->run(
            function ($type, $bytes) use ($logger) {
                $logger->debug($type . ': ' . $bytes);
            }
        );

        unlink($r1);
    }
}
