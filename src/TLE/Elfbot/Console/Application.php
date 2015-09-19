<?php

namespace TLE\Elfbot\Console;

use TLE\Elfbot\Manifest;
use TLE\Elfbot\Console\Command as ConsoleCommand;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct(Manifest::NAME, Manifest::VERSION);
    }
    
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        $commands[] = new ConsoleCommand\InstallLaunchdCommand();
        $commands[] = new ConsoleCommand\ExecCommand();
        $commands[] = new ConsoleCommand\TaskCommand();
        $commands[] = new ConsoleCommand\WorkCommand();

        return $commands;
    }
}
