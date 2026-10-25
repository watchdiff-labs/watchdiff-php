<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

final readonly class DbDiffReport
{
    /** @param DbChange[] $changes */
    public function __construct(
        public string $connectionString,
        public string $table,
        public string $label,
        public DbSnapshot $before,
        public DbSnapshot $after,
        public array $changes,
        public DbDiffMode $diffMode,
        public \DateTimeImmutable $comparedAt,
    ) {
    }

    public function hasChanges(): bool
    {
        return \count($this->changes) > 0;
    }
}
