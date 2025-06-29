<?php

declare(strict_types=1);

namespace Shopologic\Core\Monitoring;

use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\Core\Events\EventDispatcher;
use Shopologic\Core\Cache\CacheManager;
use Shopologic\Core\Logging\Logger;

/**
 * Monitoring Manager
 * 
 * Centralized monitoring and alerting system
 */
class MonitoringManager
{
    private ConfigurationManager $config;
    private EventDispatcher $events;
    private CacheManager $cache;
    private Logger $logger;
    private array $metrics = [];
    private array $alerts = [];
    private array $collectors = [];
    
    public function __construct(
        ConfigurationManager $config,
        EventDispatcher $events,
        CacheManager $cache,
        Logger $logger
    ) {
        $this->config = $config;
        $this->events = $events;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->initializeCollectors();
    }
    
    /**
     * Initialize metric collectors
     */
    private function initializeCollectors(): void
    {
        $this->collectors = [
            'system' => new SystemMetricsCollector(),
            'application' => new ApplicationMetricsCollector(),
            'database' => new DatabaseMetricsCollector(),
            'cache' => new CacheMetricsCollector(),
            'http' => new HttpMetricsCollector(),
            'business' => new BusinessMetricsCollector()
        ];
    }
    
    /**
     * Collect all metrics
     */
    public function collectMetrics(): array
    {
        $metrics = [];
        
        foreach ($this->collectors as $type => $collector) {
            try {
                $collectorMetrics = $collector->collect();
                $metrics[$type] = $collectorMetrics;
                
                // Store in cache for quick access
                $this->cache->put("metrics.{$type}", $collectorMetrics, 300);
                
            } catch (\Exception $e) {
                $this->logger->error("Failed to collect {$type} metrics", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->metrics = $metrics;
        return $metrics;
    }
    
    /**
     * Get specific metric
     */
    public function getMetric(string $key, $default = null)
    {
        return data_get($this->metrics, $key, $default);
    }
    
    /**
     * Record custom metric
     */
    public function recordMetric(string $name, $value, array $tags = []): void
    {
        $metric = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true)
        ];
        
        // Store in memory
        if (!isset($this->metrics['custom'])) {
            $this->metrics['custom'] = [];
        }
        $this->metrics['custom'][] = $metric;
        
        // Store in cache
        $cacheKey = 'metrics.custom.' . md5($name . serialize($tags));
        $this->cache->put($cacheKey, $metric, 3600);
        
        // Check for alerts
        $this->checkAlerts($name, $value, $tags);
        
        // Dispatch event
        $this->events->dispatch(new MetricRecordedEvent($metric));
    }
    
    /**
     * Increment counter metric
     */
    public function increment(string $name, int $value = 1, array $tags = []): void
    {
        $current = $this->getMetric("custom.counters.{$name}", 0);
        $this->recordMetric("counters.{$name}", $current + $value, $tags);
    }
    
    /**
     * Record gauge metric
     */
    public function gauge(string $name, $value, array $tags = []): void
    {
        $this->recordMetric("gauges.{$name}", $value, $tags);
    }
    
    /**
     * Record timing metric
     */
    public function timing(string $name, float $duration, array $tags = []): void
    {
        $this->recordMetric("timings.{$name}", $duration, $tags);
    }
    
    /**
     * Record histogram metric
     */
    public function histogram(string $name, $value, array $tags = []): void
    {
        $key = "histograms.{$name}";
        $histogram = $this->getMetric($key, []);
        
        $histogram[] = [
            'value' => $value,
            'timestamp' => microtime(true),
            'tags' => $tags
        ];
        
        // Keep only last 1000 values
        if (count($histogram) > 1000) {
            $histogram = array_slice($histogram, -1000);
        }
        
        $this->recordMetric($key, $histogram, $tags);
    }
    
    /**
     * Start timer
     */
    public function startTimer(string $name): TimerContext
    {
        return new TimerContext($this, $name);
    }
    
    /**
     * Check health status
     */
    public function checkHealth(): HealthStatus
    {
        $checks = [];
        
        // Database health
        $checks['database'] = $this->checkDatabaseHealth();
        
        // Cache health
        $checks['cache'] = $this->checkCacheHealth();
        
        // Storage health
        $checks['storage'] = $this->checkStorageHealth();
        
        // Memory health
        $checks['memory'] = $this->checkMemoryHealth();
        
        // Application health
        $checks['application'] = $this->checkApplicationHealth();
        
        // Overall status
        $overall = 'healthy';
        foreach ($checks as $check) {
            if ($check['status'] === 'critical') {
                $overall = 'critical';
                break;
            } elseif ($check['status'] === 'warning' && $overall === 'healthy') {
                $overall = 'warning';
            }
        }
        
        return new HealthStatus($overall, $checks);
    }
    
    /**
     * Check database health
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            
            // Simple connectivity test
            $pdo = new \PDO(
                'pgsql:host=' . $this->config->get('database.host') . 
                ';dbname=' . $this->config->get('database.database'),
                $this->config->get('database.username'),
                $this->config->get('database.password')
            );
            
            $result = $pdo->query('SELECT 1')->fetchColumn();
            $duration = (microtime(true) - $start) * 1000;
            
            $status = 'healthy';
            if ($duration > 1000) {
                $status = 'warning';
            } elseif ($duration > 5000) {
                $status = 'critical';
            }
            
            return [
                'status' => $status,
                'response_time' => $duration,
                'message' => 'Database connection successful'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check cache health
     */
    private function checkCacheHealth(): array
    {
        try {
            $start = microtime(true);
            
            $testKey = 'health_check_' . time();
            $this->cache->put($testKey, 'test', 60);
            $value = $this->cache->get($testKey);
            $this->cache->forget($testKey);
            
            $duration = (microtime(true) - $start) * 1000;
            
            if ($value !== 'test') {
                return [
                    'status' => 'critical',
                    'message' => 'Cache read/write test failed'
                ];
            }
            
            $status = $duration > 100 ? 'warning' : 'healthy';
            
            return [
                'status' => $status,
                'response_time' => $duration,
                'message' => 'Cache is working properly'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Cache check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check storage health
     */
    private function checkStorageHealth(): array
    {
        $storagePath = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT . '/storage' : '/tmp';
        
        try {
            // Check if storage directory is writable
            if (!is_writable($storagePath)) {
                return [
                    'status' => 'critical',
                    'message' => 'Storage directory is not writable'
                ];
            }
            
            // Check disk space
            $freeBytes = disk_free_space($storagePath);
            $totalBytes = disk_total_space($storagePath);
            $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
            
            $status = 'healthy';
            if ($usedPercent > 90) {
                $status = 'critical';
            } elseif ($usedPercent > 80) {
                $status = 'warning';
            }
            
            return [
                'status' => $status,
                'disk_usage' => round($usedPercent, 2),
                'free_space' => $this->formatBytes($freeBytes),
                'message' => 'Storage is accessible'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Storage check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check memory health
     */
    private function checkMemoryHealth(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseBytes(ini_get('memory_limit'));
        $usagePercent = ($memoryUsage / $memoryLimit) * 100;
        
        $status = 'healthy';
        if ($usagePercent > 90) {
            $status = 'critical';
        } elseif ($usagePercent > 80) {
            $status = 'warning';
        }
        
        return [
            'status' => $status,
            'usage_percent' => round($usagePercent, 2),
            'current_usage' => $this->formatBytes($memoryUsage),
            'memory_limit' => $this->formatBytes($memoryLimit),
            'message' => 'Memory usage is within limits'
        ];
    }
    
    /**
     * Check application health
     */
    private function checkApplicationHealth(): array
    {
        try {
            // Check if core services are available
            $errors = [];
            
            // Check if plugins are loaded
            if (!class_exists('Shopologic\\Core\\Plugin\\PluginManager')) {
                $errors[] = 'Plugin system not available';
            }
            
            // Check if cache is working
            try {
                $this->cache->get('test');
            } catch (\Exception $e) {
                $errors[] = 'Cache system not working';
            }
            
            if (empty($errors)) {
                return [
                    'status' => 'healthy',
                    'message' => 'All application services are working'
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => 'Some application issues detected',
                    'errors' => $errors
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Application health check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Setup alert rules
     */
    public function setupAlerts(array $rules): void
    {
        $this->alerts = $rules;
        $this->cache->put('monitoring.alerts', $rules, 3600);
    }
    
    /**
     * Check alerts for a metric
     */
    private function checkAlerts(string $name, $value, array $tags): void
    {
        $alertRules = $this->cache->get('monitoring.alerts', []);
        
        foreach ($alertRules as $rule) {
            if ($this->matchesRule($rule, $name, $value, $tags)) {
                $this->triggerAlert($rule, $name, $value, $tags);
            }
        }
    }
    
    /**
     * Check if metric matches alert rule
     */
    private function matchesRule(array $rule, string $name, $value, array $tags): bool
    {
        // Check metric name pattern
        if (isset($rule['metric_pattern'])) {
            if (!fnmatch($rule['metric_pattern'], $name)) {
                return false;
            }
        }
        
        // Check value threshold
        if (isset($rule['threshold'])) {
            $operator = $rule['operator'] ?? '>';
            
            switch ($operator) {
                case '>':
                    return $value > $rule['threshold'];
                case '>=':
                    return $value >= $rule['threshold'];
                case '<':
                    return $value < $rule['threshold'];
                case '<=':
                    return $value <= $rule['threshold'];
                case '==':
                    return $value == $rule['threshold'];
                case '!=':
                    return $value != $rule['threshold'];
            }
        }
        
        return true;
    }
    
    /**
     * Trigger alert
     */
    private function triggerAlert(array $rule, string $name, $value, array $tags): void
    {
        $alert = new Alert(
            $rule['name'] ?? 'Unknown Alert',
            $rule['severity'] ?? 'warning',
            "Metric {$name} triggered alert with value {$value}",
            [
                'metric' => $name,
                'value' => $value,
                'tags' => $tags,
                'rule' => $rule
            ]
        );
        
        // Send alert through configured channels
        $this->sendAlert($alert, $rule['channels'] ?? ['log']);
        
        // Dispatch event
        $this->events->dispatch(new AlertTriggeredEvent($alert));
    }
    
    /**
     * Send alert through channels
     */
    private function sendAlert(Alert $alert, array $channels): void
    {
        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'log':
                        $this->logger->warning('Alert triggered', [
                            'alert' => $alert->toArray()
                        ]);
                        break;
                        
                    case 'email':
                        $this->sendEmailAlert($alert);
                        break;
                        
                    case 'slack':
                        $this->sendSlackAlert($alert);
                        break;
                        
                    case 'webhook':
                        $this->sendWebhookAlert($alert);
                        break;
                }
            } catch (\Exception $e) {
                $this->logger->error("Failed to send alert via {$channel}", [
                    'error' => $e->getMessage(),
                    'alert' => $alert->toArray()
                ]);
            }
        }
    }
    
    /**
     * Send email alert
     */
    private function sendEmailAlert(Alert $alert): void
    {
        // Implementation would depend on mail system
        // For now, just log
        $this->logger->info('Email alert would be sent', [
            'alert' => $alert->toArray()
        ]);
    }
    
    /**
     * Send Slack alert
     */
    private function sendSlackAlert(Alert $alert): void
    {
        $webhookUrl = $this->config->get('monitoring.slack.webhook_url');
        
        if (!$webhookUrl) {
            return;
        }
        
        $payload = [
            'text' => "Alert: {$alert->getName()}",
            'attachments' => [
                [
                    'color' => $alert->getSeverity() === 'critical' ? 'danger' : 'warning',
                    'fields' => [
                        [
                            'title' => 'Severity',
                            'value' => $alert->getSeverity(),
                            'short' => true
                        ],
                        [
                            'title' => 'Message',
                            'value' => $alert->getMessage(),
                            'short' => false
                        ]
                    ]
                ]
            ]
        ];
        
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
    
    /**
     * Send webhook alert
     */
    private function sendWebhookAlert(Alert $alert): void
    {
        $webhookUrl = $this->config->get('monitoring.webhook.url');
        
        if (!$webhookUrl) {
            return;
        }
        
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($alert->toArray()));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
    
    /**
     * Export metrics in Prometheus format
     */
    public function exportPrometheusMetrics(): string
    {
        $output = '';
        
        foreach ($this->metrics as $type => $metrics) {
            if ($type === 'custom') {
                foreach ($metrics as $metric) {
                    $name = str_replace('.', '_', $metric['name']);
                    $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
                    
                    $labels = '';
                    if (!empty($metric['tags'])) {
                        $labelPairs = [];
                        foreach ($metric['tags'] as $key => $value) {
                            $labelPairs[] = $key . '="' . addslashes($value) . '"';
                        }
                        $labels = '{' . implode(',', $labelPairs) . '}';
                    }
                    
                    $output .= "shopologic_{$name}{$labels} {$metric['value']}\n";
                }
            } else {
                foreach ($metrics as $key => $value) {
                    if (is_numeric($value)) {
                        $name = str_replace('.', '_', "{$type}_{$key}");
                        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
                        $output .= "shopologic_{$name} {$value}\n";
                    }
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Parse bytes from string
     */
    private function parseBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;
        
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
}

/**
 * Timer Context for measuring execution time
 */
class TimerContext
{
    private MonitoringManager $monitor;
    private string $name;
    private float $startTime;
    private array $tags;
    
    public function __construct(MonitoringManager $monitor, string $name, array $tags = [])
    {
        $this->monitor = $monitor;
        $this->name = $name;
        $this->tags = $tags;
        $this->startTime = microtime(true);
    }
    
    public function stop(): float
    {
        $duration = (microtime(true) - $this->startTime) * 1000; // Convert to milliseconds
        $this->monitor->timing($this->name, $duration, $this->tags);
        return $duration;
    }
    
    public function __destruct()
    {
        // Auto-stop if not manually stopped
        if ($this->startTime > 0) {
            $this->stop();
        }
    }
}

/**
 * Health Status
 */
class HealthStatus
{
    private string $status;
    private array $checks;
    
    public function __construct(string $status, array $checks)
    {
        $this->status = $status;
        $this->checks = $checks;
    }
    
    public function getStatus(): string
    {
        return $this->status;
    }
    
    public function getChecks(): array
    {
        return $this->checks;
    }
    
    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }
    
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'checks' => $this->checks,
            'timestamp' => date('c')
        ];
    }
}

/**
 * Alert
 */
class Alert
{
    private string $name;
    private string $severity;
    private string $message;
    private array $context;
    private float $timestamp;
    
    public function __construct(string $name, string $severity, string $message, array $context = [])
    {
        $this->name = $name;
        $this->severity = $severity;
        $this->message = $message;
        $this->context = $context;
        $this->timestamp = microtime(true);
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getSeverity(): string
    {
        return $this->severity;
    }
    
    public function getMessage(): string
    {
        return $this->message;
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
    
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }
    
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'severity' => $this->severity,
            'message' => $this->message,
            'context' => $this->context,
            'timestamp' => $this->timestamp
        ];
    }
}

/**
 * Events
 */
class MetricRecordedEvent extends \Shopologic\Core\Events\Event
{
    private array $metric;
    
    public function __construct(array $metric)
    {
        $this->metric = $metric;
    }
    
    public function getName(): string
    {
        return 'monitoring.metric.recorded';
    }
    
    public function getMetric(): array
    {
        return $this->metric;
    }
}

class AlertTriggeredEvent extends \Shopologic\Core\Events\Event
{
    private Alert $alert;
    
    public function __construct(Alert $alert)
    {
        $this->alert = $alert;
    }
    
    public function getName(): string
    {
        return 'monitoring.alert.triggered';
    }
    
    public function getAlert(): Alert
    {
        return $this->alert;
    }
}