<?php

declare(strict_types=1);

namespace WatchDiff\Core\Fetch;

use Psr\Http\Message\StreamInterface;

/**
 * Reads an HTTP response body with a hard size cap, instead of the usual
 * `(string) $response->getBody()` (which calls getContents() with no limit).
 * A malicious or compromised monitored endpoint could otherwise serve an
 * arbitrarily large response and exhaust the watcher process's memory.
 */
final class BoundedBodyReader
{
    public const DEFAULT_MAX_BYTES = 10 * 1024 * 1024; // 10 MiB

    public static function read(StreamInterface $stream, int $maxBytes = self::DEFAULT_MAX_BYTES): string
    {
        if ($stream->isSeekable()) {
            try {
                $stream->rewind();
            } catch (\RuntimeException) {
                // some streams report seekable but still refuse to rewind; fall through
            }
        }

        $buffer    = '';
        $chunkSize = 64 * 1024;

        while (!$stream->eof()) {
            $buffer .= $stream->read($chunkSize);

            if (\strlen($buffer) > $maxBytes) {
                throw new \RuntimeException(sprintf(
                    'Response body exceeded the %d byte limit.',
                    $maxBytes,
                ));
            }
        }

        return $buffer;
    }
}
