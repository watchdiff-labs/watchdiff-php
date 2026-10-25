<?php

declare(strict_types=1);

namespace WatchDiff\Core\Contract;

use WatchDiff\Core\Model\DiffReport;
use WatchDiff\Core\Model\Snapshot;

interface StoreInterface
{
    public function saveSnapshot(string $key, Snapshot $snapshot, ?int $maxSnapshots = null): void;

    public function latestSnapshot(string $key): ?Snapshot;

    /** @return Snapshot[] */
    public function snapshots(string $key, ?int $limit = null): array;

    public function saveReport(string $key, DiffReport $report): void;

    /** @return DiffReport[] */
    public function reports(string $key, ?int $limit = null): array;

    public function lastAlertAt(string $key): ?\DateTimeImmutable;

    public function setLastAlertAt(string $key, \DateTimeImmutable $at): void;

    public function clear(string $key): void;

    /** Deletes only snapshots older than $cutoff - reports and state are left untouched. */
    public function pruneSnapshotsOlderThan(string $key, \DateTimeImmutable $cutoff): void;

    /**
     * Arbitrary per-watcher state bag (pause flag, counters, failure streaks,
     * pending-confirmation data, spike-detection timestamps, ...).
     *
     * @return array<string, mixed>
     */
    public function getState(string $key): array;

    /** @param array<string, mixed> $state */
    public function setState(string $key, array $state): void;

    /** @return string[] every watcher key that currently has stored state or snapshots */
    public function keys(): array;
}
