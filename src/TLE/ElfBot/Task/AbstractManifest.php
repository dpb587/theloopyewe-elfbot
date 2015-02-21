<?php

namespace TLE\ElfBot\Task;

use TLE\ElfBot\Task\ManifestInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use TLE\ElfBot\Console\Application;

abstract class AbstractManifest implements ManifestInterface
{
    /**
     * @var mixed[string]
     */
    protected $options;

    /**
     * @var Application
     */
    protected $application;

    public function __construct(Application $application, array $options)
    {
        $this->application = $application;
        $this->options = $options;
    }

    static public function setManifestOptions(OptionsResolverInterface $options)
    {
        // nop
    }

    public function setTaskOptions(OptionsResolverInterface $options)
    {
        // nop
    }
}
