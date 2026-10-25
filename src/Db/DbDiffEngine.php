<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

final class DbDiffEngine
{
    /** @return DbChange[] */
    public function diff(DbSnapshot $before, DbSnapshot $after, DbWatchConfig $config): array
    {
        return match ($config->diffMode) {
            DbDiffMode::Row                          => $this->diffRows($before, $after, $config),
            DbDiffMode::Schema                       => $this->diffSchema($before, $after),
            DbDiffMode::Aggregate, DbDiffMode::Value => $this->diffScalar($before, $after, $config),
        };
    }

    /** @return DbChange[] */
    private function diffRows(DbSnapshot $before, DbSnapshot $after, DbWatchConfig $config): array
    {
        $primaryKey = $config->primaryKey;
        $ignore     = $config->ignoreColumns;

        $beforeIndex = $this->indexRows($before->rows, $primaryKey);
        $afterIndex  = $this->indexRows($after->rows, $primaryKey);

        $changes = [];

        foreach ($afterIndex as $rowKey => $row) {
            if (!isset($beforeIndex[$rowKey])) {
                if ($config->alertOnInsert) {
                    $changes[] = new DbChange(DbChangeKind::Inserted, row: $row, rowKey: [] !== $primaryKey ? $rowKey : null);
                }

                continue;
            }

            if ([] === $primaryKey) {
                continue;
            }

            $modifications = $this->diffColumns($beforeIndex[$rowKey], $row, $ignore);
            if ([] !== $modifications && $config->alertOnUpdate) {
                $changes[] = new DbChange(DbChangeKind::Updated, rowKey: $rowKey, modifications: $modifications);
            }
        }

        if ($config->alertOnDelete) {
            foreach ($beforeIndex as $rowKey => $row) {
                if (!isset($afterIndex[$rowKey])) {
                    $changes[] = new DbChange(DbChangeKind::Deleted, row: $row, rowKey: [] !== $primaryKey ? $rowKey : null);
                }
            }
        }

        return $changes;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param string[]                          $primaryKey
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexRows(array $rows, array $primaryKey): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $key = [] === $primaryKey
                ? (string) json_encode($row)
                : implode('|', array_map(static fn (string $k) => (string) ($row[$k] ?? ''), $primaryKey));

            $indexed[$key] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param string[]              $ignore
     *
     * @return array<int, array{column: string, before: mixed, after: mixed}>
     */
    private function diffColumns(array $before, array $after, array $ignore): array
    {
        $modifications = [];

        foreach ($after as $column => $value) {
            if (\in_array($column, $ignore, true)) {
                continue;
            }

            if (!array_key_exists($column, $before) || $before[$column] !== $value) {
                $modifications[] = ['column' => $column, 'before' => $before[$column] ?? null, 'after' => $value];
            }
        }

        return $modifications;
    }

    /** @return DbChange[] */
    private function diffSchema(DbSnapshot $before, DbSnapshot $after): array
    {
        $beforeColumns = [];
        foreach ($before->columns as $col) {
            $beforeColumns[$col['name']] = $col;
        }

        $changes = [];

        foreach ($after->columns as $col) {
            if (!isset($beforeColumns[$col['name']])) {
                $changes[] = new DbChange(DbChangeKind::SchemaChanged, column: $col['name'], context: 'column added');
                continue;
            }

            if ($beforeColumns[$col['name']]['type'] !== $col['type']) {
                $changes[] = new DbChange(DbChangeKind::SchemaChanged, column: $col['name'], context: 'type changed');
            }

            if ($beforeColumns[$col['name']]['primaryKey'] !== $col['primaryKey']) {
                $changes[] = new DbChange(DbChangeKind::SchemaChanged, column: $col['name'], context: 'primary key changed');
            }

            unset($beforeColumns[$col['name']]);
        }

        foreach ($beforeColumns as $name => $col) {
            $changes[] = new DbChange(DbChangeKind::SchemaChanged, column: $name, context: 'column removed');
        }

        return $changes;
    }

    /** @return DbChange[] */
    private function diffScalar(DbSnapshot $before, DbSnapshot $after, DbWatchConfig $config): array
    {
        $beforeValue = $this->firstScalar($before);
        $afterValue  = $this->firstScalar($after);

        if ($beforeValue === $afterValue) {
            return [];
        }

        if (DbDiffMode::Aggregate === $config->diffMode && \is_numeric($beforeValue) && \is_numeric($afterValue)) {
            $changePercent = 0.0 == (float) $beforeValue
                ? 100.0
                : abs(((float) $afterValue - (float) $beforeValue) / (float) $beforeValue) * 100;

            if ($changePercent < $config->threshold) {
                return [];
            }
        }

        $kind = DbDiffMode::Value === $config->diffMode ? DbChangeKind::ValueChanged : DbChangeKind::ThresholdExceeded;

        return [new DbChange($kind, modifications: [[
            'column' => 'value',
            'before' => $beforeValue,
            'after'  => $afterValue,
        ]])];
    }

    private function firstScalar(DbSnapshot $snapshot): mixed
    {
        $row = $snapshot->rows[0] ?? null;
        if (null === $row) {
            return null;
        }

        return array_values($row)[0] ?? null;
    }
}
