<?php

namespace TLE\ElfBot\Tasks\Core\DisplayDialog;

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
        $options->setRequired(
            [
                'text',
            ]
        );

        $options->setDefaults(
            [
                'buttons' => [ 'OK' ],
                'default_button' => function (Options $options) {
                    return $options['buttons'][count($options['buttons']) - 1];
                },
                'cancel_button' => null,
                'title' => null,
                'icon' => null,
                'timeout' => null,
                'result_url' => null,
            ]
        );
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $script = 'tell application "System Events" to display dialog "' . $options['text'] . '"';

        if ($options['buttons']) {
            $script .= ' buttons [ ' . implode(', ', array_map(function ($v) { return '"' . addslashes($v) . '"'; }, $options['buttons'])) . ' ]';
        }

        if ($options['default_button']) {
            $script .= ' default button ' . (is_string($options['default_button']) ? ('"' . addslashes($options['default_button']) . '"') : $options['default_button']);
        }

        if ($options['cancel_button']) {
            $script .= ' cancel button ' . (is_string($options['cancel_button']) ? ('"' . addslashes($options['cancel_button']) . '"') : $options['cancel_button']);
        }

        if ($options['title']) {
            $script .= ' with title "' . $options['title'] . '"';
        }

        if ($options['icon']) {
            $script .= ' with icon ';

            if (in_array($options['icon'], [ 'caution', 'note', 'stop' ])) {
                $script .= $options['icon'];
            } else {
                $script .= ' with icon ' . (is_string($options['icon']) ? ('"' . addslashes($options['icon']) . '"') : $options['icon']);
            }
        }

        if ($options['timeout']) {
            $script .= ' giving up after ' . $options['timeout'];
        }

        $logger->debug($script);

        $p = new Process(
            sprintf(
                '%s -e %s',
                $this->options['executable'],
                escapeshellarg($script)
            ),
            null,
            null,
            null,
            null
        );

        $p->run(
            function ($type, $bytes) use ($logger) {
                $logger->debug($type . ': ' . $bytes);
            }
        );

        if ($p->getExitCode() && preg_match('/(syntax|execution) error:/', $p->getErrorOutput()) && !preg_match('/user canceled/i', $p->getErrorOutput())) {
            throw new \RuntimeException($p->getErrorOutput());
        }

        if ($options['result_url']) {
            if ($p->getExitCode()) {
                $data = [
                    'canceled' => true,
                ];
            } else {
                preg_match('/(^|\s)button returned:(' . implode('|', array_map(function ($v) { return preg_quote($v); }, $options['buttons'])) . ')(\s|$)/', $p->getOutput(), $button);
                preg_match('/(^|\s)gave up:(false|true)(\s|$)/', $p->getOutput(), $gaveup);

                $data = [
                    'button' => isset($button[2]) ? $button[2] : null,
                    'timed_out' => (isset($gaveup[2]) && ('true' == $gaveup[2])) ? true : false,
                ];
            }

            $logger->debug('posting ' . json_encode($data, JSON_UNESCAPED_SLASHES));

            $this->application->getHttpClient()->request(
                'POST',
                $options['result_url'],
                [
                    'form_params' => $data,
                ]
            );
        }
    }
}
