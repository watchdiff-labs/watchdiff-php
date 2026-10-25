<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Cron\CronExpression;
use WatchDiff\Core\Config\ActiveHours;
use WatchDiff\Core\Config\AiProvider;
use WatchDiff\Core\Config\AiProviderType;
use WatchDiff\Core\Config\BasicAuth;
use WatchDiff\Core\Config\FailurePolicy;
use WatchDiff\Core\Config\MaintenanceWindow;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\Enum\DiffMode;

final class ConfigFile
{
    /** @return WatchConfig[] */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Config file "%s" not found.', $path));
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!\is_array($data) || !isset($data['watchers']) || !\is_array($data['watchers'])) {
            throw new \RuntimeException('Config file must contain a "watchers" array.');
        }

        return array_map(self::fromArray(...), $data['watchers']);
    }

    /**
     * Validates the raw config file directly (not through fromArray(), which
     * throws on the first bad field) so every watcher is checked and every
     * problem is reported in one pass, instead of stopping at the first one.
     *
     * @return string[]
     */
    public static function validate(string $path): array
    {
        if (!is_file($path)) {
            return [sprintf('Config file "%s" not found.', $path)];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!\is_array($data) || !isset($data['watchers']) || !\is_array($data['watchers'])) {
            return ['Config file must contain a "watchers" array.'];
        }

        $errors = [];

        foreach ($data['watchers'] as $i => $watcher) {
            if (!\is_array($watcher)) {
                $errors[] = sprintf('watcher #%d: must be an object.', $i);

                continue;
            }

            $errors = [...$errors, ...self::validateWatcher($i, $watcher)];
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $watcher
     *
     * @return string[]
     */
    private static function validateWatcher(int $i, array $watcher): array
    {
        $errors = [];
        $isUrl  = isset($watcher['url']) && !isset($watcher['localPath']) && !isset($watcher['certHost']);

        if (!isset($watcher['url']) && !isset($watcher['localPath']) && !isset($watcher['certHost'])) {
            $errors[] = sprintf('watcher #%d: requires a "url", a "localPath", or a "certHost".', $i);
        }

        if ($isUrl && !str_starts_with((string) $watcher['url'], 'http://') && !str_starts_with((string) $watcher['url'], 'https://')) {
            $errors[] = sprintf('watcher #%d: url must start with http:// or https://, got "%s".', $i, $watcher['url']);
        } elseif ($isUrl && false === filter_var($watcher['url'], FILTER_VALIDATE_URL)) {
            $errors[] = sprintf('watcher #%d: invalid URL "%s".', $i, $watcher['url']);
        }

        if (isset($watcher['interval']) && (!\is_int($watcher['interval']) || $watcher['interval'] <= 0)) {
            $errors[] = sprintf('watcher #%d: "interval" must be a positive integer, got %s.', $i, json_encode($watcher['interval']) ?: 'null');
        }

        if (isset($watcher['diffMode']) && null === DiffMode::tryFrom($watcher['diffMode'])) {
            $errors[] = sprintf('watcher #%d: invalid "diffMode" "%s".', $i, $watcher['diffMode']);
        }

        if (isset($watcher['jsonPath']) && (!\is_string($watcher['jsonPath']) || !str_starts_with($watcher['jsonPath'], '$'))) {
            $errors[] = sprintf('watcher #%d: "jsonPath" must be a string starting with "$" (e.g. "$.data.price").', $i);
        }

        if (isset($watcher['schedule']) && !self::isValidCronExpression((string) $watcher['schedule'])) {
            $errors[] = sprintf('watcher #%d: invalid cron expression "%s".', $i, $watcher['schedule']);
        }

        foreach ((array) ($watcher['webhooks'] ?? []) as $webhook) {
            $webhook = (string) $webhook;
            if ((!str_starts_with($webhook, 'http://') && !str_starts_with($webhook, 'https://'))
                || false === filter_var($webhook, FILTER_VALIDATE_URL)) {
                $errors[] = sprintf('watcher #%d: invalid webhook URL "%s".', $i, $webhook);
            }
        }

        return $errors;
    }

    private static function isValidCronExpression(string $expression): bool
    {
        try {
            new CronExpression($expression);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $data */
    private static function fromArray(array $data): WatchConfig
    {
        if (!isset($data['url']) && !isset($data['localPath']) && !isset($data['certHost'])) {
            throw new \RuntimeException('Each watcher requires a "url", a "localPath", or a "certHost".');
        }

        return new WatchConfig(
            url: $data['url'] ?? '',
            localPath: $data['localPath'] ?? null,
            certHost: $data['certHost'] ?? null,
            certPort: $data['certPort'] ?? 443,
            warnDaysBeforeExpiry: $data['warnDaysBeforeExpiry'] ?? null,
            target: $data['target'] ?? null,
            diffMode: isset($data['diffMode']) ? DiffMode::from($data['diffMode']) : DiffMode::Line,
            interval: $data['interval'] ?? 300,
            label: $data['label'] ?? null,
            headers: $data['headers'] ?? [],
            retries: $data['retries'] ?? 0,
            retryDelayMs: $data['retryDelayMs'] ?? 1000,
            maxRetryDelayMs: $data['maxRetryDelayMs'] ?? PHP_INT_MAX,
            cooldown: $data['cooldown'] ?? 0,
            jitter: $data['jitter'] ?? 0.0,
            ignoreNumbers: $data['ignoreNumbers'] ?? false,
            changeThreshold: $data['changeThreshold'] ?? 0.0,
            dryRun: $data['dryRun'] ?? false,
            maxSnapshots: $data['maxSnapshots'] ?? null,
            webhooks: $data['webhooks'] ?? [],
            id: $data['id'] ?? null,
            userAgents: $data['userAgents'] ?? [],
            auth: isset($data['auth']) ? new BasicAuth($data['auth']['user'], $data['auth']['pass']) : null,
            cookies: $data['cookies'] ?? [],
            requestBody: $data['requestBody'] ?? null,
            webhookHeaders: $data['webhookHeaders'] ?? [],
            webhookRetries: $data['webhookRetries'] ?? 3,
            alertOnFirstCheck: $data['alertOnFirstCheck'] ?? false,
            jsonPath: $data['jsonPath'] ?? null,
            expectedStatus: $data['expectedStatus'] ?? null,
            alertOnStatusChange: $data['alertOnStatusChange'] ?? false,
            archiveHtml: $data['archiveHtml'] ?? false,
            retentionDays: $data['retentionDays'] ?? null,
            tags: $data['tags'] ?? [],
            minInterval: $data['minInterval'] ?? null,
            alertIfNoChangeAfter: $data['alertIfNoChangeAfter'] ?? null,
            failurePolicy: isset($data['failurePolicy']) ? new FailurePolicy(
                consecutiveFailures: $data['failurePolicy']['consecutiveFailures'] ?? 1,
                consecutiveSuccesses: $data['failurePolicy']['consecutiveSuccesses'] ?? 1,
                respectRetryAfter: $data['failurePolicy']['respectRetryAfter'] ?? false,
            ) : null,
            changeSpikeWindow: $data['changeSpikeWindow'] ?? null,
            changeSpikeThreshold: $data['changeSpikeThreshold'] ?? null,
            confirmAfter: $data['confirmAfter'] ?? null,
            maintenanceWindows: array_map(
                static fn (array $w) => new MaintenanceWindow(new \DateTimeImmutable($w['from']), new \DateTimeImmutable($w['to'])),
                $data['maintenanceWindows'] ?? [],
            ),
            activeBetween: isset($data['activeBetween']) ? new ActiveHours(
                from: $data['activeBetween']['from'],
                to: $data['activeBetween']['to'],
                days: $data['activeBetween']['days'] ?? [],
                timezone: $data['activeBetween']['timezone'] ?? 'UTC',
            ) : null,
            schedule: $data['schedule'] ?? null,
            aiSummary: $data['aiSummary'] ?? false,
            aiProvider: isset($data['aiProvider']) ? new AiProvider(
                type: AiProviderType::from($data['aiProvider']['type']),
                apiKey: $data['aiProvider']['apiKey'] ?? '',
                model: $data['aiProvider']['model'] ?? null,
                baseUrl: $data['aiProvider']['baseUrl'] ?? null,
            ) : null,
            aiPrompt: $data['aiPrompt'] ?? null,
        );
    }
}
