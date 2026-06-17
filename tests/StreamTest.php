<?php

declare(strict_types=1);

namespace RunOpenCode\Component\Stream\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RunOpenCode\Component\Stream\Stream;

final class StreamTest extends TestCase
{
    #[Test]
    public function constructor_initializes_properties(): void
    {
        $stream = Stream::memory('data');

        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isSeekable());
        $this->assertEquals('php://memory', $stream->getMetadata('uri'));
        $this->assertIsArray($stream->getMetadata());
        $this->assertEquals(4, $stream->getSize());
        $this->assertFalse($stream->eof());

        $stream->close();
    }

    #[Test]
    public function stream_closes_handle_on_destruct(): void
    {
        $handler = \fopen('php://memory', 'rb');

        $this->assertIsResource($handler);

        $stream = new Stream($handler);

        unset($stream);

        $this->assertFalse(\is_resource($handler));
    }

    #[Test]
    public function cast_to_string(): void
    {
        $stream = Stream::memory('data');

        $this->assertEquals('data', (string)$stream);

        $stream->close();
    }

    #[Test]
    public function throws_exception_when_not_created_from_resource(): void
    {
        $this->expectException(\RuntimeException::class);

        // @phpstan-ignore-next-line
        new Stream('data');
    }

    #[Test]
    public function gets_contents(): void
    {
        $stream = Stream::memory('data', 'a+b');

        $this->assertEquals('', $stream->getContents());

        $stream->seek(0);

        $this->assertEquals('data', $stream->getContents());
        $this->assertEquals('', $stream->getContents());

        $stream->close();
    }

    #[Test]
    public function checks_eof(): void
    {
        $stream = Stream::memory('data');

        $this->assertFalse($stream->eof());

        $stream->read(4);

        $this->assertTrue($stream->eof());

        $stream->close();
    }

    #[Test]
    public function get_size(): void
    {
        $size    = \filesize(__FILE__);
        $handler = \fopen(__FILE__, 'rb');

        \assert(\is_resource($handler));

        $stream = new Stream($handler);

        $this->assertEquals($size, $stream->getSize());

        $stream->close();
    }

    #[Test]
    public function size_is_updated_on_write(): void
    {
        $stream = Stream::memory('foo', 'a+b');

        $this->assertEquals(3, $stream->getSize());
        $this->assertEquals(4, $stream->write('test'));
        $this->assertEquals(7, $stream->getSize());

        $stream->close();
    }

    #[Test]
    public function throws_exception_when_mode_is_not_write(): void
    {
        $this->expectException(\RuntimeException::class);

        /** @var resource $handler */
        $handler = \fopen('php://input', 'rb');

        $this->assertIsResource($handler);

        $stream = new Stream($handler);

        $stream->write('add');
    }

    #[Test]
    public function provides_stream_position(): void
    {
        $stream = Stream::memory();

        $this->assertEquals(0, $stream->tell());

        $stream->write('foo');

        $this->assertEquals(3, $stream->tell());

        $stream->seek(1);

        $this->assertEquals(1, $stream->tell());

        $stream->close();
    }

    #[Test]
    public function detached_stream_is_unusable(): void
    {
        $handler = \fopen('php://memory', 'wb+');

        \assert(\is_resource($handler));

        $stream = new Stream($handler);

        $stream->write('foo');

        $this->assertTrue($stream->isReadable());
        $this->assertSame($handler, $stream->detach());

        $stream->detach();

        $this->assertTrue($stream->eof());
        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertFalse($stream->isSeekable());
        $this->assertNull($stream->getSize());

        /** @var \Closure $throws */
        $throws = \Closure::bind(function(callable $fn) use ($stream): void {
            try {
                $fn($stream);
            } catch (\RuntimeException) {
                return;
            }

            $this->fail('It seams that stream is not detached.');
        }, $this);

        $throws(static function(StreamInterface $stream): void {
            $stream->read(10);
        });

        $throws(static function(StreamInterface $stream): void {
            $stream->write('bar');
        });

        $throws(static function(StreamInterface $stream): void {
            $stream->seek(10);
        });

        $throws(static function(StreamInterface $stream): void {
            $stream->tell();
        });

        $throws(static function(StreamInterface $stream): void {
            $stream->getContents();
        });

        $stream->close();
    }

    #[Test]
    public function close_clear_properties(): void
    {
        $stream = Stream::memory();

        $stream->close();

        $this->assertFalse($stream->isSeekable());
        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertNull($stream->getSize());
        $this->assertEmpty($stream->getMetadata());
    }

    #[Test]
    public function rewind(): void
    {
        $stream = Stream::memory('data', 'a+b');

        $this->assertEquals('', $stream->getContents());

        $stream->rewind();

        $this->assertEquals('data', $stream->getContents());
        $this->assertEquals('', $stream->getContents());

        $stream->close();
    }

    #[Test]
    public function throws_exception_when_mode_is_not_readable(): void
    {
        $this->expectException(\RuntimeException::class);

        /** @var resource $handler */
        $handler = \fopen('php://output', 'wb');

        $this->assertIsResource($handler);

        $stream = new Stream($handler);

        $stream->read(2);
    }

    #[Test]
    public function create_stream_from_path(): void
    {
        $stream = Stream::path(__FILE__);

        $this->assertStringEqualsFile(__FILE__, $stream->getContents());

        $stream->close();
    }

    #[Test]
    public function cas_to_string_throws_exception_when_stream_is_closed(): void
    {
        $stream = Stream::memory('foo', 'a+b');
        $stream->close();

        $this->expectException(\RuntimeException::class);

        (string)$stream;
    }
}
