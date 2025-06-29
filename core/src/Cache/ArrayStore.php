<?php

declare(strict_types=1);

namespace Shopologic\Core\Cache;

class ArrayStore implements CacheInterface
{
    private array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            $item = $this->storage[$key];
            
            if ($item['expires_at'] === null || $item['expires_at'] > time()) {
                return $item['value'];
            }
            
            $this->delete($key);
        }

        return $default;
    }

    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $expiresAt = $ttl ? time() + $ttl : null;
        
        $this->storage[$key] = [
            'value' => $value,
            'expires_at' => $expiresAt
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->storage[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->storage = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    public function setMultiple(iterable $values, int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value);
    }

    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    public function flush(): bool
    {
        return $this->clear();
    }
}