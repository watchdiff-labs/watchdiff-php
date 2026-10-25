<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests\Store;

use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Model\Snapshot;
use WatchDiff\Core\Store\SqliteStore;

final class SqliteStoreTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir().'/watchdiff-store-'.bin2hex(random_bytes(8)).'.sqlite';

        if (!\in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is not installed in this environment.');
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
    }

    public function testSavesAndRetrievesSnapshotsAndState(): void
    {
        $store = new SqliteStore($this->dbPath);

        $store->saveSnapshot('https://example.test', new Snapshot('https://example.test', 'v1', new \DateTimeImmutable()));
        $store->saveSnapshot('https://example.test', new Snapshot('https://example.test', 'v2', new \DateTimeImmutable()));

        $latest = $store->latestSnapshot('https://example.test');
        self::assertNotNull($latest);
        self::assertSame('v2', $latest->content);
        self::assertCount(2, $store->snapshots('https://example.test'));

        $store->setState('https://example.test', ['checksCount' => 3, 'paused' => true]);
        self::assertSame(['checksCount' => 3, 'paused' => true], $store->getState('https://example.test'));

        self::assertContains('https://example.test', $store->keys());

        $store->clear('https://example.test');
        self::assertSame([], $store->snapshots('https://example.test'));
        self::assertSame([], $store->getState('https://example.test'));
    }

    public function testMaxSnapshotsPrunesOldest(): void
    {
        $store = new SqliteStore($this->dbPath);

        for ($i = 0; $i < 5; ++$i) {
            $store->saveSnapshot('k', new Snapshot('k', "v{$i}", new \DateTimeImmutable()), maxSnapshots: 2);
        }

        $snapshots = $store->snapshots('k');
        self::assertCount(2, $snapshots);
        self::assertSame('v3', $snapshots[0]->content);
        self::assertSame('v4', $snapshots[1]->content);
    }

    public function testPruneSnapshotsOlderThanLeavesReportsAndStateUntouched(): void
    {
        $store = new SqliteStore($this->dbPath);

        $store->saveSnapshot('k', new Snapshot('k', 'old', new \DateTimeImmutable('-60 days')));
        $store->saveSnapshot('k', new Snapshot('k', 'recent', new \DateTimeImmutable()));
        $store->saveReport('k', new \WatchDiff\Core\Model\DiffReport(
            url: 'k',
            label: 'k',
            before: new Snapshot('k', 'old', new \DateTimeImmutable('-60 days')),
            after: new Snapshot('k', 'recent', new \DateTimeImmutable()),
            changes: [],
            diffMode: \WatchDiff\Core\Enum\DiffMode::Line,
            comparedAt: new \DateTimeImmutable(),
        ));
        $store->setState('k', ['checksCount' => 7]);

        $store->pruneSnapshotsOlderThan('k', new \DateTimeImmutable('-30 days'));

        $snapshots = $store->snapshots('k');
        self::assertCount(1, $snapshots);
        self::assertSame('recent', $snapshots[0]->content);
        self::assertCount(1, $store->reports('k'), 'reports must survive a snapshot prune');
        self::assertSame(['checksCount' => 7], $store->getState('k'), 'state must survive a snapshot prune');
    }
}
