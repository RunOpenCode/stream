<?php

declare(strict_types=1);

namespace RunOpenCode\Component\Stream;

use Psr\Http\Message\StreamInterface;

/**
 * Implementation taken from: https://github.com/guzzle/psr7/blob/2.12/src/StreamWrapper.php
 */
final class StreamWrapper
{
    private const string PROTOCOL = 'roc';

    /**
     * @var resource
     */
    public $context;

    private StreamInterface $stream;

    private string $mode;

    /**
     * Returns a resource representing the stream.
     *
     * @param StreamInterface $stream The stream to get a resource for
     *
     * @return resource
     *
     * @throws \InvalidArgumentException if stream is not readable or writable
     */
    public static function getResource(StreamInterface $stream)
    {
        if (!\in_array(self::PROTOCOL, \stream_get_wrappers(), true)) {
            \stream_wrapper_register(self::PROTOCOL, __CLASS__);
        }

        if ($stream->isReadable()) {
            $mode = $stream->isWritable() ? 'r+' : 'r';
        } elseif ($stream->isWritable()) {
            $mode = 'w';
        } else {
            throw new \InvalidArgumentException('The stream must be readable, writable, or both.');
        }

        $resource = @\fopen(
            \sprintf('%s://stream', self::PROTOCOL),
            $mode,
            false,
            \stream_context_create([
                self::PROTOCOL => ['stream' => $stream],
            ])
        );

        if ($resource === false) {
            throw new \RuntimeException('Unable to create stream resource.');
        }

        return $resource;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path = null): bool
    {
        /**
         * @var array{
         *      roc?: array{
         *          stream?: StreamInterface
         *      }
         * } $contextOptions
         */
        $contextOptions = \stream_context_get_options($this->context);

        if (!isset($contextOptions[self::PROTOCOL]['stream'])) {
            return false;
        }

        $this->mode   = $mode;
        $this->stream = $contextOptions[self::PROTOCOL]['stream'];

        return true;
    }

    public function stream_read(int $count): string
    {
        return $this->stream->read($count);
    }

    public function stream_write(string $data): int
    {
        return $this->stream->write($data);
    }

    public function stream_tell(): int
    {
        return $this->stream->tell();
    }

    public function stream_eof(): bool
    {
        return $this->stream->eof();
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        $this->stream->seek($offset, $whence);

        return true;
    }

    /**
     * @return resource|false
     */
    public function stream_cast(int $cast_as)
    {
        $stream   = clone $this->stream;
        $resource = $stream->detach();

        return $resource ?? false;
    }

    /**
     * @return array{
     *   dev: int,
     *   ino: int,
     *   mode: int,
     *   nlink: int,
     *   uid: int,
     *   gid: int,
     *   rdev: int,
     *   size: int,
     *   atime: int,
     *   mtime: int,
     *   ctime: int,
     *   blksize: int,
     *   blocks: int
     * }|false
     */
    public function stream_stat(): false|array
    {
        if ($this->stream->getSize() === null) {
            return false;
        }

        /**
         * @var array<string, int> $modeMap
         */
        static $modeMap = [
            'r'  => 33060,
            'rb' => 33060,
            'r+' => 33206,
            'w'  => 33188,
            'wb' => 33188,
        ];

        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => $modeMap[$this->mode],
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => $this->stream->getSize() ?: 0,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0,
        ];
    }

    /**
     * @return array{
     *   dev: int,
     *   ino: int,
     *   mode: int,
     *   nlink: int,
     *   uid: int,
     *   gid: int,
     *   rdev: int,
     *   size: int,
     *   atime: int,
     *   mtime: int,
     *   ctime: int,
     *   blksize: int,
     *   blocks: int
     * }
     */
    public function url_stat(string $path, int $flags): array
    {
        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => 0,
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => 0,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0,
        ];
    }
}
