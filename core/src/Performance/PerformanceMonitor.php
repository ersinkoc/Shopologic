<?php

declare(strict_types=1);

namespace Shopologic\Core\Performance;

use Shopologic\Core\Database\DB;
use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Events\EventDispatcherInterface;

/**
 * Performance monitoring and optimization system
 */
class PerformanceMonitor
{
    private DB $db;
    private CacheInterface $cache;
    private EventDispatcherInterface $events;
    private array $config;
    private array $metrics = [];
    private float $startTime;
    private int $startMemory;

    public function __construct(
        DB $db,
        CacheInterface $cache,
        EventDispatcherInterface $events,
        array $config = []
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->events = $events;
        $this->config = array_merge([
            'enabled' => true,
            'slow_query_threshold' => 100, // milliseconds
            'memory_limit_warning' => 80, // percentage
            'track_queries' => true,
            'track_cache' => true,
            'track_http' => true,
            'sampling_rate' => 1.0
        ], $config);
        
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Start timing an operation
     */
    public function startTimer(string $name): Timer
    {
        return new Timer($name, $this);
    }

    /**
     * Record a metric
     */
    public function record(string $type, string $name, float $duration, array $metadata = []): void
    {
        if (!$this->shouldSample()) {
            return;
        }
        
        $metric = [
            'type' => $type,
            'name' => $name,
            'duration' => $duration,
            'memory_usage' => memory_get_usage(true),
            'cpu_usage' => $this->getCpuUsage(),
            'metadata' => $metadata,
            'recorded_at' => microtime(true)
        ];
        
        $this->metrics[] = $metric;
        
        // Check thresholds
        $this->checkThresholds($metric);
        
        // Store if needed
        if ($this->shouldStore($metric)) {
            $this->storeMetric($metric);
        }
    }

    /**
     * Track database query
     */
    public function trackQuery(string $query, array $bindings, float $duration): void
    {
        if (!$this->config['track_queries']) {
            return;
        }
        
        $this->record('query', $this->getQueryType($query), $duration, [
            'query' => $query,
            'bindings' => $bindings
        ]);
        
        // Log slow queries
        if ($duration > $this->config['slow_query_threshold']) {
            $this->logSlowQuery($query, $bindings, $duration);
        }
    }

    /**
     * Track cache operation
     */
    public function trackCache(string $operation, string $key, bool $hit = true): void
    {
        if (!$this->config['track_cache']) {
            return;
        }
        
        $this->record('cache', $operation, 0, [
            'key' => $key,
            'hit' => $hit
        ]);
    }

    /**
     * Track HTTP request
     */
    public function trackHttpRequest(string $method, string $url, float $duration, int $statusCode): void
    {
        if (!$this->config['track_http']) {
            return;
        }
        
        $this->record('http', $method . ' ' . $url, $duration, [
            'method' => $method,
            'url' => $url,
            'status_code' => $statusCode
        ]);
    }

    /**
     * Get current performance metrics
     */
    public function getMetrics(): array
    {
        $totalTime = (microtime(true) - $this->startTime) * 1000;
        $totalMemory = memory_get_usage(true) - $this->startMemory;
        
        return [
            'total_time' => $totalTime,
            'total_memory' => $totalMemory,
            'peak_memory' => memory_get_peak_usage(true),
            'metrics' => $this->metrics,
            'summary' => $this->generateSummary()
        ];
    }

    /**
     * Generate performance report
     */
    public function generateReport(string $period = 'hour'): array
    {
        $startTime = $this->getPeriodStartTime($period);
        
        return [
            'overview' => $this->getOverviewMetrics($startTime),
            'slow_queries' => $this->getSlowQueries($startTime),
            'cache_performance' => $this->getCachePerformance($startTime),
            'endpoint_performance' => $this->getEndpointPerformance($startTime),
            'resource_usage' => $this->getResourceUsage($startTime),
            'bottlenecks' => $this->identifyBottlenecks($startTime)
        ];
    }

    /**
     * Optimize performance based on metrics
     */
    public function optimize(): array
    {
        $optimizations = [];
        
        // Query optimization
        $slowQueries = $this->identifySlowQueries();
        if (!empty($slowQueries)) {
            $optimizations['queries'] = $this->optimizeQueries($slowQueries);
        }
        
        // Cache optimization
        $cacheIssues = $this->identifyCacheIssues();
        if (!empty($cacheIssues)) {
            $optimizations['cache'] = $this->optimizeCache($cacheIssues);
        }
        
        // Memory optimization
        $memoryIssues = $this->identifyMemoryIssues();
        if (!empty($memoryIssues)) {
            $optimizations['memory'] = $this->optimizeMemory($memoryIssues);
        }
        
        return $optimizations;
    }

    /**
     * Clear performance data
     */
    public function clear(string $olderThan = '7 days'): int
    {
        $date = date('Y-m-d H:i:s', strtotime('-' . $olderThan));
        
        $deleted = 0;
        
        $deleted += $this->db->table('performance_metrics')
            ->where('recorded_at', '<', $date)
            ->delete();
        
        $deleted += $this->db->table('slow_queries')
            ->where('executed_at', '<', $date)
            ->delete();
        
        return $deleted;
    }

    // Private methods

    private function shouldSample(): bool
    {
        return mt_rand() / mt_getrandmax() <= $this->config['sampling_rate'];
    }

    private function shouldStore(array $metric): bool
    {
        // Store all slow operations
        if ($metric['duration'] > $this->config['slow_query_threshold']) {
            return true;
        }
        
        // Sample other operations
        return $this->shouldSample();
    }

    private function storeMetric(array $metric): void
    {
        $this->db->table('performance_metrics')->insert([
            'type' => $metric['type'],
            'name' => $metric['name'],
            'duration' => $metric['duration'],
            'memory_usage' => $metric['memory_usage'],
            'cpu_usage' => $metric['cpu_usage'],
            'metadata' => json_encode($metric['metadata']),
            'recorded_at' => date('Y-m-d H:i:s', (int)$metric['recorded_at'])
        ]);
    }

    private function checkThresholds(array $metric): void
    {
        // Check memory usage
        $memoryUsage = (memory_get_usage(true) / ini_get('memory_limit')) * 100;
        if ($memoryUsage > $this->config['memory_limit_warning']) {
            $this->events->dispatch('performance.memory_warning', [
                'usage' => $memoryUsage,
                'limit' => ini_get('memory_limit')
            ]);
        }
        
        // Check slow operations
        if ($metric['duration'] > $this->config['slow_query_threshold'] * 10) {
            $this->events->dispatch('performance.critical_slowness', $metric);
        }
    }

    private function logSlowQuery(string $query, array $bindings, float $duration): void
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = $this->findQueryCaller($backtrace);
        
        $this->db->table('slow_queries')->insert([
            'query' => $query,
            'bindings' => json_encode($bindings),
            'duration' => $duration,
            'connection' => 'default',
            'file' => $caller['file'] ?? null,
            'line' => $caller['line'] ?? null,
            'executed_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function findQueryCaller(array $backtrace): array
    {
        foreach ($backtrace as $frame) {
            if (isset($frame['file']) && !$this->isFrameworkFile($frame['file'])) {
                return $frame;
            }
        }
        
        return [];
    }

    private function isFrameworkFile(string $file): bool
    {
        return strpos($file, '/core/') !== false || strpos($file, '/vendor/') !== false;
    }

    private function getQueryType(string $query): string
    {
        $query = strtoupper(trim($query));
        
        if (strpos($query, 'SELECT') === 0) return 'SELECT';
        if (strpos($query, 'INSERT') === 0) return 'INSERT';
        if (strpos($query, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($query, 'DELETE') === 0) return 'DELETE';
        
        return 'OTHER';
    }

    private function getCpuUsage(): ?int
    {
        if (!function_exists('sys_getloadavg')) {
            return null;
        }
        
        $load = sys_getloadavg();
        return (int)($load[0] * 100);
    }

    private function generateSummary(): array
    {
        $summary = [];
        
        // Group metrics by type
        $grouped = [];
        foreach ($this->metrics as $metric) {
            $type = $metric['type'];
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $metric;
        }
        
        // Calculate summaries
        foreach ($grouped as $type => $metrics) {
            $durations = array_column($metrics, 'duration');
            
            $summary[$type] = [
                'count' => count($metrics),
                'total' => array_sum($durations),
                'average' => count($durations) > 0 ? array_sum($durations) / count($durations) : 0,
                'min' => !empty($durations) ? min($durations) : 0,
                'max' => !empty($durations) ? max($durations) : 0
            ];
        }
        
        return $summary;
    }

    private function getPeriodStartTime(string $period): string
    {
        switch ($period) {
            case 'hour':
                return date('Y-m-d H:i:s', strtotime('-1 hour'));
            case 'day':
                return date('Y-m-d H:i:s', strtotime('-1 day'));
            case 'week':
                return date('Y-m-d H:i:s', strtotime('-1 week'));
            case 'month':
                return date('Y-m-d H:i:s', strtotime('-1 month'));
            default:
                return date('Y-m-d H:i:s', strtotime('-1 hour'));
        }
    }

    private function getOverviewMetrics(string $startTime): array
    {
        return $this->db->table('performance_metrics')
            ->selectRaw('
                type,
                COUNT(*) as count,
                AVG(duration) as avg_duration,
                MIN(duration) as min_duration,
                MAX(duration) as max_duration,
                SUM(duration) as total_duration
            ')
            ->where('recorded_at', '>=', $startTime)
            ->groupBy('type')
            ->get()
            ->toArray();
    }

    private function getSlowQueries(string $startTime): array
    {
        return $this->db->table('slow_queries')
            ->where('executed_at', '>=', $startTime)
            ->orderBy('duration', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    private function getCachePerformance(string $startTime): array
    {
        $metrics = $this->db->table('performance_metrics')
            ->where('type', 'cache')
            ->where('recorded_at', '>=', $startTime)
            ->get();
        
        $hits = 0;
        $misses = 0;
        
        foreach ($metrics as $metric) {
            $metadata = json_decode($metric->metadata, true);
            if ($metadata['hit'] ?? false) {
                $hits++;
            } else {
                $misses++;
            }
        }
        
        $total = $hits + $misses;
        
        return [
            'total_operations' => $total,
            'hits' => $hits,
            'misses' => $misses,
            'hit_rate' => $total > 0 ? ($hits / $total) * 100 : 0
        ];
    }

    private function getEndpointPerformance(string $startTime): array
    {
        return $this->db->table('performance_metrics')
            ->selectRaw('
                name,
                COUNT(*) as count,
                AVG(duration) as avg_duration,
                MIN(duration) as min_duration,
                MAX(duration) as max_duration,
                PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY duration) as p50,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY duration) as p95,
                PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY duration) as p99
            ')
            ->where('type', 'http')
            ->where('recorded_at', '>=', $startTime)
            ->groupBy('name')
            ->orderBy('count', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    private function getResourceUsage(string $startTime): array
    {
        return $this->db->table('performance_metrics')
            ->selectRaw('
                DATE_FORMAT(recorded_at, "%Y-%m-%d %H:00:00") as hour,
                AVG(memory_usage) as avg_memory,
                MAX(memory_usage) as peak_memory,
                AVG(cpu_usage) as avg_cpu,
                MAX(cpu_usage) as peak_cpu
            ')
            ->where('recorded_at', '>=', $startTime)
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->toArray();
    }

    private function identifyBottlenecks(string $startTime): array
    {
        $bottlenecks = [];
        
        // Slow queries
        $slowQueries = $this->db->table('slow_queries')
            ->selectRaw('query, COUNT(*) as count, AVG(duration) as avg_duration')
            ->where('executed_at', '>=', $startTime)
            ->groupBy('query')
            ->having('count', '>', 10)
            ->orderBy('avg_duration', 'desc')
            ->limit(10)
            ->get();
        
        if ($slowQueries->isNotEmpty()) {
            $bottlenecks['queries'] = $slowQueries->toArray();
        }
        
        // Slow endpoints
        $slowEndpoints = $this->db->table('performance_metrics')
            ->selectRaw('name, AVG(duration) as avg_duration')
            ->where('type', 'http')
            ->where('recorded_at', '>=', $startTime)
            ->groupBy('name')
            ->having('avg_duration', '>', 1000)
            ->orderBy('avg_duration', 'desc')
            ->limit(10)
            ->get();
        
        if ($slowEndpoints->isNotEmpty()) {
            $bottlenecks['endpoints'] = $slowEndpoints->toArray();
        }
        
        return $bottlenecks;
    }

    private function identifySlowQueries(): array
    {
        return $this->db->table('slow_queries')
            ->selectRaw('query, COUNT(*) as count, AVG(duration) as avg_duration')
            ->where('executed_at', '>=', date('Y-m-d H:i:s', strtotime('-1 day')))
            ->groupBy('query')
            ->having('avg_duration', '>', $this->config['slow_query_threshold'])
            ->orderBy('avg_duration', 'desc')
            ->get()
            ->toArray();
    }

    private function optimizeQueries(array $slowQueries): array
    {
        $optimizations = [];
        
        foreach ($slowQueries as $query) {
            $optimization = $this->analyzeQuery($query->query);
            if ($optimization) {
                $optimizations[] = [
                    'query' => $query->query,
                    'suggestion' => $optimization,
                    'potential_improvement' => $this->estimateImprovement($query)
                ];
            }
        }
        
        return $optimizations;
    }

    private function analyzeQuery(string $query): ?string
    {
        $query = strtoupper($query);
        
        // Check for missing indexes
        if (strpos($query, 'WHERE') !== false && strpos($query, 'INDEX') === false) {
            return 'Consider adding indexes on WHERE clause columns';
        }
        
        // Check for SELECT *
        if (strpos($query, 'SELECT *') !== false) {
            return 'Avoid SELECT *, specify only needed columns';
        }
        
        // Check for JOIN without indexes
        if (strpos($query, 'JOIN') !== false) {
            return 'Ensure JOIN columns are indexed';
        }
        
        // Check for subqueries
        if (substr_count($query, 'SELECT') > 1) {
            return 'Consider replacing subqueries with JOINs';
        }
        
        return null;
    }

    private function estimateImprovement(object $query): string
    {
        $improvement = 0;
        
        if ($query->avg_duration > 1000) {
            $improvement = 50;
        } elseif ($query->avg_duration > 500) {
            $improvement = 30;
        } else {
            $improvement = 10;
        }
        
        return $improvement . '% potential improvement';
    }

    private function identifyCacheIssues(): array
    {
        $issues = [];
        
        // Low hit rate
        $cachePerf = $this->getCachePerformance(date('Y-m-d H:i:s', strtotime('-1 hour')));
        if ($cachePerf['hit_rate'] < 80) {
            $issues[] = [
                'type' => 'low_hit_rate',
                'value' => $cachePerf['hit_rate'],
                'threshold' => 80
            ];
        }
        
        return $issues;
    }

    private function optimizeCache(array $issues): array
    {
        $optimizations = [];
        
        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'low_hit_rate':
                    $optimizations[] = [
                        'issue' => 'Low cache hit rate',
                        'suggestion' => 'Increase cache TTL or implement cache warming',
                        'action' => 'warm_cache'
                    ];
                    break;
            }
        }
        
        return $optimizations;
    }

    private function identifyMemoryIssues(): array
    {
        $issues = [];
        
        // High memory usage
        $currentUsage = memory_get_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        if ($currentUsage / $limit > 0.8) {
            $issues[] = [
                'type' => 'high_memory_usage',
                'current' => $currentUsage,
                'limit' => $limit,
                'percentage' => ($currentUsage / $limit) * 100
            ];
        }
        
        return $issues;
    }

    private function optimizeMemory(array $issues): array
    {
        $optimizations = [];
        
        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'high_memory_usage':
                    $optimizations[] = [
                        'issue' => 'High memory usage',
                        'suggestion' => 'Clear unused objects and optimize data structures',
                        'action' => 'garbage_collection'
                    ];
                    
                    // Force garbage collection
                    gc_collect_cycles();
                    break;
            }
        }
        
        return $optimizations;
    }

    private function parseMemoryLimit(string $limit): int
    {
        $unit = strtolower($limit[strlen($limit) - 1]);
        $value = (int)$limit;
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }
}

/**
 * Timer class for measuring operation duration
 */
class Timer
{
    private string $name;
    private PerformanceMonitor $monitor;
    private float $startTime;
    private array $metadata = [];

    public function __construct(string $name, PerformanceMonitor $monitor)
    {
        $this->name = $name;
        $this->monitor = $monitor;
        $this->startTime = microtime(true);
    }

    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function stop(string $type = 'operation'): float
    {
        $duration = (microtime(true) - $this->startTime) * 1000;
        
        $this->monitor->record($type, $this->name, $duration, $this->metadata);
        
        return $duration;
    }
}