<?php

declare(strict_types=1);

namespace WatchDiff\Core\Model;

final readonly class Snapshot
{
    public function __construct(
        public string $url,
        public string $content,
        public \DateTimeImmutable $capturedAt,
        public int $statusCode = 200,
        public ?float $responseTimeMs = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url'            => $this->url,
            'content'        => $this->content,
            'capturedAt'     => $this->capturedAt->format(DATE_ATOM),
            'statusCode'     => $this->statusCode,
            'responseTimeMs' => $this->responseTimeMs,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'],
            content: $data['content'],
            capturedAt: new \DateTimeImmutable($data['capturedAt']),
            statusCode: $data['statusCode'] ?? 200,
            responseTimeMs: $data['responseTimeMs'] ?? null,
        );
    }
}
