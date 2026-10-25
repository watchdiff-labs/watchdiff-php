<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

final readonly class DbWatcherStatus
{
    public function __construct(
        public string $connectionString,
        public string $table,
        public ?string $id,
        public string $label,
        public DbDiffMode $diffMode,
        public int $interval,
        public ?\DateTimeImmutable $lastCheckAt,
        public ?\DateTimeImmutable $lastChangeAt,
        public int $checksCount,
        public int $changesCount,
        public int $errorsCount,
    ) {
    }
}
