<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WatchDiff\Core\Config\WatchConfig;

#[AsCommand(name: 'history', description: 'List stored snapshots for a URL.')]
final class HistoryCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max snapshots to show. Env: WATCHDIFF_LIMIT');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url       = $input->getArgument('url');
        $watchDiff = WatchDiffFactory::create();
        $snapshots = $watchDiff->history((new WatchConfig($url))->key(), EnvOption::int($input, 'limit', 'WATCHDIFF_LIMIT', 10));

        if ([] === $snapshots) {
            $output->writeln('<info>No snapshots stored for this URL yet.</info>');

            return Command::SUCCESS;
        }

        foreach ($snapshots as $snapshot) {
            $output->writeln(sprintf('%s - status %d', $snapshot->capturedAt->format(DATE_ATOM), $snapshot->statusCode));
        }

        return Command::SUCCESS;
    }
}
