<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

final class DbWatcherStatus
{
    public function __construct(
        public readonly string $connectionString,
        public readonly string $table,
        public readonly ?string $id,
        public readonly string $label,
        public readonly DbDiffMode $diffMode,
        public readonly int $interval,
        public readonly ?\DateTimeImmutable $lastCheckAt,
        public readonly ?\DateTimeImmutable $lastChangeAt,
        public readonly int $checksCount,
        public readonly int $changesCount,
        public readonly int $errorsCount,
    ) {
    }
}
