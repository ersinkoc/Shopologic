<?php

declare(strict_types=1);

namespace Shopologic\PSR\Http\Message;

interface UriInterface
{
    public function getScheme(): string;
    public function getAuthority(): string;
    public function getUserInfo(): string;
    public function getHost(): string;
    public function getPort(): ?int;
    public function getPath(): string;
    public function getQuery(): string;
    public function getFragment(): string;
    public function withScheme(string $scheme): static;
    public function withUserInfo(string $user, ?string $password = null): static;
    public function withHost(string $host): static;
    public function withPort(?int $port): static;
    public function withPath(string $path): static;
    public function withQuery(string $query): static;
    public function withFragment(string $fragment): static;
    public function __toString(): string;
}