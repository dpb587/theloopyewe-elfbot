<?php

namespace TLE\ElfBot\Task\ElfBot\DisplayDialog;

use TLE\ElfBot\Task\AbstractTask;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Psr\Log\LoggerInterface;

class Task extends AbstractTask
{
    static public function getDefinition(NodeDefinition $service, NodeDefinition $task)
    {
        $service->children()
            ->scalarNode('osascript')
                ->info('The path to osascript')
                ->defaultValue('osascript')
                ->beforeNormalization()
                    ->always()
                    ->then(function ($value) {
                        return (new ExecutableFinder())->find($value);
                    })
                    ->end()
                ->end()
            ->end()
            ;

        $task->children()
            ->scalarNode('text')
                ->info('The text to show in the dialog')
                ->isRequired()
                ->end()
            ->arrayNode('buttons')
                ->info('Buttons to show in the dialog')
                ->defaultValue([ 'OK' ])
                ->prototype('scalar')
                    ->end()
                ->end()
            ->scalarNode('default_button')
                ->info('The default button')
                ->defaultValue('OK')
                ->end()
            ->scalarNode('cancel_button')
                ->info('The button to cancel')
                ->end()
            ->scalarNode('title')
                ->info('The title of the dialog')
                ->end()
            ->scalarNode('icon')
                ->info('The icon for the dialog')
                ->end()
            ->scalarNode('timeout')
                ->info('The timeout in seconds for the dialog to cancel')
                ->end()
            ->scalarNode('result_url')
                ->info('The callback URL to post the result')
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $script = 'tell application "System Events" to display dialog "' . $options['text'] . '"';

        if (isset($options['buttons'])) {
            $script .= ' buttons [ ' . implode(', ', array_map(function ($v) { return '"' . addslashes($v) . '"'; }, $options['buttons'])) . ' ]';
        }

        if (isset($options['default_button'])) {
            $script .= ' default button ' . (is_string($options['default_button']) ? ('"' . addslashes($options['default_button']) . '"') : $options['default_button']);
        }

        if (isset($options['cancel_button'])) {
            $script .= ' cancel button ' . (is_string($options['cancel_button']) ? ('"' . addslashes($options['cancel_button']) . '"') : $options['cancel_button']);
        }

        if (isset($options['title'])) {
            $script .= ' with title "' . $options['title'] . '"';
        }

        if (isset($options['icon'])) {
            $script .= ' with icon ';

            if (in_array($options['icon'], [ 'caution', 'note', 'stop' ])) {
                $script .= $options['icon'];
            } else {
                $script .= ' with icon ' . (is_string($options['icon']) ? ('"' . addslashes($options['icon']) . '"') : $options['icon']);
            }
        }

        if (isset($options['timeout'])) {
            $script .= ' giving up after ' . $options['timeout'];
        }

        $logger->debug($script);

        $p = new Process(
            sprintf(
                '%s -e %s',
                $this->options['osascript'],
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

        if (isset($options['result_url'])) {
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

            $this->container['http.app']->request(
                'POST',
                $options['result_url'],
                [
                    'form_params' => $data,
                ]
            );
        }
    }
}
