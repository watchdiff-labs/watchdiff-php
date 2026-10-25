<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

final class DbDiffReport
{
    /** @param DbChange[] $changes */
    public function __construct(
        public readonly string $connectionString,
        public readonly string $table,
        public readonly string $label,
        public readonly DbSnapshot $before,
        public readonly DbSnapshot $after,
        public readonly array $changes,
        public readonly DbDiffMode $diffMode,
        public readonly \DateTimeImmutable $comparedAt,
    ) {
    }

    public function hasChanges(): bool
    {
        return \count($this->changes) > 0;
    }
}
