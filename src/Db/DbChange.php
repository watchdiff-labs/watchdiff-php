<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

final class DbChange
{
    /**
     * @param array<string, mixed>|null                                        $row
     * @param array<int, array{column: string, before: mixed, after: mixed}>|null $modifications
     */
    public function __construct(
        public readonly DbChangeKind $kind,
        public readonly ?array $row = null,
        public readonly ?string $rowKey = null,
        public readonly ?array $modifications = null,
        public readonly ?string $column = null,
        public readonly ?string $context = null,
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
