# watchdiff-core

[![Packagist Version](https://img.shields.io/packagist/v/watchdiff-labs/watchdiff-core)](https://packagist.org/packages/watchdiff-labs/watchdiff-core)
[![Tests](https://github.com/watchdiff-labs/watchdiff-php/actions/workflows/ci.yml/badge.svg)](https://github.com/watchdiff-labs/watchdiff-php/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-777bb4)](composer.json)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue)](LICENSE)
[![Packagist Downloads](https://img.shields.io/packagist/dt/watchdiff-labs/watchdiff-core)](https://packagist.org/packages/watchdiff-labs/watchdiff-core)

Framework-agnostic PHP library for web, file, SSL certificate, and database change
monitoring: readable diffs and webhook/email/AI alerts.

WatchDiff watches web pages, local files, SSL certificates, and databases - then tells
you exactly what changed, as structured `Change` objects, via a callback, a webhook, an
email, or an AI-generated summary.

PHP port of [watchdiff-core](https://www.npmjs.com/package/watchdiff-core) (npm) /
[watchdiff-core](https://pypi.org/project/watchdiff-core/) (PyPI). Same concepts, same
diff output shape, native PHP implementation.

Use this package directly in any PHP project, WordPress, a script, or a framework that
doesn't have its own adapter yet. For Laravel or Symfony, use
[`watchdiff-labs/watchdiff-laravel`](https://packagist.org/packages/watchdiff-labs/watchdiff-laravel) or
[`watchdiff-labs/watchdiff-symfony`](https://packagist.org/packages/watchdiff-labs/watchdiff-symfony)
instead - they pull this package in automatically.

- **Deterministic** - same input always produces the same output
- **Human-readable diffs** - structured `Change` objects (added/removed/modified), not a wall of HTML
- **CSS selectors AND XPath** - target any zone of any page
- **JSON path targeting** - diff only a specific field inside a JSON API response
- **5 diff modes** - line, word, json, rss, sitemap
- **File monitoring** - watch local files with the same diff pipeline as web pages
- **API monitoring** - the same `watch()` pipeline, with `diffMode: Json` and `expectedStatus`
- **SSL certificate monitoring** - alert when a cert is about to expire or gets replaced, pure PHP, no extra dependency
- **Database monitoring** - watch SQLite, PostgreSQL, or MySQL tables for inserts, deletes, updates, schema changes, and aggregate/value thresholds
- **RSS / Atom monitoring** - item-level diff by guid/id
- **Sitemap monitoring** - watch a sitemap.xml for added or removed URLs
- **AI summaries** - optional Claude / OpenAI / Gemini natural-language summary of every diff, via direct HTTP calls, no vendor SDK
- **Cookie-based authentication**
- **HTTP Basic Auth**
- **POST request support**
- **User-Agent rotation**
- **5 webhook channels** - Discord, Slack (Block Kit), Telegram, Microsoft Teams, ntfy.sh, generic POST
- **Email alerts** - optional, via `symfony/mailer`
- **Retry with exponential backoff**, capped by `maxRetryDelayMs`
- **Alert cooldown** - minimum delay between two alerts per watcher
- **Interval jitter** - randomise polling to avoid thundering herds
- **minInterval** - global or per-watcher floor on how often a watcher can run
- **Cron scheduling** - fire checks on standard 5-field cron expressions
- **Maintenance windows** - skip checks during scheduled downtime
- **Active hours** - restrict monitoring to specific hours/days, with IANA timezone support
- **Pause / resume** - by stable id, URL, or tag, persisted across cron runs
- **Watcher status** - checks/changes/errors counters, next/last check, maintenance state
- **Failure policy** - require N consecutive failures/successes before alerting/recovering
- **onRecovery callback** - fired when a watcher recovers after a failure streak
- **Silence detection** - alert when a watcher stops changing
- **Change spike detection** - alert when something changes too often in a time window
- **Flapping detection** (`confirmAfter`) - re-verify before alerting
- **Change threshold** - require a minimum fraction of content to change before alerting
- **minChanges** - require a minimum number of discrete changes before alerting
- **ignoreSelectors** - strip matching elements from the DOM before diffing
- **ignorePatterns** - strip matching regex patterns from the extracted text before diffing
- **ignoreNumbers** - strip standalone numbers to ignore counter/date noise
- **Dry-run mode** - fetch and diff without saving or alerting
- **Snapshot pruning** - keep only the last N snapshots per watcher
- **Retention by age** - auto-prune snapshots older than N days
- **HTML archiving** - save full page HTML to disk on every change
- **HTTP status monitoring** - alert on status code changes or on an unexpected status
- **Response-time tracking** - captured on every snapshot
- **URL comparison** - diff two arbitrary URLs in one call
- **CSV / JSON export** of reports and snapshots
- **XLSX export** - optional, via `phpoffice/phpspreadsheet`
- **JS-rendered pages / screenshots** - optional, via a real Chrome/Chromium binary
- **Opt-in HTTP status server** - `/health`, `/status`, `/metrics` (Prometheus text format)
- **Stable watcher id + tags** - target `pause()`/`resume()` at one watcher or a whole group
- **alertOnFirstCheck** - optionally fire `onChange` on the very first snapshot capture
- **webhookHeaders** - add custom headers to every outgoing webhook POST
- **webhookRetries** - retry failed webhook deliveries with backoff
- **Config validation** - `watchdiff validate` checks a config file before deploying
- **maxWatchers** - optional cap on the number of registered watchers per process
- **SSRF protection** - the resolved host is checked against loopback/link-local/private IP ranges before every request; `allowPrivateUrls: true` opts out for internal monitoring
- **SQL injection guards** - table names are validated as plain identifiers; a custom DB `query` is validated as a single `SELECT`
- **Two storage backends** - JSON files or SQLite (PDO), nothing external required
- **Zero forced HTTP dependency** - bring your own PSR-18 client (Guzzle, Symfony HttpClient, ...)
- **Fully typed** - `declare(strict_types=1)`, readonly value objects, native enums

## Table of contents

- [At a glance](#at-a-glance)
- [Install](#install)
- [Quick start](#quick-start)
- [How it works](#how-it-works)
- [API](#api)
  - [`new WatchDiff(...)`](#new-watchdiff)
  - [`new WatchConfig(...)`](#new-watchconfig)
- [Feature details](#feature-details)
  - [CSS and XPath selectors](#css-and-xpath-selectors)
  - [Ignoring content before diffing](#ignoring-content-before-diffing)
  - [Diff modes](#diff-modes)
  - [JSON path targeting](#json-path-targeting)
  - [Alert cooldown](#alert-cooldown)
  - [Retry with exponential backoff](#retry-with-exponential-backoff)
  - [Interval jitter](#interval-jitter)
  - [minInterval](#mininterval)
  - [Pause and resume](#pause-and-resume)
  - [Watcher status](#watcher-status)
  - [Silence detection](#silence-detection)
  - [Dry-run mode](#dry-run-mode)
  - [Snapshot pruning](#snapshot-pruning)
  - [Retention by age](#retention-by-age)
  - [Change threshold](#change-threshold)
  - [minChanges](#minchanges)
  - [ignoreNumbers](#ignorenumbers)
  - [Error callback](#error-callback)
  - [onBeforeCheck hook](#onbeforecheck-hook)
  - [Failure policy and onRecovery](#failure-policy-and-onrecovery)
  - [Webhook channels](#webhook-channels)
  - [webhookHeaders and webhookRetries](#webhookheaders-and-webhookretries)
  - [HTML archiving](#html-archiving)
  - [Change spike detection](#change-spike-detection)
  - [HTTP status monitoring](#http-status-monitoring)
  - [File monitoring](#file-monitoring)
  - [API monitoring](#api-monitoring)
  - [SSL certificate monitoring](#ssl-certificate-monitoring)
  - [Database monitoring](#database-monitoring)
  - [RSS / Atom monitoring](#rss--atom-monitoring)
  - [Sitemap monitoring](#sitemap-monitoring)
  - [AI summaries](#ai-summaries)
  - [Condition-based alerts](#condition-based-alerts)
  - [Cron scheduling](#cron-scheduling)
  - [Flapping detection](#flapping-detection)
  - [Cookie-based authentication](#cookie-based-authentication)
  - [HTTP Basic Auth](#http-basic-auth)
  - [POST request support](#post-request-support)
  - [User-Agent rotation](#user-agent-rotation)
  - [Watcher tags](#watcher-tags)
  - [alertOnFirstCheck](#alertonfirstcheck)
  - [Maintenance windows](#maintenance-windows)
  - [Active hours](#active-hours)
  - [URL comparison](#url-comparison)
  - [CSV / JSON export](#csv--json-export)
  - [Human-readable formatting](#human-readable-formatting)
  - [XLSX export (optional)](#xlsx-export-optional)
  - [Email alerts (optional)](#email-alerts-optional)
  - [JS-rendered pages / screenshots (optional)](#js-rendered-pages--screenshots-optional)
  - [Status server (opt-in)](#status-server-opt-in)
  - [SQLite storage backend](#sqlite-storage-backend)
  - [Config file and CLI](#config-file-and-cli)
  - [Custom storage backend](#custom-storage-backend)
- [Types](#types)
- [Security considerations](#security-considerations)
- [Not implemented](#not-implemented-compared-to-the-npmpypi-ports)
- [Development](#development)
- [License](#license)

## At a glance

| What you want | How |
|---|---|
| Monitor a URL for changes | `new WatchConfig(url: ..., interval: ..., target: ...)` + `$watchDiff->watch(...)` |
| Target a specific element | `target: '.price'` (CSS) or `target: "//span[@class='p']"` (XPath) |
| Get notified on change | `onChange: fn ($report) => ...` or `webhooks: ['https://discord.com/...']` |
| Render JS-heavy pages | optional, see [JS-rendered pages](#js-rendered-pages--screenshots-optional) |
| Avoid notification spam | `cooldown: 3600` |
| Retry on fetch errors | `retries: 3, retryDelayMs: 1000, maxRetryDelayMs: 30000` |
| Randomise polling interval | `jitter: 0.2` |
| Word-level diff | `diffMode: DiffMode::Word` |
| Diff a JSON API endpoint | `diffMode: DiffMode::Json`, optionally `jsonPath: 'data.price'` |
| Monitor an RSS/Atom feed | `diffMode: DiffMode::Rss` |
| Diff a sitemap for added/removed URLs | `diffMode: DiffMode::Sitemap` |
| Monitor a local file | `new WatchConfig(localPath: '/etc/nginx/nginx.conf')` |
| Monitor an SSL certificate | `new WatchConfig(certHost: 'example.com', warnDaysBeforeExpiry: 14)` |
| Monitor a DB table | `new DbWatchConfig(connectionString: 'sqlite://./app.db', table: 'products')` |
| Monitor a REST API | `diffMode: DiffMode::Json`, `expectedStatus: 200` |
| AI summary of changes | `aiSummary: true, aiProvider: new AiProvider(AiProviderType::Anthropic, $apiKey)` |
| Pause / resume a watcher | `$watchDiff->pause('my-id')` / `$watchDiff->resume('my-id')` |
| Pause a whole group | `tags: ['prod']` then `$watchDiff->pause('prod')` |
| Check watcher state | `$watchDiff->status()` |
| Fire checks on a schedule | `schedule: '0 9 * * *'` (cron expression) |
| Confirm a change before alerting | `confirmAfter: 30` |
| Alert on silence | `alertIfNoChangeAfter: 86400` |
| Detect change spikes | `changeSpikeWindow: 300, changeSpikeThreshold: 5` |
| Monitor HTTP status | `alertOnStatusChange: true, onStatusChange: fn ($info) => ...` |
| HTTP Basic Auth | `auth: new BasicAuth('alice', 'secret')` |
| Monitor authenticated pages | `cookies: ['session' => 'abc123']` |
| POST to an API | `requestBody: ['query' => 'SELECT 1']` |
| Rotate User-Agents | `userAgents: ['Mozilla/5.0 ...', 'Mozilla/5.0 ...']` |
| Dry-run (no save/alert) | `dryRun: true` |
| Limit snapshot storage | `maxSnapshots: 100` |
| Auto-prune old snapshots | `retentionDays: 30` |
| Ignore counters/dates | `ignoreNumbers: true` |
| Require significant change | `changeThreshold: 0.05` |
| Require several distinct changes | `minChanges: 3` |
| Strip ads/widgets before diffing | `ignoreSelectors: ['.ad-slot']` |
| Strip matching text before diffing | `ignorePatterns: ['/\d{2}:\d{2}:\d{2}/']` |
| Allow watching internal/private URLs | `allowPrivateUrls: true` |
| Cap the number of registered watchers | `new WatchDiff(..., maxWatchers: 1000)` |
| Handle errors gracefully | `onError: fn ($e, $config) => ...` |
| Archive HTML on change | `archiveHtml: true` |
| Require N failures before alerting | `failurePolicy: new FailurePolicy(consecutiveFailures: 3)` |
| React when a watcher recovers | `onRecovery: fn ($config) => ...` |
| Fire a hook before every check | `onBeforeCheck: fn ($config) => ...` |
| Diff two URLs | `$watchDiff->compareUrls($urlA, $urlB)` |
| Export history | `$watchDiff->exportReportsCsv($key)` / `exportReportsJson($key)` |
| Give a watcher a stable id | `id: 'my-watcher'` then `pause('my-watcher')` / `resume('my-watcher')` |
| Skip checks during scheduled downtime | `maintenanceWindows: [new MaintenanceWindow($from, $to)]` |
| Restrict monitoring to business hours | `activeBetween: new ActiveHours(from: '09:00', to: '18:00', days: ['monday'])` |
| Validate config before deploying | `watchdiff validate` or `watchdiff validate --config my.json` |
| Add auth headers to webhooks | `webhookHeaders: ['Authorization' => 'Bearer token']` |
| List configured watchers | `watchdiff list` |
| Group watchers by tag | `tags: ['prod']` then `$watchDiff->pause('prod')` |
| Expose status over HTTP | `watchdiff serve --port 9090` |

## Install

```bash
composer require watchdiff-labs/watchdiff-core
```

The core has no hard dependency on an HTTP client - plug in Guzzle, Symfony HttpClient, or
anything else that implements PSR-18. `php-http/discovery` picks one up automatically if
installed:

```bash
composer require guzzlehttp/guzzle
```

## Quick start

```php
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\Enum\DiffMode;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;

$watchDiff = new WatchDiff(
    new JsonStore('.watchdiff'),
    Psr18ClientDiscovery::find(),
    Psr17FactoryDiscovery::findRequestFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);

$config = new WatchConfig(
    url: 'https://example.com/product',
    target: '.price',
    diffMode: DiffMode::Word,
    interval: 60,
    webhooks: ['https://discord.com/api/webhooks/...'],
);
$watchDiff->watch($config);

// The first check only stores a snapshot. Run again later (e.g. from cron) to get a report.
$report = $watchDiff->checkOnce($config->key());
```

Several watchers, checked periodically:

```php
$watchDiff
    ->watch(new WatchConfig(url: 'https://example.com/product', target: '.price', interval: 30))
    ->watch(new WatchConfig(url: 'https://news.ycombinator.com', interval: 300, label: 'HN'));

// Call this periodically (cron, systemd timer, ...). It only checks watchers that are due.
$reports = $watchDiff->tick();
foreach ($reports as $report) {
    echo "{$report->label}: ".count($report->changes)." change(s)\n";
}
```

## How it works

Every web/file/cert check runs through a fixed pipeline:

```
Fetcher (HTTP, file read, or TLS handshake) -> Cleaner -> Parser -> DiffEngine -> Store -> dispatch (callback + webhooks)
```

- **Fetcher** - downloads the page via your PSR-18 client, retrying with exponential backoff; or reads a local file; or opens a TLS connection and reads the peer certificate
- **Cleaner** - strips `<script>`, `<style>`, and comments
- **Parser** - extracts the target CSS selector or XPath expression (or the full body), or a `jsonPath` for JSON content
- **DiffEngine** - compares content in line, word, json, rss, or sitemap mode
- **Store** - persists snapshots, reports, and per-watcher state (counters, pause flag, failure streaks, ...) as JSON files or SQLite
- **dispatch** - fires `onChange` and webhooks once cooldown/confirmAfter/alertIf all agree the change should actually alert

Database checks run a parallel pipeline through `WatchDiff\Core\Db\DbWatcher`:

```
DbFetcher (PDO: SQLite / PostgreSQL / MySQL) -> DbDiffEngine -> Store -> callbacks / webhooks
```

## API

### `new WatchDiff(...)`

```php
new WatchDiff(
    StoreInterface $store,
    ClientInterface $httpClient,
    RequestFactoryInterface $requestFactory,
    ?StreamFactoryInterface $streamFactory = null,
    int $minInterval = 0,
    int $maxWatchers = 0,
)
```

`$streamFactory` is required for webhooks, AI summaries, and POST request bodies - pass
`null` only if you need none of those. `$minInterval` is a *global* floor (seconds) on how
often any watcher may run. `$maxWatchers` (0 = unlimited) rejects `watch()` calls past
that count by throwing `OverflowException`.

| Method | Description |
|---|---|
| `watch(WatchConfig $config): self` | Register a watcher. Chainable. |
| `watchers(): WatchConfig[]` | All registered watchers. |
| `tick(?DateTimeImmutable $now = null): DiffReport[]` | Check every due, non-paused watcher. |
| `checkOnce(string $key): ?DiffReport` | Run one watcher immediately, by its `key()`. |
| `pause(string $tagOrId): void` / `resume(string $tagOrId): void` | By id, URL, or tag. |
| `status(): WatcherStatus[]` | Per-watcher counters and scheduling info. |
| `history(string $key, ?int $limit = null): Snapshot[]` | Stored snapshots. |
| `reports(string $key, ?int $limit = null): DiffReport[]` | Stored diff reports. |
| `clear(string $key): void` | Delete all stored data for a watcher. |
| `storedKeys(): string[]` | Every watcher key with stored data. |
| `compareUrls(string $urlA, string $urlB, ?string $target = null, ?DiffMode $diffMode = null): DiffReport` | One-off diff, nothing persisted. |
| `exportReportsCsv/Json(string $key, ?int $limit = null): string` | |
| `exportSnapshotsCsv/Json(string $key, ?int $limit = null): string` | |
| `withArchiveDirectory(string $directory): self` | Override where `archiveHtml` writes (default `.watchdiff/archive`). |

### `new WatchConfig(...)`

```php
$watchDiff->watch(new WatchConfig(
    url: 'https://example.com/product',
    target: '.price',
    interval: 30,
    onChange: fn ($report) => console_log($report->changes),
    webhooks: ['https://discord.com/api/webhooks/YOUR_WEBHOOK'],
    retries: 3,
    cooldown: 3600,
));
```

Pick exactly one target kind: `url`, `localPath`, or `certHost`. The full option list is
in the [feature details](#feature-details) below, one section per option.

## Feature details

### CSS and XPath selectors

`target` accepts both CSS selectors and XPath expressions. XPath is detected
automatically when the value starts with `/` or `./`.

```php
// CSS selector (default)
new WatchConfig(url: 'https://example.com', target: '.product-price');

// XPath expression
new WatchConfig(url: 'https://example.com', target: "//span[@class='product-price']");

// More complex XPath
new WatchConfig(
    url: 'https://example.com',
    target: "//table[@id='results']//tr[position()>1]/td[2]",
    label: 'Table column 2',
);
```

### Ignoring content before diffing

`ignoreSelectors` strips matching elements from the DOM before diffing (e.g. an ad slot
or a "last updated" widget that isn't the thing you care about); `ignorePatterns` strips
matching regex patterns from the extracted text afterwards:

```php
new WatchConfig(
    url: 'https://example.com/article',
    ignoreSelectors: ['.ad-slot', '#cookie-banner'],
    ignorePatterns: ['/\d{2}:\d{2}:\d{2}/'], // strip HH:MM:SS timestamps
);
```

### Diff modes

| Mode | Best for | Granularity |
|---|---|---|
| `DiffMode::Line` (default) | General text content | Line by line |
| `DiffMode::Word` | Short values, prices, status text | Word by word |
| `DiffMode::Json` | REST APIs, JSON feeds | Nested key path |
| `DiffMode::Rss` | RSS 2.0 and Atom feeds | Item by item (guid / id) |
| `DiffMode::Sitemap` | `sitemap.xml` files | URL by URL (added/removed) |

**Line mode** - a Myers line-by-line diff (via `sebastian/diff`). Each change is one line.

**Word mode** - word-level diff, useful for dense text where you only care about one
value changing:

```php
new WatchConfig(url: 'https://example.com/status', target: '.status-text', diffMode: DiffMode::Word);
// change: Modified, before: "operational", after: "degraded"
```

**JSON mode** - recursively walks the JSON structure and reports changes by key path:

```php
new WatchConfig(
    url: 'https://api.example.com/product/42',
    diffMode: DiffMode::Json,
    headers: ['Accept' => 'application/json'],
    label: 'Product API',
    onChange: function ($report) {
        foreach ($report->changes as $c) {
            // $c->context = "price", "stock.available", ...
            echo "{$c->kind->value} ({$c->context}): {$c->before} -> {$c->after}\n";
        }
    },
);
```

Falls back to line diff automatically when the content can't be parsed as JSON.

**RSS mode** - parses RSS 2.0/Atom XML and reports added/removed/modified items by
`guid`/`id` instead of a raw XML diff:

```php
new WatchConfig(
    url: 'https://hnrss.org/frontpage',
    diffMode: DiffMode::Rss,
    label: 'Hacker News front page',
    interval: 300,
);
// [added] "Show HN: My project" (context: https://news.ycombinator.com/item?id=42)
```

Falls back to line diff automatically when no `<item>`/`<entry>` elements are found.

**Sitemap mode** - see [Sitemap monitoring](#sitemap-monitoring) below.

### JSON path targeting

Extract a sub-value from a JSON response before diffing, instead of diffing the whole
payload:

```php
new WatchConfig(
    url: 'https://api.example.com/price',
    diffMode: DiffMode::Word,
    jsonPath: 'data.price',
);
// "19.99" -> "24.99", not the whole JSON blob
```

### Alert cooldown

Minimum delay between two alerts for the same watcher - avoids notification storms on
pages that change on every check:

```php
new WatchConfig(
    url: 'https://example.com/live-stats',
    target: '.visitor-count',
    interval: 30,
    cooldown: 3600, // at most one alert per hour
);
```

The change is still recorded in snapshot/report history even when the cooldown
suppresses the alert - only `onChange` and the webhooks are skipped for that check.

### Retry with exponential backoff

```php
new WatchConfig(
    url: 'https://example.com',
    retries: 3,       // up to 3 extra attempts (4 total)
    retryDelayMs: 2000,
    maxRetryDelayMs: 30000, // cap on the backoff delay
);
```

Network errors and persistent 5xx/429 responses are retried; once retries are exhausted,
`Fetcher` throws and `onError` fires (it never silently treats a failed fetch as a
successful one).

### Interval jitter

```php
new WatchConfig(url: 'https://example.com', interval: 300, jitter: 0.2); // 240-360s
```

### minInterval

A floor on how often a watcher may run, either globally (`new WatchDiff(..., minInterval: 30)`)
or per watcher (`new WatchConfig(minInterval: 30)`), whichever is higher wins.

### Pause and resume

```php
$watchDiff->watch(new WatchConfig(url: 'https://example.com', id: 'my-watcher'));

$watchDiff->pause('my-watcher');   // by id
$watchDiff->pause('https://example.com'); // or by URL if no id was set
$watchDiff->resume('my-watcher');
```

Paused state is persisted in storage, so it survives between separate `watchdiff tick`
cron invocations.

### Watcher status

```php
foreach ($watchDiff->status() as $s) {
    echo "{$s->label}: {$s->checksCount} checks, {$s->changesCount} changes, paused=" . ($s->paused ? 'yes' : 'no') . "\n";
}
```

From the CLI: `watchdiff list`.

### Silence detection

Fires a callback when a watcher hasn't changed in longer than expected - useful for
detecting a feed that's gone stale or offline without any content change:

```php
new WatchConfig(
    url: 'https://example.com/feed',
    interval: 300,
    alertIfNoChangeAfter: 86400, // 24 hours
    onSilence: function (array $info) {
        // $info = ['url' => ..., 'label' => ..., 'secondsSinceLastChange' => ...]
        error_log("{$info['label']} has not changed in {$info['secondsSinceLastChange']}s");
    },
);
```

Fires at most once per `alertIfNoChangeAfter` seconds; the timer resets on the next
detected change.

### Dry-run mode

Fetch and diff without saving snapshots/reports or sending alerts:

```php
new WatchConfig(
    url: 'https://example.com',
    target: '.price',
    dryRun: true,
    onChange: fn ($report) => error_log('Would have alerted: '.json_encode($report->changes)),
);
```

`onChange` is the one documented exception: it still fires in dry-run mode so you can
preview what would have been alerted, even though nothing else does.

### Snapshot pruning

```php
new WatchConfig(url: 'https://example.com', maxSnapshots: 100); // keep the last 100
```

### Retention by age

```php
new WatchConfig(url: 'https://example.com', retentionDays: 30); // auto-delete snapshots older than 30 days
```

Only snapshots are pruned - stored reports and watcher state (counters, pause flag, ...)
are left untouched.

### Change threshold

```php
new WatchConfig(url: 'https://example.com/article', changeThreshold: 0.05); // need >= 5% of content to change
```

Changes below the threshold are detected but not alerted.

### minChanges

Require a minimum number of discrete changes (not a fraction of content, see
`changeThreshold` above) before alerting - useful when a single stray change is noise but
several at once is a real signal:

```php
new WatchConfig(url: 'https://example.com', diffMode: DiffMode::Word, minChanges: 3);
```

### ignoreNumbers

```php
new WatchConfig(url: 'https://example.com/live', ignoreNumbers: true);
// "1,234 views updated at 14:52" -> no change detected when only the numbers move
```

### Error callback

```php
new WatchConfig(
    url: 'https://example.com',
    retries: 2,
    onError: function (\Throwable $e, WatchConfig $config) {
        error_log("[{$config->label()}] {$e->getMessage()}");
    },
);
```

### onBeforeCheck hook

```php
new WatchConfig(
    url: 'https://api.example.com',
    onBeforeCheck: function (WatchConfig $config) {
        // e.g. refresh a token, log activity
    },
);
```

An exception thrown from `onBeforeCheck` is caught the same way a fetch failure is - it
increments `errorsCount`, routes to `onError`, and `checkOnce()`/`tick()` return `null`
for that watcher instead of crashing the whole run.

### Failure policy and onRecovery

Require several consecutive failures/successes before suppressing or restoring change
alerts, so a flaky endpoint doesn't flood you the moment it comes back:

```php
use WatchDiff\Core\Config\FailurePolicy;

new WatchConfig(
    url: 'https://example.com',
    retries: 1,
    failurePolicy: new FailurePolicy(consecutiveFailures: 3, consecutiveSuccesses: 2),
    onRecovery: fn ($config) => error_log("Recovered: {$config->label()}"),
);
```

`respectRetryAfter` makes a 429/503 retry honor the response's `Retry-After` header
(delta-seconds form only, e.g. `Retry-After: 30` - not the HTTP-date form) instead of
the configured exponential backoff, for APIs that tell you exactly how long to wait:

```php
new WatchConfig(
    url: 'https://api.example.com',
    retries: 3,
    failurePolicy: new FailurePolicy(respectRetryAfter: true),
);
```

### Webhook channels

Payload format is auto-detected from the URL:

| URL pattern | Channel | Payload |
|---|---|---|
| `discord.com` | Discord | `{ "content": "..." }` (<= 2000 chars) |
| `hooks.slack.com` | Slack | Block Kit (header + section + context blocks), <= 3000 chars |
| `api.telegram.org` | Telegram Bot API | `{ "text": "..." }` |
| `office.com` | Microsoft Teams | MessageCard JSON |
| `ntfy.sh` / `ntfy.` | ntfy.sh | `{ "message": "..." }` |
| anything else | Generic | Full `DiffReport` as JSON |

```php
// Telegram - format the URL as:
// https://api.telegram.org/bot<TOKEN>/sendMessage?chat_id=<CHAT_ID>
new WatchConfig(url: 'https://example.com', webhooks: [
    'https://api.telegram.org/botABC123/sendMessage?chat_id=-1001234567',
]);

// ntfy.sh - self-hosted or public push notifications
new WatchConfig(url: 'https://example.com', webhooks: ['https://ntfy.sh/my-topic']);

// Microsoft Teams - an Incoming Webhook connector URL
new WatchConfig(url: 'https://example.com', webhooks: ['https://outlook.office.com/webhook/YOUR/IncomingWebhook/TOKEN']);
```

### webhookHeaders and webhookRetries

```php
new WatchConfig(
    url: 'https://example.com',
    webhooks: ['https://my-internal-endpoint.example.com/hook'],
    webhookHeaders: ['Authorization' => 'Bearer token'],
    webhookRetries: 3, // exponential backoff on delivery failure
);
```

### HTML archiving

Saves the raw fetched content to disk on every detected change:

```php
$watchDiff->withArchiveDirectory('/var/lib/watchdiff/archive');

new WatchConfig(url: 'https://example.com', archiveHtml: true, interval: 300);
```

Archived files are never pruned automatically - manage retention yourself.

### Change spike detection

Alert when a watcher changes too often within a rolling time window - useful for
detecting flapping deployments or runaway content updates:

```php
new WatchConfig(
    url: 'https://example.com/live',
    interval: 30,
    changeSpikeWindow: 300,     // 5-minute window
    changeSpikeThreshold: 5,    // alert if >= 5 changes in that window
    onSpike: function (array $info) {
        // $info = ['url'=>..., 'label'=>..., 'changesInWindow'=>..., 'windowSeconds'=>...]
        error_log("{$info['label']}: {$info['changesInWindow']} changes in {$info['windowSeconds']}s");
    },
);
```

Fires at most once per `changeSpikeWindow` seconds, to avoid repeated alerts during the
same spike.

### HTTP status monitoring

Detect HTTP-level failures independently of content changes:

```php
new WatchConfig(
    url: 'https://example.com',
    interval: 60,
    expectedStatus: 200,         // fires onError if the status differs
    alertOnStatusChange: true,   // fires onStatusChange whenever the status changes between checks
    onStatusChange: function (array $info) {
        // $info = ['url'=>..., 'label'=>..., 'previousStatus'=>..., 'currentStatus'=>...]
        error_log("{$info['label']}: {$info['previousStatus']} -> {$info['currentStatus']}");
    },
);
```

Response time is always captured on every `Snapshot` (`$snapshot->responseTimeMs`), no
separate flag needed.

### File monitoring

Watch a local file through the same pipeline (clean/parse/diff) as a web page:

```php
new WatchConfig(
    localPath: '/etc/nginx/nginx.conf',
    interval: 60,
    onChange: fn ($report) => error_log("Config changed: {$report->label}"),
);
```

### API monitoring

There's no separate "API mode" - it's the same `watch()` pipeline with sensible defaults
for a JSON endpoint:

```php
new WatchConfig(
    url: 'https://api.example.com/health',
    diffMode: DiffMode::Json,
    expectedStatus: 200,
    interval: 60,
);
```

### SSL certificate monitoring

```php
new WatchConfig(
    certHost: 'example.com',
    certPort: 443,
    interval: 3600,
    warnDaysBeforeExpiry: 14,
    onCertExpiring: function (array $info) {
        // $info = ['host'=>..., 'daysUntilExpiry'=>..., 'notAfter'=>...]
        error_log("Certificate for {$info['host']} expires in {$info['daysUntilExpiry']} days");
    },
);
```

The diff itself reports field-level changes (e.g. `fingerprint` and `notAfter` both
changing means the certificate was renewed) - pure PHP via `stream_socket_client()` and
`openssl_x509_parse()`, no extra dependency.

### Database monitoring

> **Use a separate storage directory from your `WatchDiff` instance** (e.g.
> `.watchdiff-db` rather than `.watchdiff`). `watchdiff clean` deletes any stored key
> that isn't a URL/file/cert watcher in the current config file - if `DbWatcher` shares
> that storage, its watchers look like orphans and get deleted too.
>
> `table` is validated against a plain SQL identifier (letters, digits, underscore) and
> rejected otherwise - it can never reach the database as raw SQL. `query`, on the other
> hand, is an intentional raw-SQL escape hatch for `Aggregate`/`Value` mode: never build
> it by concatenating untrusted input. `connectionString`'s embedded credentials are
> stripped before being used as the default storage key - set `id` explicitly if you'd
> rather not rely on that.

```php
use WatchDiff\Core\Db\DbChangeKind;
use WatchDiff\Core\Db\DbDiffMode;
use WatchDiff\Core\Db\DbWatchConfig;
use WatchDiff\Core\Db\DbWatcher;

$db = new DbWatcher(
    new JsonStore('.watchdiff-db'),
    Psr18ClientDiscovery::find(),              // optional: only needed for webhooks
    Psr17FactoryDiscovery::findRequestFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);

// Row-level diff - detect inserts, deletes, and column-level updates
$db->watch(new DbWatchConfig(
    connectionString: 'postgresql://user:pass@localhost/mydb',
    table: 'products',
    primaryKey: ['id'],
    ignoreColumns: ['updated_at'],
    onChange: function ($report) {
        foreach ($report->changes as $change) {
            match ($change->kind) {
                DbChangeKind::Inserted => error_log('New row: '.json_encode($change->row)),
                DbChangeKind::Deleted  => error_log('Deleted row: '.json_encode($change->row)),
                DbChangeKind::Updated  => error_log("Updated {$change->rowKey}: ".json_encode($change->modifications)),
                default => null,
            };
        }
    },
    webhooks: ['https://ntfy.sh/my-products'],
));

// Schema diff - alert when columns are added, removed, or change type
$db->watch(new DbWatchConfig(
    connectionString: 'sqlite://./app.db',
    table: 'users',
    diffMode: DbDiffMode::Schema,
    onSchemaChange: fn (array $info) => error_log('Schema changed: '.json_encode($info['changes'])),
));

// Aggregate / threshold diff - alert when a scalar query result changes by >= 10%
$db->watch(new DbWatchConfig(
    connectionString: 'mysql://user:pass@localhost/shop',
    table: 'orders',
    diffMode: DbDiffMode::Aggregate,
    query: "SELECT COUNT(*) FROM orders WHERE status = 'pending'",
    threshold: 10.0,
    onThreshold: fn (array $info) => error_log("Pending orders changed by {$info['changePercent']}%"),
));

// Value diff - alert whenever a single value changes at all
$db->watch(new DbWatchConfig(
    connectionString: 'postgresql://user:pass@localhost/app',
    table: 'config',
    diffMode: DbDiffMode::Value,
    query: "SELECT value FROM config WHERE key = 'maintenance_mode'",
    onChange: function ($report) {
        $change = $report->changes[0];
        $mod    = $change->modifications[0];
        error_log("maintenance_mode: {$mod['before']} -> {$mod['after']}");
    },
));

$db->tick();

// Per-watcher counters, mirroring WatchDiff::status() for URL/file/cert watchers
foreach ($db->status() as $status) {
    printf("%s: %d checks, %d changes, %d errors\n", $status->label, $status->checksCount, $status->changesCount, $status->errorsCount);
}
```

Without `primaryKey`, rows are compared as an unordered set - inserts and deletes are
detected but updates are not.

Row mode watches inserts, deletes, and updates by default - toggle them off individually
with `alertOnInsert`, `alertOnDelete`, `alertOnUpdate`:

```php
$db->watch(new DbWatchConfig(
    connectionString: 'sqlite://./app.db',
    table: 'products',
    primaryKey: ['id'],
    alertOnUpdate: false, // only report inserted/deleted rows, ignore column changes
));
```

`DbChange::kind` is `ThresholdExceeded` for a change reported in `Aggregate` mode, and
`ValueChanged` in `Value` mode - two distinct kinds rather than both reusing `Updated`, so a
consumer switching on `$change->kind` can tell a row-level update apart from a scalar-query
change. In `Schema` mode, each column in `DbSnapshot::$columns` also carries `primaryKey`
(bool) and `defaultValue` (the driver's raw default-value expression, e.g. SQLite's own
`'misc'` literal text) alongside `name`/`type`/`nullable`; `diffSchema()` now also reports a
`"primary key changed"` change when a column's primary-key status flips. Every `DbSnapshot`
additionally carries a derived `rowCount` and a `checksum` (a fast content fingerprint of its
rows, not a cryptographic hash - it's for change detection, not identity/security).

### RSS / Atom monitoring

See [Diff modes](#diff-modes) above.

### Sitemap monitoring

```php
new WatchConfig(
    url: 'https://example.com/sitemap.xml',
    diffMode: DiffMode::Sitemap,
    interval: 3600,
    onChange: function ($report) {
        foreach ($report->changes as $c) {
            // kind Added -> new URL in $c->after, kind Removed -> gone URL in $c->before
        }
    },
);
```

### AI summaries

```php
use WatchDiff\Core\Config\AiProvider;
use WatchDiff\Core\Config\AiProviderType;

new WatchConfig(
    url: 'https://example.com/changelog',
    aiSummary: true,
    aiProvider: new AiProvider(AiProviderType::Anthropic, apiKey: $_ENV['ANTHROPIC_API_KEY']),
    // aiPrompt: 'Summarize this diff in French.' or a Closure(DiffReport): string
);

$report = $watchDiff->checkOnce(/* ... */);
echo $report->aiSummary; // null if no change, or the model's summary
```

#### Auto-detection from the environment

Leaving `aiProvider` unset while `aiSummary: true` falls back to `AiProvider::fromEnvironment()`,
which picks the first of `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY` (checked in
that order) that's set - no `aiProvider` needed on `WatchConfig` at all:

```php
// $_ENV / putenv() has ANTHROPIC_API_KEY set -> no aiProvider needed
new WatchConfig(url: 'https://example.com/changelog', aiSummary: true);
```

#### OpenAI-compatible endpoints (Ollama, Mistral, Groq, Together AI, ...)

`baseUrl` points the `OpenAi` provider type at any OpenAI-compatible chat completions
endpoint instead of `api.openai.com` - these providers all speak the same request/response
shape, so no separate provider type is needed per one:

```php
new AiProvider(AiProviderType::OpenAi, apiKey: 'ollama', baseUrl: 'http://localhost:11434/v1');
new AiProvider(AiProviderType::OpenAi, apiKey: $_ENV['GROQ_API_KEY'], baseUrl: 'https://api.groq.com/openai/v1', model: 'llama-3.3-70b-versatile');
```

#### Custom provider

`AiProviderType::Custom` with a `custom` closure bypasses the built-in HTTP calls entirely -
useful for a provider this library doesn't speak directly, or for routing through your own
infrastructure:

```php
new AiProvider(
    type: AiProviderType::Custom,
    custom: function (string $prompt, DiffReport $report): string {
        return myInHouseLlmClient()->complete($prompt);
    },
);
```

`baseUrl` and `custom` are both PHP-only - a JSON config file can set `baseUrl` (it's a plain
string), but `custom` needs a `Closure` and can only be set when building a `WatchConfig` in
code.

`AiProviderType` supports `Anthropic`, `OpenAi`, and `Gemini` - each a direct HTTP call to
that provider's REST API through the same PSR-18 client, no vendor SDK required.

### Condition-based alerts

```php
new WatchConfig(
    url: 'https://example.com',
    diffMode: DiffMode::Json,
    alertIf: fn ($report) => in_array('price', array_map(fn ($c) => $c->context, $report->changes), true),
);
```

### Cron scheduling

```php
new WatchConfig(url: 'https://example.com', schedule: '0 9 * * *'); // every day at 09:00
```

`schedule` overrides `interval` when set, using standard 5-field cron syntax (via
`dragonmantank/cron-expression`).

### Flapping detection

```php
new WatchConfig(url: 'https://example.com', confirmAfter: 30);
```

A change must be observed again after at least `confirmAfter` seconds before it's
actually alerted - if the page reverts in the meantime, nothing fires.

### Cookie-based authentication

```php
new WatchConfig(url: 'https://example.com/dashboard', cookies: ['session' => 'abc123', 'csrftoken' => 'xyz']);
```

### HTTP Basic Auth

```php
use WatchDiff\Core\Config\BasicAuth;

new WatchConfig(url: 'https://example.com/private', auth: new BasicAuth('alice', 'secret'));
```

### POST request support

```php
new WatchConfig(url: 'https://api.example.com/query', requestBody: ['query' => 'SELECT 1']);
// objects are JSON-encoded automatically; a string is sent as-is
```

### User-Agent rotation

```php
new WatchConfig(url: 'https://example.com', userAgents: [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0.0.0',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/124.0.0.0',
]);
```

### Watcher tags

```php
new WatchConfig(url: 'https://example.com', tags: ['prod', 'prices']);

$watchDiff->pause('prod'); // pauses every watcher tagged "prod"
```

### alertOnFirstCheck

```php
new WatchConfig(url: 'https://example.com', alertOnFirstCheck: true);
// fires onChange on the very first snapshot capture, instead of only storing a baseline
```

### Maintenance windows

```php
use WatchDiff\Core\Config\MaintenanceWindow;

new WatchConfig(url: 'https://example.com', maintenanceWindows: [
    new MaintenanceWindow(new DateTimeImmutable('2026-09-01T02:00:00Z'), new DateTimeImmutable('2026-09-01T04:00:00Z')),
]);
```

### Active hours

```php
use WatchDiff\Core\Config\ActiveHours;

new WatchConfig(url: 'https://example.com', activeBetween: new ActiveHours(
    from: '09:00',
    to: '18:00',
    days: ['monday', 'friday'],
    timezone: 'Europe/Paris',
));
```

### URL comparison

```php
$report = $watchDiff->compareUrls('https://example.com/v1', 'https://example.com/v2', target: '.content', diffMode: DiffMode::Word);
echo count($report->changes).' difference(s)';
```

### CSV / JSON export

```php
file_put_contents('reports.csv', $watchDiff->exportReportsCsv($key, limit: 100));
file_put_contents('reports.json', $watchDiff->exportReportsJson($key));
file_put_contents('snapshots.csv', $watchDiff->exportSnapshotsCsv($key));
```

`exportReportsCsv()` columns: `url, label, comparedAt, kind, before, after, context` - one row
per change, so a single report with 3 changes becomes 3 rows sharing the same `url`/`label`/
`comparedAt`. `exportSnapshotsCsv()` columns: `url, capturedAt, statusCode, responseTimeMs,
content`.

### Human-readable formatting

```php
use WatchDiff\Core\Support\Human;

echo Human::changeHuman($change);     // "[modified] old value -> new value"
echo Human::reportSummary($report);   // label + "N change(s) detected" + one changeHuman() line each
echo Human::dbChangeHuman($dbChange); // "[inserted] {...}", "[updated] key=1 [...]", "[threshold_exceeded] [...]", ...
echo Human::dbReportSummary($dbReport);
```

This is the exact formatting `WebhookSender` and `DbWatcher` already use to build webhook
message bodies internally (Discord/Slack/Telegram/ntfy), now exposed as standalone functions -
useful for your own `onChange`/`onThreshold` callbacks, logging, or a CLI command, without
reimplementing the same one-line-per-change text.

### XLSX export (optional)

```bash
composer require phpoffice/phpspreadsheet
```

```php
$xlsx = new \WatchDiff\Core\Export\XlsxExporter();
file_put_contents('reports.xlsx', $xlsx->exportReports($watchDiff->reports($key)));
```

### Email alerts (optional)

```bash
composer require symfony/mailer
```

```php
$email = new \WatchDiff\Core\Notify\EmailSender('smtp://user:pass@smtp.example.com:587');
$email->send(to: 'ops@example.com', from: 'watchdiff@example.com', report: $report);
```

### JS-rendered pages / screenshots (optional)

```bash
composer require chrome-php/chrome
# and have a Chrome/Chromium binary installed on the host
```

```php
$browser = new \WatchDiff\Core\Fetch\BrowserFetcher();
$result  = $browser->fetch('https://spa-example.com', waitForSelector: '.content');
$browser->screenshot('https://spa-example.com', '/tmp/shot.png');
```

Not wired into `WatchDiff::checkOnce()` and not covered by CI (no Chrome binary there) -
call it directly rather than through a `browser: true` option on `WatchConfig`.

### Status server (opt-in)

```bash
vendor/bin/watchdiff serve --port 9090
# GET /health  -> {"status":"ok"}
# GET /status  -> JSON array of WatcherStatus
# GET /metrics -> Prometheus text format
```

Built on PHP's own `stream_socket_server` (no extra dependency). It does **not** poll
watchers itself - it only reports whatever state `watchdiff tick` cron runs have left in
the shared store. Run it under a process supervisor (systemd, supervisord, ...), never
from cron.

Binds to `127.0.0.1` by default - `/status` and `/metrics` are **unauthenticated** and
list every watched URL (which may embed API keys or tokens in the query string), so put
it behind your own auth/reverse proxy before passing `--host 0.0.0.0` to expose it beyond
localhost.

### SQLite storage backend

```php
use WatchDiff\Core\Store\SqliteStore;

$watchDiff = new WatchDiff(new SqliteStore('.watchdiff.db'), $httpClient, $requestFactory, $streamFactory);
```

### Config file and CLI

```bash
vendor/bin/watchdiff init                              # create watchdiff.config.json
vendor/bin/watchdiff validate                          # validate the config file
vendor/bin/watchdiff tick                              # check every watcher that is due (run from cron)
vendor/bin/watchdiff check <url> --target .price --diff-mode word
vendor/bin/watchdiff diff <url>                        # print the last stored diff
vendor/bin/watchdiff reports <url>                      # list stored change reports (dates + change counts)
vendor/bin/watchdiff history <url>                     # list stored snapshots
vendor/bin/watchdiff list                              # all configured watchers + status
vendor/bin/watchdiff pause <id|url|tag>
vendor/bin/watchdiff resume <id|url|tag>
vendor/bin/watchdiff snapshot <url>                     # print the latest stored snapshot
vendor/bin/watchdiff clear <url>                        # delete ALL stored data for a single URL
vendor/bin/watchdiff clean                              # delete stored data for watchers no longer in the config
vendor/bin/watchdiff compare <urlA> <urlB>
vendor/bin/watchdiff export <url> --format csv|json --type reports|snapshots [--output file]
vendor/bin/watchdiff db <connectionString> <table> --primary-key id [--webhook ...] [--no-update]
vendor/bin/watchdiff serve --port 9090 --host 127.0.0.1
```

`clear` removes everything stored for one URL/id (snapshots, reports, state); `clean` only
removes watchers that no longer appear in the current config file - `clean` calls the same
underlying `WatchDiff::clear()` once per orphan it finds. `clean` also asks for confirmation
before deleting (listing what it's about to remove) unless `--yes`/`-y` is passed or stdin
isn't a TTY, in which case it refuses rather than deleting silently - pass `--yes` for cron/CI.

Every list-producing command (`check`, `compare`, `diff`, `snapshot`, `list`, `reports`,
`validate`, `db`) accepts `--json` to print a single JSON value instead of human-readable
text, for scripting against. `tick` instead has `--log-format json`, which prints one JSON
object per line (one per changed watcher) rather than a single value, since it's meant to be
tailed/ingested by a log pipeline, not parsed as a whole.

`-q`/`--quiet` and `-v`/`--verbose` work on every command - they're Symfony Console's own
global flags, not something this library adds, and every `writeln()` call here already
respects them for free.

#### Environment variable fallback

Every option below falls back to a `WATCHDIFF_*` environment variable when the flag isn't
passed, so a systemd unit, Docker image, or CI job can set them once instead of repeating
flags on every invocation. Precedence is always: **explicit CLI flag > environment variable
> the option's own default.**

| Option | Commands | Environment variable |
|---|---|---|
| `--config` | `list`, `pause`, `resume`, `clean`, `validate`, `serve`, `tick` | `WATCHDIFF_CONFIG` |
| `--target` | `check`, `compare` | `WATCHDIFF_TARGET` |
| `--diff-mode` | `check` | `WATCHDIFF_DIFF_MODE` |
| `--dry-run` | `check` | `WATCHDIFF_DRY_RUN` |
| `--json` | `check`, `compare`, `diff`, `snapshot`, `list`, `reports`, `validate`, `db` | `WATCHDIFF_JSON` |
| `--format` | `export` | `WATCHDIFF_FORMAT` |
| `--type` | `export` | `WATCHDIFF_EXPORT_TYPE` |
| `--limit` | `export`, `history`, `reports` | `WATCHDIFF_LIMIT` |
| `--output` | `export` | `WATCHDIFF_OUTPUT` |
| `--yes`/`-y` | `clean` | `WATCHDIFF_YES` |
| `--port` | `serve` | `WATCHDIFF_PORT` |
| `--host` | `serve` | `WATCHDIFF_HOST` |
| `--log-format` | `tick` | `WATCHDIFF_LOG_FORMAT` |
| `--diff-mode` | `db` | `WATCHDIFF_DB_DIFF_MODE` |
| `--query` | `db` | `WATCHDIFF_DB_QUERY` |
| `--threshold` | `db` | `WATCHDIFF_DB_THRESHOLD` |

```bash
export WATCHDIFF_CONFIG=/etc/watchdiff/prod.config.json
export WATCHDIFF_LOG_FORMAT=json
vendor/bin/watchdiff tick   # reads both from the environment, no flags needed
```

#### `validate` checks

Beyond "does the config file parse", `validate` (and `--json`'s `{"valid": bool, "errors":
[...]}` output) checks each watcher for:

- a `url`, `localPath`, or `certHost` is present, and `url` starts with `http://`/`https://`
  and is otherwise a well-formed URL
- `interval`, if set, is a positive integer
- `diffMode`, if set, is one of the valid `DiffMode` values
- `jsonPath`, if set, is a string starting with `$` (e.g. `$.data.price`)
- `schedule`, if set, is a parseable cron expression
- every `webhooks` entry starts with `http://`/`https://` and is a well-formed URL

All watchers are checked in one pass - every problem across every watcher is reported, not
just the first one found.

`watchdiff.config.json` (as produced by `init`) accepts almost every `WatchConfig` option
that doesn't need a closure:

```json
{
    "watchers": [
        {
            "url": "https://example.com",
            "target": ".price",
            "interval": 300,
            "diffMode": "line",
            "webhooks": [],
            "cooldown": 3600,
            "jsonPath": "data.price",
            "tags": ["prod"],
            "auth": { "user": "alice", "pass": "secret" },
            "schedule": "0 9 * * *",
            "confirmAfter": 30,
            "activeBetween": { "from": "09:00", "to": "18:00", "days": ["monday"] },
            "maintenanceWindows": [{ "from": "2026-01-01T00:00:00+00:00", "to": "2026-01-01T02:00:00+00:00" }],
            "failurePolicy": { "consecutiveFailures": 3, "consecutiveSuccesses": 2, "respectRetryAfter": true },
            "aiProvider": { "type": "anthropic", "apiKey": "...", "model": "claude-haiku-4-5-20251001" }
        },
        { "localPath": "/etc/nginx/nginx.conf", "interval": 60 },
        { "certHost": "example.com", "warnDaysBeforeExpiry": 14 }
    ]
}
```

Closures (`onChange`, `onError`, `onBeforeCheck`, `onRecovery`, `onSilence`, `onSpike`,
`onStatusChange`, `onCertExpiring`, `alertIf`, and `aiProvider.custom`) can't be expressed in
JSON - set those up in PHP code instead when you need them. `aiProvider.baseUrl` (a plain
string) works fine from JSON, for OpenAI-compatible endpoints like Ollama/Groq/Together AI.

### Custom storage backend

`JsonStore` and `SqliteStore` both implement `WatchDiff\Core\Contract\StoreInterface` -
write your own class against that interface (e.g. to store in Redis, DynamoDB, a
different SQL schema, ...) and pass it to `new WatchDiff($yourStore, ...)` instead:

```php
use WatchDiff\Core\Contract\StoreInterface;
use WatchDiff\Core\Model\DiffReport;
use WatchDiff\Core\Model\Snapshot;

final class RedisStore implements StoreInterface
{
    public function saveSnapshot(string $key, Snapshot $snapshot, ?int $maxSnapshots = null): void { /* ... */ }
    public function latestSnapshot(string $key): ?Snapshot { /* ... */ }
    public function snapshots(string $key, ?int $limit = null): array { /* ... */ }
    public function saveReport(string $key, DiffReport $report): void { /* ... */ }
    public function reports(string $key, ?int $limit = null): array { /* ... */ }
    public function lastAlertAt(string $key): ?\DateTimeImmutable { /* ... */ }
    public function setLastAlertAt(string $key, \DateTimeImmutable $at): void { /* ... */ }
    public function clear(string $key): void { /* ... */ }
    public function pruneSnapshotsOlderThan(string $key, \DateTimeImmutable $cutoff): void { /* ... */ }
    public function getState(string $key): array { /* ... */ }    // pause flag, counters, failure streaks, ...
    public function setState(string $key, array $state): void { /* ... */ }
    public function keys(): array { /* ... */ }                    // every watcher key with stored data
}
```

## Types

The exact shape of every object you get back from a callback, `checkOnce()`,
`tick()`, `history()`, `reports()`, or `status()`.

```php
enum ChangeKind: string { case Added = 'added'; case Removed = 'removed'; case Modified = 'modified'; }

final readonly class Change {
    public ChangeKind $kind;
    public ?string $before;
    public ?string $after;
    public ?string $context;   // e.g. the JSON key path, or an RSS item's link
}

final readonly class Snapshot {
    public string $url;              // or the local path / "host:port" key, for file/cert watchers
    public string $content;
    public \DateTimeImmutable $capturedAt;
    public int $statusCode;          // 200 for file/cert watchers
    public ?float $responseTimeMs;
}

final readonly class DiffReport {
    public string $url;
    public string $label;
    public Snapshot $before;
    public Snapshot $after;
    public Change[] $changes;
    public DiffMode $diffMode;
    public \DateTimeImmutable $comparedAt;
    public ?string $aiSummary;       // set only when aiSummary: true and there are changes
    public function hasChanges(): bool;
}

final readonly class WatcherStatus {
    public string $url;
    public ?string $id;
    public string $label;
    public ?string $target;
    public int $interval;
    public bool $paused;
    public bool $inMaintenance;
    public ?\DateTimeImmutable $lastCheckAt;
    public ?\DateTimeImmutable $nextCheckAt;
    public ?\DateTimeImmutable $lastChangeAt;
    public int $checksCount;
    public int $changesCount;
    public int $errorsCount;
}

// Database monitoring (WatchDiff\Core\Db\*)

enum DbChangeKind: string {
    case Inserted          = 'inserted';
    case Deleted           = 'deleted';
    case Updated           = 'updated';
    case SchemaChanged     = 'schema_changed';
    case ThresholdExceeded = 'threshold_exceeded'; // Aggregate mode
    case ValueChanged      = 'value_changed';      // Value mode
}

final readonly class DbChange {
    public DbChangeKind $kind;
    public ?array $row;              // the full row, for Inserted/Deleted
    public ?string $rowKey;          // the primaryKey value(s), for Inserted/Deleted/Updated
    public ?array $modifications;    // [{column, before, after}, ...], for Updated/ThresholdExceeded/ValueChanged
    public ?string $column;          // for SchemaChanged
    public ?string $context;         // e.g. "column added" / "type changed" / "primary key changed" / "column removed", for SchemaChanged
}

final readonly class DbSnapshot {
    public array $rows;
    public array $columns;           // [{name, type, nullable, primaryKey, defaultValue}, ...], only populated in Schema mode
    public \DateTimeImmutable $capturedAt;
    public int $rowCount;            // derived: count($rows)
    public string $checksum;         // derived: a fast content fingerprint of $rows, not a cryptographic hash
}

final readonly class DbDiffReport {
    public string $connectionString;
    public string $table;
    public string $label;
    public DbSnapshot $before;
    public DbSnapshot $after;
    public DbChange[] $changes;
    public DbDiffMode $diffMode;
    public \DateTimeImmutable $comparedAt;
    public function hasChanges(): bool;
}
```

## Security considerations

Hardening already built in:

- `url` must start with `http://` or `https://` - rejected otherwise (defense in depth against the URL being used as a generic SSRF primitive, e.g. `file://`).
- The host is resolved and checked against loopback/link-local/private IP ranges before every request, and the fetch is refused unless `allowPrivateUrls: true` is set. This is a pre-check against the hostname's *current* resolution, not a guarantee against DNS rebinding (where the name resolves differently between this check and your HTTP client's own connection).
- Response bodies (HTTP fetches and AI provider calls) are capped at 10 MiB and the fetch fails past that, instead of buffering an unbounded response into memory.
- RSS/sitemap XML is parsed with `LIBXML_NONET` (defense in depth against XXE-driven network/entity resolution).
- The `Cookie` header strips `\r`/`\n` before being sent, to prevent header injection via cookie names/values.
- `DbWatchConfig`'s `table` is validated as a plain SQL identifier before being used in a query - it can never reach the database as raw SQL. A custom `query` (the `Aggregate`/`Value`-mode escape hatch) is validated as a single `SELECT` statement - no semicolons, no other statement type - before it's ever executed.
- A DB `connectionString`'s embedded credentials are stripped before being used as the default storage key, so a password never lands in `.watchdiff`/`.watchdiff-db` in plaintext.
- `watchdiff serve` binds to `127.0.0.1` by default; its `/status`/`/metrics` endpoints are unauthenticated.
- `maxWatchers` on `WatchDiff` (0 = unlimited) caps how many watchers a single process will register, bounding memory use in a long-running process that registers watchers dynamically.
- Webhook destination URLs (`WatchConfig::$webhooks`, `DbWatchConfig::$webhooks`) go through the same loopback/link-local/private IP check as the main `url`/`connectionString` before a request is sent, gated by the same `allowPrivateUrls` flag - a webhook URL is just as capable of being used as an SSRF primitive as the watched URL itself.
- A webhook send failure (network error, non-2xx the client throws on, etc.) is caught and routed to `onError` instead of propagating out of `checkOnce()`/`tick()` - this also avoids an uncaught exception leaking a secret embedded in the webhook URL itself (e.g. a Telegram bot token) through an upstream logger/error tracker that captures exception messages.
- Certificate monitoring (`certHost`/`certPort`) connects with `verify_peer: false` - inspecting an invalid/expired/self-signed certificate is the point of the feature - but keeps `verify_peer_name: true`, so a MITM presenting a validly-issued certificate for an unrelated domain is still rejected rather than silently inspected as if it belonged to `certHost`.
- Storage keys (`.watchdiff/<hash>.*.json` filenames, and the `keys.json` registry) are hashed with SHA-256, not a fast non-cryptographic hash, since the key space can include attacker-influenced input (e.g. a user-submitted URL).

Trust boundaries that are inherent to what these features do, not bugs:

- **`localPath`** reads whatever file your process can access. Never let untrusted input choose the path without validating it against an allowed directory yourself.
- **`url`**, even restricted to http/https and public IP ranges, can still be redirected (by the target server) to an internal address - this library has no control over how your PSR-18 client follows redirects, and doesn't re-validate each hop. If you let external users submit URLs to watch, validate/allowlist them before constructing a `WatchConfig`, and prefer an HTTP client configured to not follow redirects, or to validate them itself.
- **`query`** on `DbWatchConfig` is restricted to a single `SELECT`, but a read can still leak data your database user can see (e.g. `SELECT * FROM users`) - scope the DB credentials you hand to `DbWatcher` to only what it needs to read.
- **`certHost`/`certPort`** intentionally connect with `verify_peer: false` - the point of the feature is inspecting a certificate even if it's invalid/self-signed, not making a trusted connection. Only chain-of-trust validation is skipped; hostname verification stays on (see above).

## Not implemented (compared to the npm/PyPI ports)

Two different situations get lumped into "not implemented" below, and they're not the same
claim - the table says which is which:

- **Architectural trade-off** - genuinely blocked *by this library's own design choice* to
  accept any PSR-18 HTTP client instead of forcing Guzzle. It's not that PHP can't do it
  (Guzzle's `Pool`, `curl_multi`, ReactPHP/AMPHP all can) - it's that doing it generically,
  for whatever PSR-18 client you hand in, isn't possible. Dropping the "any PSR-18 client"
  promise would unblock these, but that's a deliberate trade, not a limitation to work around.
- **Not implemented yet** - fully possible in PHP, not blocked by anything structural, simply
  not built. These are open to a PR/issue, unlike the ones above.

| Feature | Category | Why |
|---|---|---|
| **Proxy rotation** | Architectural trade-off | Proxy configuration lives on the *concrete* HTTP client (Guzzle's `proxy` option, Symfony HttpClient's `proxy` option), not in the PSR-18 `ClientInterface::sendRequest()` contract. **Workaround:** build several `WatchDiff` instances, each with its own proxy-configured client, or add proxy-choosing middleware to your own Guzzle stack before handing the client to `WatchDiff`. |
| **Concurrency limiting** | Architectural trade-off | `ClientInterface::sendRequest()` is synchronous by contract - true parallel fetches need either `curl_multi`/Guzzle's `Pool` (which would force Guzzle) or an async runtime (ReactPHP/AMPHP), both of which break the "any PSR-18 client" promise the core is built on. **Workaround:** run several `watchdiff tick` cron invocations with disjoint config files, or bypass `WatchDiff\Core\Fetch\Fetcher` and use Guzzle's `Pool` directly in your own code. |
| **Per-request timeout** | Architectural trade-off | Same root cause as proxy rotation - a request timeout is configured on the *concrete* PSR-18 client (Guzzle's `timeout` option, Symfony HttpClient's `max_duration`), not through `ClientInterface::sendRequest()`. There is no `timeout` option on `WatchConfig`. **Workaround:** set the timeout when you construct the HTTP client you pass to `WatchDiff`. |
| **Per-hop redirect validation** | Architectural trade-off | The SSRF guard checks the URL you configured before the first request, but this library doesn't control how your PSR-18 client follows redirects (or whether it does at all) - a redirect to an internal address on hop 2 isn't re-validated. **Workaround:** configure your HTTP client to disable redirect-following (e.g. Guzzle's `allow_redirects => false`) if you watch URLs you don't fully trust, or to validate each hop itself. |
| **`semantic` diff mode** (paragraph/heading-level, like the JS/Python ports) | Not implemented yet | Only `line`, `word`, `json`, `rss`, and `sitemap` exist - nothing stops a paragraph/heading-aware diff mode from being added, it just hasn't been. **Workaround:** `target` a more specific selector to approximate paragraph-level diffing, or use `line` mode, which is close for most prose content. |
| **Automatic email alerts** | Not implemented yet | `WatchDiff\Core\Notify\EmailSender` exists and works, but it isn't wired into the `onChange`/webhook dispatch pipeline the way webhooks are - there's no `email` option on `WatchConfig` that fires automatically. Wiring it up is a small, deliberate addition away. **Workaround:** call `EmailSender::send()` yourself from an `onChange` callback. |
| **Configurable response size cap** | Not implemented yet | The 10 MiB limit in `BoundedBodyReader` is a fixed constant, not a `WatchConfig` option - trivial to expose, just hasn't been asked for yet. **Workaround:** none currently - open an issue if 10 MiB is too small for a legitimate use case. |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --allow-risky=yes
```

Code style aligns consecutive `=` and `=>` signs within a block (e.g. constructor
promoted property defaults). This is done by hand, not by `php-cs-fixer` - PHP-CS-Fixer's
PSR12 ruleset has no rule for aligning parameter defaults and actively collapses this
alignment back to a single space, so running `php-cs-fixer fix` will undo it on those
lines. CI only runs PHPStan and PHPUnit, not a style-lint gate, for this reason.

Some tests need `ext-pdo_sqlite` (SQLite-backed store/DB tests) and outbound network
access (the SSL certificate test); they're skipped automatically when unavailable
locally, but run in CI.

## License

This project is licensed under the BSD 3-Clause License.

You are free to use, modify, and distribute this software in source and binary forms,
with or without modification, provided the copyright notice and disclaimer are
retained. No copyleft restrictions - compatible with MIT and proprietary projects.
