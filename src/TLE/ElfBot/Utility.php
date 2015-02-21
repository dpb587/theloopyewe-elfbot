<?php

namespace TLE\ElfBot;

use TLE\ElfBot\Task\ManifestInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Utility
{
    static public function createService(Console\Application $application, $class, array $options = [])
    {
        $resolver = new OptionsResolver();

        call_user_func(
            [ $class, 'setManifestOptions' ],
            $resolver
        );

        return new $class(
            $application,
            $resolver->resolve($options)
        );
    }

    static public function getTaskOptions(ManifestInterface $manifest, array $options = [])
    {
        $resolver = new OptionsResolver();

        $resolver->setDefaults(
            [
                'core.notify_success' => null,
                'core.notify_failure' => null,
            ]
        );

        $manifest->setTaskOptions($resolver);

        return $resolver->resolve($options);
    }
}
