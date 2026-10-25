<?php

declare(strict_types=1);

namespace WatchDiff\Core\Support;

use WatchDiff\Core\Db\DbChange;
use WatchDiff\Core\Db\DbChangeKind;
use WatchDiff\Core\Db\DbDiffReport;
use WatchDiff\Core\Model\Change;
use WatchDiff\Core\Model\DiffReport;

/**
 * Human-readable formatting helpers, mirroring the npm/Python ports'
 * reportSummary()/changeHuman(). This is the same one-line-per-change text
 * WebhookSender and DbWatcher already build internally for webhook
 * payloads, exposed here as public, reusable functions instead of being
 * duplicated across callers (CLI output, custom notification code, ...).
 */
final class Human
{
    public static function changeHuman(Change $change): string
    {
        return sprintf('[%s] %s -> %s', $change->kind->value, $change->before ?? '-', $change->after ?? '-');
    }

    public static function reportSummary(DiffReport $report): string
    {
        $lines = [sprintf('%s: %d change(s) detected', $report->label, \count($report->changes))];

        foreach ($report->changes as $change) {
            $lines[] = self::changeHuman($change);
        }

        return implode("\n", $lines);
    }

    public static function dbChangeHuman(DbChange $change): string
    {
        return match ($change->kind) {
            DbChangeKind::Inserted          => sprintf('[inserted] %s', json_encode($change->row)),
            DbChangeKind::Deleted           => sprintf('[deleted] %s', json_encode($change->row)),
            DbChangeKind::Updated           => sprintf('[updated] key=%s %s', $change->rowKey ?? '-', json_encode($change->modifications)),
            DbChangeKind::SchemaChanged     => sprintf('[schema] %s: %s', $change->column, $change->context),
            DbChangeKind::ThresholdExceeded => sprintf('[threshold_exceeded] %s', json_encode($change->modifications)),
            DbChangeKind::ValueChanged      => sprintf('[value_changed] %s', json_encode($change->modifications)),
        };
    }

    public static function dbReportSummary(DbDiffReport $report): string
    {
        $lines = [sprintf('%s: %d change(s) detected', $report->label, \count($report->changes))];

        foreach ($report->changes as $change) {
            $lines[] = self::dbChangeHuman($change);
        }

        return implode("\n", $lines);
    }
}
