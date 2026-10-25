<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests\Support;

use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Db\DbChange;
use WatchDiff\Core\Db\DbChangeKind;
use WatchDiff\Core\Db\DbDiffMode;
use WatchDiff\Core\Db\DbDiffReport;
use WatchDiff\Core\Db\DbSnapshot;
use WatchDiff\Core\Enum\ChangeKind;
use WatchDiff\Core\Enum\DiffMode;
use WatchDiff\Core\Model\Change;
use WatchDiff\Core\Model\DiffReport;
use WatchDiff\Core\Model\Snapshot;
use WatchDiff\Core\Support\Human;

final class HumanTest extends TestCase
{
    public function testChangeHumanFormatsBeforeAndAfter(): void
    {
        $change = new Change(ChangeKind::Modified, before: 'old', after: 'new');

        self::assertSame('[modified] old -> new', Human::changeHuman($change));
    }

    public function testReportSummaryListsLabelAndEachChange(): void
    {
        $report = new DiffReport(
            url: 'https://example.test',
            label: 'example',
            before: new Snapshot('k', 'a', new \DateTimeImmutable()),
            after: new Snapshot('k', 'b', new \DateTimeImmutable()),
            changes: [new Change(ChangeKind::Modified, before: 'a', after: 'b')],
            diffMode: DiffMode::Line,
            comparedAt: new \DateTimeImmutable(),
        );

        $summary = Human::reportSummary($report);

        self::assertStringContainsString('example: 1 change(s) detected', $summary);
        self::assertStringContainsString('[modified] a -> b', $summary);
    }

    public function testDbChangeHumanFormatsEachKind(): void
    {
        self::assertStringContainsString('[inserted]', Human::dbChangeHuman(new DbChange(DbChangeKind::Inserted, row: ['id' => 1])));
        self::assertStringContainsString('[threshold_exceeded]', Human::dbChangeHuman(new DbChange(DbChangeKind::ThresholdExceeded, modifications: [['column' => 'value', 'before' => 1, 'after' => 2]])));
        self::assertStringContainsString('[value_changed]', Human::dbChangeHuman(new DbChange(DbChangeKind::ValueChanged, modifications: [['column' => 'value', 'before' => 1, 'after' => 2]])));
    }

    public function testDbReportSummaryListsLabelAndEachChange(): void
    {
        $report = new DbDiffReport(
            connectionString: 'sqlite://./app.db',
            table: 'products',
            label: 'products',
            before: new DbSnapshot([], [], new \DateTimeImmutable()),
            after: new DbSnapshot([], [], new \DateTimeImmutable()),
            changes: [new DbChange(DbChangeKind::Inserted, row: ['id' => 1])],
            diffMode: DbDiffMode::Row,
            comparedAt: new \DateTimeImmutable(),
        );

        $summary = Human::dbReportSummary($report);

        self::assertStringContainsString('products: 1 change(s) detected', $summary);
        self::assertStringContainsString('[inserted]', $summary);
    }
}
