<?php

namespace TLE\ElfBot\Task\Endicia\ArchiveMailingLog;

use TLE\ElfBot\Task\AbstractTask;
use GuzzleHttp\Client;
use Symfony\Component\Process\Process;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Psr\Log\LoggerInterface;

class Task extends AbstractTask
{
    static public function getDefinition(NodeDefinition $service, NodeDefinition $task)
    {
        $service->children()
            ->arrayNode('paths')
                ->info('The mailing log files to monitor')
                ->prototype('scalar')
                    ->end()
                ->defaultValue([
                    '~/Library/Application Support/Endicia/mailinglog.plist',
                ])
                ->end()
            ->scalarNode('localarchive')
                ->info('The directory to move the archived files to')
                ->defaultValue('~/Documents/Endicia Archive')
                ->end()
            ->end()
            ;

        $task->children()
            ->scalarNode('prepare_url')
                ->info('URL to create the upload endpoint')
                ->isRequired()
                ->end()
            ->scalarNode('finish_url')
                ->info('URL to finish the uploaded archive')
                ->isRequired()
                ->end()
            ->end()
            ;
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        foreach ($this->options['paths'] as $path) {
            if (!file_exists($path)) {
                $logger->notice($path . ' does not exist; skipping');

                continue;
            } elseif (256 >= filesize($path)) {
                $logger->notice($path . ' is tiny; skipping');

                continue;
            }

            $logger->debug($path . ' is ' . filesize($path) . ' bytes');


            /**
             * make sure endicia has clean exit
             */

            $logger->debug('quitting endicia');

            $p = new Process('osascript -e ' . escapeshellarg('tell application "Endicia" to quit'));

            $p->mustRun();


            /**
             * move out of the way
             */

            $localname = $this->options['localarchive'] . '/' . date('Y-m-d') . '-' . substr(md5_file($path), 0, 8) . '.plist';

            $logger->debug('moving "' . $path . '" to "' . $localname . '"');

            if (!is_dir($this->options['localarchive'])) {
                mkdir($this->options['localarchive'], 0700, true);
            }

            $p = new Process(
                sprintf(
                    'mv %s %s',
                    escapeshellarg($path),
                    escapeshellarg($localname)
                )
            );

            $p->mustRun();


            /**
             * convert binary to xml
             */

            $logger->debug('converting to xml');

            $p = new Process(
                sprintf(
                    'plutil -convert xml1 -o %s %s',
                    escapeshellarg($localname . '.xml'),
                    escapeshellarg($localname)
                )
            );

            $p->mustRun();


            /**
             * create upload destination
             */

            $xmlmd5 = md5_file($localname . '.xml');

            $logger->debug('creating remote archive target');

            try {
                $res = $this->container['http.app']->request(
                    'POST',
                    $options['prepare_url'],
                    [
                        'form_params' => [
                            'path' => $path,
                            'hostname' => gethostname(),
                            'source' => 'endicia.mailinglog',
                            'size' => filesize($localname . '.xml'),
                            'md5' => $xmlmd5,
                        ],
                    ]
                );
            } catch (\Exception $e) {
                $logger->critical($e->getResponse()->getBody(true));

                throw $e;
            }

            $prepare = json_decode($res->getBody(true), true);


            /**
             * upload
             */

            $logger->debug('sending ' . filesize($localname . '.xml') . ' bytes to #' . $prepare['id'] . ' storage');

            try {
                $awsraw = new Client();
                $awsraw->request(
                    'PUT',
                    $prepare['upload_url'],
                    [
                        'headers' => [
                            'Content-Type' => 'application/x-plist',
                            'Content-MD5' => base64_encode(hex2bin($xmlmd5)),
                            'Content-Length' => filesize($localname . '.xml'),
                            'x-amz-acl' => 'private',
                        ],
                        'body' => fopen($localname . '.xml', 'r'),
                    ]
                );
            } catch (\Exception $e) {
                $logger->critical($e->getResponse()->getBody(true));

                throw $e;
            }
            

            /**
             * finish upload
             */

            $logger->debug('closing upload');

            try {
                $this->container['http.app']->request(
                    'POST',
                    $options['finish_url'],
                    [
                        'form_params' => [
                            'id' => $prepare['id'],
                        ],
                    ]
                );
            } catch (\Exception $e) {
                $logger->critical($e->getResponse()->getBody(true));

                throw $e;
            }


            /**
             * remove xml1
             */

            $logger->debug('removing xml-converted file');

            $p = new Process(
                sprintf(
                    'rm %s',
                    escapeshellarg($localname . '.xml')
                )
            );

            $p->mustRun();


            /**
             * log
             */

            $logger->info('uploaded "' . $path . '" to archive #' . $prepare['id']);
        }
    }
}
