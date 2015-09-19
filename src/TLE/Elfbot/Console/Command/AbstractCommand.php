<?php

namespace TLE\Elfbot\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use TLE\Elfbot\Container;
use TLE\Elfbot\ContainerProvider;

class AbstractCommand extends Command
{
    /**
     * @return $this
     */
    protected function configure()
    {
        return $this
            ->addOption('config', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Path to config file')
            ;
    }

    protected function getContainer(InputInterface $input, OutputInterface $output)
    {
        $container = new Container();

        $container['logger.channel'] = $this->getName();
        $container['runtime.log_level'] = 500 - (100 * $output->getVerbosity());

        $provider = new ContainerProvider($input->getOption('config'));
        $provider->register($container);

        return $container;
    }
}
