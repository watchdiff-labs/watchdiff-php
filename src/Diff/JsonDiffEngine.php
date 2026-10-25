<?php

declare(strict_types=1);

namespace WatchDiff\Core\Diff;

use WatchDiff\Core\Enum\ChangeKind;
use WatchDiff\Core\Model\Change;

final class JsonDiffEngine implements DiffEngineInterface
{
    public function __construct(private readonly LineDiffEngine $fallback = new LineDiffEngine())
    {
    }

    public function diff(string $before, string $after): array
    {
        $beforeData = json_decode($before, true);
        $afterData  = json_decode($after, true);

        if (!$this->isDecodable($before, $beforeData) || !$this->isDecodable($after, $afterData)) {
            return $this->fallback->diff($before, $after);
        }

        $changes = [];
        $this->walk($beforeData, $afterData, '', $changes);

        return $changes;
    }

    private function isDecodable(string $raw, mixed $decoded): bool
    {
        return null !== $decoded || 'null' === trim($raw);
    }

    private function walk(mixed $before, mixed $after, string $path, array &$changes): void
    {
        if (\is_array($before) && \is_array($after) && $this->isAssoc($before) && $this->isAssoc($after)) {
            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
                $childPath = '' === $path ? (string) $key : $path.'.'.$key;

                if (!array_key_exists($key, $before)) {
                    $changes[] = new Change(ChangeKind::Added, after: $this->stringify($after[$key]), context: $childPath);
                    continue;
                }

                if (!array_key_exists($key, $after)) {
                    $changes[] = new Change(ChangeKind::Removed, before: $this->stringify($before[$key]), context: $childPath);
                    continue;
                }

                $this->walk($before[$key], $after[$key], $childPath, $changes);
            }

            return;
        }

        if ($before === $after) {
            return;
        }

        $changes[] = new Change(ChangeKind::Modified, before: $this->stringify($before), after: $this->stringify($after), context: '' === $path ? null : $path);
    }

    private function isAssoc(array $value): bool
    {
        return [] === $value || array_keys($value) !== range(0, \count($value) - 1);
    }

    private function stringify(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (null === $value) {
            return 'null';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value) ?: '';
    }
}
