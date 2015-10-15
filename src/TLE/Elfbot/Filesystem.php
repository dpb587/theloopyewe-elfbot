<?php

namespace TLE\Elfbot;

use Symfony\Component\Filesystem\Filesystem as BaseFilesystem;

class Filesystem extends BaseFilesystem
{
    public function realpath($path)
    {
        return realpath($this->tildify($path));
    }

    public function tildify($path)
    {
        if ('~/' == substr($path, 0, 2)) {
            $path = getenv('HOME') . substr($path, 1);
        }

        return $path;
    }
}
