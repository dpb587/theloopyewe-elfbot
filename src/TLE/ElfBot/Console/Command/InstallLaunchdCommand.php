<?php

namespace TLE\ElfBot\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class InstallLaunchdCommand extends AbstractLoggerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('install-launchd')
            ->setDescription('Install configuration to run under launchd')
            ->setDefinition(
                [
                    new InputArgument('name', InputArgument::REQUIRED, 'Launchd service name'),
                    new InputOption('library-path', null, InputOption::VALUE_REQUIRED, 'Library Path (default ~/Library)'),
                    new InputOption('log-path', null, InputOption::VALUE_REQUIRED, 'Log Path (default {library-path}/Logs)'),
                    new InputOption('executable', null, InputOption::VALUE_REQUIRED, 'The PHP executable to use'),
                    new InputOption('start', null, InputOption::VALUE_NONE, 'Start the process once installed'),
                ]
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $libraryPath = realpath($input->getOption('library-path') ?: getenv('HOME') . '/Library');
        $logPath = $input->getOption('log-path')
            ? realpath($input->getOption('log-path'))
            : $libraryPath . '/Logs/' . $input->getArgument('name') . '.log'
            ;

        $c1[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $c1[] = '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">';
        $c1[] = '<plist version="1.0">';
        $c1[] = '    <dict>';
        $c1[] = '        <key>Label</key>';
        $c1[] = '        <string>' . $input->getArgument('name') . '</string>';
        $c1[] = '        <key>ProgramArguments</key>';
        $c1[] = '        <array>';

        if ($input->getOption('executable')) {
            $c1[] = '            <string>' . $input->getOption('executable') . '</string>';
        }

        $c1[] = '            <string>' . realpath($_SERVER['argv'][0]) . '</string>';

        if (OutputInterface::VERBOSITY_QUIET == $output->getVerbosity()) {
            $c1[] = '            <string>-q</string>';
        } elseif (OutputInterface::VERBOSITY_NORMAL != $output->getVerbosity()) {
            $c1[] = '            <string>-' . str_repeat('v', $output->getVerbosity() - 1) . '</string>';
        }

        $c1[] = '            <string>run</string>';
        $c1[] = '            <string>--config-file</string>';
        $c1[] = '            <string>'. htmlentities($input->getParameterOption([ '--config-file', '-c' ])) . '</string>';
        $c1[] = '        </array>';
        $c1[] = '        <key>StandardOutPath</key>';
        $c1[] = '        <string>' . $logPath . '</string>';
        $c1[] = '        <key>StandardErrorPath</key>';
        $c1[] = '        <string>' . $logPath . '</string>';
        $c1[] = '        <key>RunAtLoad</key>';
        $c1[] = '        <true/>';
        $c1[] = '        <key>OnDemand</key>';
        $c1[] = '        <false/>';
        $c1[] = '        <key>KeepAlive</key>';
        $c1[] = '        <true/>';
        $c1[] = '    </dict>';
        $c1[] = '</plist>';

        $c1p = $libraryPath . '/LaunchAgents/' . $input->getArgument('name') . '.plist';

        $c1exists = file_exists($c1p);
        
        file_put_contents($c1p, implode("\n", $c1));
        chmod($c1p, 0740);

        if ($input->getOption('start')) {
            if ($c1exists) {
                passthru('launchctl unload ' . $c1p . ' 2>/dev/null');

                sleep(1);
            }

            passthru('launchctl load ' . $c1p);
        }
    }
}
