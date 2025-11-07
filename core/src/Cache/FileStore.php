<?php

declare(strict_types=1);

namespace Shopologic\Core\Cache;

class FileStore implements CacheInterface
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/');
        
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return $default;
        }

        $contents = file_get_contents($path);

        // SECURITY FIX (BUG-003): Use JSON instead of unserialize to prevent RCE
        $data = json_decode($contents, true);

        // Handle corrupted or invalid cache files
        if ($data === null || !is_array($data) || !isset($data['value'])) {
            $this->delete($key);
            return $default;
        }

        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $path = $this->getPath($key);
        $expiresAt = $ttl ? time() + $ttl : null;

        $data = [
            'value' => $value,
            'expires_at' => $expiresAt
        ];

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // SECURITY FIX (BUG-003): Use JSON instead of serialize to prevent RCE
        $encoded = json_encode($data);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to JSON encode cache data: ' . json_last_error_msg());
        }

        return file_put_contents($path, $encoded, LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $path = $this->getPath($key);

        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->path . '/*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

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
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
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

    protected function getPath(string $key): string
    {
        $hash = hash('sha256', $key);
        return $this->path . '/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
    }
}