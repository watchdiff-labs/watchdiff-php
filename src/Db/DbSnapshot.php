<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

final class DbSnapshot
{
    public readonly int $rowCount;
    public readonly string $checksum;

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array{name: string, type: string, nullable: bool, primaryKey: bool, defaultValue: mixed}> $columns
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $columns,
        public readonly \DateTimeImmutable $capturedAt,
    ) {
        $this->rowCount = \count($this->rows);
        // A cheap content fingerprint for "did the row set change at all"
        // comparisons - not a security boundary, so a fast non-cryptographic
        // hash (unlike the storage-key hashing in JsonStore) is the right
        // tool here.
        $this->checksum = hash('xxh128', json_encode($this->rows) ?: '');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rows'       => $this->rows,
            'columns'    => $this->columns,
            'capturedAt' => $this->capturedAt->format(DATE_ATOM),
            'rowCount'   => $this->rowCount,
            'checksum'   => $this->checksum,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            rows: $data['rows'] ?? [],
            columns: $data['columns'] ?? [],
            capturedAt: new \DateTimeImmutable($data['capturedAt']),
        );
    }
}
