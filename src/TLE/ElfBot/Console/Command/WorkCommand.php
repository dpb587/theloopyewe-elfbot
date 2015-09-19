<?php

namespace TLE\ElfBot\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Monolog\Logger;

class WorkCommand extends AbstractCommand
{
    protected function configure()
    {
        parent::configure()
            ->setName('work')
            ->setDescription('Work from the queue')
            ->addOption('max-tasks', null, InputOption::VALUE_REQUIRED, 'Exit cleanly after this many tasks')
            ->addOption('max-lifetime', null, InputOption::VALUE_REQUIRED, 'Exit cleanly after this many seconds of running')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer($input, $output);

        $logger = $container['logger'];
        $worker = $container['worker'];
        $workerEvents = $container['worker_events'];

        $canWork = true;
        $wantsExit = false;

        $tasksLimit = $input->getOption('max-tasks');
        $tasksCount = 0;

        $lifetimeStart = time();
        $lifetimeLimit = $input->getOption('max-lifetime');

        if (null !== $lifetimeLimit) {
            $lifetimeLimit += $lifetimeStart;
        }

        $signals = function () use (&$wantsExit, $logger) {
            $logger->info('received signal to exit');

            $wantsExit = true;
        };

        declare(ticks = 1);

        pcntl_signal(SIGINT, $signals);
        pcntl_signal(SIGTERM, $signals);

        $taskCallback = function ($id, $taskName, array $options) use ($container, &$tasksCount) {
            $tasksCount += 1;

            $logger = new Logger(sprintf('%s/%s', $container['runtime.name'] . ($container['logger.channel'] ? ('/' . $container['logger.channel']) : ''), $id));
            $logger->pushHandler($container['logger.handler']);

            $logger->debug($taskName . ' started');

            $mt = microtime(true);

            try {
                $container['task_factory']->execute($logger, $taskName, $options);
            } catch (\Exception $e) {
                $duration = ceil((microtime(true) - $mt) * 1000);

                $logger->info($taskName . ' failed (' . $duration . 'ms)');

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

            $duration = ceil((microtime(true) - $mt) * 1000);

            $logger->info($taskName . ' completed (' . $duration . 'ms)');

            return true;
        };

        while (!$wantsExit) {
            if ($canWork) {
                $canWork = (false !== $worker->work($taskCallback));
            } else {
                $canWork = $workerEvents->canWork();

                if (!$canWork && !$wantsExit) {
                    sleep(60);
                }
            }

            if ((null !== $tasksLimit) && ($tasksCount >= $tasksLimit)) {
                $logger->warn(sprintf('stopping (%s tasks were run)', $tasksCount));

                $wantsExit = true;
            } elseif ((null !== $lifetimeLimit) && (time() >= $lifetimeLimit)) {
                $logger->warn(sprintf('stopping (lived %s seconds)', (time() - $lifetimeStart)));

                $wantsExit = true;
            }
        }

        $logger->info('done');
    }
}
