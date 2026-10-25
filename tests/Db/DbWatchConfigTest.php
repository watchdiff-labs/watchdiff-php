<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests\Db;

use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Db\DbWatchConfig;

final class DbWatchConfigTest extends TestCase
{
    public function testDefaultKeyNeverEmbedsAPlaintextPassword(): void
    {
        $config = new DbWatchConfig(
            connectionString: 'postgresql://admin:super-secret-password@db.internal:5432/mydb',
            table: 'products',
        );

        self::assertStringNotContainsString('super-secret-password', $config->key());
        self::assertStringNotContainsString('admin', $config->key());
        self::assertStringContainsString('db.internal', $config->key());
    }

    public function testExplicitIdIsUsedAsIsAndNeverParsed(): void
    {
        $config = new DbWatchConfig(
            connectionString: 'postgresql://admin:secret@db.internal:5432/mydb',
            table: 'products',
            id: 'my-watcher',
        );

        self::assertSame('my-watcher', $config->key());
    }
}
