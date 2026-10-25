<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

final readonly class DbChange
{
    /**
     * @param array<string, mixed>|null                                        $row
     * @param array<int, array{column: string, before: mixed, after: mixed}>|null $modifications
     */
    public function __construct(
        public DbChangeKind $kind,
        public ?array $row = null,
        public ?string $rowKey = null,
        public ?array $modifications = null,
        public ?string $column = null,
        public ?string $context = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind'          => $this->kind->value,
            'row'           => $this->row,
            'rowKey'        => $this->rowKey,
            'modifications' => $this->modifications,
            'column'        => $this->column,
            'context'       => $this->context,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            kind: DbChangeKind::from($data['kind']),
            row: $data['row'] ?? null,
            rowKey: $data['rowKey'] ?? null,
            modifications: $data['modifications'] ?? null,
            column: $data['column'] ?? null,
            context: $data['context'] ?? null,
        );
    }
}
