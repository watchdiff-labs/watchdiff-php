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

#[AsCommand(name: 'export', description: 'Export stored reports or snapshots for a URL as CSV or JSON.')]
final class ExportCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED)
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'csv|json. Env: WATCHDIFF_FORMAT')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Env: WATCHDIFF_LIMIT')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'reports|snapshots. Env: WATCHDIFF_EXPORT_TYPE')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Write to this file instead of stdout. Env: WATCHDIFF_OUTPUT');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $watchDiff = WatchDiffFactory::create();
        $key       = (new WatchConfig($input->getArgument('url')))->key();

        $format     = EnvOption::stringOr($input, 'format', 'WATCHDIFF_FORMAT', 'json');
        $type       = EnvOption::stringOr($input, 'type', 'WATCHDIFF_EXPORT_TYPE', 'reports');
        $limit      = EnvOption::string($input, 'limit', 'WATCHDIFF_LIMIT');
        $outputPath = EnvOption::string($input, 'output', 'WATCHDIFF_OUTPUT');
        $limitInt   = null !== $limit ? (int) $limit : null;

        $content = match ([$type, $format]) {
            ['reports', 'csv']    => $watchDiff->exportReportsCsv($key, $limitInt),
            ['snapshots', 'csv']  => $watchDiff->exportSnapshotsCsv($key, $limitInt),
            ['snapshots', 'json'] => $watchDiff->exportSnapshotsJson($key, $limitInt),
            default               => $watchDiff->exportReportsJson($key, $limitInt),
        };

        if (null !== $outputPath) {
            file_put_contents($outputPath, $content."\n");
            $output->writeln(sprintf('<info>Wrote %s.</info>', $outputPath));

            return Command::SUCCESS;
        }

        $output->writeln($content);

        return Command::SUCCESS;
    }
}
