<?php

declare(strict_types=1);

namespace WatchDiff\Core\Fetch;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use WatchDiff\Core\Config\WatchConfig;

final class Fetcher
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly ?StreamFactoryInterface $streamFactory = null,
    ) {
    }

    public function fetch(WatchConfig $config): FetchResult
    {
        SsrfGuard::assertPublicHost($config->url, $config->allowPrivateUrls);

        $attempt       = 0;
        $delayMs       = $config->retryDelayMs;
        $lastException = null;

        do {
            $started = microtime(true);

            try {
                $request   = $this->buildRequest($config);
                $response  = $this->httpClient->sendRequest($request);
                $elapsedMs = (microtime(true) - $started) * 1000;
                $status    = $response->getStatusCode();

                if ($status >= 500 || 429 === $status) {
                    if ($attempt < $config->retries) {
                        $this->wait($this->retryAfterMs($config, $response) ?? $delayMs);
                        $delayMs = min($delayMs * 2, $config->maxRetryDelayMs);
                        ++$attempt;

                        continue;
                    }

                    throw new \RuntimeException(sprintf(
                        'Fetch of "%s" failed with HTTP %d after %d attempt(s).',
                        $config->url,
                        $status,
                        $attempt + 1,
                    ));
                }

                return new FetchResult(BoundedBodyReader::read($response->getBody()), $status, $elapsedMs);
            } catch (ClientExceptionInterface $exception) {
                $lastException = $exception;

                if ($attempt < $config->retries) {
                    $this->wait($delayMs);
                    $delayMs = min($delayMs * 2, $config->maxRetryDelayMs);
                    ++$attempt;

                    continue;
                }

                throw $exception;
            }
        } while ($attempt <= $config->retries);

        throw $lastException ?? new \RuntimeException('Fetch failed for unknown reason.');
    }

    private function buildRequest(WatchConfig $config): \Psr\Http\Message\RequestInterface
    {
        $method  = null !== $config->requestBody ? 'POST' : 'GET';
        $request = $this->requestFactory->createRequest($method, $config->url);

        foreach ($config->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ([] !== $config->userAgents) {
            $request = $request->withHeader('User-Agent', $config->userAgents[array_rand($config->userAgents)]);
        }

        if (null !== $config->auth) {
            $request = $request->withHeader('Authorization', $config->auth->headerValue());
        }

        if ([] !== $config->cookies) {
            // Strip CR/LF before concatenating by hand into the header value -
            // most PSR-7 implementations reject them too, but this doesn't
            // rely on that as the only backstop against header injection.
            $cookieHeader = implode('; ', array_map(
                static fn (string $name, string $value) => self::stripCrLf($name).'='.self::stripCrLf($value),
                array_keys($config->cookies),
                array_values($config->cookies),
            ));
            $request = $request->withHeader('Cookie', $cookieHeader);
        }

        if (null !== $config->requestBody && null !== $this->streamFactory) {
            $body = \is_array($config->requestBody)
                ? (json_encode($config->requestBody) ?: '{}')
                : $config->requestBody;

            if (\is_array($config->requestBody) && !$request->hasHeader('Content-Type')) {
                $request = $request->withHeader('Content-Type', 'application/json');
            }

            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        return $request;
    }

    /**
     * Only the delta-seconds form of Retry-After is honored (e.g. "30"), not
     * the HTTP-date form (e.g. "Wed, 21 Oct 2026 07:28:00 GMT") - the former
     * is what rate limiters overwhelmingly send in practice.
     */
    private function retryAfterMs(WatchConfig $config, ResponseInterface $response): ?int
    {
        if (true !== $config->failurePolicy?->respectRetryAfter) {
            return null;
        }

        if (!\in_array($response->getStatusCode(), [429, 503], true)) {
            return null;
        }

        $header = $response->getHeaderLine('Retry-After');
        if ('' === $header || 1 !== preg_match('/^\d+$/', $header)) {
            return null;
        }

        return ((int) $header) * 1000;
    }

    private static function stripCrLf(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }

    private function wait(int $delayMs): void
    {
        usleep($delayMs * 1000);
    }
}
