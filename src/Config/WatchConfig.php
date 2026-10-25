<?php

declare(strict_types=1);

namespace WatchDiff\Core\Config;

use WatchDiff\Core\Enum\DiffMode;
use WatchDiff\Core\Model\DiffReport;

final class WatchConfig
{
    /**
     * @param string[]                                      $headers
     * @param string[]                                       $ignoreSelectors
     * @param string[]                                       $ignorePatterns
     * @param string[]                                       $webhooks
     * @param string[]                                       $userAgents
     * @param array<string, string>                          $cookies
     * @param array<string, string>                          $webhookHeaders
     * @param string[]                                       $tags
     * @param MaintenanceWindow[]                             $maintenanceWindows
     * @param string|array<string, mixed>|null                $requestBody
     * @param (\Closure(DiffReport): void)|null                $onChange
     * @param (\Closure(\Throwable, self): void)|null          $onError
     * @param (\Closure(self): void)|null                      $onBeforeCheck
     * @param (\Closure(DiffReport): bool)|null                 $alertIf
     * @param (\Closure(array{url: string, label: string, secondsSinceLastChange: int}): void)|null $onSilence
     * @param (\Closure(self): void)|null                       $onRecovery
     * @param (\Closure(array{url: string, label: string, changesInWindow: int, windowSeconds: int}): void)|null $onSpike
     * @param (\Closure(array{url: string, label: string, previousStatus: int, currentStatus: int}): void)|null $onStatusChange
     */
    public function __construct(
        public readonly string $url                    = '',
        public readonly ?string $localPath             = null,
        public readonly ?string $certHost              = null,
        public readonly int $certPort                  = 443,
        public readonly ?int $warnDaysBeforeExpiry     = null,
        public readonly ?\Closure $onCertExpiring      = null,
        public readonly ?string $target                = null,
        public readonly array $ignoreSelectors         = [],
        public readonly array $ignorePatterns          = [],
        public readonly bool $allowPrivateUrls         = false,
        public readonly DiffMode $diffMode             = DiffMode::Line,
        public readonly int $interval                  = 300,
        public readonly ?string $label                 = null,
        public readonly array $headers                 = [],
        public readonly int $retries                   = 0,
        public readonly int $retryDelayMs              = 1000,
        public readonly int $maxRetryDelayMs           = PHP_INT_MAX,
        public readonly int $cooldown                  = 0,
        public readonly float $jitter                  = 0.0,
        public readonly bool $ignoreNumbers            = false,
        public readonly float $changeThreshold         = 0.0,
        public readonly int $minChanges                = 1,
        public readonly bool $dryRun                   = false,
        public readonly ?int $maxSnapshots             = null,
        public readonly array $webhooks                = [],
        public readonly ?\Closure $onChange            = null,
        public readonly ?string $id                    = null,
        public readonly array $userAgents              = [],
        public readonly ?BasicAuth $auth               = null,
        public readonly array $cookies                 = [],
        public readonly string|array|null $requestBody = null,
        public readonly array $webhookHeaders          = [],
        public readonly int $webhookRetries            = 3,
        public readonly bool $alertOnFirstCheck        = false,
        public readonly ?\Closure $onError             = null,
        public readonly ?\Closure $onBeforeCheck       = null,
        public readonly ?\Closure $alertIf             = null,
        public readonly ?string $jsonPath              = null,
        public readonly ?int $expectedStatus           = null,
        public readonly bool $alertOnStatusChange      = false,
        public readonly ?\Closure $onStatusChange      = null,
        public readonly bool $archiveHtml              = false,
        public readonly ?int $retentionDays            = null,
        public readonly array $tags                    = [],
        public readonly ?int $minInterval              = null,
        public readonly ?int $alertIfNoChangeAfter     = null,
        public readonly ?\Closure $onSilence           = null,
        public readonly ?\Closure $onRecovery          = null,
        public readonly ?FailurePolicy $failurePolicy  = null,
        public readonly ?int $changeSpikeWindow        = null,
        public readonly ?int $changeSpikeThreshold     = null,
        public readonly ?\Closure $onSpike             = null,
        public readonly ?int $confirmAfter             = null,
        public readonly array $maintenanceWindows      = [],
        public readonly ?ActiveHours $activeBetween    = null,
        public readonly ?string $schedule              = null,
        public readonly bool $aiSummary                = false,
        public readonly ?AiProvider $aiProvider        = null,
        public readonly string|\Closure|null $aiPrompt = null,
    ) {
        if ('' === $this->url && null === $this->localPath && null === $this->certHost) {
            throw new \InvalidArgumentException('A WatchConfig needs a url, a localPath, or a certHost.');
        }

        // Restricting to http(s) is defense in depth against the URL being
        // used as a generic SSRF primitive (file://, gopher://, ...) when it
        // comes from an untrusted source - independent of whether the PSR-18
        // client you plug in would itself reject other schemes.
        if ('' !== $this->url && !str_starts_with($this->url, 'http://') && !str_starts_with($this->url, 'https://')) {
            throw new \InvalidArgumentException(sprintf('WatchConfig url must start with http:// or https://, got "%s".', $this->url));
        }
    }

    public function isFileWatch(): bool
    {
        return null !== $this->localPath;
    }

    public function isCertWatch(): bool
    {
        return null !== $this->certHost;
    }

    private function subject(): string
    {
        return $this->localPath ?? ($this->isCertWatch() ? "{$this->certHost}:{$this->certPort}" : $this->url);
    }

    public function label(): string
    {
        return $this->label ?? $this->subject();
    }

    public function key(): string
    {
        return $this->id ?? $this->subject();
    }

    public function matchesTag(string $tagOrId): bool
    {
        return $this->key() === $tagOrId || \in_array($tagOrId, $this->tags, true);
    }
}
