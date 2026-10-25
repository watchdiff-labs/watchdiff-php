<?php

declare(strict_types=1);

namespace WatchDiff\Core\Config;

use WatchDiff\Core\Enum\DiffMode;
use WatchDiff\Core\Model\DiffReport;

final readonly class WatchConfig
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
        public string $url                    = '',
        public ?string $localPath             = null,
        public ?string $certHost              = null,
        public int $certPort                  = 443,
        public ?int $warnDaysBeforeExpiry     = null,
        public ?\Closure $onCertExpiring      = null,
        public ?string $target                = null,
        public array $ignoreSelectors         = [],
        public array $ignorePatterns          = [],
        public bool $allowPrivateUrls         = false,
        public DiffMode $diffMode             = DiffMode::Line,
        public int $interval                  = 300,
        public ?string $label                 = null,
        public array $headers                 = [],
        public int $retries                   = 0,
        public int $retryDelayMs              = 1000,
        public int $maxRetryDelayMs           = PHP_INT_MAX,
        public int $cooldown                  = 0,
        public float $jitter                  = 0.0,
        public bool $ignoreNumbers            = false,
        public float $changeThreshold         = 0.0,
        public int $minChanges                = 1,
        public bool $dryRun                   = false,
        public ?int $maxSnapshots             = null,
        public array $webhooks                = [],
        public ?\Closure $onChange            = null,
        public ?string $id                    = null,
        public array $userAgents              = [],
        public ?BasicAuth $auth               = null,
        public array $cookies                 = [],
        public string|array|null $requestBody = null,
        public array $webhookHeaders          = [],
        public int $webhookRetries            = 3,
        public bool $alertOnFirstCheck        = false,
        public ?\Closure $onError             = null,
        public ?\Closure $onBeforeCheck       = null,
        public ?\Closure $alertIf             = null,
        public ?string $jsonPath              = null,
        public ?int $expectedStatus           = null,
        public bool $alertOnStatusChange      = false,
        public ?\Closure $onStatusChange      = null,
        public bool $archiveHtml              = false,
        public ?int $retentionDays            = null,
        public array $tags                    = [],
        public ?int $minInterval              = null,
        public ?int $alertIfNoChangeAfter     = null,
        public ?\Closure $onSilence           = null,
        public ?\Closure $onRecovery          = null,
        public ?FailurePolicy $failurePolicy  = null,
        public ?int $changeSpikeWindow        = null,
        public ?int $changeSpikeThreshold     = null,
        public ?\Closure $onSpike             = null,
        public ?int $confirmAfter             = null,
        public array $maintenanceWindows      = [],
        public ?ActiveHours $activeBetween    = null,
        public ?string $schedule              = null,
        public bool $aiSummary                = false,
        public ?AiProvider $aiProvider        = null,
        public string|\Closure|null $aiPrompt = null,
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
