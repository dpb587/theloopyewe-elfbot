<?php

namespace TLE\Elfbot\Task\Printing\PrintPage;

use TLE\Elfbot\Task\AbstractTask;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Psr\Log\LoggerInterface;

class Task extends AbstractTask
{
    static public function getDefinition(NodeDefinition $service, NodeDefinition $task)
    {
        $service->children()
            ->scalarNode('lp')
                ->info('The path to lp')
                ->defaultValue('lp')
                ->beforeNormalization()
                    ->always()
                    ->then(function ($value) {
                        return ('/' == $value[0]) ? $value : (new ExecutableFinder())->find($value);
                    })
                    ->end()
                ->end()
            ->scalarNode('wkhtmltopdf')
                ->info('The path to wkhtmltopdf')
                ->defaultValue('wkhtmltopdf')
                ->beforeNormalization()
                    ->always()
                    ->then(function ($value) {
                        return ('/' == $value[0]) ? $value : (new ExecutableFinder())->find($value);
                    })
                    ->end()
                ->end()
            ->scalarNode('queue_name')
                ->info('The name of the printer queue to use')
                ->isRequired()
                ->end()
            ->end()
            ;

        $task->children()
            ->scalarNode('source_url')
                ->info('The URL to print from')
                ->isRequired()
                ->end()
            ->scalarNode('page_size')
                ->info('Page size to print on')
                ->defaultValue('Letter')
                ->end()
            ->booleanNode('grayscale')
                ->info('True to print in grayscale')
                ->defaultFalse()
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        $logger->debug('retrieving source');

        $res = $this->container['http.app']->request('GET', $options['source_url']);

        file_put_contents(
            $r1 = (uniqid('/tmp/tle-r1-') . '.data'),
            $res->getBody()->getContents()
        );

        if (false !== stripos($res->getHeaderLine('Content-Type'), 'text/html')) {
            if (null == $this->options['wkhtmltopdf']) {
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
                    escapeshellarg($this->options['wkhtmltopdf']),
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
        } elseif (false !== stripos($res->getHeaderLine('Content-Type'), 'application/pdf')) {
            $r2 = null;
            $pdffile = $r1;
        } else {
            throw new \LogicException('Cannot print mimetype (' . $res->getHeaderLine('content-type') . ')');
        }

        $logger->debug('printing pdf');

        $p = new Process(
            sprintf(
                '%s -d %s %s',
                escapeshellarg($this->options['lp']),
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
