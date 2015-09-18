<?php

namespace TLE\ElfBot\Worker;

class AlwaysEvents
{
    public function canWork()
    {
        return true;
    }

    public function canAcceptTask()
    {
        return true;
    }
}
