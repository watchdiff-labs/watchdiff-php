<?php

declare(strict_types=1);

namespace WatchDiff\Core\Model;

final class WatcherStatus
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $id,
        public readonly string $label,
        public readonly ?string $target,
        public readonly int $interval,
        public readonly bool $paused,
        public readonly bool $inMaintenance,
        public readonly ?\DateTimeImmutable $lastCheckAt,
        public readonly ?\DateTimeImmutable $nextCheckAt,
        public readonly ?\DateTimeImmutable $lastChangeAt,
        public readonly int $checksCount,
        public readonly int $changesCount,
        public readonly int $errorsCount,
    ) {
    }
}
