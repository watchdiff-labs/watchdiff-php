<?php

declare(strict_types=1);

namespace WatchDiff\Core\Fetch;

final readonly class FetchResult
{
    public function __construct(
        public string $body,
        public int $statusCode,
        public float $responseTimeMs,
    ) {
    }
}
