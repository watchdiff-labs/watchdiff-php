<?php

declare(strict_types=1);

namespace WatchDiff\Core\Model;

use WatchDiff\Core\Enum\DiffMode;

final class DiffReport
{
    /** @param Change[] $changes */
    public function __construct(
        public readonly string $url,
        public readonly string $label,
        public readonly Snapshot $before,
        public readonly Snapshot $after,
        public readonly array $changes,
        public readonly DiffMode $diffMode,
        public readonly \DateTimeImmutable $comparedAt,
        public readonly ?string $aiSummary = null,
    ) {
    }

    public function hasChanges(): bool
    {
        return \count($this->changes) > 0;
    }

    public function withAiSummary(string $summary): self
    {
        return new self($this->url, $this->label, $this->before, $this->after, $this->changes, $this->diffMode, $this->comparedAt, $summary);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url'        => $this->url,
            'label'      => $this->label,
            'before'     => $this->before->toArray(),
            'after'      => $this->after->toArray(),
            'changes'    => array_map(static fn (Change $c) => $c->toArray(), $this->changes),
            'diffMode'   => $this->diffMode->value,
            'comparedAt' => $this->comparedAt->format(DATE_ATOM),
            'aiSummary'  => $this->aiSummary,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'],
            label: $data['label'],
            before: Snapshot::fromArray($data['before']),
            after: Snapshot::fromArray($data['after']),
            changes: array_map(static fn (array $c) => Change::fromArray($c), $data['changes']),
            diffMode: DiffMode::from($data['diffMode']),
            comparedAt: new \DateTimeImmutable($data['comparedAt']),
            aiSummary: $data['aiSummary'] ?? null,
        );
    }
}
