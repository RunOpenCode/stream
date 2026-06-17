<?php

declare(strict_types=1);

namespace RunOpenCode\Component\Stream;

use Psr\Http\Message\StreamInterface;

/**
 * @return resource
 */
function stream_to_resource(StreamInterface $stream)
{
    return StreamWrapper::getResource($stream);
}

/**
 * @return resource
 */
function stream_to_file(StreamInterface $stream, string $path, int $length = 4096)
{
    $resource = \fopen($path, 'wb');

    if (false === $resource) {
        throw new \RuntimeException('Unable to create temporary stream resource.');
    }

    if (0 !== $stream->tell()) {
        $stream->rewind();
    }

    while (!$stream->eof()) {
        \fwrite($resource, $stream->read($length));
    }

    \rewind($resource);

    return $resource;
}

function stream_from_file(\SplFileInfo|string $path, string $mode = 'rb+'): StreamInterface
{
    if ($path instanceof \SplFileInfo) {
        $path = $path->getPathname();
    }

    $handler = \fopen($path, $mode);

    \assert(\is_resource($handler));

    return new Stream($handler);
}

function stream_from_data(string $data, bool $memory = false): StreamInterface
{
    $handler = \fopen($memory ? 'php://memory' : 'php://temp', 'rb+');

    \assert(\is_resource($handler));

    \fwrite($handler, $data);
    \rewind($handler);

    return new Stream($handler);
}

function stream_mode_is_readable(string $mode): bool
{
    return 'r' === $mode[0] || \str_contains($mode, '+');
}

function stream_mode_is_writable(string $mode): bool
{
    return !stream_mode_is_read_only($mode);
}

function stream_mode_is_appendable(string $mode): bool
{
    return 'a' === $mode[0];
}

function stream_mode_is_read_only(string $mode): bool
{
    return 'r' === $mode[0] && !\str_contains($mode, '+');
}

function stream_mode_is_write_only(string $mode): bool
{
    return stream_mode_is_writable($mode) && !stream_mode_is_readable($mode);
}
