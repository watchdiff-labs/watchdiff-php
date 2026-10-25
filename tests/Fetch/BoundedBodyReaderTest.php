<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests\Fetch;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Fetch\BoundedBodyReader;

final class BoundedBodyReaderTest extends TestCase
{
    public function testReadsAStreamFullyUnderTheLimit(): void
    {
        $stream = (new Psr17Factory())->createStream('hello world');

        self::assertSame('hello world', BoundedBodyReader::read($stream, maxBytes: 1024));
    }

    public function testThrowsWhenTheStreamExceedsTheLimit(): void
    {
        $stream = (new Psr17Factory())->createStream(str_repeat('a', 2000));

        $this->expectException(\RuntimeException::class);
        BoundedBodyReader::read($stream, maxBytes: 1000);
    }
}
