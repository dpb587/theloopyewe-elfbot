<?php

namespace TLE\ElfBot\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;

class TaskCommand extends AbstractCommand
{
    protected function configure()
    {
        parent::configure()
            ->setName('task')
            ->addArgument('name', InputArgument::OPTIONAL, 'A specific task to show')
            ->setDescription('Describe the available tasks')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer($input, $output);

        if ($input->getArgument('name')) {
            $task = $container['task.' . $input->getArgument('name')];

            list($serviceDefinition, $taskDefinition) = $container['task_factory']->getDefinition(get_class($task));

            $dumper = new YamlReferenceDumper();

            $output->writeln('<comment># task service options</comment>');
            $output->writeln(
                preg_replace(
                    '/^(\s+)(# .+$)/m',
                    '$1<comment>$2</comment>',
                    preg_replace(
                        '/^(\s*)(\w+:)/m',
                        '$1<info>$2</info>',
                        $dumper->dumpNode($serviceDefinition)
                    )
                )
            );

            $output->writeln('<comment># task options</comment>');
            $output->writeln(
                preg_replace(
                    '/^(\s+)(# .+$)/m',
                    '$1<comment>$2</comment>',
                    preg_replace(
                        '/^(\s*)(\w+:)/m',
                        '$1<info>$2</info>',
                        $dumper->dumpNode($taskDefinition)
                    )
                )
            );
        } else {
            $tasks = array_map(
                function ($key) {
                    return substr($key, 5);
                },
                array_filter(
                    $container->keys(),
                    function ($key) {
                        return 'task.' == substr($key, 0, 5);
                    }
                )
            );

            sort($tasks);

            foreach ($tasks as $task) {
                $output->writeln($task);
            }
        }
    }
}
