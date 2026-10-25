<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tick', aliases: ['run'], description: 'Check every watcher that is due and report changes.')]
final class TickCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the config file. Env: WATCHDIFF_CONFIG')
            ->addOption('log-format', null, InputOption::VALUE_REQUIRED, 'text|json - one JSON object per line when "json". Env: WATCHDIFF_LOG_FORMAT');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = EnvOption::stringOr($input, 'config', 'WATCHDIFF_CONFIG', 'watchdiff.config.json');
        $logFormat  = EnvOption::stringOr($input, 'log-format', 'WATCHDIFF_LOG_FORMAT', 'text');

        $watchers  = ConfigFile::load($configPath);
        $watchDiff = WatchDiffFactory::create();

        foreach ($watchers as $watcher) {
            $watchDiff->watch($watcher);
        }

        $reports = $watchDiff->tick();

        if ('json' === $logFormat) {
            foreach ($reports as $report) {
                $output->writeln(json_encode([
                    'label'   => $report->label,
                    'changes' => \count($report->changes),
                ], JSON_UNESCAPED_SLASHES) ?: '{}');
            }

            return Command::SUCCESS;
        }

        if ([] === $reports) {
            $output->writeln('<info>No changes detected.</info>');

            return Command::SUCCESS;
        }

        foreach ($reports as $report) {
            $output->writeln(sprintf('<comment>%s</comment>: %d change(s)', $report->label, \count($report->changes)));
        }

        return Command::SUCCESS;
    }
}
