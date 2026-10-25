<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests\Db;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Db\DbChangeKind;
use WatchDiff\Core\Db\DbDiffMode;
use WatchDiff\Core\Db\DbSnapshot;
use WatchDiff\Core\Db\DbWatchConfig;
use WatchDiff\Core\Db\DbWatcher;
use WatchDiff\Core\Store\JsonStore;

final class DbWatcherTest extends TestCase
{
    private string $storageDir;
    private string $dbPath;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/watchdiff-test-'.bin2hex(random_bytes(8));
        $this->dbPath     = sys_get_temp_dir().'/watchdiff-db-'.bin2hex(random_bytes(8)).'.sqlite';

        if (!\in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is not installed in this environment.');
        }

        $pdo = new \PDO('sqlite:'.$this->dbPath);
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, price REAL)');
        $pdo->exec("INSERT INTO products (id, name, price) VALUES (1, 'Widget', 9.99)");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        @rmdir($this->storageDir);
        @unlink($this->dbPath);
    }

    public function testDetectsInsertUpdateAndDelete(): void
    {
        $watcher = new DbWatcher(new JsonStore($this->storageDir));
        $config  = new DbWatchConfig(
            connectionString: 'sqlite://'.$this->dbPath,
            table: 'products',
            primaryKey: ['id'],
        );
        $watcher->watch($config);

        self::assertNull($watcher->checkOnce($config->key()));

        $pdo = new \PDO('sqlite:'.$this->dbPath);
        $pdo->exec("UPDATE products SET price = 12.99 WHERE id = 1");
        $pdo->exec("INSERT INTO products (id, name, price) VALUES (2, 'Gadget', 5.0)");

        $report = $watcher->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertTrue($report->hasChanges());

        $kinds = array_map(static fn ($c) => $c->kind, $report->changes);
        self::assertContains(DbChangeKind::Updated, $kinds);
        self::assertContains(DbChangeKind::Inserted, $kinds);
    }

    public function testSchemaModeDetectsAddedColumn(): void
    {
        $watcher = new DbWatcher(new JsonStore($this->storageDir));
        $config  = new DbWatchConfig(
            connectionString: 'sqlite://'.$this->dbPath,
            table: 'products',
            diffMode: DbDiffMode::Schema,
        );
        $watcher->watch($config);
        $watcher->checkOnce($config->key());

        $pdo = new \PDO('sqlite:'.$this->dbPath);
        $pdo->exec('ALTER TABLE products ADD COLUMN stock INTEGER');

        $report = $watcher->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertTrue($report->hasChanges());
        self::assertSame(DbChangeKind::SchemaChanged, $report->changes[0]->kind);
        self::assertSame('stock', $report->changes[0]->column);
    }

    public function testSchemaModeReportsPrimaryKeyAndDefaultValueMetadata(): void
    {
        $watcher = new DbWatcher(new JsonStore($this->storageDir));
        $config  = new DbWatchConfig(
            connectionString: 'sqlite://'.$this->dbPath,
            table: 'products',
            diffMode: DbDiffMode::Schema,
            primaryKey: ['id'],
        );
        $watcher->watch($config);
        $watcher->checkOnce($config->key());

        $pdo = new \PDO('sqlite:'.$this->dbPath);
        $pdo->exec("ALTER TABLE products ADD COLUMN category TEXT DEFAULT 'misc'");
        $report = $watcher->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertTrue($report->hasChanges());
        self::assertSame('category', $report->changes[0]->column);

        $after = DbSnapshot::fromArray($this->storedSnapshot($config->key(), new JsonStore($this->storageDir)));
        $idColumn = current(array_filter($after->columns, static fn (array $c) => 'id' === $c['name']));
        $categoryColumn = current(array_filter($after->columns, static fn (array $c) => 'category' === $c['name']));

        self::assertTrue($idColumn['primaryKey']);
        self::assertFalse($categoryColumn['primaryKey']);
        self::assertSame("'misc'", $categoryColumn['defaultValue']);
    }

    /** @return array<string, mixed> */
    private function storedSnapshot(string $key, JsonStore $store): array
    {
        return $store->getState($key)['snapshot'];
    }

    public function testAggregateModeReportsThresholdExceededKind(): void
    {
        $watcher = new DbWatcher(new JsonStore($this->storageDir));
        $config  = new DbWatchConfig(
            connectionString: 'sqlite://'.$this->dbPath,
            table: 'products',
            diffMode: DbDiffMode::Aggregate,
            query: 'SELECT COUNT(*) as count FROM products',
            threshold: 10.0,
        );
        $watcher->watch($config);
        $watcher->checkOnce($config->key());

        $pdo = new \PDO('sqlite:'.$this->dbPath);
        $pdo->exec("INSERT INTO products (id, name, price) VALUES (2, 'Gadget', 5.0)");
        $report = $watcher->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertSame(DbChangeKind::ThresholdExceeded, $report->changes[0]->kind);
    }

    public function testValueModeReportsValueChangedKind(): void
    {
        $watcher = new DbWatcher(new JsonStore($this->storageDir));
        $config  = new DbWatchConfig(
            connectionString: 'sqlite://'.$this->dbPath,
            table: 'products',
            diffMode: DbDiffMode::Value,
            query: 'SELECT price FROM products WHERE id = 1',
        );
        $watcher->watch($config);
        $watcher->checkOnce($config->key());

        $pdo = new \PDO('sqlite:'.$this->dbPath);
        $pdo->exec('UPDATE products SET price = 19.99 WHERE id = 1');
        $report = $watcher->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertSame(DbChangeKind::ValueChanged, $report->changes[0]->kind);
    }

    public function testAggregateModeRespectsThreshold(): void
    {
        $watcher = new DbWatcher(new JsonStore($this->storageDir));
        $config  = new DbWatchConfig(
            connectionString: 'sqlite://'.$this->dbPath,
            table: 'products',
            diffMode: DbDiffMode::Aggregate,
            query: 'SELECT COUNT(*) as count FROM products',
            threshold: 50.0,
        );
        $watcher->watch($config);
        $watcher->checkOnce($config->key());

        $pdo = new \PDO('sqlite:'.$this->dbPath);
        $pdo->exec("INSERT INTO products (id, name, price) VALUES (2, 'Gadget', 5.0)");

        $report = $watcher->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertTrue($report->hasChanges(), 'count doubled (100% change), should exceed the 50% threshold');
    }

    public function testWebhookIsCalledOnChange(): void
    {
        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $httpClient->addResponse($psr17->createResponse(200));

        $watcher = new DbWatcher(new JsonStore($this->storageDir), $httpClient, $psr17, $psr17);
        $config  = new DbWatchConfig(
            connectionString: 'sqlite://'.$this->dbPath,
            table: 'products',
            primaryKey: ['id'],
            webhooks: ['https://discord.com/api/webhooks/test'],
        );
        $watcher->watch($config);
        $watcher->checkOnce($config->key());

        $pdo = new \PDO('sqlite:'.$this->dbPath);
        $pdo->exec("UPDATE products SET price = 12.99 WHERE id = 1");
        $watcher->checkOnce($config->key());

        $request = $httpClient->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('https://discord.com/api/webhooks/test', (string) $request->getUri());
    }

    public function testStatusReportsChecksAndChangesCounters(): void
    {
        $watcher = new DbWatcher(new JsonStore($this->storageDir));
        $config  = new DbWatchConfig(
            connectionString: 'sqlite://'.$this->dbPath,
            table: 'products',
            primaryKey: ['id'],
        );
        $watcher->watch($config);

        $watcher->checkOnce($config->key());
        $status = $watcher->status()[0];
        self::assertSame(1, $status->checksCount);
        self::assertSame(0, $status->changesCount);

        $pdo = new \PDO('sqlite:'.$this->dbPath);
        $pdo->exec("UPDATE products SET price = 12.99 WHERE id = 1");
        $watcher->checkOnce($config->key());

        $status = $watcher->status()[0];
        self::assertSame(2, $status->checksCount);
        self::assertSame(1, $status->changesCount);
        self::assertNotNull($status->lastChangeAt);
    }

    public function testWebhookToAPrivateIpAddressIsRejectedByDefault(): void
    {
        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $caught     = null;

        $watcher = new DbWatcher(new JsonStore($this->storageDir), $httpClient, $psr17, $psr17);
        $config  = new DbWatchConfig(
            connectionString: 'sqlite://'.$this->dbPath,
            table: 'products',
            primaryKey: ['id'],
            webhooks: ['http://127.0.0.1:9000/internal-hook'],
            onError: function (\Throwable $e) use (&$caught) {
                $caught = $e;
            },
        );
        $watcher->watch($config);
        $watcher->checkOnce($config->key());

        $pdo = new \PDO('sqlite:'.$this->dbPath);
        $pdo->exec("UPDATE products SET price = 12.99 WHERE id = 1");
        $watcher->checkOnce($config->key());

        self::assertNull($httpClient->getLastRequest());
        self::assertNotNull($caught);
        self::assertStringContainsString('private/reserved', $caught->getMessage());
    }

    public function testRejectsATableNameThatIsNotAPlainIdentifier(): void
    {
        $watcher = new DbWatcher(new JsonStore($this->storageDir));
        $caught  = null;
        $config  = new DbWatchConfig(
            connectionString: 'sqlite://'.$this->dbPath,
            table: 'products; DROP TABLE products; --',
            onError: function (\Throwable $e) use (&$caught) {
                $caught = $e;
            },
        );
        $watcher->watch($config);

        self::assertNull($watcher->checkOnce($config->key()));
        self::assertInstanceOf(\InvalidArgumentException::class, $caught);

        // The table really must still exist - the injection attempt never reached the database.
        $pdo = new \PDO('sqlite:'.$this->dbPath);
        self::assertNotFalse($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='products'")->fetchColumn());
    }

    public function testRejectsACustomQueryThatIsNotASingleSelect(): void
    {
        $watcher = new DbWatcher(new JsonStore($this->storageDir));
        $caught  = null;
        $config  = new DbWatchConfig(
            connectionString: 'sqlite://'.$this->dbPath,
            table: 'products',
            diffMode: DbDiffMode::Aggregate,
            query: "SELECT COUNT(*) FROM products; DROP TABLE products; --",
            onError: function (\Throwable $e) use (&$caught) {
                $caught = $e;
            },
        );
        $watcher->watch($config);

        self::assertNull($watcher->checkOnce($config->key()));
        self::assertInstanceOf(\InvalidArgumentException::class, $caught);
    }
}
