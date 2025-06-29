<?php

declare(strict_types=1);

namespace Shopologic\Core\Monitoring;

use Shopologic\Core\Configuration\ConfigurationManager;

/**
 * Cache Metrics Collector
 * 
 * Collects cache performance and usage metrics
 */
class CacheMetricsCollector implements MetricsCollectorInterface
{
    private ConfigurationManager $config;
    
    public function __construct()
    {
        $this->config = new ConfigurationManager();
    }
    
    /**
     * Collect cache metrics
     */
    public function collect(): array
    {
        return [
            'redis' => $this->getRedisMetrics(),
            'opcache' => $this->getOpcacheMetrics(),
            'apcu' => $this->getApcuMetrics(),
            'application' => $this->getApplicationCacheMetrics(),
            'performance' => $this->getCachePerformanceMetrics()
        ];
    }
    
    /**
     * Get Redis cache metrics
     */
    private function getRedisMetrics(): array
    {
        $metrics = [
            'connected' => false,
            'server_info' => [],
            'memory' => [],
            'stats' => [],
            'keyspace' => []
        ];
        
        try {
            $redis = new \Redis();
            $connected = $redis->connect(
                $this->config->get('cache.redis.host', 'localhost'),
                $this->config->get('cache.redis.port', 6379),
                2.0 // 2 second timeout
            );
            
            if (!$connected) {
                return $metrics;
            }
            
            $metrics['connected'] = true;
            
            // Get server info
            $info = $redis->info();
            
            if (isset($info['redis_version'])) {
                $metrics['server_info'] = [
                    'version' => $info['redis_version'],
                    'mode' => $info['redis_mode'] ?? 'standalone',
                    'role' => $info['role'] ?? 'master',
                    'uptime_seconds' => (int)($info['uptime_in_seconds'] ?? 0),
                    'connected_clients' => (int)($info['connected_clients'] ?? 0),
                    'blocked_clients' => (int)($info['blocked_clients'] ?? 0),
                    'rejected_connections' => (int)($info['rejected_connections'] ?? 0)
                ];
            }
            
            // Memory usage
            if (isset($info['used_memory'])) {
                $metrics['memory'] = [
                    'used_memory' => (int)$info['used_memory'],
                    'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
                    'used_memory_rss' => (int)($info['used_memory_rss'] ?? 0),
                    'used_memory_peak' => (int)($info['used_memory_peak'] ?? 0),
                    'maxmemory' => (int)($info['maxmemory'] ?? 0),
                    'memory_fragmentation_ratio' => (float)($info['mem_fragmentation_ratio'] ?? 0)
                ];
            }
            
            // Performance statistics
            if (isset($info['total_commands_processed'])) {
                $metrics['stats'] = [
                    'total_commands_processed' => (int)$info['total_commands_processed'],
                    'total_connections_received' => (int)($info['total_connections_received'] ?? 0),
                    'instantaneous_ops_per_sec' => (int)($info['instantaneous_ops_per_sec'] ?? 0),
                    'keyspace_hits' => (int)($info['keyspace_hits'] ?? 0),
                    'keyspace_misses' => (int)($info['keyspace_misses'] ?? 0),
                    'expired_keys' => (int)($info['expired_keys'] ?? 0),
                    'evicted_keys' => (int)($info['evicted_keys'] ?? 0)
                ];
                
                // Calculate hit ratio
                $hits = $metrics['stats']['keyspace_hits'];
                $misses = $metrics['stats']['keyspace_misses'];
                $total = $hits + $misses;
                
                if ($total > 0) {
                    $metrics['stats']['hit_ratio'] = round(($hits / $total) * 100, 2);
                } else {
                    $metrics['stats']['hit_ratio'] = 0;
                }
            }
            
            // Keyspace information
            for ($db = 0; $db < 16; $db++) {
                $keyspaceKey = "db{$db}";
                if (isset($info[$keyspaceKey])) {
                    // Parse keyspace info like "keys=1000,expires=100,avg_ttl=3600000"
                    $keyspaceInfo = $info[$keyspaceKey];
                    if (preg_match('/keys=(\d+),expires=(\d+),avg_ttl=(\d+)/', $keyspaceInfo, $matches)) {
                        $metrics['keyspace'][$db] = [
                            'keys' => (int)$matches[1],
                            'expires' => (int)$matches[2],
                            'avg_ttl' => (int)$matches[3]
                        ];
                    }
                }
            }
            
            $redis->close();
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get OPcache metrics
     */
    private function getOpcacheMetrics(): array
    {
        if (!extension_loaded('Zend OPcache')) {
            return ['enabled' => false, 'message' => 'OPcache extension not loaded'];
        }
        
        if (!function_exists('opcache_get_status')) {
            return ['enabled' => false, 'message' => 'OPcache functions not available'];
        }
        
        $status = opcache_get_status();
        
        if (!$status || !$status['opcache_enabled']) {
            return ['enabled' => false, 'message' => 'OPcache is disabled'];
        }
        
        $config = opcache_get_configuration();
        
        $metrics = [
            'enabled' => true,
            'version' => $config['version']['version'] ?? 'unknown',
            'configuration' => [
                'memory_consumption' => $config['directives']['opcache.memory_consumption'] ?? 0,
                'interned_strings_buffer' => $config['directives']['opcache.interned_strings_buffer'] ?? 0,
                'max_accelerated_files' => $config['directives']['opcache.max_accelerated_files'] ?? 0,
                'max_wasted_percentage' => $config['directives']['opcache.max_wasted_percentage'] ?? 0,
                'validate_timestamps' => $config['directives']['opcache.validate_timestamps'] ?? false,
                'revalidate_freq' => $config['directives']['opcache.revalidate_freq'] ?? 0
            ],
            'memory_usage' => $status['memory_usage'] ?? [],
            'statistics' => $status['opcache_statistics'] ?? [],
            'jit' => $status['jit'] ?? []
        ];
        
        // Calculate derived metrics
        if (isset($metrics['memory_usage']['used_memory']) && isset($metrics['memory_usage']['free_memory'])) {
            $used = $metrics['memory_usage']['used_memory'];
            $free = $metrics['memory_usage']['free_memory'];
            $total = $used + $free;
            
            if ($total > 0) {
                $metrics['memory_usage']['usage_percent'] = round(($used / $total) * 100, 2);
            }
        }
        
        // Calculate hit ratio
        if (isset($metrics['statistics']['hits']) && isset($metrics['statistics']['misses'])) {
            $hits = $metrics['statistics']['hits'];
            $misses = $metrics['statistics']['misses'];
            $total = $hits + $misses;
            
            if ($total > 0) {
                $metrics['statistics']['hit_ratio'] = round(($hits / $total) * 100, 2);
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get APCu metrics
     */
    private function getApcuMetrics(): array
    {
        if (!extension_loaded('apcu')) {
            return ['enabled' => false, 'message' => 'APCu extension not loaded'];
        }
        
        if (!function_exists('apcu_cache_info')) {
            return ['enabled' => false, 'message' => 'APCu functions not available'];
        }
        
        try {
            $info = apcu_cache_info();
            $sma = apcu_sma_info();
            
            $metrics = [
                'enabled' => true,
                'version' => phpversion('apcu'),
                'cache_info' => [
                    'num_slots' => $info['num_slots'] ?? 0,
                    'num_hits' => $info['num_hits'] ?? 0,
                    'num_misses' => $info['num_misses'] ?? 0,
                    'num_inserts' => $info['num_inserts'] ?? 0,
                    'num_entries' => $info['num_entries'] ?? 0,
                    'expunges' => $info['expunges'] ?? 0,
                    'start_time' => $info['start_time'] ?? 0,
                    'mem_size' => $info['mem_size'] ?? 0
                ],
                'memory_info' => [
                    'num_seg' => $sma['num_seg'] ?? 0,
                    'seg_size' => $sma['seg_size'] ?? 0,
                    'avail_mem' => $sma['avail_mem'] ?? 0
                ]
            ];
            
            // Calculate hit ratio
            $hits = $metrics['cache_info']['num_hits'];
            $misses = $metrics['cache_info']['num_misses'];
            $total = $hits + $misses;
            
            if ($total > 0) {
                $metrics['cache_info']['hit_ratio'] = round(($hits / $total) * 100, 2);
            } else {
                $metrics['cache_info']['hit_ratio'] = 0;
            }
            
            // Calculate memory usage
            $segSize = $metrics['memory_info']['seg_size'];
            $availMem = $metrics['memory_info']['avail_mem'];
            
            if ($segSize > 0) {
                $usedMem = $segSize - $availMem;
                $metrics['memory_info']['used_mem'] = $usedMem;
                $metrics['memory_info']['usage_percent'] = round(($usedMem / $segSize) * 100, 2);
            }
            
        } catch (\Exception $e) {
            return ['enabled' => false, 'error' => $e->getMessage()];
        }
        
        return $metrics;
    }
    
    /**
     * Get application cache metrics
     */
    private function getApplicationCacheMetrics(): array
    {
        $metrics = [
            'hit_count' => 0,
            'miss_count' => 0,
            'set_count' => 0,
            'delete_count' => 0,
            'hit_ratio' => 0,
            'average_get_time' => 0,
            'average_set_time' => 0
        ];
        
        // Try to get application cache statistics
        $cacheStatsFile = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT . '/storage/cache/stats.json' : null;
        
        if ($cacheStatsFile && file_exists($cacheStatsFile)) {
            try {
                $stats = json_decode(file_get_contents($cacheStatsFile), true);
                
                if ($stats) {
                    $metrics = array_merge($metrics, $stats);
                    
                    // Calculate hit ratio
                    $total = $metrics['hit_count'] + $metrics['miss_count'];
                    if ($total > 0) {
                        $metrics['hit_ratio'] = round(($metrics['hit_count'] / $total) * 100, 2);
                    }
                }
                
            } catch (\Exception $e) {
                $metrics['error'] = 'Failed to read cache stats: ' . $e->getMessage();
            }
        }
        
        // Get cache directory size
        $cacheDir = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT . '/storage/cache' : null;
        
        if ($cacheDir && is_dir($cacheDir)) {
            $metrics['cache_size'] = $this->getDirectorySize($cacheDir);
            $metrics['file_count'] = $this->countFiles($cacheDir);
        }
        
        return $metrics;
    }
    
    /**
     * Get cache performance metrics
     */
    private function getCachePerformanceMetrics(): array
    {
        $metrics = [
            'test_results' => []
        ];
        
        // Performance test for different cache operations
        $testData = str_repeat('x', 1024); // 1KB test data
        $iterations = 100;
        
        // Test Redis performance if available
        try {
            $redis = new \Redis();
            $connected = $redis->connect(
                $this->config->get('cache.redis.host', 'localhost'),
                $this->config->get('cache.redis.port', 6379),
                1.0
            );
            
            if ($connected) {
                $testKey = 'perf_test_' . time();
                
                // Test SET operations
                $start = microtime(true);
                for ($i = 0; $i < $iterations; $i++) {
                    $redis->set($testKey . $i, $testData, 60);
                }
                $setTime = (microtime(true) - $start) * 1000;
                
                // Test GET operations
                $start = microtime(true);
                for ($i = 0; $i < $iterations; $i++) {
                    $redis->get($testKey . $i);
                }
                $getTime = (microtime(true) - $start) * 1000;
                
                // Test DELETE operations
                $start = microtime(true);
                for ($i = 0; $i < $iterations; $i++) {
                    $redis->del($testKey . $i);
                }
                $deleteTime = (microtime(true) - $start) * 1000;
                
                $metrics['test_results']['redis'] = [
                    'set_time_ms' => round($setTime, 2),
                    'get_time_ms' => round($getTime, 2),
                    'delete_time_ms' => round($deleteTime, 2),
                    'avg_set_time_ms' => round($setTime / $iterations, 4),
                    'avg_get_time_ms' => round($getTime / $iterations, 4),
                    'avg_delete_time_ms' => round($deleteTime / $iterations, 4)
                ];
                
                $redis->close();
            }
            
        } catch (\Exception $e) {
            $metrics['test_results']['redis'] = ['error' => $e->getMessage()];
        }
        
        // Test APCu performance if available
        if (extension_loaded('apcu') && function_exists('apcu_store')) {
            try {
                $testKey = 'perf_test_' . time();
                
                // Test STORE operations
                $start = microtime(true);
                for ($i = 0; $i < $iterations; $i++) {
                    apcu_store($testKey . $i, $testData, 60);
                }
                $storeTime = (microtime(true) - $start) * 1000;
                
                // Test FETCH operations
                $start = microtime(true);
                for ($i = 0; $i < $iterations; $i++) {
                    apcu_fetch($testKey . $i);
                }
                $fetchTime = (microtime(true) - $start) * 1000;
                
                // Test DELETE operations
                $start = microtime(true);
                for ($i = 0; $i < $iterations; $i++) {
                    apcu_delete($testKey . $i);
                }
                $deleteTime = (microtime(true) - $start) * 1000;
                
                $metrics['test_results']['apcu'] = [
                    'store_time_ms' => round($storeTime, 2),
                    'fetch_time_ms' => round($fetchTime, 2),
                    'delete_time_ms' => round($deleteTime, 2),
                    'avg_store_time_ms' => round($storeTime / $iterations, 4),
                    'avg_fetch_time_ms' => round($fetchTime / $iterations, 4),
                    'avg_delete_time_ms' => round($deleteTime / $iterations, 4)
                ];
                
            } catch (\Exception $e) {
                $metrics['test_results']['apcu'] = ['error' => $e->getMessage()];
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get directory size recursively
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Exception $e) {
            // Directory not accessible
        }
        
        return $size;
    }
    
    /**
     * Count files in directory recursively
     */
    private function countFiles(string $directory): int
    {
        $count = 0;
        
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        } catch (\Exception $e) {
            // Directory not accessible
        }
        
        return $count;
    }
}