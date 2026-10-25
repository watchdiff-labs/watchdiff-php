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

#[AsCommand(name: 'reports', description: 'List stored change reports for a URL.')]
final class ReportsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max reports to show. Env: WATCHDIFF_LIMIT')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON. Env: WATCHDIFF_JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url       = $input->getArgument('url');
        $watchDiff = WatchDiffFactory::create();
        $reports   = $watchDiff->reports((new WatchConfig($url))->key(), EnvOption::int($input, 'limit', 'WATCHDIFF_LIMIT', 10));

        if (EnvOption::bool($input, 'json', 'WATCHDIFF_JSON')) {
            $output->writeln(json_encode(array_map(static fn ($r) => $r->toArray(), $reports), JSON_UNESCAPED_SLASHES) ?: '[]');

            return Command::SUCCESS;
        }

        if ([] === $reports) {
            $output->writeln('<info>No stored reports for this URL yet.</info>');

            return Command::SUCCESS;
        }

        foreach ($reports as $report) {
            $output->writeln(sprintf(
                '%s - %d change(s)',
                $report->comparedAt->format(DATE_ATOM),
                \count($report->changes),
            ));
        }

        return Command::SUCCESS;
    }
}
