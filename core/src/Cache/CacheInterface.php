<?php

declare(strict_types=1);

namespace Shopologic\Core\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $ttl = null): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function getMultiple(iterable $keys, mixed $default = null): iterable;
    public function setMultiple(iterable $values, int $ttl = null): bool;
    public function deleteMultiple(iterable $keys): bool;
    public function has(string $key): bool;
    public function remember(string $key, int $ttl, callable $callback): mixed;
    public function forever(string $key, mixed $value): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
    public function tags(array $tags): self;
}