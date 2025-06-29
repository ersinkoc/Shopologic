<?php

declare(strict_types=1);

namespace Shopologic\Core\Http;

use Shopologic\PSR\Http\Message\RequestInterface;
use Shopologic\PSR\Http\Message\UriInterface;
use Shopologic\PSR\Http\Message\StreamInterface;

class Request extends Message implements RequestInterface
{
    private string $method;
    private string $requestTarget = '';
    private UriInterface $uri;
    private array $attributes = [];

    public function __construct(
        string $method,
        UriInterface $uri,
        array $headers = [],
        StreamInterface $body = null,
        string $protocolVersion = '1.1'
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->protocolVersion = $protocolVersion;
        
        parent::__construct($body);
        $this->setHeaders($headers);
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== '') {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }

        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): static
    {
        $new = clone $this;
        $new->method = strtoupper($method);
        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost || !$new->hasHeader('Host')) {
            $host = $uri->getHost();
            if ($host !== '') {
                $port = $uri->getPort();
                if ($port !== null) {
                    $host .= ':' . $port;
                }
                $new = $new->withHeader('Host', $host);
            }
        }

        return $new;
    }
    
    public function getQueryParams(): array
    {
        $query = $this->uri->getQuery();
        if (empty($query)) {
            return [];
        }
        
        parse_str($query, $params);
        return $params;
    }
    
    public function getParsedBody(): ?array
    {
        $contentType = $this->getHeaderLine('Content-Type');
        
        if (str_contains($contentType, 'application/json')) {
            $body = (string) $this->getBody();
            return json_decode($body, true);
        }
        
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $body = (string) $this->getBody();
            parse_str($body, $params);
            return $params;
        }
        
        return null;
    }
    
    public function getAttribute(string $name, $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }
    
    public function withAttribute(string $name, $value): self
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }
    
    public function getServerParam(string $name, $default = null): mixed
    {
        return $_SERVER[$name] ?? $default;
    }
    
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }
}