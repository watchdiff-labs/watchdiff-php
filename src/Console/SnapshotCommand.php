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

#[AsCommand(name: 'snapshot', description: 'Print the latest stored snapshot for a URL.')]
final class SnapshotCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED)
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON. Env: WATCHDIFF_JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $watchDiff = WatchDiffFactory::create();
        $snapshots = $watchDiff->history((new WatchConfig($input->getArgument('url')))->key(), 1);

        if ([] === $snapshots) {
            if (EnvOption::bool($input, 'json', 'WATCHDIFF_JSON')) {
                $output->writeln(json_encode(['status' => 'no_snapshot'], JSON_UNESCAPED_SLASHES) ?: '{}');
            } else {
                $output->writeln('<info>No snapshot stored for this URL yet.</info>');
            }

            return Command::SUCCESS;
        }

        if (EnvOption::bool($input, 'json', 'WATCHDIFF_JSON')) {
            $output->writeln(json_encode($snapshots[0]->toArray(), JSON_UNESCAPED_SLASHES) ?: '{}');

            return Command::SUCCESS;
        }

        $output->writeln($snapshots[0]->content);

        return Command::SUCCESS;
    }
}
