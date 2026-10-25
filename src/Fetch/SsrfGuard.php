<?php

declare(strict_types=1);

namespace WatchDiff\Core\Fetch;

/**
 * Blocks requests to a host that resolves to a loopback, link-local, or
 * private IP range - the common SSRF case where an attacker-controlled URL
 * (a watched page, or a webhook destination) is used to reach internal
 * services. This is a pre-check against the hostname's current resolution,
 * not a guarantee against DNS rebinding (where the name resolves
 * differently between this check and the HTTP client's own connection).
 */
final class SsrfGuard
{
    public static function assertPublicHost(string $url, bool $allowPrivateUrls = false): void
    {
        if ($allowPrivateUrls) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!\is_string($host) || '' === $host) {
            return;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            // DNS resolution failed - gethostbyname() returns the hostname
            // unchanged on failure. Let the HTTP client surface that error.
            return;
        }

        if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new \RuntimeException(sprintf(
                'Refusing to reach "%s": host resolves to a private/reserved IP address (%s). Set allowPrivateUrls: true to allow internal destinations.',
                $url,
                $ip,
            ));
        }
    }
}
