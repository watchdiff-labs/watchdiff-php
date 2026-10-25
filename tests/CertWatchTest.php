<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;

final class CertWatchTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/watchdiff-test-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        @rmdir($this->storageDir);
    }

    public function testFirstCheckStoresCertificateSnapshot(): void
    {
        $socket = @stream_socket_client('ssl://example.com:443', $errno, $errstr, 3);
        if (false === $socket) {
            self::markTestSkipped('No network access to example.com:443 in this environment.');
        }
        fclose($socket);

        $watchDiff = new WatchDiff(new JsonStore($this->storageDir), new Client(), new Psr17Factory());
        $config    = new WatchConfig(certHost: 'example.com');
        $watchDiff->watch($config);

        self::assertNull($watchDiff->checkOnce($config->key()));

        $snapshot = $watchDiff->history($config->key())[0];
        $data     = json_decode($snapshot->content, true);
        self::assertArrayHasKey('fingerprint', $data);
        self::assertArrayHasKey('notAfter', $data);
    }
}
