<?php

namespace TLE\ElfBot\Console\Command;

use Guzzle\Http\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use TLE\ElfBot\Tasks\Core\SelfUpdate\Manifest as SelfUpdateManifest;

class SelfUpdateCommand extends AbstractLoggerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('self-update')
            ->setDescription('Self-update from a manifest')
            ->setDefinition(
                [
                    new InputArgument('manifest-url', InputArgument::REQUIRED, 'Manifest URL with versioning'),
                    new InputOption('major', null, InputOption::VALUE_NONE, 'Allow major version upgrades'),
                    new InputOption('pre', null, InputOption::VALUE_NONE, 'Allow pre-release upgrades'),
                ]
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $task = new SelfUpdateManifest(
            $this->getApplication(),
            [
                'manifest_url' => $input->getArgument('manifest-url'),
            ]
        );

        $task->execute(
            $this->logger,
            [
                'upgrade_major' => (Bool) $input->getOption('major'),
                'upgrade_pre' => (Bool) $input->getOption('pre'),
                'terminate' => false,
            ]
        );
    }
}
