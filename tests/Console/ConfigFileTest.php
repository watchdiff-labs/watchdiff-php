<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests\Console;

use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Console\ConfigFile;
use WatchDiff\Core\Enum\DiffMode;

final class ConfigFileTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/watchdiff-config-'.bin2hex(random_bytes(8)).'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testLoadsAUrlWatcherWithDefaults(): void
    {
        $this->write(['watchers' => [['url' => 'https://example.com']]]);

        $watchers = ConfigFile::load($this->path);

        self::assertCount(1, $watchers);
        self::assertSame('https://example.com', $watchers[0]->url);
        self::assertSame(DiffMode::Line, $watchers[0]->diffMode);
        self::assertSame(300, $watchers[0]->interval);
    }

    public function testLoadsAFileWatcherWithoutRequiringUrl(): void
    {
        $this->write(['watchers' => [['localPath' => '/etc/hosts']]]);

        $watchers = ConfigFile::load($this->path);

        self::assertCount(1, $watchers);
        self::assertTrue($watchers[0]->isFileWatch());
        self::assertSame('/etc/hosts', $watchers[0]->localPath);
    }

    public function testLoadsACertWatcherWithoutRequiringUrl(): void
    {
        $this->write(['watchers' => [['certHost' => 'example.com', 'warnDaysBeforeExpiry' => 14]]]);

        $watchers = ConfigFile::load($this->path);

        self::assertCount(1, $watchers);
        self::assertTrue($watchers[0]->isCertWatch());
        self::assertSame(14, $watchers[0]->warnDaysBeforeExpiry);
    }

    public function testThrowsWhenAWatcherHasNoTarget(): void
    {
        $this->write(['watchers' => [['label' => 'nothing to watch']]]);

        $this->expectException(\RuntimeException::class);
        ConfigFile::load($this->path);
    }

    public function testValidateAcceptsFileAndCertWatchersWithoutFlaggingThemAsInvalidUrls(): void
    {
        $this->write(['watchers' => [
            ['url' => 'https://example.com'],
            ['localPath' => '/etc/hosts'],
            ['certHost' => 'example.com'],
        ]]);

        self::assertSame([], ConfigFile::validate($this->path));
    }

    public function testValidateRejectsAUrlWithoutAnHttpScheme(): void
    {
        $this->write(['watchers' => [['url' => 'not-a-url']]]);

        $errors = ConfigFile::validate($this->path);

        self::assertCount(1, $errors);
        self::assertStringContainsString('http://', $errors[0]);
    }

    public function testValidateFlagsAWellSchemedButOtherwiseInvalidUrl(): void
    {
        $this->write(['watchers' => [['url' => 'https://']]]);

        $errors = ConfigFile::validate($this->path);

        self::assertCount(1, $errors);
        self::assertStringContainsString('invalid URL', $errors[0]);
    }

    public function testValidateRejectsANonPositiveInterval(): void
    {
        $this->write(['watchers' => [['url' => 'https://example.com', 'interval' => -5]]]);

        $errors = ConfigFile::validate($this->path);

        self::assertCount(1, $errors);
        self::assertStringContainsString('"interval" must be a positive integer', $errors[0]);
    }

    public function testValidateRejectsAnInvalidDiffMode(): void
    {
        $this->write(['watchers' => [['url' => 'https://example.com', 'diffMode' => 'bogus']]]);

        $errors = ConfigFile::validate($this->path);

        self::assertCount(1, $errors);
        self::assertStringContainsString('invalid "diffMode"', $errors[0]);
    }

    public function testValidateRejectsAJsonPathNotStartingWithDollar(): void
    {
        $this->write(['watchers' => [['url' => 'https://example.com', 'jsonPath' => 'data.price']]]);

        $errors = ConfigFile::validate($this->path);

        self::assertCount(1, $errors);
        self::assertStringContainsString('"jsonPath" must be a string starting with "$"', $errors[0]);
    }

    public function testValidateRejectsAnInvalidCronExpression(): void
    {
        $this->write(['watchers' => [['url' => 'https://example.com', 'schedule' => 'not a cron']]]);

        $errors = ConfigFile::validate($this->path);

        self::assertCount(1, $errors);
        self::assertStringContainsString('invalid cron expression', $errors[0]);
    }

    public function testValidateAcceptsAValidCronExpression(): void
    {
        $this->write(['watchers' => [['url' => 'https://example.com', 'schedule' => '0 9 * * *']]]);

        self::assertSame([], ConfigFile::validate($this->path));
    }

    public function testValidateRejectsAnInvalidWebhookUrl(): void
    {
        $this->write(['watchers' => [['url' => 'https://example.com', 'webhooks' => ['ftp://bad']]]]);

        $errors = ConfigFile::validate($this->path);

        self::assertCount(1, $errors);
        self::assertStringContainsString('invalid webhook URL', $errors[0]);
    }

    public function testValidateCollectsErrorsFromMultipleWatchersInOnePass(): void
    {
        $this->write(['watchers' => [
            ['url' => 'not-a-url'],
            ['url' => 'https://example.com', 'interval' => -1],
        ]]);

        self::assertCount(2, ConfigFile::validate($this->path));
    }

    public function testLoadsExtendedOptions(): void
    {
        $this->write(['watchers' => [[
            'url'                => 'https://example.com',
            'jsonPath'           => 'data.price',
            'tags'               => ['prod'],
            'auth'               => ['user' => 'alice', 'pass' => 'secret'],
            'schedule'           => '0 9 * * *',
            'confirmAfter'       => 30,
            'activeBetween'      => ['from' => '09:00', 'to' => '18:00', 'days' => ['monday']],
            'maintenanceWindows' => [['from' => '2026-01-01T00:00:00+00:00', 'to' => '2026-01-01T02:00:00+00:00']],
        ]]]);

        $watcher = ConfigFile::load($this->path)[0];

        self::assertSame('data.price', $watcher->jsonPath);
        self::assertSame(['prod'], $watcher->tags);
        self::assertSame('alice', $watcher->auth?->user);
        self::assertSame('0 9 * * *', $watcher->schedule);
        self::assertSame(30, $watcher->confirmAfter);
        self::assertNotNull($watcher->activeBetween);
        self::assertCount(1, $watcher->maintenanceWindows);
    }

    /** @param array<string, mixed> $data */
    private function write(array $data): void
    {
        file_put_contents($this->path, json_encode($data));
    }
}
