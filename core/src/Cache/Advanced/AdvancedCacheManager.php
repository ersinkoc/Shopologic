<?php

declare(strict_types=1);

namespace Shopologic\Core\Cache\Advanced;

use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Events\EventDispatcherInterface;

/**
 * Advanced cache manager with performance optimizations
 */
class AdvancedCacheManager
{
    private array $stores = [];
    private array $config;
    private EventDispatcherInterface $events;
    private CacheWarmer $warmer;
    private CacheInvalidator $invalidator;

    public function __construct(
        array $config,
        EventDispatcherInterface $events,
        CacheWarmer $warmer,
        CacheInvalidator $invalidator
    ) {
        $this->config = array_merge([
            'default' => 'tiered',
            'prefix' => 'shopologic',
            'ttl' => 3600,
            'stores' => [
                'memory' => [
                    'driver' => 'memory',
                    'limit' => 100 * 1024 * 1024 // 100MB
                ],
                'file' => [
                    'driver' => 'file',
                    'path' => 'storage/cache',
                    'compression' => true
                ],
                'tiered' => [
                    'driver' => 'tiered',
                    'stores' => ['memory', 'file']
                ]
            ]
        ], $config);
        
        $this->events = $events;
        $this->warmer = $warmer;
        $this->invalidator = $invalidator;
    }

    /**
     * Get cache store
     */
    public function store(?string $name = null): CacheInterface
    {
        $name = $name ?: $this->config['default'];
        
        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->createStore($name);
        }
        
        return $this->stores[$name];
    }

    /**
     * Cache with automatic key generation
     */
    public function cache(string $key, callable $callback, ?int $ttl = null)
    {
        $key = $this->prefixKey($key);
        $ttl = $ttl ?: $this->config['ttl'];
        
        return $this->store()->remember($key, $ttl, $callback);
    }

    /**
     * Cache query results
     */
    public function cacheQuery(string $query, array $bindings, callable $callback, ?int $ttl = null)
    {
        $key = $this->generateQueryKey($query, $bindings);
        return $this->cache($key, $callback, $ttl);
    }

    /**
     * Cache view rendering
     */
    public function cacheView(string $view, array $data, callable $callback, ?int $ttl = null)
    {
        $key = $this->generateViewKey($view, $data);
        return $this->cache($key, $callback, $ttl);
    }

    /**
     * Cache HTTP response
     */
    public function cacheResponse(string $url, array $headers, callable $callback, ?int $ttl = null)
    {
        $key = $this->generateResponseKey($url, $headers);
        $ttl = $ttl ?: $this->determineTtlFromHeaders($headers);
        
        return $this->cache($key, $callback, $ttl);
    }

    /**
     * Invalidate cache by tags
     */
    public function invalidateTags(array $tags): void
    {
        $this->invalidator->invalidateTags($tags);
        
        $this->events->dispatch('cache.invalidated', [
            'tags' => $tags,
            'timestamp' => time()
        ]);
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidatePattern(string $pattern): void
    {
        $this->invalidator->invalidatePattern($pattern);
        
        $this->events->dispatch('cache.invalidated', [
            'pattern' => $pattern,
            'timestamp' => time()
        ]);
    }

    /**
     * Warm cache
     */
    public function warm(array $keys = []): void
    {
        $this->warmer->warm($keys);
        
        $this->events->dispatch('cache.warmed', [
            'keys' => $keys,
            'timestamp' => time()
        ]);
    }

    /**
     * Get cache statistics
     */
    public function getStatistics(): array
    {
        $stats = [];
        
        foreach ($this->stores as $name => $store) {
            if (method_exists($store, 'getStatistics')) {
                $stats[$name] = $store->getStatistics();
            }
        }
        
        return $stats;
    }

    /**
     * Optimize cache storage
     */
    public function optimize(): void
    {
        foreach ($this->stores as $store) {
            if (method_exists($store, 'optimize')) {
                $store->optimize();
            }
        }
        
        $this->events->dispatch('cache.optimized', [
            'timestamp' => time()
        ]);
    }

    // Private methods

    private function createStore(string $name): CacheInterface
    {
        if (!isset($this->config['stores'][$name])) {
            throw new \Exception("Cache store '{$name}' is not defined.");
        }
        
        $config = $this->config['stores'][$name];
        
        switch ($config['driver']) {
            case 'memory':
                return new MemoryStore($config);
                
            case 'file':
                return new OptimizedFileStore($config);
                
            case 'tiered':
                return new TieredStore($config, $this);
                
            case 'distributed':
                return new DistributedStore($config);
                
            default:
                throw new \Exception("Cache driver '{$config['driver']}' is not supported.");
        }
    }

    private function prefixKey(string $key): string
    {
        return $this->config['prefix'] . ':' . $key;
    }

    private function generateQueryKey(string $query, array $bindings): string
    {
        return 'query:' . md5($query . serialize($bindings));
    }

    private function generateViewKey(string $view, array $data): string
    {
        return 'view:' . md5($view . serialize($data));
    }

    private function generateResponseKey(string $url, array $headers): string
    {
        return 'response:' . md5($url . serialize($headers));
    }

    private function determineTtlFromHeaders(array $headers): int
    {
        // Check Cache-Control header
        if (isset($headers['Cache-Control'])) {
            if (preg_match('/max-age=(\d+)/', $headers['Cache-Control'], $matches)) {
                return (int)$matches[1];
            }
        }
        
        // Check Expires header
        if (isset($headers['Expires'])) {
            $expires = strtotime($headers['Expires']);
            if ($expires > time()) {
                return $expires - time();
            }
        }
        
        return $this->config['ttl'];
    }
}

/**
 * Memory-based cache store with LRU eviction
 */
class MemoryStore implements CacheInterface
{
    private array $data = [];
    private array $metadata = [];
    private int $maxSize;
    private int $currentSize = 0;
    private array $accessOrder = [];

    public function __construct(array $config)
    {
        $this->maxSize = $config['limit'] ?? 100 * 1024 * 1024; // 100MB default
    }

    public function get(string $key, $default = null)
    {
        if (!$this->has($key)) {
            return $default;
        }
        
        // Update access order
        $this->touchKey($key);
        
        return $this->data[$key];
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $size = $this->calculateSize($value);
        
        // Evict items if necessary
        while ($this->currentSize + $size > $this->maxSize && !empty($this->data)) {
            $this->evictLeastRecentlyUsed();
        }
        
        $this->data[$key] = $value;
        $this->metadata[$key] = [
            'size' => $size,
            'expiry' => $ttl ? time() + $ttl : null,
            'hits' => 0
        ];
        
        $this->currentSize += $size;
        $this->touchKey($key);
        
        return true;
    }

    public function delete(string $key): bool
    {
        if (isset($this->data[$key])) {
            $this->currentSize -= $this->metadata[$key]['size'];
            unset($this->data[$key], $this->metadata[$key], $this->accessOrder[$key]);
            return true;
        }
        
        return false;
    }

    public function clear(): bool
    {
        $this->data = [];
        $this->metadata = [];
        $this->accessOrder = [];
        $this->currentSize = 0;
        return true;
    }

    public function getMultiple(array $keys, $default = null): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        return $values;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->data[$key])) {
            return false;
        }
        
        // Check expiry
        if ($this->metadata[$key]['expiry'] !== null && time() > $this->metadata[$key]['expiry']) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }

    public function remember(string $key, int $ttl, callable $callback)
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    public function getStatistics(): array
    {
        return [
            'items' => count($this->data),
            'size' => $this->currentSize,
            'max_size' => $this->maxSize,
            'utilization' => ($this->currentSize / $this->maxSize) * 100,
            'hits' => array_sum(array_column($this->metadata, 'hits'))
        ];
    }

    private function calculateSize($value): int
    {
        return strlen(serialize($value));
    }

    private function touchKey(string $key): void
    {
        $this->accessOrder[$key] = microtime(true);
        $this->metadata[$key]['hits']++;
    }

    private function evictLeastRecentlyUsed(): void
    {
        if (empty($this->accessOrder)) {
            return;
        }
        
        asort($this->accessOrder);
        $key = key($this->accessOrder);
        
        $this->delete($key);
    }
}

/**
 * Optimized file store with compression and indexing
 */
class OptimizedFileStore implements CacheInterface
{
    private string $path;
    private bool $compression;
    private array $index = [];
    private string $indexFile;

    public function __construct(array $config)
    {
        $this->path = rtrim($config['path'], '/');
        $this->compression = $config['compression'] ?? false;
        $this->indexFile = $this->path . '/.index';
        
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
        
        $this->loadIndex();
    }

    public function get(string $key, $default = null)
    {
        if (!$this->has($key)) {
            return $default;
        }
        
        $file = $this->getFilePath($key);
        $content = file_get_contents($file);
        
        if ($this->compression) {
            $content = gzuncompress($content);
        }
        
        $data = unserialize($content);
        
        // Update hit count
        $this->index[$key]['hits']++;
        $this->saveIndex();
        
        return $data['value'];
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $file = $this->getFilePath($key);
        $dir = dirname($file);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $data = [
            'value' => $value,
            'expiry' => $ttl ? time() + $ttl : null
        ];
        
        $content = serialize($data);
        
        if ($this->compression) {
            $content = gzcompress($content, 9);
        }
        
        if (file_put_contents($file, $content, LOCK_EX) !== false) {
            $this->index[$key] = [
                'file' => $file,
                'size' => strlen($content),
                'created' => time(),
                'hits' => 0
            ];
            $this->saveIndex();
            return true;
        }
        
        return false;
    }

    public function delete(string $key): bool
    {
        if (isset($this->index[$key])) {
            $file = $this->index[$key]['file'];
            if (file_exists($file)) {
                unlink($file);
            }
            unset($this->index[$key]);
            $this->saveIndex();
            return true;
        }
        
        return false;
    }

    public function clear(): bool
    {
        foreach ($this->index as $key => $info) {
            if (file_exists($info['file'])) {
                unlink($info['file']);
            }
        }
        
        $this->index = [];
        $this->saveIndex();
        
        // Clean empty directories
        $this->cleanEmptyDirectories($this->path);
        
        return true;
    }

    public function getMultiple(array $keys, $default = null): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        return $values;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->index[$key])) {
            return false;
        }
        
        $file = $this->index[$key]['file'];
        
        if (!file_exists($file)) {
            unset($this->index[$key]);
            $this->saveIndex();
            return false;
        }
        
        // Check expiry
        $content = file_get_contents($file);
        
        if ($this->compression) {
            $content = gzuncompress($content);
        }
        
        $data = unserialize($content);
        
        if ($data['expiry'] !== null && time() > $data['expiry']) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }

    public function remember(string $key, int $ttl, callable $callback)
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    public function optimize(): void
    {
        // Remove expired entries
        foreach ($this->index as $key => $info) {
            if (!$this->has($key)) {
                // Already deleted by has() check
            }
        }
        
        // Reorganize files based on access patterns
        $this->reorganizeFiles();
    }

    public function getStatistics(): array
    {
        $totalSize = array_sum(array_column($this->index, 'size'));
        $totalHits = array_sum(array_column($this->index, 'hits'));
        
        return [
            'items' => count($this->index),
            'size' => $totalSize,
            'hits' => $totalHits,
            'compression' => $this->compression
        ];
    }

    private function getFilePath(string $key): string
    {
        $hash = sha1($key);
        $parts = array_slice(str_split($hash, 2), 0, 2);
        return $this->path . '/' . implode('/', $parts) . '/' . $hash;
    }

    private function loadIndex(): void
    {
        if (file_exists($this->indexFile)) {
            $content = file_get_contents($this->indexFile);
            $this->index = unserialize($content) ?: [];
        }
    }

    private function saveIndex(): void
    {
        file_put_contents($this->indexFile, serialize($this->index), LOCK_EX);
    }

    private function cleanEmptyDirectories(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..', '.index']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->cleanEmptyDirectories($path);
            }
        }
        
        $files = array_diff(scandir($dir), ['.', '..', '.index']);
        if (empty($files) && $dir !== $this->path) {
            rmdir($dir);
        }
    }

    private function reorganizeFiles(): void
    {
        // Group frequently accessed files together
        $hotKeys = [];
        
        foreach ($this->index as $key => $info) {
            if ($info['hits'] > 10) {
                $hotKeys[] = $key;
            }
        }
        
        // Move hot files to optimized location
        foreach ($hotKeys as $key) {
            // Implementation would move files to faster access location
        }
    }
}

/**
 * Tiered cache store
 */
class TieredStore implements CacheInterface
{
    private array $stores = [];
    private array $config;
    private AdvancedCacheManager $manager;

    public function __construct(array $config, AdvancedCacheManager $manager)
    {
        $this->config = $config;
        $this->manager = $manager;
        
        foreach ($config['stores'] as $storeName) {
            $this->stores[] = $manager->store($storeName);
        }
    }

    public function get(string $key, $default = null)
    {
        foreach ($this->stores as $index => $store) {
            $value = $store->get($key);
            
            if ($value !== null) {
                // Promote to higher tiers
                for ($i = 0; $i < $index; $i++) {
                    $this->stores[$i]->set($key, $value);
                }
                
                return $value;
            }
        }
        
        return $default;
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $success = true;
        
        foreach ($this->stores as $store) {
            if (!$store->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }

    public function delete(string $key): bool
    {
        $success = true;
        
        foreach ($this->stores as $store) {
            if (!$store->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }

    public function clear(): bool
    {
        $success = true;
        
        foreach ($this->stores as $store) {
            if (!$store->clear()) {
                $success = false;
            }
        }
        
        return $success;
    }

    public function getMultiple(array $keys, $default = null): array
    {
        $values = [];
        $missing = [];
        
        // Try each tier
        foreach ($this->stores as $index => $store) {
            if (empty($missing) && $index > 0) {
                $missing = array_keys(array_filter($values, fn($v) => $v === null));
            }
            
            $keysToFetch = $index === 0 ? $keys : $missing;
            $tierValues = $store->getMultiple($keysToFetch, $default);
            
            foreach ($tierValues as $key => $value) {
                if ($value !== null && !isset($values[$key])) {
                    $values[$key] = $value;
                    
                    // Promote to higher tiers
                    for ($i = 0; $i < $index; $i++) {
                        $this->stores[$i]->set($key, $value);
                    }
                }
            }
        }
        
        // Fill missing with default
        foreach ($keys as $key) {
            if (!isset($values[$key])) {
                $values[$key] = $default;
            }
        }
        
        return $values;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $success = true;
        
        foreach ($this->stores as $store) {
            if (!$store->setMultiple($values, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }

    public function deleteMultiple(array $keys): bool
    {
        $success = true;
        
        foreach ($this->stores as $store) {
            if (!$store->deleteMultiple($keys)) {
                $success = false;
            }
        }
        
        return $success;
    }

    public function has(string $key): bool
    {
        foreach ($this->stores as $store) {
            if ($store->has($key)) {
                return true;
            }
        }
        
        return false;
    }

    public function remember(string $key, int $ttl, callable $callback)
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
}

/**
 * Cache warmer
 */
class CacheWarmer
{
    private AdvancedCacheManager $cache;
    private array $warmers = [];

    public function registerWarmer(string $name, callable $warmer): void
    {
        $this->warmers[$name] = $warmer;
    }

    public function warm(array $keys = []): void
    {
        if (empty($keys)) {
            // Warm all registered warmers
            foreach ($this->warmers as $name => $warmer) {
                $warmer($this->cache);
            }
        } else {
            // Warm specific keys
            foreach ($keys as $key) {
                if (isset($this->warmers[$key])) {
                    $this->warmers[$key]($this->cache);
                }
            }
        }
    }
}

/**
 * Cache invalidator
 */
class CacheInvalidator
{
    private AdvancedCacheManager $cache;
    private array $taggedKeys = [];

    public function tag(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            if (!isset($this->taggedKeys[$tag])) {
                $this->taggedKeys[$tag] = [];
            }
            $this->taggedKeys[$tag][] = $key;
        }
    }

    public function invalidateTags(array $tags): void
    {
        $keys = [];
        
        foreach ($tags as $tag) {
            if (isset($this->taggedKeys[$tag])) {
                $keys = array_merge($keys, $this->taggedKeys[$tag]);
                unset($this->taggedKeys[$tag]);
            }
        }
        
        $keys = array_unique($keys);
        
        foreach ($keys as $key) {
            $this->cache->store()->delete($key);
        }
    }

    public function invalidatePattern(string $pattern): void
    {
        // Pattern-based invalidation would require store support
        // This is a simplified implementation
        $regex = $this->patternToRegex($pattern);
        
        foreach ($this->taggedKeys as $tag => $keys) {
            foreach ($keys as $key) {
                if (preg_match($regex, $key)) {
                    $this->cache->store()->delete($key);
                }
            }
        }
    }

    private function patternToRegex(string $pattern): string
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        return '/^' . $pattern . '$/';
    }
}