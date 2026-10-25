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

#[AsCommand(name: 'diff', description: 'Print the last stored diff for a URL.')]
final class DiffCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED)
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON. Env: WATCHDIFF_JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url       = $input->getArgument('url');
        $watchDiff = WatchDiffFactory::create();
        $reports   = $watchDiff->reports((new WatchConfig($url))->key(), 1);
        $json      = EnvOption::bool($input, 'json', 'WATCHDIFF_JSON');

        if ([] === $reports) {
            if ($json) {
                $output->writeln(json_encode(['status' => 'no_diff'], JSON_UNESCAPED_SLASHES) ?: '{}');
            } else {
                $output->writeln('<info>No stored diff for this URL yet.</info>');
            }

            return Command::SUCCESS;
        }

        $report = $reports[0];

        if ($json) {
            $output->writeln(json_encode($report->toArray(), JSON_UNESCAPED_SLASHES) ?: '{}');

            return Command::SUCCESS;
        }

        foreach ($report->changes as $change) {
            $output->writeln(sprintf('[%s] %s -> %s', $change->kind->value, $change->before ?? '-', $change->after ?? '-'));
        }

        return Command::SUCCESS;
    }
}
