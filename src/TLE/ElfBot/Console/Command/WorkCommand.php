<?php

namespace TLE\ElfBot\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Monolog\Logger;

class WorkCommand extends AbstractCommand
{
    protected function configure()
    {
        parent::configure()
            ->setName('work')
            ->setDescription('Work from the queue')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer($input, $output);

        $logger = $container['logger'];
        $wantsExit = false;

        $signals = function () use (&$wantsExit, $logger) {
            $logger->info('received signal to exit');

            $wantsExit = true;
        };

        declare(ticks = 1);

        pcntl_signal(SIGINT, $signals);
        pcntl_signal(SIGTERM, $signals);

        $taskCallback = function ($id, $task, array $options) use ($container) {
            $logger = new Logger(sprintf('%s/%s', $container['runtime.name'] . ($container['logger.channel'] ? ('/' . $container['logger.channel']) : ''), $id));
            $logger->pushHandler($container['logger.handler']);

            try {
                $logger->debug($task . ' started');

                $mt = microtime(true);

                $container['task_factory']->execute($logger, $task, $options);

                $duration = (microtime(true) - $mt) * 1000;

                $logger->info($task . ' completed (' . $duration . 'ms)');

                return true;
            } catch (\Exception $e) {
                $duration = (microtime(true) - $mt) * 1000;

                $logger->info($task . ' failed (' . $duration . 'ms)');

                $logger->critical($e->getMessage());

                foreach ($e->getTrace() as $i => $t) {
                    $logger->error(
                        sprintf(
                            '%s) %s:%s',
                            $i,
                            isset($t['file']) ? $t['file'] : '?',
                            isset($t['line']) ? $t['line'] : '?'
                        )
                    );
                }

                return false;
            }
        };

        while (!$wantsExit) {
            $container['worker']->work($taskCallback);
        }

        $logger->info('done');
    }
}
