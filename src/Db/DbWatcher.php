<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use WatchDiff\Core\Contract\StoreInterface;
use WatchDiff\Core\Fetch\SsrfGuard;
use WatchDiff\Core\Support\Human;

/**
 * Standalone orchestrator for database monitoring. Kept separate from the
 * HTTP-oriented WatchDiff class because rows/schema diffs need a structurally
 * different report shape (inserted/deleted/updated rows, column changes)
 * than a text-based DiffReport. Persists state through the same
 * StoreInterface (its generic getState/setState bag), so it can share a
 * JsonStore or SqliteStore with the main WatchDiff instance.
 */
final class DbWatcher
{
    /** @var DbWatchConfig[] */
    private array $watchers = [];

    private readonly DbFetcher $fetcher;
    private readonly DbDiffEngine $diffEngine;

    public function __construct(
        private readonly StoreInterface $store,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->fetcher    = new DbFetcher();
        $this->diffEngine = new DbDiffEngine();
    }

    public function watch(DbWatchConfig $config): self
    {
        $this->watchers[$config->key()] = $config;

        return $this;
    }

    /** @return DbDiffReport[] */
    public function tick(\DateTimeImmutable $now = new \DateTimeImmutable()): array
    {
        $reports = [];

        foreach ($this->watchers as $config) {
            if (!$this->isDue($config, $now)) {
                continue;
            }

            $report = $this->checkOnce($config->key());
            if (null !== $report && $report->hasChanges()) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    private function isDue(DbWatchConfig $config, \DateTimeImmutable $now): bool
    {
        $state       = $this->store->getState($config->key());
        $lastCheckAt = isset($state['lastCheckAt']) ? new \DateTimeImmutable((string) $state['lastCheckAt']) : null;

        return null === $lastCheckAt || $lastCheckAt->modify(sprintf('+%d seconds', $config->interval)) <= $now;
    }

    public function checkOnce(string $key): ?DbDiffReport
    {
        $config = $this->watchers[$key] ?? null;
        if (null === $config) {
            throw new \InvalidArgumentException(sprintf('No DB watcher registered for key "%s".', $key));
        }

        $state = $this->store->getState($key);

        try {
            $after = $this->fetcher->fetch($config);
        } catch (\Throwable $exception) {
            $state['errorsCount'] = ($state['errorsCount'] ?? 0) + 1;
            $this->store->setState($key, $state);

            if (null !== $config->onError) {
                ($config->onError)($exception);
            }

            return null;
        }

        $state['checksCount'] = ($state['checksCount'] ?? 0) + 1;
        $state['lastCheckAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);

        $beforeData = $state['snapshot'] ?? null;
        $before     = null !== $beforeData ? DbSnapshot::fromArray($beforeData) : null;

        if (!$config->dryRun) {
            $state['snapshot'] = $after->toArray();
        }

        if (null === $before) {
            if (!$config->dryRun) {
                $this->store->setState($key, $state);
            }

            return null;
        }

        $changes = $this->diffEngine->diff($before, $after, $config);
        $report  = new DbDiffReport(
            connectionString: $config->connectionString,
            table: $config->table,
            label: $config->label(),
            before: $before,
            after: $after,
            changes: $changes,
            diffMode: $config->diffMode,
            comparedAt: new \DateTimeImmutable(),
        );

        if ($config->dryRun) {
            return $report;
        }

        if (!$report->hasChanges()) {
            $this->store->setState($key, $state);

            return $report;
        }

        $state['changesCount'] = ($state['changesCount'] ?? 0) + 1;
        $state['lastChangeAt'] = $report->comparedAt->format(DATE_ATOM);
        $this->store->setState($key, $state);

        $this->dispatch($config, $report);

        return $report;
    }

    /** @return DbWatcherStatus[] */
    public function status(): array
    {
        $statuses = [];

        foreach ($this->watchers as $config) {
            $state = $this->store->getState($config->key());

            $statuses[] = new DbWatcherStatus(
                connectionString: $config->connectionString,
                table:            $config->table,
                id:               $config->id,
                label:            $config->label(),
                diffMode:         $config->diffMode,
                interval:         $config->interval,
                lastCheckAt:      isset($state['lastCheckAt']) ? new \DateTimeImmutable((string) $state['lastCheckAt']) : null,
                lastChangeAt:     isset($state['lastChangeAt']) ? new \DateTimeImmutable((string) $state['lastChangeAt']) : null,
                checksCount:      (int) ($state['checksCount'] ?? 0),
                changesCount:     (int) ($state['changesCount'] ?? 0),
                errorsCount:      (int) ($state['errorsCount'] ?? 0),
            );
        }

        return $statuses;
    }

    private function dispatch(DbWatchConfig $config, DbDiffReport $report): void
    {
        if (DbDiffMode::Schema === $config->diffMode && null !== $config->onSchemaChange) {
            ($config->onSchemaChange)(['changes' => $report->changes]);
        }

        if (\in_array($config->diffMode, [DbDiffMode::Aggregate, DbDiffMode::Value], true)
            && null !== $config->onThreshold
            && [] !== $report->changes) {
            $mod = $report->changes[0]->modifications[0] ?? null;
            if (null !== $mod && is_numeric($mod['before']) && is_numeric($mod['after'])) {
                $before = (float) $mod['before'];
                $after  = (float) $mod['after'];
                ($config->onThreshold)([
                    'previousValue' => $before,
                    'currentValue'  => $after,
                    'changePercent' => 0.0 === $before ? 100.0 : abs(($after - $before) / $before) * 100,
                ]);
            }
        }

        if (!$this->isCooldownElapsed($config)) {
            return;
        }

        if (null !== $config->onChange) {
            ($config->onChange)($report);
        }

        $this->sendWebhooks($config, $report);

        $state                = $this->store->getState($config->key());
        $state['lastAlertAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->store->setState($config->key(), $state);
    }

    private function sendWebhooks(DbWatchConfig $config, DbDiffReport $report): void
    {
        if ([] === $config->webhooks || null === $this->httpClient || null === $this->requestFactory || null === $this->streamFactory) {
            return;
        }

        $summary = $this->summary($report);

        foreach ($config->webhooks as $webhookUrl) {
            try {
                SsrfGuard::assertPublicHost($webhookUrl, $config->allowPrivateUrls);

                $payload = $this->buildPayload($webhookUrl, $summary, $report);
                $request = $this->requestFactory->createRequest('POST', $webhookUrl)
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($this->streamFactory->createStream(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}'));

                $this->httpClient->sendRequest($request);
            } catch (\Throwable $exception) {
                if (null !== $config->onError) {
                    ($config->onError)($exception);
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function buildPayload(string $webhookUrl, string $summary, DbDiffReport $report): array
    {
        if (str_contains($webhookUrl, 'discord.com')) {
            return ['content' => substr($summary, 0, 2000)];
        }

        if (str_contains($webhookUrl, 'hooks.slack.com')) {
            return ['text' => substr($summary, 0, 3000)];
        }

        if (str_contains($webhookUrl, 'api.telegram.org')) {
            return ['text' => $summary];
        }

        if (str_contains($webhookUrl, 'ntfy.sh') || str_contains($webhookUrl, 'ntfy.')) {
            return ['message' => $summary];
        }

        return [
            'table'    => $report->table,
            'diffMode' => $report->diffMode->value,
            'changes'  => array_map(static fn (DbChange $c) => $c->toArray(), $report->changes),
        ];
    }

    private function summary(DbDiffReport $report): string
    {
        return Human::dbReportSummary($report);
    }

    private function isCooldownElapsed(DbWatchConfig $config): bool
    {
        if ($config->cooldown <= 0) {
            return true;
        }

        $state       = $this->store->getState($config->key());
        $lastAlertAt = isset($state['lastAlertAt']) ? new \DateTimeImmutable((string) $state['lastAlertAt']) : null;

        return null === $lastAlertAt
            || $lastAlertAt->modify(sprintf('+%d seconds', $config->cooldown)) <= new \DateTimeImmutable();
    }
}
