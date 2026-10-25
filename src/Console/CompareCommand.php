<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'compare', description: 'Diff two arbitrary URLs in a single command.')]
final class CompareCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('urlA', InputArgument::REQUIRED)
            ->addArgument('urlB', InputArgument::REQUIRED)
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'CSS selector to scope the comparison to. Env: WATCHDIFF_TARGET')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON. Env: WATCHDIFF_JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $watchDiff = WatchDiffFactory::create();
        $report    = $watchDiff->compareUrls(
            $input->getArgument('urlA'),
            $input->getArgument('urlB'),
            EnvOption::string($input, 'target', 'WATCHDIFF_TARGET'),
        );

        if (EnvOption::bool($input, 'json', 'WATCHDIFF_JSON')) {
            $output->writeln(json_encode([
                'status'  => $report->hasChanges() ? 'changed' : 'unchanged',
                'changes' => array_map(static fn ($c) => $c->toArray(), $report->changes),
            ], JSON_UNESCAPED_SLASHES) ?: '{}');

            return Command::SUCCESS;
        }

        if (!$report->hasChanges()) {
            $output->writeln('<info>No differences found.</info>');

            return Command::SUCCESS;
        }

        foreach ($report->changes as $change) {
            $output->writeln(sprintf('[%s] %s -> %s', $change->kind->value, $change->before ?? '-', $change->after ?? '-'));
        }

        return Command::SUCCESS;
    }
}
