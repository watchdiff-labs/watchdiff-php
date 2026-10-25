<?php

declare(strict_types=1);

namespace WatchDiff\Core\Fetch;

/**
 * Optional JS-rendering / screenshot support for pages that need a real
 * browser engine. Requires chrome-php/chrome, which drives an actual
 * Chrome/Chromium **binary** via the DevTools protocol - the same class of
 * external dependency as Playwright in the Node version, not a pure-PHP
 * library. Not wired into WatchDiff::checkOnce() by design (no CI coverage
 * without a real browser installed) - call it directly when you need it:
 *
 *     composer require chrome-php/chrome
 *     (and have google-chrome or chromium installed on the host)
 */
final class BrowserFetcher
{
    public function __construct()
    {
        if (!class_exists(\HeadlessChromium\BrowserFactory::class)) {
            throw new \RuntimeException(
                'BrowserFetcher requires chrome-php/chrome and a Chrome/Chromium binary. '.
                'Install it with: composer require chrome-php/chrome'
            );
        }
    }

    public function fetch(string $url, ?string $waitForSelector = null, int $timeoutSeconds = 30): FetchResult
    {
        $started = microtime(true);
        $factory = new \HeadlessChromium\BrowserFactory();
        $browser = $factory->createBrowser(['noSandbox' => true]);

        try {
            $page = $browser->createPage();
            $page->navigate($url)->waitForNavigation();

            if (null !== $waitForSelector) {
                $page->waitForFunction(
                    sprintf("document.querySelector('%s') !== null", addslashes($waitForSelector)),
                    [],
                    $timeoutSeconds * 1000,
                );
            }

            $html = $page->getHtml();

            return new FetchResult($html, 200, (microtime(true) - $started) * 1000);
        } finally {
            $browser->close();
        }
    }

    public function screenshot(string $url, string $outputPath): void
    {
        $factory = new \HeadlessChromium\BrowserFactory();
        $browser = $factory->createBrowser(['noSandbox' => true]);

        try {
            $page = $browser->createPage();
            $page->navigate($url)->waitForNavigation();
            $page->screenshot()->saveToFile($outputPath);
        } finally {
            $browser->close();
        }
    }
}
