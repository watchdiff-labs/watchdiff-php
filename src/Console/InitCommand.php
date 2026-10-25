<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'init', description: 'Create a starter watchdiff.config.json file.')]
final class InitCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::OPTIONAL, 'Path to the config file to create', 'watchdiff.config.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');

        if (is_file($path)) {
            $output->writeln(sprintf('<error>%s already exists.</error>', $path));

            return Command::FAILURE;
        }

        $template = [
            'watchers' => [
                [
                    'url'      => 'https://example.com',
                    'target'   => '.price',
                    'interval' => 300,
                    'diffMode' => 'line',
                    'webhooks' => [],
                ],
            ],
        ];

        file_put_contents($path, json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $output->writeln(sprintf('<info>Created %s</info>', $path));

        return Command::SUCCESS;
    }
}
