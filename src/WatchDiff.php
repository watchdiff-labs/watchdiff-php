<?php

declare(strict_types=1);

namespace WatchDiff\Core;

use Cron\CronExpression;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use WatchDiff\Core\Ai\AiSummarizer;
use WatchDiff\Core\Config\AiProvider;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\Contract\StoreInterface;
use WatchDiff\Core\Diff\DiffEngineFactory;
use WatchDiff\Core\Enum\DiffMode;
use WatchDiff\Core\Fetch\Fetcher;
use WatchDiff\Core\Fetch\FetchResult;
use WatchDiff\Core\Model\Change;
use WatchDiff\Core\Model\DiffReport;
use WatchDiff\Core\Model\Snapshot;
use WatchDiff\Core\Model\WatcherStatus;
use WatchDiff\Core\Notify\WebhookSender;
use WatchDiff\Core\Parse\Cleaner;
use WatchDiff\Core\Parse\JsonPathExtractor;
use WatchDiff\Core\Parse\Parser;

final class WatchDiff
{
    /** @var WatchConfig[] */
    private array $watchers = [];

    private readonly Fetcher $fetcher;
    private readonly Cleaner $cleaner;
    private readonly Parser $parser;
    private readonly JsonPathExtractor $jsonPathExtractor;
    private readonly DiffEngineFactory $diffEngineFactory;
    private readonly ?WebhookSender $webhookSender;
    private readonly ?AiSummarizer $aiSummarizer;
    private ?string $archiveDirectory = null;

    public function __construct(
        private readonly StoreInterface $store,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly int $minInterval = 0,
        private readonly int $maxWatchers = 0,
    ) {
        $this->fetcher           = new Fetcher($httpClient, $requestFactory, $streamFactory);
        $this->cleaner           = new Cleaner();
        $this->parser            = new Parser();
        $this->jsonPathExtractor = new JsonPathExtractor();
        $this->diffEngineFactory = new DiffEngineFactory();
        $this->webhookSender     = null !== $streamFactory
            ? new WebhookSender($httpClient, $requestFactory, $streamFactory)
            : null;
        $this->aiSummarizer = null !== $streamFactory
            ? new AiSummarizer($httpClient, $requestFactory, $streamFactory)
            : null;
    }

    public function withArchiveDirectory(string $directory): self
    {
        $this->archiveDirectory = $directory;

        return $this;
    }

    public function watch(WatchConfig $config): self
    {
        $isNew = !isset($this->watchers[$config->key()]);
        if ($isNew && $this->maxWatchers > 0 && \count($this->watchers) >= $this->maxWatchers) {
            throw new \OverflowException(sprintf('Cannot register more than %d watchers.', $this->maxWatchers));
        }

        $this->watchers[$config->key()] = $config;

        return $this;
    }

    /** @return WatchConfig[] */
    public function watchers(): array
    {
        return $this->watchers;
    }

    /**
     * Runs every watcher whose interval/schedule has elapsed, isn't paused,
     * and isn't inside a maintenance window / outside its active hours.
     * Intended to be invoked periodically (e.g. from a cron-triggered command).
     *
     * @return DiffReport[] reports for watchers that produced a change
     */
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

    private function isDue(WatchConfig $config, \DateTimeImmutable $now): bool
    {
        $state = $this->store->getState($config->key());
        if (true === ($state['paused'] ?? false)) {
            return false;
        }

        foreach ($config->maintenanceWindows as $window) {
            if ($window->contains($now)) {
                return false;
            }
        }

        if (null !== $config->activeBetween && !$config->activeBetween->isActive($now)) {
            return false;
        }

        $latest = $this->store->latestSnapshot($config->key());
        if (null === $latest) {
            return true;
        }

        if (null !== $config->schedule) {
            return (new CronExpression($config->schedule))->isDue($now);
        }

        $interval = max($config->interval, $this->minInterval, $config->minInterval ?? 0);
        $interval = $this->applyJitter($interval, $config->jitter);

        return $latest->capturedAt->modify(sprintf('+%d seconds', $interval)) <= $now;
    }

    private function applyJitter(int $interval, float $jitter): int
    {
        if ($jitter <= 0.0) {
            return $interval;
        }

        $variance = $interval * $jitter;

        return (int) round($interval + (mt_rand() / mt_getrandmax() * 2 - 1) * $variance);
    }

    public function checkOnce(string $key): ?DiffReport
    {
        $config = $this->watchers[$key] ?? null;
        if (null === $config) {
            throw new \InvalidArgumentException(sprintf('No watcher registered for key "%s".', $key));
        }

        $state = $this->store->getState($key);

        try {
            if (null !== $config->onBeforeCheck) {
                ($config->onBeforeCheck)($config);
            }

            $fetched = match (true) {
                $config->isFileWatch() => $this->readFile($config),
                $config->isCertWatch() => $this->readCert($config),
                default                => $this->fetcher->fetch($config),
            };
        } catch (\Throwable $exception) {
            $state['errorsCount']   = ($state['errorsCount'] ?? 0) + 1;
            $state['failureStreak'] = ($state['failureStreak'] ?? 0) + 1;
            $state['successStreak'] = 0;
            $this->store->setState($key, $state);

            if (null !== $config->onError) {
                ($config->onError)($exception, $config);
            }

            return null;
        }

        $state['checksCount'] = ($state['checksCount'] ?? 0) + 1;
        $state['lastCheckAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);

        if (null !== $config->expectedStatus && $fetched->statusCode !== $config->expectedStatus) {
            $state['errorsCount'] = ($state['errorsCount'] ?? 0) + 1;
            $this->store->setState($key, $state);

            if (null !== $config->onError) {
                ($config->onError)(new \RuntimeException(sprintf(
                    'Expected HTTP status %d, got %d.',
                    $config->expectedStatus,
                    $fetched->statusCode,
                )), $config);
            }

            return null;
        }

        $before = $this->store->latestSnapshot($key);

        if ($config->alertOnStatusChange && null !== $before && $before->statusCode !== $fetched->statusCode
            && null !== $config->onStatusChange) {
            ($config->onStatusChange)([
                'url'            => $config->key(),
                'label'          => $config->label(),
                'previousStatus' => $before->statusCode,
                'currentStatus'  => $fetched->statusCode,
            ]);
        }

        $content = $config->isCertWatch() ? trim($fetched->body) : $this->extractContent($config, $fetched->body);
        $after   = new Snapshot(
            url: $config->key(),
            content: $content,
            capturedAt: new \DateTimeImmutable(),
            statusCode: $fetched->statusCode,
            responseTimeMs: $fetched->responseTimeMs,
        );

        if ($config->isCertWatch()) {
            $this->checkCertExpiry($config, $content, $state);
        }

        $wasFailing             = ($state['failureStreak'] ?? 0) > 0;
        $state['failureStreak'] = 0;

        if ($wasFailing) {
            $state['successStreak'] = ($state['successStreak'] ?? 0) + 1;
            $requiredSuccesses      = $config->failurePolicy?->consecutiveSuccesses ?? 1;

            if ($state['successStreak'] >= $requiredSuccesses) {
                if (null !== $config->onRecovery) {
                    ($config->onRecovery)($config);
                }
                $state['successStreak'] = 0;
            }
        }

        if (null === $before) {
            if (!$config->alertOnFirstCheck) {
                if (!$config->dryRun) {
                    $this->store->saveSnapshot($key, $after, $config->maxSnapshots);
                }
                $this->store->setState($key, $state);

                return null;
            }

            return $this->buildAndDispatchReport($config, $key, new Snapshot($config->key(), '', $after->capturedAt), $after, $state);
        }

        return $this->buildAndDispatchReport($config, $key, $before, $after, $state);
    }

    /** @param array<string, mixed> $state */
    private function buildAndDispatchReport(WatchConfig $config, string $key, Snapshot $before, Snapshot $after, array $state): DiffReport
    {
        $diffMode = $config->isCertWatch() ? DiffMode::Json : $config->diffMode;
        $changes  = $this->diffEngineFactory->create($diffMode)->diff($before->content, $after->content);
        $changes  = $this->applyChangeThreshold($changes, $before->content, $config->changeThreshold);
        $changes  = \count($changes) < $config->minChanges ? [] : $changes;

        $report = new DiffReport(
            url: $config->key(),
            label: $config->label(),
            before: $before,
            after: $after,
            changes: $changes,
            diffMode: $diffMode,
            comparedAt: new \DateTimeImmutable(),
        );

        if ($config->dryRun) {
            $this->store->setState($key, $state);
            // dryRun is an explicit exception: onChange still fires so callers
            // can preview what would have been alerted, but nothing else does.
            $this->dispatch($config, $report, alert: false, forceOnChange: true);

            return $report;
        }

        $this->store->saveSnapshot($key, $after, $config->maxSnapshots);

        if (null !== $config->retentionDays) {
            $this->pruneByRetention($key, $config->retentionDays);
        }

        if (!$report->hasChanges()) {
            // A pending flapping confirmation that never saw the change again
            // is stale - clear it so it can't fast-track an unrelated,
            // future change straight past confirmAfter.
            unset($state['pendingConfirmation']);
            $this->checkSilence($config, $key, $state);
            $this->store->setState($key, $state);

            return $report;
        }

        $state['changesCount'] = ($state['changesCount'] ?? 0) + 1;
        $state['lastChangeAt'] = $report->comparedAt->format(DATE_ATOM);
        unset($state['silenceAlertedAt']);

        $report = $this->applyAiSummary($config, $report);

        if ($config->archiveHtml) {
            $this->archiveHtml($config, $key, $after);
        }

        $this->recordSpike($config, $state, $report->comparedAt);

        $shouldAlert = null === $config->alertIf || ($config->alertIf)($report);
        $shouldAlert = $shouldAlert && $this->passesConfirmation($config, $key, $state, $report);
        $shouldAlert = $shouldAlert && $this->isCooldownElapsed($config);

        $this->store->saveReport($key, $report);
        $this->store->setState($key, $state);
        $this->dispatch($config, $report, alert: $shouldAlert);

        return $report;
    }

    private function applyAiSummary(WatchConfig $config, DiffReport $report): DiffReport
    {
        if (!$config->aiSummary || null === $this->aiSummarizer) {
            return $report;
        }

        $provider = $config->aiProvider ?? AiProvider::fromEnvironment();
        if (null === $provider) {
            return $report;
        }

        return $report->withAiSummary($this->aiSummarizer->summarize($report, $provider, $config->aiPrompt));
    }

    /** @param array<string, mixed> $state */
    private function checkSilence(WatchConfig $config, string $key, array &$state): void
    {
        if (null === $config->alertIfNoChangeAfter || null === $config->onSilence) {
            return;
        }

        $lastChangeAt = isset($state['lastChangeAt']) ? new \DateTimeImmutable((string) $state['lastChangeAt']) : null;
        if (null === $lastChangeAt) {
            return;
        }

        $now                = new \DateTimeImmutable();
        $secondsSinceChange = $now->getTimestamp() - $lastChangeAt->getTimestamp();

        if ($secondsSinceChange < $config->alertIfNoChangeAfter) {
            return;
        }

        $lastAlertedAt = isset($state['silenceAlertedAt']) ? new \DateTimeImmutable((string) $state['silenceAlertedAt']) : null;
        if (null !== $lastAlertedAt
            && ($now->getTimestamp() - $lastAlertedAt->getTimestamp()) < $config->alertIfNoChangeAfter) {
            return;
        }

        ($config->onSilence)([
            'url'                    => $config->key(),
            'label'                  => $config->label(),
            'secondsSinceLastChange' => $secondsSinceChange,
        ]);

        $state['silenceAlertedAt'] = $now->format(DATE_ATOM);
    }

    /** @param array<string, mixed> $state */
    private function recordSpike(WatchConfig $config, array &$state, \DateTimeImmutable $at): void
    {
        if (null === $config->changeSpikeWindow || null === $config->changeSpikeThreshold) {
            return;
        }

        /** @var string[] $timestamps */
        $timestamps   = $state['changeTimestamps'] ?? [];
        $timestamps[] = $at->format(DATE_ATOM);

        $cutoff     = $at->getTimestamp() - $config->changeSpikeWindow;
        $timestamps = array_values(array_filter(
            $timestamps,
            static fn (string $ts) => (new \DateTimeImmutable($ts))->getTimestamp() >= $cutoff,
        ));

        $state['changeTimestamps'] = $timestamps;

        if (\count($timestamps) < $config->changeSpikeThreshold) {
            return;
        }

        $lastSpikeAt = isset($state['lastSpikeAlertAt']) ? new \DateTimeImmutable((string) $state['lastSpikeAlertAt']) : null;
        if (null !== $lastSpikeAt && ($at->getTimestamp() - $lastSpikeAt->getTimestamp()) < $config->changeSpikeWindow) {
            return;
        }

        if (null !== $config->onSpike) {
            ($config->onSpike)([
                'url'             => $config->key(),
                'label'           => $config->label(),
                'changesInWindow' => \count($timestamps),
                'windowSeconds'   => $config->changeSpikeWindow,
            ]);
        }

        $state['lastSpikeAlertAt'] = $at->format(DATE_ATOM);
    }

    /**
     * Flapping detection: when confirmAfter is set, a change must be seen
     * again after at least that many seconds before it's actually alerted.
     *
     * @param array<string, mixed> $state
     */
    private function passesConfirmation(WatchConfig $config, string $key, array &$state, DiffReport $report): bool
    {
        if (null === $config->confirmAfter) {
            return true;
        }

        $pending = $state['pendingConfirmation'] ?? null;
        $now     = $report->comparedAt;

        if (null === $pending) {
            $state['pendingConfirmation'] = ['firstSeenAt' => $now->format(DATE_ATOM)];

            return false;
        }

        $firstSeenAt = new \DateTimeImmutable((string) $pending['firstSeenAt']);
        if (($now->getTimestamp() - $firstSeenAt->getTimestamp()) < $config->confirmAfter) {
            return false;
        }

        unset($state['pendingConfirmation']);

        return true;
    }

    private function pruneByRetention(string $key, int $retentionDays): void
    {
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', $retentionDays));
        $this->store->pruneSnapshotsOlderThan($key, $cutoff);
    }

    private function archiveHtml(WatchConfig $config, string $key, Snapshot $after): void
    {
        $directory = $this->archiveDirectory ?? '.watchdiff/archive';
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        $filename = sprintf('%s_%s.html', hash('xxh128', $key), $after->capturedAt->format('Ymd_His'));
        file_put_contents($directory.'/'.$filename, $after->content);
    }

    private function readFile(WatchConfig $config): FetchResult
    {
        $path    = (string) $config->localPath;
        $started = microtime(true);

        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException(sprintf('File "%s" does not exist or is not readable.', $path));
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new \RuntimeException(sprintf('Unable to read file "%s".', $path));
        }

        return new FetchResult($contents, 200, (microtime(true) - $started) * 1000);
    }

    private function readCert(WatchConfig $config): FetchResult
    {
        $started = microtime(true);

        // verify_peer is intentionally off: the whole point of this feature
        // is inspecting a certificate even if it's expired/self-signed/not
        // chain-trusted, to monitor and report on exactly that. Hostname
        // verification stays on, though - it still catches a MITM presenting
        // an unrelated (but otherwise valid) certificate for a different
        // domain, which "the cert doesn't chain-validate" alone wouldn't.
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => true,
        ]]);

        $socket = @stream_socket_client(
            sprintf('ssl://%s:%d', $config->certHost, $config->certPort),
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (false === $socket) {
            throw new \RuntimeException(sprintf('Unable to connect to %s:%d (%s)', $config->certHost, $config->certPort, $errstr));
        }

        $params = stream_context_get_params($socket);
        fclose($socket);

        $certResource = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$certResource instanceof \OpenSSLCertificate && !\is_string($certResource)) {
            throw new \RuntimeException(sprintf('No certificate presented by %s:%d', $config->certHost, $config->certPort));
        }

        $info = openssl_x509_parse($certResource);
        if (false === $info) {
            throw new \RuntimeException('Unable to parse the presented certificate.');
        }

        $body = json_encode([
            'subject' => $info['name'] ?? null,
            'issuer'  => implode(', ', array_map(
                static fn ($k, $v) => "{$k}={$v}",
                array_keys($info['issuer'] ?? []),
                array_values($info['issuer'] ?? []),
            )),
            'fingerprint' => openssl_x509_fingerprint($certResource, 'sha256') ?: null,
            'notBefore'   => isset($info['validFrom_time_t']) ? date(DATE_ATOM, $info['validFrom_time_t']) : null,
            'notAfter'    => isset($info['validTo_time_t']) ? date(DATE_ATOM, $info['validTo_time_t']) : null,
        ]) ?: '{}';

        return new FetchResult($body, 200, (microtime(true) - $started) * 1000);
    }

    /** @param array<string, mixed> $state */
    private function checkCertExpiry(WatchConfig $config, string $content, array &$state): void
    {
        if (null === $config->warnDaysBeforeExpiry || null === $config->onCertExpiring) {
            return;
        }

        $data = json_decode($content, true);
        if (!\is_array($data) || !isset($data['notAfter'])) {
            return;
        }

        $notAfter        = new \DateTimeImmutable((string) $data['notAfter']);
        $daysUntilExpiry = (int) floor(($notAfter->getTimestamp() - time()) / 86400);

        if ($daysUntilExpiry > $config->warnDaysBeforeExpiry) {
            return;
        }

        $lastWarnedAt = isset($state['certExpiryWarnedAt']) ? new \DateTimeImmutable((string) $state['certExpiryWarnedAt']) : null;
        if (null !== $lastWarnedAt && (time() - $lastWarnedAt->getTimestamp()) < 86400) {
            return;
        }

        ($config->onCertExpiring)([
            'host'            => (string) $config->certHost,
            'daysUntilExpiry' => $daysUntilExpiry,
            'notAfter'        => (string) $data['notAfter'],
        ]);

        $state['certExpiryWarnedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
    }

    private function extractContent(WatchConfig $config, string $body): string
    {
        if (null !== $config->jsonPath) {
            $content = $this->jsonPathExtractor->extract($body, $config->jsonPath);
        } else {
            $cleaned = $this->cleaner->clean($body);
            $content = $this->parser->extract($cleaned, $config->target, $config->ignoreSelectors);
        }

        if ([] !== $config->ignorePatterns) {
            $content = $this->cleaner->stripIgnorePatterns($content, $config->ignorePatterns);
        }

        return $config->ignoreNumbers ? $this->cleaner->stripNumbers($content) : $content;
    }

    /**
     * @param Change[] $changes
     *
     * @return Change[]
     */
    private function applyChangeThreshold(array $changes, string $beforeContent, float $threshold): array
    {
        if ($threshold <= 0.0 || [] === $changes) {
            return $changes;
        }

        $baseLength    = max(1, \mb_strlen($beforeContent));
        $changedLength = array_sum(array_map(
            static fn (Change $c) => \mb_strlen((string) $c->before) + \mb_strlen((string) $c->after),
            $changes
        ));

        return ($changedLength / $baseLength) >= $threshold ? $changes : [];
    }

    private function isCooldownElapsed(WatchConfig $config): bool
    {
        if ($config->cooldown <= 0) {
            return true;
        }

        $lastAlertAt = $this->store->lastAlertAt($config->key());

        return null === $lastAlertAt
            || $lastAlertAt->modify(sprintf('+%d seconds', $config->cooldown)) <= new \DateTimeImmutable();
    }

    /**
     * `onChange` fires whenever an alert actually happens - cooldown,
     * confirmAfter, and alertIf all suppress it along with the webhooks, so
     * that "alert" means the same thing for every alerting mechanism.
     * `forceOnChange` is the one documented exception, used by dryRun to let
     * callers preview what would have been alerted.
     */
    private function dispatch(WatchConfig $config, DiffReport $report, bool $alert, bool $forceOnChange = false): void
    {
        if (($alert || $forceOnChange) && null !== $config->onChange) {
            ($config->onChange)($report);
        }

        if (!$alert) {
            return;
        }

        if ([] !== $config->webhooks && null !== $this->webhookSender) {
            foreach ($config->webhooks as $webhook) {
                try {
                    $this->webhookSender->send($webhook, $report, $config->webhookHeaders, $config->webhookRetries, $config->allowPrivateUrls);
                } catch (\Throwable $exception) {
                    // A failing webhook must not crash the whole tick/checkOnce
                    // call, nor let an exception that may embed a secret
                    // (e.g. a Telegram bot token in the URL) propagate to
                    // whatever catches it upstream without going through the
                    // same onError channel as a fetch failure.
                    if (null !== $config->onError) {
                        ($config->onError)($exception, $config);
                    }
                }
            }
        }

        $this->store->setLastAlertAt($config->key(), new \DateTimeImmutable());
    }

    public function pause(string $tagOrId): void
    {
        $this->setPaused($tagOrId, true);
    }

    public function resume(string $tagOrId): void
    {
        $this->setPaused($tagOrId, false);
    }

    private function setPaused(string $tagOrId, bool $paused): void
    {
        foreach ($this->watchers as $config) {
            if (!$config->matchesTag($tagOrId)) {
                continue;
            }

            $state           = $this->store->getState($config->key());
            $state['paused'] = $paused;
            $this->store->setState($config->key(), $state);
        }
    }

    /** @return WatcherStatus[] */
    public function status(): array
    {
        $now      = new \DateTimeImmutable();
        $statuses = [];

        foreach ($this->watchers as $config) {
            $state  = $this->store->getState($config->key());
            $latest = $this->store->latestSnapshot($config->key());

            $inMaintenance = false;
            foreach ($config->maintenanceWindows as $window) {
                if ($window->contains($now)) {
                    $inMaintenance = true;
                    break;
                }
            }

            $lastCheckAt = isset($state['lastCheckAt']) ? new \DateTimeImmutable((string) $state['lastCheckAt']) : null;
            $interval    = max($config->interval, $this->minInterval, $config->minInterval ?? 0);

            $statuses[] = new WatcherStatus(
                url: $config->url,
                id: $config->id,
                label: $config->label(),
                target: $config->target,
                interval: $config->interval,
                paused: (bool) ($state['paused'] ?? false),
                inMaintenance: $inMaintenance,
                lastCheckAt: $lastCheckAt,
                nextCheckAt: null !== $latest ? $latest->capturedAt->modify(sprintf('+%d seconds', $interval)) : null,
                lastChangeAt: isset($state['lastChangeAt']) ? new \DateTimeImmutable((string) $state['lastChangeAt']) : null,
                checksCount: (int) ($state['checksCount'] ?? 0),
                changesCount: (int) ($state['changesCount'] ?? 0),
                errorsCount: (int) ($state['errorsCount'] ?? 0),
            );
        }

        return $statuses;
    }

    /** @return Snapshot[] */
    public function history(string $key, ?int $limit = null): array
    {
        return $this->store->snapshots($key, $limit);
    }

    /** @return DiffReport[] */
    public function reports(string $key, ?int $limit = null): array
    {
        return $this->store->reports($key, $limit);
    }

    public function clear(string $key): void
    {
        $this->store->clear($key);
    }

    /** @return string[] every watcher key currently stored (even ones no longer registered via watch()) */
    public function storedKeys(): array
    {
        return $this->store->keys();
    }

    public function exportReportsJson(string $key, ?int $limit = null): string
    {
        return json_encode(
            array_map(static fn (DiffReport $r) => $r->toArray(), $this->reports($key, $limit)),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ) ?: '[]';
    }

    public function exportSnapshotsJson(string $key, ?int $limit = null): string
    {
        return json_encode(
            array_map(static fn (Snapshot $s) => $s->toArray(), $this->history($key, $limit)),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ) ?: '[]';
    }

    public function exportReportsCsv(string $key, ?int $limit = null): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open a temporary stream for CSV export.');
        }
        fputcsv($handle, ['url', 'label', 'comparedAt', 'kind', 'before', 'after', 'context'], escape: '\\');

        foreach ($this->reports($key, $limit) as $report) {
            foreach ($report->changes as $change) {
                fputcsv($handle, [
                    $report->url,
                    $report->label,
                    $report->comparedAt->format(DATE_ATOM),
                    $change->kind->value,
                    $change->before,
                    $change->after,
                    $change->context,
                ], escape: '\\');
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return false === $csv ? '' : $csv;
    }

    public function exportSnapshotsCsv(string $key, ?int $limit = null): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open a temporary stream for CSV export.');
        }
        fputcsv($handle, ['url', 'capturedAt', 'statusCode', 'responseTimeMs', 'content'], escape: '\\');

        foreach ($this->history($key, $limit) as $snapshot) {
            fputcsv($handle, [
                $snapshot->url,
                $snapshot->capturedAt->format(DATE_ATOM),
                $snapshot->statusCode,
                $snapshot->responseTimeMs,
                $snapshot->content,
            ], escape: '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return false === $csv ? '' : $csv;
    }

    public function compareUrls(string $urlA, string $urlB, ?string $target = null, ?DiffMode $diffMode = null): DiffReport
    {
        $configA = new WatchConfig(url: $urlA, target: $target, diffMode: $diffMode ?? DiffMode::Line);
        $configB = new WatchConfig(url: $urlB, target: $target, diffMode: $diffMode ?? DiffMode::Line);

        $fetchedA = $this->fetcher->fetch($configA);
        $fetchedB = $this->fetcher->fetch($configB);

        $contentA = $this->extractContent($configA, $fetchedA->body);
        $contentB = $this->extractContent($configB, $fetchedB->body);

        $now       = new \DateTimeImmutable();
        $snapshotA = new Snapshot($urlA, $contentA, $now, $fetchedA->statusCode, $fetchedA->responseTimeMs);
        $snapshotB = new Snapshot($urlB, $contentB, $now, $fetchedB->statusCode, $fetchedB->responseTimeMs);

        $changes = $this->diffEngineFactory->create($configA->diffMode)->diff($contentA, $contentB);

        return new DiffReport(
            url: $urlB,
            label: sprintf('%s vs %s', $urlA, $urlB),
            before: $snapshotA,
            after: $snapshotB,
            changes: $changes,
            diffMode: $configA->diffMode,
            comparedAt: $now,
        );
    }
}
