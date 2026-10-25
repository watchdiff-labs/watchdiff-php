<?php

declare(strict_types=1);

namespace WatchDiff\Core\Diff;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\DiffOnlyOutputBuilder;

final class WordDiffEngine extends LineDiffEngine
{
    public function diff(string $before, string $after): array
    {
        return $this->tokensToChanges(
            (new Differ(new DiffOnlyOutputBuilder()))->diffToArray($this->toWords($before), $this->toWords($after))
        );
    }

    /** @return string[] */
    private function toWords(string $text): array
    {
        $words = preg_split('/\s+/', trim($text));

        return false === $words ? [] : array_values(array_filter($words, static fn (string $w) => '' !== $w));
    }
}
