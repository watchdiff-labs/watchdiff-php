<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests\Store;

use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Enum\DiffMode;
use WatchDiff\Core\Model\DiffReport;
use WatchDiff\Core\Model\Snapshot;
use WatchDiff\Core\Store\JsonStore;

final class JsonStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/watchdiff-jsonstore-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        @rmdir($this->directory);
    }

    public function testSavesAndRetrievesSnapshotsAndState(): void
    {
        $store = new JsonStore($this->directory);

        $store->saveSnapshot('k', new Snapshot('k', 'v1', new \DateTimeImmutable()));
        $store->saveSnapshot('k', new Snapshot('k', 'v2', new \DateTimeImmutable()));

        self::assertSame('v2', $store->latestSnapshot('k')?->content);
        self::assertCount(2, $store->snapshots('k'));

        $store->setState('k', ['checksCount' => 3, 'paused' => true]);
        self::assertSame(['checksCount' => 3, 'paused' => true], $store->getState('k'));
        self::assertContains('k', $store->keys());

        $store->clear('k');
        self::assertSame([], $store->snapshots('k'));
        self::assertSame([], $store->getState('k'));
    }

    public function testMaxSnapshotsPrunesOldest(): void
    {
        $store = new JsonStore($this->directory);

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
        $store = new JsonStore($this->directory);

        $store->saveSnapshot('k', new Snapshot('k', 'old', new \DateTimeImmutable('-60 days')));
        $store->saveSnapshot('k', new Snapshot('k', 'recent', new \DateTimeImmutable()));
        $store->saveReport('k', new DiffReport(
            url: 'k',
            label: 'k',
            before: new Snapshot('k', 'old', new \DateTimeImmutable('-60 days')),
            after: new Snapshot('k', 'recent', new \DateTimeImmutable()),
            changes: [],
            diffMode: DiffMode::Line,
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

    public function testStorageFilenamesAreDerivedWithSha256NotAWeakerHash(): void
    {
        $store = new JsonStore($this->directory);
        $store->setState('k', ['checksCount' => 1]);

        $expected = hash('sha256', 'k');
        self::assertFileExists($this->directory.'/'.$expected.'.state.json');
    }
}
