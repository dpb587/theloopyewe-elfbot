<?php

namespace TLE\ElfBot\Tasks\Core\SelfUpdate;

use TLE\ElfBot\Manifest as WorkerManifest;
use TLE\ElfBot\Task\AbstractManifest;
use Guzzle\Http\Client;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest as UpdateManifest;
use Herrera\Version\Parser;
use Psr\Log\LoggerInterface;

class Manifest extends AbstractManifest
{
    static public function setManifestOptions(OptionsResolverInterface $options)
    {
        $options->setRequired(
            [
                'manifest_url',
            ]
        );
    }

    public function setTaskOptions(OptionsResolverInterface $options)
    {
        $options->setDefaults(
            [
                'upgrade_major' => false,
                'upgrade_pre' => false,
                'terminate' => true,
            ]
        );
    }

    public function execute(LoggerInterface $logger, array $options)
    {
        if (!WorkerManifest::isReleaseVersion()) {
            throw new \LogicException('Cannot perform update checks on an unversioned application.');
        }

        $httpClient = new Client();

        $manifest = UpdateManifest::load($httpClient->get($this->options['manifest_url'])->send()->getBody(true));

        $version = Parser::toVersion(ltrim(WorkerManifest::VERSION, 'v'));
        $update = $update = $manifest->findRecent($version, $options['upgrade_major'], $options['upgrade_pre']);

        if (null === $update) {
            $logger->info('no update is available');

            return;
        }

        $logger->warn('updating from ' . $version . ' to ' . $update->getVersion());

        if (!WorkerManifest::isRunningPhar()) {
            $logger->info('not running as a PHAR; skipping update.');

            return;
        }

        $manager = new Manager($manifest);

        $manager->update($version, $options['upgrade_major'], $options['upgrade_pre']);

        if ($options['terminate']) {
            posix_kill(getmypid(), SIGTERM);
        }
    }
}
