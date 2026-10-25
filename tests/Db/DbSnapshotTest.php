<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests\Db;

use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Db\DbSnapshot;

final class DbSnapshotTest extends TestCase
{
    public function testRowCountAndChecksumAreDerivedFromRows(): void
    {
        $snapshot = new DbSnapshot(
            rows: [['id' => 1], ['id' => 2]],
            columns: [],
            capturedAt: new \DateTimeImmutable(),
        );

        self::assertSame(2, $snapshot->rowCount);
        self::assertSame(hash('xxh128', json_encode($snapshot->rows) ?: ''), $snapshot->checksum);
    }

    public function testDifferentRowsProduceDifferentChecksums(): void
    {
        $a = new DbSnapshot(rows: [['id' => 1]], columns: [], capturedAt: new \DateTimeImmutable());
        $b = new DbSnapshot(rows: [['id' => 2]], columns: [], capturedAt: new \DateTimeImmutable());

        self::assertNotSame($a->checksum, $b->checksum);
    }

    public function testToArrayIncludesRowCountAndChecksum(): void
    {
        $snapshot = new DbSnapshot(rows: [['id' => 1]], columns: [], capturedAt: new \DateTimeImmutable());
        $array    = $snapshot->toArray();

        self::assertSame(1, $array['rowCount']);
        self::assertSame($snapshot->checksum, $array['checksum']);
    }
}
