<?php

declare(strict_types=1);

namespace Shopologic\Core\Http;

use Shopologic\PSR\Http\Message\StreamInterface;

class Stream implements StreamInterface
{
    private $resource;
    private bool $readable;
    private bool $writable;
    private bool $seekable;

    public function __construct($resource = 'php://memory', string $mode = 'r+')
    {
        if (is_string($resource)) {
            $resource = fopen($resource, $mode);
        }

        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('Stream must be a resource or a string resource identifier');
        }

        $this->resource = $resource;
        $meta = stream_get_meta_data($this->resource);
        
        $this->readable = str_contains($meta['mode'], 'r') || str_contains($meta['mode'], '+');
        $this->writable = str_contains($meta['mode'], 'w') || str_contains($meta['mode'], 'a') || str_contains($meta['mode'], 'x') || str_contains($meta['mode'], 'c') || str_contains($meta['mode'], '+');
        $this->seekable = $meta['seekable'];
    }

    public function __toString(): string
    {
        try {
            $this->rewind();
            return $this->getContents();
        } catch (\Exception $e) {
            return '';
        }
    }

    public function close(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
        $this->resource = null;
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;
        
        return $resource;
    }

    public function getSize(): ?int
    {
        if (!is_resource($this->resource)) {
            return null;
        }

        $stats = fstat($this->resource);
        return $stats['size'] ?? null;
    }

    public function tell(): int
    {
        if (!is_resource($this->resource)) {
            throw new \RuntimeException('Stream is detached');
        }

        $position = ftell($this->resource);
        if ($position === false) {
            throw new \RuntimeException('Unable to determine stream position');
        }

        return $position;
    }

    public function eof(): bool
    {
        if (!is_resource($this->resource)) {
            return true;
        }

        return feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new \RuntimeException('Stream is not seekable');
        }

        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new \RuntimeException('Unable to seek to stream position');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new \RuntimeException('Stream is not writable');
        }

        $written = fwrite($this->resource, $string);
        if ($written === false) {
            throw new \RuntimeException('Unable to write to stream');
        }

        return $written;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function read(int $length): string
    {
        if (!$this->isReadable()) {
            throw new \RuntimeException('Stream is not readable');
        }

        $data = fread($this->resource, $length);
        if ($data === false) {
            throw new \RuntimeException('Unable to read from stream');
        }

        return $data;
    }

    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new \RuntimeException('Stream is not readable');
        }

        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null)
    {
        if (!is_resource($this->resource)) {
            return $key ? null : [];
        }

        $metadata = stream_get_meta_data($this->resource);
        
        if ($key === null) {
            return $metadata;
        }

        return $metadata[$key] ?? null;
    }
}