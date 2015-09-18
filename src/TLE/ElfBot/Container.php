<?php

namespace TLE\ElfBot;

use Phar;
use Pimple\Container as PimpleContainer;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Container extends PimpleContainer
{
    public function __construct()
    {
        parent::__construct();

        $this['runtime.name'] = Manifest::NAME;
        $this['runtime.version'] = Manifest::VERSION;
        $this['runtime.commit'] = Manifest::COMMIT;
        $this['runtime.log_level'] = 500;

        $this['runtime.is_release'] = function ($c) {
            return ('@' . 'git-version@') !== $c['runtime.version'];
        };
        $this['runtime.is_phar'] = function () {
            return Phar::running();
        };

        $this['logger.channel'] = null;

        $this['logger.formatter'] = function () {
            return new LineFormatter("%datetime% %channel% %level_name% %message%\n", 'c');
        };

        $this['logger.handler'] = function ($c) {
            $handler = new StreamHandler(fopen('php://stderr', 'w'), $c['runtime.log_level']);
            $handler->setFormatter($c['logger.formatter']);

            return $handler;
        };

        $this['logger'] = function ($c) {
            $logger = new Logger($c['runtime.name'] . ($c['logger.channel'] ? ('/' . $c['logger.channel']) : ''));
            $logger->pushHandler($c['logger.handler']);

            return $logger;
        };

        $this['filesystem'] = function () {
            return new Filesystem();
        };

        $this['task_factory'] = function ($c) {
            return new Task\TaskFactory($c);
        };
    }
}
