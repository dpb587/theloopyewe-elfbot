<?php

namespace TLE\ElfBot\Worker\AwsSqs;

use Aws\Sqs\SqsClient;
use Pimple\Container;

class Worker
{
    protected $container;
    protected $client;
    protected $queue;

    public function __construct(Container $container, array $options)
    {
        $this->container = $container;
        $this->client = SqsClient::factory(
            [
                'key' => $options['key'],
                'secret' => $options['secret'],
                'region' => $options['region'],
            ]
        );

        if (isset($options['queue_url'])) {
            $this->queue = $options['queue_url'];
        } else {
            $this->queue = $this->client->getQueueUrl([
                'QueueName' => $options['queue_name'],
            ])['QueueUrl'];

            $this->container['logger']->debug('resolved sqs queue url: ' . $this->queue);
        }
    }

    public function work($taskCallback)
    {
        $msgpack = $this->client->receiveMessage([
            'QueueUrl' => $this->queue,
            'WaitTimeSeconds' => 20,
            'MaxNumberOfMessages' => 1,
            'VisibilityTimeout' => 20,
        ]);

        if (!isset($msgpack['Messages'])) {
            $this->container['logger']->debug('nothing to do');

            return;
        }

        foreach ($msgpack['Messages'] as $msg) {
            $this->container['logger']->debug(sprintf('message %s received: %s', $msg['MessageId'], $msg['Body']));

            if (!$this->container['worker_events']->canAcceptTask()) {
                $this->container['logger']->debug(sprintf('message %s rejected', $msg['MessageId']));

                return false;
            }

            $this->client->deleteMessage([
                'QueueUrl' => $this->queue,
                'ReceiptHandle' => $msg['ReceiptHandle'],
            ]);

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

            $taskCallback($msg['MessageId'], key($data), current($data));
        }
    }
}
