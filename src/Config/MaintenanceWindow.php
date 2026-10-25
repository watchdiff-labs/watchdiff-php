<?php

declare(strict_types=1);

namespace WatchDiff\Core\Config;

final class MaintenanceWindow
{
    public function __construct(
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to,
    ) {
    }

    public function contains(\DateTimeImmutable $now): bool
    {
        return $now >= $this->from && $now <= $this->to;
    }
}
