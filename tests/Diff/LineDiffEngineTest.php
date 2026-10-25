<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests\Diff;

use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Diff\LineDiffEngine;
use WatchDiff\Core\Enum\ChangeKind;

final class LineDiffEngineTest extends TestCase
{
    public function testDetectsModifiedLine(): void
    {
        $changes = (new LineDiffEngine())->diff("Price: $19\n", "Price: $24\n");

        self::assertCount(1, $changes);
        self::assertSame(ChangeKind::Modified, $changes[0]->kind);
        self::assertSame('Price: $19', $changes[0]->before);
        self::assertSame('Price: $24', $changes[0]->after);
    }

    public function testNoChangesWhenIdentical(): void
    {
        self::assertSame([], (new LineDiffEngine())->diff('same', 'same'));
    }

    public function testDetectsAddedLine(): void
    {
        $changes = (new LineDiffEngine())->diff("a\nb", "a\nb\nc");

        self::assertCount(1, $changes);
        self::assertSame(ChangeKind::Added, $changes[0]->kind);
        self::assertSame('c', $changes[0]->after);
    }
}
