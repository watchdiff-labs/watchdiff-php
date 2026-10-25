<?php

declare(strict_types=1);

namespace WatchDiff\Core\Config;

final class FailurePolicy
{
    public function __construct(
        public readonly int $consecutiveFailures     = 1,
        public readonly int $consecutiveSuccesses    = 1,
        public readonly bool $respectRetryAfter      = false,
    ) {
    }
}
