<?php

declare(strict_types=1);

namespace RunOpenCode\Component\Stream\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RunOpenCode\Component\Stream\Stream;

use function RunOpenCode\Component\Stream\stream_from_data;
use function RunOpenCode\Component\Stream\stream_from_file;
use function RunOpenCode\Component\Stream\stream_mode_is_readable;
use function RunOpenCode\Component\Stream\stream_mode_is_writable;
use function RunOpenCode\Component\Stream\stream_mode_is_write_only;
use function RunOpenCode\Component\Stream\stream_to_resource;

final class FunctionsTest extends TestCase
{
    #[Test]
    public function stream_to_resource(): void
    {
        $stream   = stream_from_data('data');
        $resource = stream_to_resource($stream);

        $this->assertIsResource($resource);

        $stream->close();
    }

    #[Test]
    public function stream_to_resource_rewinds(): void
    {
        $stream = stream_from_data('data');

        // move stream pointer forward
        $stream->seek(2);
        $this->assertSame(2, $stream->tell());

        $resource = stream_to_resource($stream);

        $this->assertIsResource($resource);
        $this->assertSame(0, \ftell($resource));
        $this->assertSame('data', (new Stream($resource))->getContents());

        $stream->close();
    }

    #[Test]
    #[DataProvider('getValueForStreamFromFile')]
    public function stream_from_file(\SplFileInfo|string $file): void
    {
        $stream = stream_from_file($file);

        $this->assertSame(0, $stream->tell());
        $this->assertStringEqualsFile(__FILE__, $stream->getContents());

        $stream->close();
    }

    /**
     * @return iterable<string, array{string|\SplFileInfo}>
     */
    public static function getValueForStreamFromFile(): iterable
    {
        yield 'Get stream from file path' => [__FILE__];
        yield 'Get stream from SplFileInfo' => [new \SplFileInfo(__FILE__)];
    }

    #[Test]
    public function stream_from_data(): void
    {
        $stream = stream_from_data('data');

        $this->assertSame(0, $stream->tell());
        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isSeekable());
        $this->assertEquals('php://temp', $stream->getMetadata('uri'));
        $this->assertIsArray($stream->getMetadata());
        $this->assertEquals(4, $stream->getSize());
        $this->assertFalse($stream->eof());

        $stream->close();
    }

    #[Test]
    #[DataProvider('getValueForModeIsReadable')]
    public function stream_mode_is_readable(string $mode): void
    {
        $this->assertTrue(stream_mode_is_readable($mode));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function getValueForModeIsReadable(): iterable
    {
        yield 'Stream mode is readable when mode start with "r"' => ['rwb'];
        yield 'Stream mode is readable when mode contain "+"' => ['w+b'];
    }

    #[Test]
    #[DataProvider('getValueForModeIsWritable')]
    public function stream_mode_is_writable(string $mode): void
    {
        $this->assertTrue(stream_mode_is_writable($mode));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function getValueForModeIsWritable(): iterable
    {
        yield 'Stream mode is writable when mode not start with "r"' => ['wb'];
        yield 'Stream mode is writable when mode start with "r" and content "+"' => ['r+b'];
        yield 'Stream mode is writable when mode not content "+"' => ['ab'];
    }

    #[Test]
    #[DataProvider('getDataForWriteOnly')]
    public function stream_mode_is_write_only(string $mode): void
    {
        $this->assertTrue(stream_mode_is_write_only($mode));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function getDataForWriteOnly(): iterable
    {
        yield 'Mode for fopen when is "ab"' => ['ab'];
        yield 'Mode for fopen when is "wb"' => ['wb'];
        yield 'Mode for fopen when is "cb"' => ['cb'];
    }
}
