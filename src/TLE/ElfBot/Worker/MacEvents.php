<?php

namespace TLE\ElfBot\Worker;

use Pimple\Container;

class MacEvents
{
    protected $container;
    protected $options;
    private $idleTime;
    private $lastCanWorkError;

    public function __construct(Container $container, array $options)
    {
        $this->container = $container;
        $this->options = array_merge(
            [
                'screensaver_check' => false,
                'console_check' => true,
            ],
            $options
        );
    }

    public function canWork()
    {
        $error = $this->getInteractiveError();

        if (null === $error) {
            $this->lastCanWorkError = null;

            return true;
        }

        if ($error != $this->lastCanWorkError) {
            $this->lastCanWorkError = $error;

            $this->container['logger']->debug('cannot work: ' . $error);
        }

        return false;
    }

    public function canAcceptTask()
    {
        $error = $this->getInteractiveError();

        if (null === $error) {
            return true;
        }

        $this->container['logger']->debug('rejecting task: ' . $error);

        return false;
    }

    protected function getInteractiveError()
    {
        if ($this->options['screensaver_check']) {
            $idleTime = $this->requireIdleTime();

            $null = $return_var = null;
            exec(
                sprintf(
                    '/usr/bin/pgrep -q ScreenSaverEngine || [[ $( /usr/sbin/ioreg -c IOHIDSystem | /usr/bin/awk \'/HIDIdleTime/ {print int($NF/1000000000); exit}\' ) > %s ]]',
                    $idleTime
                ),
                $null,
                $return_var
            );

            if (0 == $return_var) {
                return 'idle or screen saver running';
            }
        }

        if ($this->options['console_check']) {
            if ($this->container['env.user'] != exec('stat -f \'%Su\' /dev/console')) {
                return 'not the console user';
            }
        }

        return null;
    }

    protected function requireIdleTime()
    {
        if (null === $this->idleTime) {
            $this->idleTime = exec('/usr/bin/defaults read com.apple.screensaver idleTime 2>/dev/null || /usr/bin/defaults -currentHost read com.apple.screensaver idleTime');

            $this->container['logger']->debug('screensaver delay is ' . $this->idleTime . 's');
        }

        return $this->idleTime;
    }
}
