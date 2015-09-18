<?php

namespace TLE\ElfBot\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TLE\ElfBot\Utility;

class ExecCommand extends AbstractCommand
{
    protected function configure()
    {
        parent::configure()
            ->setName('exec')
            ->addArgument('task', InputArgument::REQUIRED, 'The task to execute')
            ->addOption('option', 'o', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Task options')
            ->setDescription('Execute a specific task')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $options = [];

        foreach ($input->getOption('option') as $option) {
            $optionSplit = explode('=', $option, 2);

            if (!isset($optionSplit[1])) {
                $optionSplit[1] = 'true';
            }

            list($optionKey, $optionValue) = $optionSplit;

            if (preg_match('/^json:\{.*\}$/', $optionValue)) {
                $optionValue = json_decode(substr($optionValue, 5), true);
            } elseif ('true' == $optionValue) {
                $optionValue = true;
            } elseif ('false' == $optionValue) {
                $optionValue = false;
            } elseif ('null' == $optionValue) {
                unset($options[$optionKey]);

                continue;
            }

            $options[$optionKey] = $optionValue;
        }

        $container = $this->getContainer($input, $output);

        $container['task_factory']->execute($container['logger'], $input->getArgument('task'), $options);
    }
}
