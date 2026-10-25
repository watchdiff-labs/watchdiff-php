<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WatchDiff\Core\Config\WatchConfig;

#[AsCommand(name: 'clear', description: 'Delete all stored snapshots, reports and state for a single URL.')]
final class ClearCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = (new WatchConfig($input->getArgument('url')))->key();

        WatchDiffFactory::create()->clear($key);
        $output->writeln(sprintf('<info>Cleared stored data for "%s".</info>', $key));

        return Command::SUCCESS;
    }
}
