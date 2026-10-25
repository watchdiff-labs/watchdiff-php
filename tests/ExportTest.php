<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;

final class ExportTest extends TestCase
{
    private string $storageDir;
    private Client $httpClient;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/watchdiff-test-'.bin2hex(random_bytes(8));
        $this->httpClient = new Client();
        $this->psr17      = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        @rmdir($this->storageDir);
    }

    public function testExportReportsCsvIncludesUrlAndLabel(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', label: 'My Page');
        $watchDiff->watch($config);

        $this->respondWith('first');
        $watchDiff->checkOnce($config->key());
        $this->respondWith('second');
        $watchDiff->checkOnce($config->key());

        $csv  = $watchDiff->exportReportsCsv($config->key());
        $rows = array_map(static fn (string $line) => str_getcsv($line, escape: '\\'), array_filter(explode("\n", trim($csv))));

        self::assertSame(['url', 'label', 'comparedAt', 'kind', 'before', 'after', 'context'], $rows[0]);
        self::assertSame('https://example.test/page', $rows[1][0]);
        self::assertSame('My Page', $rows[1][1]);
    }

    public function testExportSnapshotsCsvIncludesUrl(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page');
        $watchDiff->watch($config);

        $this->respondWith('first');
        $watchDiff->checkOnce($config->key());

        $csv  = $watchDiff->exportSnapshotsCsv($config->key());
        $rows = array_map(static fn (string $line) => str_getcsv($line, escape: '\\'), array_filter(explode("\n", trim($csv))));

        self::assertSame(['url', 'capturedAt', 'statusCode', 'responseTimeMs', 'content'], $rows[0]);
        self::assertSame('https://example.test/page', $rows[1][0]);
    }

    private function respondWith(string $body): void
    {
        $this->httpClient->addResponse(
            $this->psr17->createResponse(200)->withBody($this->psr17->createStream($body))
        );
    }

    private function makeWatchDiff(): WatchDiff
    {
        return new WatchDiff(
            new JsonStore($this->storageDir),
            $this->httpClient,
            $this->psr17,
            $this->psr17,
        );
    }
}
