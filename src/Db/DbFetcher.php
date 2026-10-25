<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

final class DbFetcher
{
    /** @var array<string, \PDO> */
    private array $connections = [];

    public function fetch(DbWatchConfig $config): DbSnapshot
    {
        $pdo = $this->connect($config->connectionString);

        $query     = null !== $config->query
            ? $this->validateSelectOnly($config->query)
            : sprintf('SELECT * FROM %s', $this->quoteIdentifier($config->table, $config->connectionString));
        $statement = $pdo->query($query);
        $rows      = false === $statement ? [] : $statement->fetchAll(\PDO::FETCH_ASSOC);

        $columns = DbDiffMode::Schema === $config->diffMode
            ? $this->fetchColumns($pdo, $config->connectionString, $config->table, $config->primaryKey)
            : [];

        return new DbSnapshot($rows, $columns, new \DateTimeImmutable());
    }

    private function connect(string $connectionString): \PDO
    {
        if (isset($this->connections[$connectionString])) {
            return $this->connections[$connectionString];
        }

        $dsn         = $this->toDsn($connectionString);
        $credentials = $this->credentials($connectionString);

        $pdo = new \PDO($dsn, $credentials['user'], $credentials['pass']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $this->connections[$connectionString] = $pdo;
    }

    private function toDsn(string $connectionString): string
    {
        if (str_starts_with($connectionString, 'sqlite://')) {
            return 'sqlite:'.substr($connectionString, \strlen('sqlite://'));
        }

        $parts  = (array) parse_url($connectionString);
        $scheme = (string) ($parts['scheme'] ?? '');
        $driver = match ($scheme) {
            'postgresql', 'postgres', 'pgsql' => 'pgsql',
            'mysql'                           => 'mysql',
            default                           => throw new \InvalidArgumentException(sprintf('Unsupported connection string scheme "%s".', $scheme)),
        };

        $host   = (string) ($parts['host'] ?? 'localhost');
        $port   = isset($parts['port']) ? (string) $parts['port'] : ('pgsql' === $driver ? '5432' : '3306');
        $dbname = ltrim((string) ($parts['path'] ?? ''), '/');

        return sprintf('%s:host=%s;port=%s;dbname=%s', $driver, $host, $port, $dbname);
    }

    /** @return array{user: string|null, pass: string|null} */
    private function credentials(string $connectionString): array
    {
        $parts = (array) parse_url($connectionString);

        return [
            'user' => isset($parts['user']) ? (string) $parts['user'] : null,
            'pass' => isset($parts['pass']) ? (string) $parts['pass'] : null,
        ];
    }

    /**
     * @param string[] $primaryKey
     *
     * @return array<int, array{name: string, type: string, nullable: bool, primaryKey: bool, defaultValue: mixed}>
     */
    private function fetchColumns(\PDO $pdo, string $connectionString, string $table, array $primaryKey): array
    {
        if (str_starts_with($connectionString, 'sqlite://')) {
            $stmt = $pdo->query(sprintf('PRAGMA table_info(%s)', $this->quoteIdentifier($table, $connectionString)));
            $rows = false === $stmt ? [] : $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(static fn (array $r) => [
                'name'         => (string) $r['name'],
                'type'         => (string) $r['type'],
                'nullable'     => '0' === (string) $r['notnull'],
                // SQLite's own "pk" flag is authoritative here, unlike the
                // other drivers below where primaryKey is only known from
                // the watcher's own configured $primaryKey.
                'primaryKey'   => '0' !== (string) $r['pk'],
                'defaultValue' => $r['dflt_value'],
            ], $rows);
        }

        $stmt = $pdo->prepare(
            'SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = :table'
        );
        $stmt->execute(['table' => $table]);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $r) => [
            'name'         => (string) $r['column_name'],
            'type'         => (string) $r['data_type'],
            'nullable'     => 'YES' === (string) $r['is_nullable'],
            'primaryKey'   => \in_array((string) $r['column_name'], $primaryKey, true),
            'defaultValue' => $r['column_default'],
        ], $rows);
    }

    /**
     * Table names can't be bound as PDO parameters, and the only way to use
     * one safely in a query built by this library (as opposed to a
     * user-supplied `query` string, which is an intentional raw-SQL escape
     * hatch) is to validate its character set and quote it. Rejects
     * anything that isn't a plain SQL identifier (letters, digits,
     * underscore, starting with a letter or underscore) - this is what
     * stops `products; DROP TABLE users; --` from reaching the database.
     */
    private function quoteIdentifier(string $identifier, string $connectionString): string
    {
        if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid table name "%s": only letters, digits, and underscores are allowed, starting with a letter or underscore.',
                $identifier,
            ));
        }

        if (str_starts_with($connectionString, 'mysql://')) {
            return '`'.$identifier.'`';
        }

        return '"'.$identifier.'"';
    }

    /**
     * `query` is an intentional raw-SQL escape hatch, but this watcher only
     * ever reads data - it should never be able to run anything other than
     * a single SELECT, even if the config comes from a source you trust
     * less than your own code (a JSON config file, a CLI argument, ...).
     */
    private function validateSelectOnly(string $query): string
    {
        $body = rtrim(trim($query), "; \t\n\r\0\x0B");

        if (1 !== preg_match('/^SELECT\b/i', $body)) {
            throw new \InvalidArgumentException('DbWatchConfig query must be a SELECT statement.');
        }

        if (str_contains($body, ';')) {
            throw new \InvalidArgumentException('DbWatchConfig query must be a single statement (no semicolons).');
        }

        return $query;
    }
}
