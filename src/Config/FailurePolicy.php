<?php

declare(strict_types=1);

namespace WatchDiff\Core\Config;

final readonly class FailurePolicy
{
    public function __construct(
        public int $consecutiveFailures     = 1,
        public int $consecutiveSuccesses    = 1,
        public bool $respectRetryAfter      = false,
    ) {
    }
}
