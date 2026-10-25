<?php

declare(strict_types=1);

namespace WatchDiff\Core\Store;

use WatchDiff\Core\Contract\StoreInterface;
use WatchDiff\Core\Model\DiffReport;
use WatchDiff\Core\Model\Snapshot;

final class JsonStore implements StoreInterface
{
    public function __construct(private readonly string $directory = '.watchdiff')
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(sprintf('Unable to create storage directory "%s".', $this->directory));
        }
    }

    public function saveSnapshot(string $key, Snapshot $snapshot, ?int $maxSnapshots = null): void
    {
        $this->registerKey($key);
        $snapshots   = $this->snapshots($key);
        $snapshots[] = $snapshot;

        if (null !== $maxSnapshots && \count($snapshots) > $maxSnapshots) {
            $snapshots = array_slice($snapshots, -$maxSnapshots);
        }

        $this->writeJson($this->snapshotsPath($key), array_map(static fn (Snapshot $s) => $s->toArray(), $snapshots));
    }

    public function latestSnapshot(string $key): ?Snapshot
    {
        $snapshots = $this->snapshots($key);

        return $snapshots === [] ? null : $snapshots[\count($snapshots) - 1];
    }

    /** @return Snapshot[] */
    public function snapshots(string $key, ?int $limit = null): array
    {
        /** @var array<array<string, mixed>> $data */
        $data      = $this->readJson($this->snapshotsPath($key)) ?? [];
        $snapshots = array_map(static fn (array $s) => Snapshot::fromArray($s), $data);

        return null === $limit ? $snapshots : array_slice($snapshots, -$limit);
    }

    public function saveReport(string $key, DiffReport $report): void
    {
        $reports   = $this->readJson($this->reportsPath($key)) ?? [];
        $reports[] = $report->toArray();
        $this->writeJson($this->reportsPath($key), $reports);
    }

    /** @return DiffReport[] */
    public function reports(string $key, ?int $limit = null): array
    {
        /** @var array<array<string, mixed>> $data */
        $data    = $this->readJson($this->reportsPath($key)) ?? [];
        $reports = array_map(static fn (array $r) => DiffReport::fromArray($r), $data);

        return null === $limit ? $reports : array_slice($reports, -$limit);
    }

    public function lastAlertAt(string $key): ?\DateTimeImmutable
    {
        $data = $this->readJson($this->alertsPath());

        return isset($data[$key]) ? new \DateTimeImmutable((string) $data[$key]) : null;
    }

    public function setLastAlertAt(string $key, \DateTimeImmutable $at): void
    {
        $data       = $this->readJson($this->alertsPath()) ?? [];
        $data[$key] = $at->format(DATE_ATOM);
        $this->writeJson($this->alertsPath(), $data);
    }

    public function pruneSnapshotsOlderThan(string $key, \DateTimeImmutable $cutoff): void
    {
        $remaining = array_filter($this->snapshots($key), static fn (Snapshot $s) => $s->capturedAt >= $cutoff);

        $this->writeJson(
            $this->snapshotsPath($key),
            array_map(static fn (Snapshot $s) => $s->toArray(), array_values($remaining)),
        );
    }

    public function clear(string $key): void
    {
        @unlink($this->snapshotsPath($key));
        @unlink($this->reportsPath($key));
        @unlink($this->statePath($key));

        $keys = $this->readJson($this->keysPath()) ?? [];
        unset($keys[$this->hash($key)]);
        $this->writeJson($this->keysPath(), $keys);
    }

    /** @return array<string, mixed> */
    public function getState(string $key): array
    {
        /** @var array<string, mixed> $state */
        $state = $this->readJson($this->statePath($key)) ?? [];

        return $state;
    }

    /** @param array<string, mixed> $state */
    public function setState(string $key, array $state): void
    {
        $this->registerKey($key);
        $this->writeJson($this->statePath($key), $state);
    }

    /** @return string[] */
    public function keys(): array
    {
        $keys = $this->readJson($this->keysPath()) ?? [];

        return array_values(array_map('strval', $keys));
    }

    private function registerKey(string $key): void
    {
        $keys                    = $this->readJson($this->keysPath()) ?? [];
        $keys[$this->hash($key)] = $key;
        $this->writeJson($this->keysPath(), $keys);
    }

    private function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    private function snapshotsPath(string $key): string
    {
        return $this->directory.'/'.$this->hash($key).'.snapshots.json';
    }

    private function reportsPath(string $key): string
    {
        return $this->directory.'/'.$this->hash($key).'.reports.json';
    }

    private function statePath(string $key): string
    {
        return $this->directory.'/'.$this->hash($key).'.state.json';
    }

    private function alertsPath(): string
    {
        return $this->directory.'/alerts.json';
    }

    private function keysPath(): string
    {
        return $this->directory.'/keys.json';
    }

    /** @return array<mixed>|null */
    private function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if (false === $contents || '' === $contents) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /** @param array<mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
