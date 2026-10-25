<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'list', description: 'List configured watchers and their status.')]
final class ListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the config file. Env: WATCHDIFF_CONFIG')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON. Env: WATCHDIFF_JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $watchDiff = WatchDiffFactory::create();
        foreach (ConfigFile::load(EnvOption::stringOr($input, 'config', 'WATCHDIFF_CONFIG', 'watchdiff.config.json')) as $watcher) {
            $watchDiff->watch($watcher);
        }

        $statuses = $watchDiff->status();

        if (EnvOption::bool($input, 'json', 'WATCHDIFF_JSON')) {
            $output->writeln(json_encode(array_map(static fn ($s) => [
                'url'           => $s->url,
                'id'            => $s->id,
                'label'         => $s->label,
                'target'        => $s->target,
                'interval'      => $s->interval,
                'paused'        => $s->paused,
                'inMaintenance' => $s->inMaintenance,
                'checksCount'   => $s->checksCount,
                'changesCount'  => $s->changesCount,
                'errorsCount'   => $s->errorsCount,
            ], $statuses), JSON_UNESCAPED_SLASHES) ?: '[]');

            return Command::SUCCESS;
        }

        if ([] === $statuses) {
            $output->writeln('<info>No watchers configured.</info>');

            return Command::SUCCESS;
        }

        foreach ($statuses as $status) {
            $output->writeln(sprintf(
                '%s%s | target=%s | interval=%ds | checks=%d changes=%d errors=%d',
                $status->label,
                $status->paused ? ' <comment>[paused]</comment>' : '',
                $status->target ?? '(full page)',
                $status->interval,
                $status->checksCount,
                $status->changesCount,
                $status->errorsCount,
            ));
        }

        return Command::SUCCESS;
    }
}
