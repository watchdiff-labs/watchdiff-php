<?php

declare(strict_types=1);

namespace WatchDiff\Core\Config;

final class ActiveHours
{
    /** @param string[] $days lowercase day names, e.g. ["monday", "friday"]. Empty = every day. */
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly array $days         = [],
        public readonly string $timezone    = 'UTC',
    ) {
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        $local = $now->setTimezone(new \DateTimeZone($this->timezone));

        if ([] !== $this->days && !\in_array(strtolower($local->format('l')), $this->days, true)) {
            return false;
        }

        $time = $local->format('H:i');

        return $time >= $this->from && $time <= $this->to;
    }
}
