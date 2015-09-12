<?php

namespace TLE\ElfBot\Tasks\Printing\PrintPage;

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
                'exec_lp' => function (Options $options) {
                    $exec = (new ExecutableFinder())->find('lp');

                    if (null === $exec) {
                        throw new RuntimeException('Failed to find lp');
                    }

                    return $exec;
                },
                'exec_wkhtmltopdf' => function (Options $options) {
                    $exec = (new ExecutableFinder())->find('wkhtmltopdf');

                    return $exec;
                },
            ]
        );
    }

    public function setTaskOptions(OptionsResolverInterface $options)
    {
        $options->setRequired([
            'source_url',
        ]);

        $options->setDefaults([
            'page_size' => 'Letter',
            'grayscale' => false,
        ]);
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $logger->debug('retrieving source');

        $res = $this->application->getHttpClient()->request('GET', $options['source_url']);

        file_put_contents(
            $r1 = (uniqid('/tmp/tle-r1-') . '.data'),
            $res->getBody()->getContents()
        );

        $res->getContentType();
        if ($res->isContentType('text/html')) {
            if (null == $this->options['exec_wkhtmltopdf']) {
                throw new \LogicException('Unable to convert text/html without wkhtmltopdf configured.');
            }

            $logger->debug('converting html to pdf');

            // wkhtmltopdf needs a standard extension
            rename($r1, $r1 . '.html');
            $r1 = $r1 . '.html';

            $r2 = uniqid('/tmp/tle-r1-') . '.converted';

            $p = new Process(
                sprintf(
                    '%s --disable-local-file-access %s %s %s %s',
                    escapeshellarg($this->options['exec_wkhtmltopdf']),
                    $options['page_size'] ? ('--page-size ' . $options['page_size']) : '',
                    $options['grayscale'] ? '--grayscale' : '',
                    escapeshellarg($r1),
                    escapeshellarg($r2)
                )
            );

            $p->mustRun(
                function ($type, $bytes) use ($logger) {
                    $logger->debug($type . ': ' . $bytes);
                }
            );

            $pdffile = $r2;
        } elseif ($res->isContentType('application/pdf')) {
            $r2 = null;
            $pdffile = $r1;
        } else {
            throw new \LogicException('Cannot print mimetype (' . $res->getHeader('content-type') . ')');
        }

        $logger->debug('printing pdf');

        $p = new Process(
            sprintf(
                '%s -d %s %s',
                escapeshellarg($this->options['exec_lp']),
                escapeshellarg($this->options['queue_name']),
                escapeshellarg($pdffile)
            )
        );

        $p->mustRun(
            function ($type, $bytes) use ($logger) {
                $logger->debug($type . ': ' . $bytes);
            }
        );

        $logger->debug('removing files');

        unlink($r1);

        if ($r2) {
            unlink($r2);
        }
    }
}
