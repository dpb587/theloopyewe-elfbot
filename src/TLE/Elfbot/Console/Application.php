<?php

namespace TLE\Elfbot\Console;

use TLE\Elfbot\Manifest;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct(Manifest::NAME, ltrim(Manifest::VERSION, 'v'));
    }
    
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        $commands[] = new Command\InstallLaunchdCommand();
        $commands[] = new Command\ExecCommand();
        $commands[] = new Command\TaskCommand();
        $commands[] = new Command\WorkCommand();

        return $commands;
    }
}
