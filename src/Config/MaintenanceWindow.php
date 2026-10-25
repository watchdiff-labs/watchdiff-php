<?php

declare(strict_types=1);

namespace WatchDiff\Core\Config;

final readonly class MaintenanceWindow
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
    ) {
    }

    public function contains(\DateTimeImmutable $now): bool
    {
        return $now >= $this->from && $now <= $this->to;
    }
}
