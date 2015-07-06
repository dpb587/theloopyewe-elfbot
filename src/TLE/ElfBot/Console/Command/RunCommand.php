<?php

namespace TLE\ElfBot\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TLE\ElfBot\Utility;

class RunCommand extends AbstractLoggerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Run the queue worker')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $application = $this->getApplication();

        // tasks

        $tasks = [
            'core.display_dialog' => Utility::createService($application, 'TLE\ElfBot\Tasks\Core\DisplayDialog\Manifest'),
            'core.log_message' => Utility::createService($application, 'TLE\ElfBot\Tasks\Core\LogMessage\Manifest'),
            'core.self_terminate' => Utility::createService($application, 'TLE\ElfBot\Tasks\Core\SelfTerminate\Manifest'),
        ];

        foreach ($application->getConfig()['tasks'] as $name => $task) {
            $tasks[$name] = Utility::createService(
                $application,
                $task['class'],
                isset($task['options']) ? $task['options'] : []
            );
        }

        // signals

        $wantsExit = false;
        $isWorking = false;
        $timeoutQueue = null;
        $timeoutAlarm = time() + 20;

        $logger = $this->logger;

        $signals = function ($signal) use (&$wantsExit, &$timeoutAlarm, &$timeoutQueue, &$isWorking, $logger) {
            if ($signal == SIGALRM) {
                $now = time();

                if ($timeoutAlarm < $now) {
                    $logger->error('missed a SIGALRM');

                    if (!$isWorking) {
                        $logger->info('suicide since no job is being worked');

                        posix_kill(getmypid(), SIGKILL);
                    }
                } elseif ((null !== $timeoutQueue) && ($timeoutQueue < $now)) {
                    if ($wantsExit) {
                        // assume we've already been here
                        $logger->info('suicide since queue polling timed out');

                        posix_kill(getmypid(), SIGKILL);
                    }

                    $logger->error('queue polling is timing out');

                    posix_kill(getmypid(), SIGTERM);
                } else {
                    $timeoutAlarm = $now + 20;

                    pcntl_alarm(16);

                    return;
                }
            }

            $logger->info('received signal to exit');

            if (!$isWorking) {
                $logger->warn('waiting for queue polling to finish');
            } else {
                $logger->warn('waiting for task to finish');
            }

            $wantsExit = true;
        };

        declare(ticks = 1);

        pcntl_signal(SIGINT, $signals);
        pcntl_signal(SIGTERM, $signals);
        pcntl_signal(SIGALRM, $signals);

        pcntl_alarm(16);

        // ready, set, go

        $this->logger->debug('resolving queue url');

        $queueClient = $application->getQueueClient();

        $queueUrl = $queueClient->getQueueUrl([
            'QueueName' => $application->getConfig()['queue']['aws_sqs']['queue_name'],
        ])['QueueUrl'];

        $this->logger->debug($queueUrl);

        $this->logger->info('ready');

        while (!$wantsExit) {
            $timeoutQueue = time() + 60;

            $msgpack = $queueClient->receiveMessage(
                [
                    'QueueUrl' => $queueUrl,
                    'WaitTimeSeconds' => 20,
                ]
            );

            $timeoutQueue = null;

            if (!isset($msgpack['Messages'])) {
                $this->logger->debug('no messages received');

                continue;
            }

            foreach ($msgpack['Messages'] as $msg) {
                $this->logger->info('message ' . $msg['MessageId'] . ' received');

                $isWorking = true;

                $messageLogger = $application->getLogger($msg['MessageId'], null, 400);

                $messageLogger->debug($msg['Body']);

                try {
                    $queueClient->deleteMessage(
                        [
                            'QueueUrl' => $queueUrl,
                            'ReceiptHandle' => $msg['ReceiptHandle'],
                        ]
                    );

                    $data = json_decode($msg['Body'], true);

                    $jsonerr = json_last_error();

                    if ($jsonerr) {
                        switch ($jsonerr) {
                            case JSON_ERROR_DEPTH:
                                throw new \RuntimeException('JSON Parse Failure: The maximum stack depth has been exceeded');
                            case JSON_ERROR_STATE_MISMATCH:
                                throw new \RuntimeException('JSON Parse Failure: Invalid or malformed JSON');
                            case JSON_ERROR_CTRL_CHAR:
                                throw new \RuntimeException('JSON Parse Failure: Control character error, possibly incorrectly encoded');
                            case JSON_ERROR_SYNTAX:
                                throw new \RuntimeException('JSON Parse Failure: Syntax error');
                            case JSON_ERROR_UTF8:
                                throw new \RuntimeException('JSON Parse Failure: Malformed UTF-8 characters, possibly incorrectly encoded');
                        }
                    }

                    $messageLogger->debug('starting ' . key($data));

                    if (!isset($tasks[key($data)])) {
                        throw new \LogicException('No task definition for ' . key($data) . ' is registered.');
                    }

                    $tasks[key($data)]->execute(
                        $messageLogger,
                        Utility::getTaskOptions($tasks[key($data)], current($data))
                    );

                    $this->logger->info('message ' . $msg['MessageId'] . ' succeeded');
                } catch (\Exception $e) {
                    $messageLogger->critical($e->getMessage());

                    foreach ($e->getTrace() as $i => $t) {
                        $messageLogger->error(
                            sprintf(
                                '%s) %s:%s',
                                $i,
                                isset($t['file']) ? $t['file'] : '?',
                                isset($t['line']) ? $t['line'] : '?'
                            )
                        );
                    }

                    $this->logger->info('message ' . $msg['MessageId'] . ' failed');
                }

                $isWorking = false;
            }
        }

        $this->logger->info('done');
    }
}
