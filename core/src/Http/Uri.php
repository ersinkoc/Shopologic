<?php

declare(strict_types=1);

namespace Shopologic\Core\Http;

use Shopologic\PSR\Http\Message\UriInterface;

class Uri implements UriInterface
{
    private string $scheme = '';
    private string $userInfo = '';
    private string $host = '';
    private ?int $port = null;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';

    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $this->parseUri($uri);
        }
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        $authority = '';

        if ($this->userInfo !== '') {
            $authority .= $this->userInfo . '@';
        }

        $authority .= $this->host;

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): static
    {
        $new = clone $this;
        $new->scheme = strtolower($scheme);
        return $new;
    }

    public function withUserInfo(string $user, ?string $password = null): static
    {
        $new = clone $this;
        $new->userInfo = $user;
        
        if ($password !== null) {
            $new->userInfo .= ':' . $password;
        }
        
        return $new;
    }

    public function withHost(string $host): static
    {
        $new = clone $this;
        $new->host = strtolower($host);
        return $new;
    }

    public function withPort(?int $port): static
    {
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new \InvalidArgumentException('Port must be between 1 and 65535');
        }

        $new = clone $this;
        $new->port = $port;
        return $new;
    }

    public function withPath(string $path): static
    {
        $new = clone $this;
        $new->path = $path;
        return $new;
    }

    public function withQuery(string $query): static
    {
        $new = clone $this;
        $new->query = $query;
        return $new;
    }

    public function withFragment(string $fragment): static
    {
        $new = clone $this;
        $new->fragment = $fragment;
        return $new;
    }

    public function __toString(): string
    {
        $uri = '';

        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        $uri .= $this->path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    private function parseUri(string $uri): void
    {
        $parts = parse_url($uri);

        if ($parts === false) {
            throw new \InvalidArgumentException('Unable to parse URI');
        }

        $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $this->userInfo = $parts['user'] ?? '';
        
        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $parts['pass'];
        }

        $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $this->port = $parts['port'] ?? null;
        $this->path = $parts['path'] ?? '';
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';
    }
}