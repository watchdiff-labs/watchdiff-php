<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WatchDiff\Core\Db\DbDiffMode;
use WatchDiff\Core\Db\DbWatchConfig;
use WatchDiff\Core\Db\DbWatcher;
use WatchDiff\Core\Store\JsonStore;

#[AsCommand(name: 'db', description: 'Run a single one-off check against a database table.')]
final class DbCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('connectionString', InputArgument::REQUIRED, 'e.g. sqlite://./app.db')
            ->addArgument('table', InputArgument::REQUIRED)
            ->addOption('primary-key', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Primary key column(s)', [])
            ->addOption('ignore-columns', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Columns to exclude', [])
            ->addOption('diff-mode', null, InputOption::VALUE_REQUIRED, 'row|schema|aggregate|value. Env: WATCHDIFF_DB_DIFF_MODE')
            ->addOption('query', null, InputOption::VALUE_REQUIRED, 'Env: WATCHDIFF_DB_QUERY')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Percent change required to alert (aggregate mode). Env: WATCHDIFF_DB_THRESHOLD')
            ->addOption('webhook', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Webhook URL(s) to notify on change', [])
            ->addOption('no-update', null, InputOption::VALUE_NONE, 'Suppress update alerts, watch only inserts and deletes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON. Env: WATCHDIFF_JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = EnvOption::bool($input, 'json', 'WATCHDIFF_JSON');

        $config = new DbWatchConfig(
            connectionString: $input->getArgument('connectionString'),
            table: $input->getArgument('table'),
            diffMode: DbDiffMode::from(EnvOption::stringOr($input, 'diff-mode', 'WATCHDIFF_DB_DIFF_MODE', 'row')),
            primaryKey: $input->getOption('primary-key'),
            ignoreColumns: $input->getOption('ignore-columns'),
            query: EnvOption::string($input, 'query', 'WATCHDIFF_DB_QUERY'),
            alertOnUpdate: !$input->getOption('no-update'),
            threshold: EnvOption::float($input, 'threshold', 'WATCHDIFF_DB_THRESHOLD', 0.0),
            webhooks: $input->getOption('webhook'),
        );

        // A separate directory from the default URL/file/cert store (.watchdiff):
        // `watchdiff clean` deletes any stored key that isn't in the current
        // config file, so DB watchers must not share that storage.
        $watcher = new DbWatcher(
            new JsonStore('.watchdiff-db'),
            Psr18ClientDiscovery::find(),
            Psr17FactoryDiscovery::findRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );
        $watcher->watch($config);
        $report = $watcher->checkOnce($config->key());

        if (null === $report) {
            $output->writeln($json
                ? (json_encode(['status' => 'first_check'], JSON_UNESCAPED_SLASHES) ?: '{}')
                : '<info>First check - snapshot saved, nothing to compare yet.</info>');

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
            $output->writeln(sprintf(
                '[%s]%s%s',
                $change->kind->value,
                null !== $change->rowKey ? ' key='.$change->rowKey : '',
                null !== $change->column ? ' column='.$change->column.' ('.$change->context.')' : '',
            ));
        }

        return Command::SUCCESS;
    }
}
