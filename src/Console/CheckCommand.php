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
use WatchDiff\Core\Enum\DiffMode;

#[AsCommand(name: 'check', description: 'Run a single one-off check against a URL.')]
final class CheckCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED)
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'CSS selector to scope the check to. Env: WATCHDIFF_TARGET')
            ->addOption('diff-mode', null, InputOption::VALUE_REQUIRED, 'line|word|json|rss. Env: WATCHDIFF_DIFF_MODE')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Env: WATCHDIFF_DRY_RUN')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON. Env: WATCHDIFF_JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = EnvOption::bool($input, 'json', 'WATCHDIFF_JSON');

        $config = new WatchConfig(
            url: $input->getArgument('url'),
            target: EnvOption::string($input, 'target', 'WATCHDIFF_TARGET'),
            diffMode: DiffMode::from(EnvOption::stringOr($input, 'diff-mode', 'WATCHDIFF_DIFF_MODE', 'line')),
            dryRun: EnvOption::bool($input, 'dry-run', 'WATCHDIFF_DRY_RUN'),
        );

        $watchDiff = WatchDiffFactory::create();
        $watchDiff->watch($config);
        $report = $watchDiff->checkOnce($config->key());

        if (null === $report) {
            if ($json) {
                $output->writeln(json_encode(['status' => 'first_check'], JSON_UNESCAPED_SLASHES) ?: '{}');
            } else {
                $output->writeln('<info>First check - snapshot saved, nothing to compare yet.</info>');
            }

            return Command::SUCCESS;
        }

        if ($json) {
            $output->writeln(json_encode([
                'status'  => $report->hasChanges() ? 'changed' : 'unchanged',
                'changes' => array_map(static fn ($c) => $c->toArray(), $report->changes),
            ], JSON_UNESCAPED_SLASHES) ?: '{}');

            return Command::SUCCESS;
        }

        if (!$report->hasChanges()) {
            $output->writeln('<info>No changes detected.</info>');

            return Command::SUCCESS;
        }

        foreach ($report->changes as $change) {
            $output->writeln(sprintf('[%s] %s -> %s', $change->kind->value, $change->before ?? '-', $change->after ?? '-'));
        }

        return Command::SUCCESS;
    }
}
