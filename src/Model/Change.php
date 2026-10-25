<?php

declare(strict_types=1);

namespace WatchDiff\Core\Model;

use WatchDiff\Core\Enum\ChangeKind;

final class Change
{
    public function __construct(
        public readonly ChangeKind $kind,
        public readonly ?string $before     = null,
        public readonly ?string $after      = null,
        public readonly ?string $context    = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind'    => $this->kind->value,
            'before'  => $this->before,
            'after'   => $this->after,
            'context' => $this->context,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            kind: ChangeKind::from($data['kind']),
            before: $data['before'] ?? null,
            after: $data['after'] ?? null,
            context: $data['context'] ?? null,
        );
    }
}
