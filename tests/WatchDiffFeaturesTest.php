<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests;

use Http\Client\Exception\TransferException;
use Http\Message\RequestMatcher\CallbackRequestMatcher;
use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use WatchDiff\Core\Config\BasicAuth;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\Enum\DiffMode;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;

final class WatchDiffFeaturesTest extends TestCase
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

    public function testPausedWatcherIsSkippedByTick(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', id: 'w1');
        $watchDiff->watch($config);

        $this->respondWith('first');
        $watchDiff->checkOnce($config->key());

        $watchDiff->pause('w1');

        $this->respondWith('second');
        $reports = $watchDiff->tick(new \DateTimeImmutable('+1 hour'));

        self::assertSame([], $reports);

        $watchDiff->resume('w1');
        $reports = $watchDiff->tick(new \DateTimeImmutable('+1 hour'));
        self::assertCount(1, $reports);
    }

    public function testAlertOnFirstCheckFiresImmediately(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', alertOnFirstCheck: true);
        $watchDiff->watch($config);

        $this->respondWith('hello world');
        $report = $watchDiff->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertTrue($report->hasChanges());
    }

    public function testConfirmAfterDelaysAlertUntilSecondObservation(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', confirmAfter: 60);
        $watchDiff->watch($config);

        $this->respondWith('v1');
        $watchDiff->checkOnce($config->key());

        $this->respondWith('v2');
        $firstChangeReport = $watchDiff->checkOnce($config->key());
        self::assertNotNull($firstChangeReport);
        self::assertTrue($firstChangeReport->hasChanges());

        $store = new JsonStore($this->storageDir);
        self::assertNull($store->lastAlertAt($config->key()), 'the first observation must not trigger an alert (no webhook/cooldown timestamp set)');
    }

    public function testJsonPathExtractsNestedField(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://api.test/price', diffMode: DiffMode::Word, jsonPath: 'data.price');
        $watchDiff->watch($config);

        $this->respondWith('{"data":{"price":"19.99"}}');
        $watchDiff->checkOnce($config->key());

        $this->respondWith('{"data":{"price":"24.99"}}');
        $report = $watchDiff->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertTrue($report->hasChanges());
        self::assertSame('19.99', $report->changes[0]->before);
        self::assertSame('24.99', $report->changes[0]->after);
    }

    public function testBasicAuthHeaderIsSentOnEveryRequest(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/private', auth: new BasicAuth('alice', 'secret'));
        $watchDiff->watch($config);

        $this->respondWith('secret page');
        $watchDiff->checkOnce($config->key());

        $request = $this->httpClient->getLastRequest();
        self::assertSame('Basic '.base64_encode('alice:secret'), $request->getHeaderLine('Authorization'));
    }

    public function testRequestBodySwitchesToPost(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://api.test/query', requestBody: ['q' => 'SELECT 1']);
        $watchDiff->watch($config);

        $this->respondWith('{}');
        $watchDiff->checkOnce($config->key());

        $request = $this->httpClient->getLastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('{"q":"SELECT 1"}', (string) $request->getBody());
    }

    public function testStatusTracksCounts(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', id: 'w1');
        $watchDiff->watch($config);

        $this->respondWith('v1');
        $watchDiff->checkOnce($config->key());
        $this->respondWith('v2');
        $watchDiff->checkOnce($config->key());

        $statuses = $watchDiff->status();
        self::assertCount(1, $statuses);
        self::assertSame(2, $statuses[0]->checksCount);
        self::assertSame(1, $statuses[0]->changesCount);
        self::assertFalse($statuses[0]->paused);
    }

    public function testCooldownSuppressesOnChangeAsWellAsWebhooks(): void
    {
        $watchDiff     = $this->makeWatchDiff();
        $onChangeCalls = 0;
        $config        = new WatchConfig(
            url: 'https://example.test/page',
            cooldown: 3600,
            onChange: function () use (&$onChangeCalls) {
                ++$onChangeCalls;
            },
        );
        $watchDiff->watch($config);

        $this->respondWith('v1');
        $watchDiff->checkOnce($config->key());

        $this->respondWith('v2');
        $watchDiff->checkOnce($config->key());
        self::assertSame(1, $onChangeCalls, 'first change within the cooldown window should alert');

        $this->respondWith('v3');
        $watchDiff->checkOnce($config->key());
        self::assertSame(1, $onChangeCalls, 'a second change inside the same cooldown window must not fire onChange either');
    }

    public function testDryRunStillFiresOnChangeButNothingElse(): void
    {
        $watchDiff = $this->makeWatchDiff();

        // Seed a real baseline snapshot first - dryRun previews a diff against
        // an existing baseline, it doesn't create one (same as a normal first check).
        $seedConfig = new WatchConfig(url: 'https://example.test/page');
        $watchDiff->watch($seedConfig);
        $this->respondWith('v1');
        $watchDiff->checkOnce($seedConfig->key());

        $onChangeCalls = 0;
        $config        = new WatchConfig(
            url: 'https://example.test/page',
            dryRun: true,
            onChange: function () use (&$onChangeCalls) {
                ++$onChangeCalls;
            },
        );
        $watchDiff->watch($config);

        $this->respondWith('v2');
        $report = $watchDiff->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertTrue($report->hasChanges());
        self::assertSame(1, $onChangeCalls, 'dryRun is the documented exception: onChange still fires to preview the change');

        $store = new JsonStore($this->storageDir);
        self::assertCount(1, $store->snapshots($config->key()), 'dryRun must never persist a new snapshot on top of the seeded baseline');
    }

    public function testStaleConfirmationDoesNotFastTrackAnUnrelatedLaterChange(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', confirmAfter: 60);
        $watchDiff->watch($config);

        $this->respondWith('v1');
        $watchDiff->checkOnce($config->key());

        // First change: creates a pendingConfirmation, suppressed (tested elsewhere).
        $this->respondWith('v2');
        $watchDiff->checkOnce($config->key());

        // The page reverts: the pending confirmation must be cleared, not left dangling.
        $this->respondWith('v1');
        $watchDiff->checkOnce($config->key());

        // A brand new change arrives immediately after. Without the fix, the stale
        // pendingConfirmation (created far enough in the past for a fast unit test)
        // could let this alert through on its very first observation.
        $this->respondWith('v3');
        $watchDiff->checkOnce($config->key());

        $store = new JsonStore($this->storageDir);
        self::assertNull($store->lastAlertAt($config->key()), 'a brand new change must start its own confirmation window, not reuse a stale one');
    }

    public function testRetentionDaysOnlyPrunesOldSnapshotsNotReportsOrState(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', retentionDays: 30, id: 'w1');
        $watchDiff->watch($config);

        $this->respondWith('v1');
        $watchDiff->checkOnce($config->key());

        $this->respondWith('v2');
        $watchDiff->checkOnce($config->key());

        $store = new JsonStore($this->storageDir);
        self::assertNotSame([], $store->reports($config->key()), 'retentionDays must not wipe stored reports');
        self::assertNotSame([], $store->getState($config->key()), 'retentionDays must not wipe the watcher state (counters, pause flag, ...)');
        self::assertSame(2, $watchDiff->status()[0]->checksCount, 'checksCount must survive a retentionDays prune');
    }

    public function testExhaustedRetriesOn5xxRaiseAnErrorInsteadOfAFakeSuccess(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $caught    = null;
        $config    = new WatchConfig(
            url: 'https://example.test/flaky',
            retries: 1,
            retryDelayMs: 1,
            onError: function (\Throwable $e) use (&$caught) {
                $caught = $e;
            },
        );
        $watchDiff->watch($config);

        $this->httpClient->addResponse($this->psr17->createResponse(503));
        $this->httpClient->addResponse($this->psr17->createResponse(503));

        $report = $watchDiff->checkOnce($config->key());

        self::assertNull($report);
        self::assertNotNull($caught, 'onError must fire once retries are exhausted on a persistent 5xx');
        self::assertSame(1, $watchDiff->status()[0]->errorsCount);
    }

    public function testRejectsNonHttpSchemes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WatchConfig(url: 'file:///etc/passwd');
    }

    public function testCookieHeaderStripsCrLfToPreventHeaderInjection(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(
            url: 'https://example.test/page',
            cookies: ["session" => "abc\r\nX-Injected: evil"],
        );
        $watchDiff->watch($config);

        $this->respondWith('v1');
        $watchDiff->checkOnce($config->key());

        $request = $this->httpClient->getLastRequest();
        self::assertSame('session=abcX-Injected: evil', $request->getHeaderLine('Cookie'));
    }

    public function testIgnoreSelectorsStripsElementsBeforeDiffing(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', ignoreSelectors: ['.ad']);
        $watchDiff->watch($config);

        $this->respondWith('<html><body><p>price $19</p><div class="ad">ad A</div></body></html>');
        $watchDiff->checkOnce($config->key());

        $this->respondWith('<html><body><p>price $19</p><div class="ad">ad B</div></body></html>');
        $report = $watchDiff->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertFalse($report->hasChanges(), 'only the ignored .ad block changed, nothing else');
    }

    public function testIgnorePatternsStripsMatchingTextBeforeDiffing(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', ignorePatterns: ['/\d{2}:\d{2}:\d{2}/']);
        $watchDiff->watch($config);

        $this->respondWith('Last updated at 10:30:01');
        $watchDiff->checkOnce($config->key());

        $this->respondWith('Last updated at 10:30:59');
        $report = $watchDiff->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertFalse($report->hasChanges(), 'only the timestamp changed, and it matches ignorePatterns');
    }

    public function testMinChangesSuppressesReportsBelowTheThreshold(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://example.test/page', diffMode: DiffMode::Word, minChanges: 3);
        $watchDiff->watch($config);

        $this->respondWith('a b c d e');
        $watchDiff->checkOnce($config->key());

        $this->respondWith('x b c d e'); // only 1 word changed
        $report = $watchDiff->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertFalse($report->hasChanges(), 'fewer than minChanges (3) changes occurred');
    }

    public function testMaxWatchersRejectsRegisteringTooMany(): void
    {
        $watchDiff = new WatchDiff(
            new JsonStore($this->storageDir),
            $this->httpClient,
            $this->psr17,
            $this->psr17,
            maxWatchers: 1,
        );
        $watchDiff->watch(new WatchConfig(url: 'https://example.test/a'));

        $this->expectException(\OverflowException::class);
        $watchDiff->watch(new WatchConfig(url: 'https://example.test/b'));
    }

    public function testRefusesToFetchAPrivateIpAddressByDefault(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $caught    = null;
        $config    = new WatchConfig(
            url: 'https://127.0.0.1/internal',
            onError: function (\Throwable $e) use (&$caught) {
                $caught = $e;
            },
        );
        $watchDiff->watch($config);

        self::assertNull($watchDiff->checkOnce($config->key()));
        self::assertNotNull($caught);
        self::assertStringContainsString('private/reserved', $caught->getMessage());
    }

    public function testAllowPrivateUrlsPermitsFetchingAPrivateIpAddress(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(url: 'https://127.0.0.1/internal', allowPrivateUrls: true);
        $watchDiff->watch($config);

        $this->respondWith('ok');

        self::assertNull($watchDiff->checkOnce($config->key()));
    }

    public function testWebhookToAPrivateIpAddressIsRejectedByDefault(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $caught    = null;
        $config    = new WatchConfig(
            url: 'https://example.test/page',
            webhooks: ['http://127.0.0.1:9000/internal-hook'],
            onError: function (\Throwable $e) use (&$caught) {
                $caught = $e;
            },
        );
        $watchDiff->watch($config);

        $this->respondWith('first');
        $watchDiff->checkOnce($config->key());

        $this->respondWith('second');
        $watchDiff->checkOnce($config->key());

        self::assertNotNull($caught);
        self::assertStringContainsString('private/reserved', $caught->getMessage());
    }

    public function testFailingWebhookDoesNotCrashCheckOnceAndRoutesToOnError(): void
    {
        $caught = null;
        $this->httpClient->on(
            new CallbackRequestMatcher(
                static fn (RequestInterface $r) => str_contains((string) $r->getUri(), 'webhook'),
            ),
            new TransferException('webhook destination unreachable'),
        );

        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(
            url: 'https://example.test/page',
            webhooks: ['https://example.test/webhook'],
            onError: function (\Throwable $e) use (&$caught) {
                $caught = $e;
            },
        );
        $watchDiff->watch($config);

        $this->respondWith('first');
        $watchDiff->checkOnce($config->key());

        $this->respondWith('second');
        $report = $watchDiff->checkOnce($config->key());

        self::assertNotNull($report);
        self::assertTrue($report->hasChanges());
        self::assertNotNull($caught);
        self::assertStringContainsString('unreachable', $caught->getMessage());
    }

    public function testOnBeforeCheckExceptionIsCaughtAndRoutedToOnErrorInsteadOfCrashing(): void
    {
        $watchDiff = $this->makeWatchDiff();
        $caught    = null;
        $config    = new WatchConfig(
            url: 'https://example.test/page',
            onBeforeCheck: function () {
                throw new \RuntimeException('boom');
            },
            onError: function (\Throwable $e) use (&$caught) {
                $caught = $e;
            },
        );
        $watchDiff->watch($config);

        $report = $watchDiff->checkOnce($config->key());

        self::assertNull($report);
        self::assertNotNull($caught);
        self::assertSame('boom', $caught->getMessage());
    }

    public function testRespectRetryAfterUsesTheHeaderInsteadOfTheConfiguredBackoff(): void
    {
        $this->httpClient->addResponse(
            $this->psr17->createResponse(429)->withHeader('Retry-After', '0'),
        );
        $this->respondWith('ok');

        $watchDiff = $this->makeWatchDiff();
        $config    = new WatchConfig(
            url: 'https://example.test/page',
            retries: 1,
            retryDelayMs: 5000,
            failurePolicy: new \WatchDiff\Core\Config\FailurePolicy(respectRetryAfter: true),
        );
        $watchDiff->watch($config);

        $started = microtime(true);
        $watchDiff->checkOnce($config->key());
        $elapsedMs = (microtime(true) - $started) * 1000;

        self::assertLessThan(2000, $elapsedMs, 'Retry-After: 0 should be honored instead of the 5000ms configured backoff');
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
