<?php

declare(strict_types=1);

namespace RunOpenCode\Component\Stream;

use Psr\Http\Message\StreamInterface;

final class Stream implements StreamInterface
{
    /**
     * @var array{
     *     read: array<string, bool>,
     *     write: array<string, bool>,
     * } Hash of readable and writable stream types
     */
    private const array READ_WRITE_HASH = [
        'read'  => [
            'r'   => true, 'w+' => true, 'r+' => true, 'x+' => true, 'c+' => true,
            'rb'  => true, 'w+b' => true, 'r+b' => true, 'x+b' => true,
            'c+b' => true, 'rt' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a+' => true, 'a+b' => true, 'rb+' => true,
        ],
        'write' => [
            'w'   => true, 'w+' => true, 'rw' => true, 'r+' => true, 'x+' => true,
            'c+'  => true, 'wb' => true, 'w+b' => true, 'r+b' => true,
            'x+b' => true, 'c+b' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a' => true, 'a+' => true, 'a+b' => true, 'wb+' => true,
        ],
    ];

    /**
     * @var resource|null
     */
    public private(set) mixed $resource;

    public private(set) bool $seekable;

    public private(set) bool $readable;

    public private(set) bool $writable;

    /**
     * @var array|mixed|void|null
     */
    public private(set) mixed $uri;

    public private(set) ?int $size = null;

    /**
     * @param resource $resource
     */
    public function __construct(mixed $resource)
    {
        \assert(\is_resource($resource), new \RuntimeException(\sprintf(
            'Expected resource, got "%s".',
            \get_debug_type($resource)
        )));

        $this->resource = $resource;
        $meta           = \stream_get_meta_data($resource);
        $this->seekable = $meta['seekable'] && 0 === \fseek($resource, 0, \SEEK_CUR);
        $this->readable = isset(self::READ_WRITE_HASH['read'][$meta['mode']]);
        $this->writable = isset(self::READ_WRITE_HASH['write'][$meta['mode']]);
        $this->uri      = $this->getMetadata('uri');
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->seekable) {
            throw new \RuntimeException('Stream is not seekable');
        }

        if (!isset($this->resource)) {
            throw new \RuntimeException('Stream is closed.');
        }

        if (-1 === \fseek($this->resource, $offset, $whence)) {
            throw new \RuntimeException(\sprintf(
                'Unable to seek to stream position %d with whence %s.',
                $offset,
                \var_export($whence, true),
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $length): string
    {
        if (!isset($this->resource)) {
            throw new \RuntimeException('Stream is closed.');
        }

        if (!$this->readable) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }

        if ($length < 0) {
            throw new \InvalidArgumentException(\sprintf(
                'Expected integer greater or equal to 0, got "%s"',
                \get_debug_type($length)
            ));
        }

        return (string)\fread($this->resource, $length); // @phpstan-ignore-line
    }

    /**
     * {@inheritdoc}
     */
    public function write(\Stringable|string $string): int
    {
        if (!isset($this->resource)) {
            throw new \RuntimeException('Stream is closed.');
        }

        if (!$this->writable) {
            throw new \RuntimeException('Cannot write to a non-writable stream');
        }
        // We can't know the size after writing anything
        $this->size = null;

        if (false === $result = \fwrite($this->resource, (string)$string)) {
            throw new \RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool
    {
        if (!isset($this->resource)) {
            return true;
        }

        return \feof($this->resource) || $this->tell() === $this->getSize();
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int
    {
        if (!isset($this->resource)) {
            throw new \RuntimeException('Stream is closed.');
        }

        if (false === $result = \ftell($this->resource)) {
            throw new \RuntimeException('Unable to determine stream position');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents(): string
    {
        if (!isset($this->resource)) {
            throw new \RuntimeException('Unable to read stream contents');
        }

        if (false === $contents = \stream_get_contents($this->resource)) {
            throw new \RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): ?int
    {
        if (isset($this->size)) {
            return $this->size;
        }

        if (!isset($this->resource)) {
            return null;
        }
        // Clear the stat cache if the stream has a URI
        if (\is_string($this->uri)) {
            \clearstatcache(true, $this->uri);
        }

        /**
         * @var array{
         *     size?: int|string
         * } $stats
         */
        $stats = \fstat($this->resource);

        if (isset($stats['size'])) {
            $this->size = (int)$stats['size'];

            return $this->size;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($key = null)
    {
        if (!isset($this->resource)) {
            return $key ? null : [];
        }

        $meta = \stream_get_meta_data($this->resource);

        if (null === $key) {
            return $meta;
        }
        return $meta[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        if (!isset($this->resource)) {
            return null;
        }

        $result = $this->resource;

        unset($this->resource);

        $this->size     = null;
        $this->uri      = null;
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if (!isset($this->resource)) {
            return;
        }

        if (\is_resource($this->resource)) {
            \fclose($this->resource);
        }

        $this->detach();
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        if (!isset($this->resource)) {
            throw new \RuntimeException('Stream is closed.');
        }

        if ($this->isSeekable()) {
            $this->seek(0);
        }

        return $this->getContents();
    }

    /**
     * Closes the stream when the destructed.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Create in-memory stream for given content.
     *
     * You may provide initial data. If mode is not for append,
     * rewind will be executed on stream.
     */
    public static function memory(string $content = '', string $mode = 'rb+'): self
    {
        $handler = \fopen('php://memory', $mode);

        \assert(\is_resource($handler));

        if ('' !== $content) {
            \fwrite($handler, $content);
        }

        if (!stream_mode_is_appendable($mode)) {
            \rewind($handler);
        }

        return new self($handler);
    }

    /**
     * Create stream from file located on the disk.
     */
    public static function path(string $path, string $mode = 'rb'): self
    {
        $handler = \fopen($path, $mode);

        \assert(\is_resource($handler));

        return new self($handler);
    }
}
