<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

final class DbWatchConfig
{
    /**
     * @param string[] $primaryKey
     * @param string[] $ignoreColumns
     * @param string[] $webhooks
     * @param (\Closure(DbDiffReport): void)|null $onChange
     * @param (\Closure(array{changes: DbChange[]}): void)|null $onSchemaChange
     * @param (\Closure(array{previousValue: float, currentValue: float, changePercent: float}): void)|null $onThreshold
     * @param (\Closure(\Throwable): void)|null $onError
     */
    public function __construct(
        public readonly string $connectionString,
        public readonly string $table,
        public readonly DbDiffMode $diffMode = DbDiffMode::Row,
        public readonly array $primaryKey = [],
        public readonly array $ignoreColumns = [],
        public readonly ?string $query = null,
        public readonly int $interval = 300,
        public readonly ?string $label = null,
        public readonly ?string $id = null,
        public readonly bool $alertOnInsert = true,
        public readonly bool $alertOnDelete = true,
        public readonly bool $alertOnUpdate = true,
        public readonly float $threshold = 0.0,
        public readonly ?\Closure $onChange = null,
        public readonly ?\Closure $onSchemaChange = null,
        public readonly ?\Closure $onThreshold = null,
        public readonly ?\Closure $onError = null,
        public readonly array $webhooks = [],
        public readonly bool $allowPrivateUrls = false,
        public readonly int $cooldown = 0,
        public readonly bool $dryRun = false,
    ) {
    }

    public function key(): string
    {
        return $this->id ?? $this->redactedConnectionString().'#'.$this->table.'#'.($this->query ?? '');
    }

    public function label(): string
    {
        return $this->label ?? $this->table;
    }

    /**
     * The connection string with any embedded credentials stripped, so the
     * default storage key never writes a plaintext DB password to disk
     * (JsonStore's key registry and SqliteStore's watcher_key column both
     * store this value verbatim). Set `id` explicitly to bypass this.
     */
    private function redactedConnectionString(): string
    {
        $parts = parse_url($this->connectionString);
        if (false === $parts || (!isset($parts['user']) && !isset($parts['pass']))) {
            return $this->connectionString;
        }

        unset($parts['user'], $parts['pass']);

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $host   = $parts['host'] ?? '';
        $port   = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path   = $parts['path'] ?? '';

        return $scheme.$host.$port.$path;
    }
}
