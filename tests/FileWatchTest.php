<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;

final class FileWatchTest extends TestCase
{
    private string $storageDir;
    private string $filePath;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/watchdiff-test-'.bin2hex(random_bytes(8));
        $this->filePath   = sys_get_temp_dir().'/watchdiff-file-'.bin2hex(random_bytes(8)).'.conf';
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        @rmdir($this->storageDir);
        @unlink($this->filePath);
    }

    public function testDetectsLocalFileChange(): void
    {
        file_put_contents($this->filePath, "listen 80;\n");

        $watchDiff = new WatchDiff(new JsonStore($this->storageDir), new Client(), new Psr17Factory());
        $config    = new WatchConfig(localPath: $this->filePath);
        $watchDiff->watch($config);

        self::assertNull($watchDiff->checkOnce($config->key()));

        file_put_contents($this->filePath, "listen 443;\n");
        $report = $watchDiff->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertTrue($report->hasChanges());
    }
}
