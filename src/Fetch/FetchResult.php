<?php

declare(strict_types=1);

namespace WatchDiff\Core\Fetch;

final class FetchResult
{
    public function __construct(
        public readonly string $body,
        public readonly int $statusCode,
        public readonly float $responseTimeMs,
    ) {
    }
}
