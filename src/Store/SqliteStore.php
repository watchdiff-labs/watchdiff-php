<?php

declare(strict_types=1);

namespace WatchDiff\Core\Store;

use WatchDiff\Core\Contract\StoreInterface;
use WatchDiff\Core\Model\DiffReport;
use WatchDiff\Core\Model\Snapshot;

final class SqliteStore implements StoreInterface
{
    private \PDO $pdo;

    public function __construct(string $path = '.watchdiff.db')
    {
        $this->pdo = new \PDO('sqlite:'.$path);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->migrate();
    }

    private function migrate(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS snapshots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            watcher_key TEXT NOT NULL,
            data TEXT NOT NULL,
            captured_at TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_snapshots_key ON snapshots (watcher_key)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            watcher_key TEXT NOT NULL,
            data TEXT NOT NULL,
            compared_at TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_key ON reports (watcher_key)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS alerts (
            watcher_key TEXT PRIMARY KEY,
            last_alert_at TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS watcher_state (
            watcher_key TEXT PRIMARY KEY,
            data TEXT NOT NULL
        )');
    }

    public function saveSnapshot(string $key, Snapshot $snapshot, ?int $maxSnapshots = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO snapshots (watcher_key, data, captured_at) VALUES (:key, :data, :captured_at)');
        $stmt->execute([
            'key'         => $key,
            'data'        => json_encode($snapshot->toArray()),
            'captured_at' => $snapshot->capturedAt->format(DATE_ATOM),
        ]);

        if (null !== $maxSnapshots) {
            $this->pdo->prepare(
                'DELETE FROM snapshots WHERE watcher_key = :key AND id NOT IN (
                    SELECT id FROM snapshots WHERE watcher_key = :key ORDER BY id DESC LIMIT :limit
                )'
            )->execute(['key' => $key, 'limit' => $maxSnapshots]);
        }
    }

    public function latestSnapshot(string $key): ?Snapshot
    {
        $stmt = $this->pdo->prepare('SELECT data FROM snapshots WHERE watcher_key = :key ORDER BY id DESC LIMIT 1');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetchColumn();

        return false === $row ? null : Snapshot::fromArray((array) json_decode((string) $row, true));
    }

    public function snapshots(string $key, ?int $limit = null): array
    {
        $sql = 'SELECT data FROM snapshots WHERE watcher_key = :key ORDER BY id ASC';
        if (null !== $limit) {
            $sql = 'SELECT data FROM (SELECT * FROM snapshots WHERE watcher_key = :key ORDER BY id DESC LIMIT '.(int) $limit.') ORDER BY id ASC';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['key' => $key]);

        /** @var string[] $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return array_map(
            static fn (string $row) => Snapshot::fromArray((array) json_decode($row, true)),
            $rows
        );
    }

    public function saveReport(string $key, DiffReport $report): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO reports (watcher_key, data, compared_at) VALUES (:key, :data, :compared_at)');
        $stmt->execute([
            'key'         => $key,
            'data'        => json_encode($report->toArray()),
            'compared_at' => $report->comparedAt->format(DATE_ATOM),
        ]);
    }

    public function reports(string $key, ?int $limit = null): array
    {
        $sql = 'SELECT data FROM reports WHERE watcher_key = :key ORDER BY id ASC';
        if (null !== $limit) {
            $sql = 'SELECT data FROM (SELECT * FROM reports WHERE watcher_key = :key ORDER BY id DESC LIMIT '.(int) $limit.') ORDER BY id ASC';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['key' => $key]);

        /** @var string[] $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return array_map(
            static fn (string $row) => DiffReport::fromArray((array) json_decode($row, true)),
            $rows
        );
    }

    public function lastAlertAt(string $key): ?\DateTimeImmutable
    {
        $stmt = $this->pdo->prepare('SELECT last_alert_at FROM alerts WHERE watcher_key = :key');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetchColumn();

        return false === $row ? null : new \DateTimeImmutable((string) $row);
    }

    public function setLastAlertAt(string $key, \DateTimeImmutable $at): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO alerts (watcher_key, last_alert_at) VALUES (:key, :at)
             ON CONFLICT(watcher_key) DO UPDATE SET last_alert_at = :at'
        );
        $stmt->execute(['key' => $key, 'at' => $at->format(DATE_ATOM)]);
    }

    public function pruneSnapshotsOlderThan(string $key, \DateTimeImmutable $cutoff): void
    {
        $this->pdo->prepare('DELETE FROM snapshots WHERE watcher_key = :key AND captured_at < :cutoff')
            ->execute(['key' => $key, 'cutoff' => $cutoff->format(DATE_ATOM)]);
    }

    public function clear(string $key): void
    {
        $this->pdo->prepare('DELETE FROM snapshots WHERE watcher_key = :key')->execute(['key' => $key]);
        $this->pdo->prepare('DELETE FROM reports WHERE watcher_key = :key')->execute(['key' => $key]);
        $this->pdo->prepare('DELETE FROM alerts WHERE watcher_key = :key')->execute(['key' => $key]);
        $this->pdo->prepare('DELETE FROM watcher_state WHERE watcher_key = :key')->execute(['key' => $key]);
    }

    /** @return array<string, mixed> */
    public function getState(string $key): array
    {
        $stmt = $this->pdo->prepare('SELECT data FROM watcher_state WHERE watcher_key = :key');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetchColumn();

        return false === $row ? [] : (array) json_decode((string) $row, true);
    }

    /** @param array<string, mixed> $state */
    public function setState(string $key, array $state): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO watcher_state (watcher_key, data) VALUES (:key, :data)
             ON CONFLICT(watcher_key) DO UPDATE SET data = :data'
        );
        $stmt->execute(['key' => $key, 'data' => json_encode($state)]);
    }

    /** @return string[] */
    public function keys(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DISTINCT watcher_key FROM snapshots
             UNION SELECT DISTINCT watcher_key FROM watcher_state'
        );

        /** @var string[] $keys */
        $keys = false === $stmt ? [] : $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $keys;
    }
}
