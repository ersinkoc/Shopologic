<?php

declare(strict_types=1);

namespace Shopologic\Core\Http;

use Shopologic\PSR\Http\Message\MessageInterface;
use Shopologic\PSR\Http\Message\StreamInterface;

abstract class Message implements MessageInterface
{
    protected string $protocolVersion = '1.1';
    protected array $headers = [];
    protected array $headerNames = [];
    protected StreamInterface $body;

    public function __construct(StreamInterface $body = null)
    {
        $this->body = $body ?? new Stream();
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $name = strtolower($name);
        
        if (!isset($this->headerNames[$name])) {
            return [];
        }

        $header = $this->headerNames[$name];
        return $this->headers[$header];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): static
    {
        $normalized = strtolower($name);
        $new = clone $this;

        if (isset($new->headerNames[$normalized])) {
            unset($new->headers[$new->headerNames[$normalized]]);
        }

        $new->headerNames[$normalized] = $name;
        $new->headers[$name] = is_array($value) ? $value : [$value];

        return $new;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $normalized = strtolower($name);
        $new = clone $this;

        if (isset($new->headerNames[$normalized])) {
            $header = $new->headerNames[$normalized];
            $new->headers[$header] = array_merge($new->headers[$header], is_array($value) ? $value : [$value]);
        } else {
            $new->headerNames[$normalized] = $name;
            $new->headers[$name] = is_array($value) ? $value : [$value];
        }

        return $new;
    }

    public function withoutHeader(string $name): static
    {
        $normalized = strtolower($name);
        $new = clone $this;

        if (isset($new->headerNames[$normalized])) {
            $header = $new->headerNames[$normalized];
            unset($new->headers[$header], $new->headerNames[$normalized]);
        }

        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    protected function setHeaders(array $headers): void
    {
        $this->headers = [];
        $this->headerNames = [];

        foreach ($headers as $name => $value) {
            $normalized = strtolower($name);
            $this->headerNames[$normalized] = $name;
            $this->headers[$name] = is_array($value) ? $value : [$value];
        }
    }
}