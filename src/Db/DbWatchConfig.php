<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

final readonly class DbWatchConfig
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
        public string $connectionString,
        public string $table,
        public DbDiffMode $diffMode = DbDiffMode::Row,
        public array $primaryKey = [],
        public array $ignoreColumns = [],
        public ?string $query = null,
        public int $interval = 300,
        public ?string $label = null,
        public ?string $id = null,
        public bool $alertOnInsert = true,
        public bool $alertOnDelete = true,
        public bool $alertOnUpdate = true,
        public float $threshold = 0.0,
        public ?\Closure $onChange = null,
        public ?\Closure $onSchemaChange = null,
        public ?\Closure $onThreshold = null,
        public ?\Closure $onError = null,
        public array $webhooks = [],
        public bool $allowPrivateUrls = false,
        public int $cooldown = 0,
        public bool $dryRun = false,
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
