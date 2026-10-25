<?php

declare(strict_types=1);

namespace WatchDiff\Core\Model;

final readonly class WatcherStatus
{
    public function __construct(
        public string $url,
        public ?string $id,
        public string $label,
        public ?string $target,
        public int $interval,
        public bool $paused,
        public bool $inMaintenance,
        public ?\DateTimeImmutable $lastCheckAt,
        public ?\DateTimeImmutable $nextCheckAt,
        public ?\DateTimeImmutable $lastChangeAt,
        public int $checksCount,
        public int $changesCount,
        public int $errorsCount,
    ) {
    }
}
