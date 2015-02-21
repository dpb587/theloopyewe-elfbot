<?php

namespace TLE\ElfBot;

use Phar;

class Manifest {
    const NAME = 'worker';
    const VERSION = '@git-version@';
    const COMMIT = '@git-commit@';

    static public function isReleaseVersion()
    {
        return ('@' . 'git-version@') !== self::VERSION;
    }

    static public function isRunningPhar()
    {
        return Phar::running();
    }
}
