<?php

declare(strict_types=1);

namespace WatchDiff\Core\Diff;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\DiffOnlyOutputBuilder;
use WatchDiff\Core\Enum\ChangeKind;
use WatchDiff\Core\Model\Change;

class LineDiffEngine implements DiffEngineInterface
{
    public function diff(string $before, string $after): array
    {
        return $this->tokensToChanges(
            (new Differ(new DiffOnlyOutputBuilder()))->diffToArray($this->toLines($before), $this->toLines($after))
        );
    }

    /** @return string[] */
    protected function toLines(string $text): array
    {
        return explode("\n", $text);
    }

    /** @param array<int, array{0: string, 1: int}> $tokens */
    protected function tokensToChanges(array $tokens): array
    {
        $changes        = [];
        $pendingRemoval = null;

        foreach ($tokens as [$token, $tag]) {
            if (Differ::REMOVED === $tag) {
                if (null !== $pendingRemoval) {
                    $changes[] = new Change(ChangeKind::Removed, before: $pendingRemoval);
                }
                $pendingRemoval = $token;

                continue;
            }

            if (Differ::ADDED === $tag) {
                if (null !== $pendingRemoval) {
                    $changes[]      = new Change(ChangeKind::Modified, before: $pendingRemoval, after: $token);
                    $pendingRemoval = null;
                } else {
                    $changes[] = new Change(ChangeKind::Added, after: $token);
                }

                continue;
            }

            if (null !== $pendingRemoval) {
                $changes[]      = new Change(ChangeKind::Removed, before: $pendingRemoval);
                $pendingRemoval = null;
            }
        }

        if (null !== $pendingRemoval) {
            $changes[] = new Change(ChangeKind::Removed, before: $pendingRemoval);
        }

        return $changes;
    }
}
