<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;

final class WatchDiffTest extends TestCase
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

    public function testFirstCheckOnlySavesSnapshot(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', target: '.price');

        $watchDiff->watch($config);
        $this->respondWith('<html><body><span class="price">$19</span></body></html>');

        self::assertNull($watchDiff->checkOnce($config->key()));
    }

    public function testSecondCheckDetectsPriceChange(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', target: '.price');
        $watchDiff->watch($config);

        $this->respondWith('<html><body><span class="price">$19</span></body></html>');
        $watchDiff->checkOnce($config->key());

        $this->respondWith('<html><body><span class="price">$24</span></body></html>');
        $report = $watchDiff->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertTrue($report->hasChanges());
        self::assertSame('$19', $report->changes[0]->before);
        self::assertSame('$24', $report->changes[0]->after);
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
