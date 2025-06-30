<?php

declare(strict_types=1);

namespace Shopologic\Plugins\Shared\Monitoring;

/**
 * Comprehensive plugin health monitoring system
 * Tracks performance, errors, and system health
 */
class PluginHealthMonitor
{
    private array $metrics = [];
    private array $healthChecks = [];
    private array $alerts = [];
    private array $thresholds = [];
    private bool $monitoringEnabled = true;
    
    /**
     * Default thresholds for monitoring
     */
    private const DEFAULT_THRESHOLDS = [
        'response_time_ms' => 1000,
        'memory_usage_mb' => 50,
        'error_rate_percent' => 5,
        'cpu_usage_percent' => 80,
        'database_query_time_ms' => 500,
        'cache_hit_rate_percent' => 90
    ];
    
    public function __construct(array $customThresholds = [])
    {
        $this->thresholds = array_merge(self::DEFAULT_THRESHOLDS, $customThresholds);
    }
    
    /**
     * Record performance metric
     */
    public function recordMetric(string $pluginName, string $metricName, float $value, array $tags = []): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }
        
        $timestamp = microtime(true);
        
        if (!isset($this->metrics[$pluginName])) {
            $this->metrics[$pluginName] = [];
        }
        
        if (!isset($this->metrics[$pluginName][$metricName])) {
            $this->metrics[$pluginName][$metricName] = [];
        }
        
        $this->metrics[$pluginName][$metricName][] = [
            'value' => $value,
            'timestamp' => $timestamp,
            'tags' => $tags
        ];
        
        // Keep only recent metrics (last 1000 entries per metric)
        if (count($this->metrics[$pluginName][$metricName]) > 1000) {
            array_shift($this->metrics[$pluginName][$metricName]);
        }
        
        // Check thresholds and generate alerts
        $this->checkThresholds($pluginName, $metricName, $value);
    }
    
    /**
     * Start performance tracking
     */
    public function startTracking(string $pluginName, string $operation): string
    {
        $trackingId = uniqid($pluginName . '_', true);
        
        $this->recordMetric($pluginName, 'operation_started', 1, [
            'operation' => $operation,
            'tracking_id' => $trackingId
        ]);
        
        return $trackingId;
    }
    
    /**
     * End performance tracking
     */
    public function endTracking(string $trackingId, string $pluginName, string $operation, bool $success = true): void
    {
        $this->recordMetric($pluginName, 'operation_completed', 1, [
            'operation' => $operation,
            'tracking_id' => $trackingId,
            'success' => $success
        ]);
        
        if (!$success) {
            $this->recordMetric($pluginName, 'operation_errors', 1, [
                'operation' => $operation,
                'tracking_id' => $trackingId
            ]);
        }
    }
    
    /**
     * Record response time
     */
    public function recordResponseTime(string $pluginName, string $operation, float $responseTimeMs): void
    {
        $this->recordMetric($pluginName, 'response_time_ms', $responseTimeMs, [
            'operation' => $operation
        ]);
    }
    
    /**
     * Record memory usage
     */
    public function recordMemoryUsage(string $pluginName, float $memoryMb): void
    {
        $this->recordMetric($pluginName, 'memory_usage_mb', $memoryMb);
    }
    
    /**
     * Record database query time
     */
    public function recordDatabaseQueryTime(string $pluginName, string $query, float $timeMs): void
    {
        $this->recordMetric($pluginName, 'database_query_time_ms', $timeMs, [
            'query_type' => $this->getQueryType($query)
        ]);
    }
    
    /**
     * Record cache performance
     */
    public function recordCacheHit(string $pluginName, bool $hit): void
    {
        $this->recordMetric($pluginName, 'cache_hits', $hit ? 1 : 0);
    }
    
    /**
     * Register health check
     */
    public function registerHealthCheck(string $pluginName, string $checkName, callable $check): void
    {
        if (!isset($this->healthChecks[$pluginName])) {
            $this->healthChecks[$pluginName] = [];
        }
        
        $this->healthChecks[$pluginName][$checkName] = $check;
    }
    
    /**
     * Run health checks for a plugin
     */
    public function runHealthChecks(string $pluginName): array
    {
        if (!isset($this->healthChecks[$pluginName])) {
            return [];
        }
        
        $results = [];
        
        foreach ($this->healthChecks[$pluginName] as $checkName => $check) {
            $startTime = microtime(true);
            
            try {
                $result = call_user_func($check);
                $endTime = microtime(true);
                
                $results[$checkName] = [
                    'status' => $result ? 'healthy' : 'unhealthy',
                    'success' => true,
                    'response_time_ms' => ($endTime - $startTime) * 1000,
                    'timestamp' => time()
                ];
            } catch (\Exception $e) {
                $endTime = microtime(true);
                
                $results[$checkName] = [
                    'status' => 'error',
                    'success' => false,
                    'error' => $e->getMessage(),
                    'response_time_ms' => ($endTime - $startTime) * 1000,
                    'timestamp' => time()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Run all health checks
     */
    public function runAllHealthChecks(): array
    {
        $allResults = [];
        
        foreach (array_keys($this->healthChecks) as $pluginName) {
            $allResults[$pluginName] = $this->runHealthChecks($pluginName);
        }
        
        return $allResults;
    }
    
    /**
     * Get plugin performance summary
     */
    public function getPerformanceSummary(string $pluginName, int $timeWindowMinutes = 60): array
    {
        if (!isset($this->metrics[$pluginName])) {
            return [];
        }
        
        $cutoffTime = microtime(true) - ($timeWindowMinutes * 60);
        $summary = [];
        
        foreach ($this->metrics[$pluginName] as $metricName => $values) {
            $recentValues = array_filter($values, fn($v) => $v['timestamp'] > $cutoffTime);
            
            if (empty($recentValues)) {
                continue;
            }
            
            $metricValues = array_column($recentValues, 'value');
            
            $summary[$metricName] = [
                'count' => count($metricValues),
                'avg' => array_sum($metricValues) / count($metricValues),
                'min' => min($metricValues),
                'max' => max($metricValues),
                'latest' => end($metricValues),
                'trend' => $this->calculateTrend($metricValues)
            ];
        }
        
        return $summary;
    }
    
    /**
     * Get system health status
     */
    public function getSystemHealth(): array
    {
        $healthChecks = $this->runAllHealthChecks();
        $overallHealth = 'healthy';
        $issues = [];
        
        foreach ($healthChecks as $pluginName => $checks) {
            foreach ($checks as $checkName => $result) {
                if ($result['status'] !== 'healthy') {
                    $overallHealth = 'unhealthy';
                    $issues[] = [
                        'plugin' => $pluginName,
                        'check' => $checkName,
                        'status' => $result['status'],
                        'error' => $result['error'] ?? null
                    ];
                }
            }
        }
        
        return [
            'overall_status' => $overallHealth,
            'timestamp' => time(),
            'plugins_monitored' => count($this->healthChecks),
            'total_checks' => array_sum(array_map('count', $healthChecks)),
            'issues' => $issues,
            'system_resources' => $this->getSystemResources()
        ];
    }
    
    /**
     * Get alerts
     */
    public function getAlerts(int $timeWindowMinutes = 60): array
    {
        $cutoffTime = time() - ($timeWindowMinutes * 60);
        
        return array_filter($this->alerts, fn($alert) => $alert['timestamp'] > $cutoffTime);
    }
    
    /**
     * Clear old alerts
     */
    public function clearOldAlerts(int $maxAgeMinutes = 1440): void
    {
        $cutoffTime = time() - ($maxAgeMinutes * 60);
        $this->alerts = array_filter($this->alerts, fn($alert) => $alert['timestamp'] > $cutoffTime);
    }
    
    /**
     * Check thresholds and generate alerts
     */
    private function checkThresholds(string $pluginName, string $metricName, float $value): void
    {
        if (!isset($this->thresholds[$metricName])) {
            return;
        }
        
        $threshold = $this->thresholds[$metricName];
        $shouldAlert = false;
        $severity = 'info';
        
        switch ($metricName) {
            case 'response_time_ms':
            case 'memory_usage_mb':
            case 'cpu_usage_percent':
            case 'database_query_time_ms':
                if ($value > $threshold) {
                    $shouldAlert = true;
                    $severity = $value > ($threshold * 1.5) ? 'critical' : 'warning';
                }
                break;
                
            case 'error_rate_percent':
                if ($value > $threshold) {
                    $shouldAlert = true;
                    $severity = $value > ($threshold * 2) ? 'critical' : 'warning';
                }
                break;
                
            case 'cache_hit_rate_percent':
                if ($value < $threshold) {
                    $shouldAlert = true;
                    $severity = $value < ($threshold * 0.5) ? 'critical' : 'warning';
                }
                break;
        }
        
        if ($shouldAlert) {
            $this->generateAlert($pluginName, $metricName, $value, $threshold, $severity);
        }
    }
    
    /**
     * Generate alert
     */
    private function generateAlert(string $pluginName, string $metricName, float $value, float $threshold, string $severity): void
    {
        $alert = [
            'id' => uniqid('alert_', true),
            'plugin' => $pluginName,
            'metric' => $metricName,
            'value' => $value,
            'threshold' => $threshold,
            'severity' => $severity,
            'message' => $this->generateAlertMessage($pluginName, $metricName, $value, $threshold),
            'timestamp' => time()
        ];
        
        $this->alerts[] = $alert;
        
        // Log critical alerts
        if ($severity === 'critical') {
            error_log("CRITICAL ALERT: {$alert['message']}");
        }
    }
    
    /**
     * Generate alert message
     */
    private function generateAlertMessage(string $pluginName, string $metricName, float $value, float $threshold): string
    {
        return sprintf(
            "Plugin '%s' metric '%s' is %s (value: %.2f, threshold: %.2f)",
            $pluginName,
            $metricName,
            $value > $threshold ? 'above threshold' : 'below threshold',
            $value,
            $threshold
        );
    }
    
    /**
     * Calculate trend from values
     */
    private function calculateTrend(array $values): string
    {
        if (count($values) < 2) {
            return 'stable';
        }
        
        $recent = array_slice($values, -10); // Last 10 values
        $older = array_slice($values, -20, 10); // Previous 10 values
        
        if (empty($older)) {
            return 'stable';
        }
        
        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);
        
        $change = ($recentAvg - $olderAvg) / $olderAvg * 100;
        
        if ($change > 10) {
            return 'increasing';
        } elseif ($change < -10) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }
    
    /**
     * Get query type from SQL
     */
    private function getQueryType(string $query): string
    {
        $query = trim(strtoupper($query));
        
        if (strpos($query, 'SELECT') === 0) return 'SELECT';
        if (strpos($query, 'INSERT') === 0) return 'INSERT';
        if (strpos($query, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($query, 'DELETE') === 0) return 'DELETE';
        
        return 'OTHER';
    }
    
    /**
     * Get system resources
     */
    private function getSystemResources(): array
    {
        return [
            'memory_usage_bytes' => memory_get_usage(true),
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'cpu_load' => sys_getloadavg()[0] ?? 0,
            'disk_free_bytes' => disk_free_space('.'),
            'disk_total_bytes' => disk_total_space('.'),
            'uptime_seconds' => $this->getUptime()
        ];
    }
    
    /**
     * Get system uptime
     */
    private function getUptime(): ?int
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = file_get_contents('/proc/uptime');
            return $uptime ? (int)explode(' ', $uptime)[0] : null;
        }
        
        return null;
    }
    
    /**
     * Enable monitoring
     */
    public function enableMonitoring(): void
    {
        $this->monitoringEnabled = true;
    }
    
    /**
     * Disable monitoring
     */
    public function disableMonitoring(): void
    {
        $this->monitoringEnabled = false;
    }
    
    /**
     * Export metrics data
     */
    public function exportMetrics(string $format = 'json'): string
    {
        $data = [
            'metrics' => $this->metrics,
            'alerts' => $this->alerts,
            'thresholds' => $this->thresholds,
            'export_timestamp' => time()
        ];
        
        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->convertToCSV($data);
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }
    
    /**
     * Convert data to CSV format
     */
    private function convertToCSV(array $data): string
    {
        $csv = "plugin,metric,value,timestamp,tags\n";
        
        foreach ($data['metrics'] as $plugin => $metrics) {
            foreach ($metrics as $metricName => $values) {
                foreach ($values as $value) {
                    $tags = isset($value['tags']) ? json_encode($value['tags']) : '';
                    $csv .= sprintf(
                        "%s,%s,%s,%s,%s\n",
                        $plugin,
                        $metricName,
                        $value['value'],
                        date('Y-m-d H:i:s', (int)$value['timestamp']),
                        $tags
                    );
                }
            }
        }
        
        return $csv;
    }
    
    /**
     * Get singleton instance
     */
    private static ?self $instance = null;
    
    public static function getInstance(array $customThresholds = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($customThresholds);
        }
        return self::$instance;
    }
}