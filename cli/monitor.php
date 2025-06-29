<?php

declare(strict_types=1);

/**
 * Shopologic Monitoring CLI Tool
 * 
 * Provides monitoring setup, metrics collection, and health checks
 */

define('SHOPOLOGIC_ROOT', dirname(__DIR__));

require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\Core\Monitoring\MonitoringManager;
use Shopologic\Core\Events\EventDispatcher;
use Shopologic\Core\Cache\CacheManager;
use Shopologic\Core\Logging\Logger;

Autoloader::register();

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'setup':
        setupMonitoring();
        break;
        
    case 'collect':
        collectMetrics();
        break;
        
    case 'health':
        checkHealth();
        break;
        
    case 'alerts':
        manageAlerts($argv[2] ?? 'list', array_slice($argv, 3));
        break;
        
    case 'export':
        exportMetrics($argv[2] ?? 'prometheus');
        break;
        
    case 'dashboard':
        launchDashboard();
        break;
        
    case 'test':
        testMonitoring();
        break;
        
    case 'clean':
        cleanOldMetrics();
        break;
        
    case 'status':
        showMonitoringStatus();
        break;
        
    case 'help':
    default:
        showHelp();
        break;
}

/**
 * Setup monitoring system
 */
function setupMonitoring(): void
{
    echo "Setting up Shopologic monitoring system...\n";
    
    try {
        // Create monitoring directories
        $directories = [
            SHOPOLOGIC_ROOT . '/storage/monitoring',
            SHOPOLOGIC_ROOT . '/storage/monitoring/metrics',
            SHOPOLOGIC_ROOT . '/storage/monitoring/alerts',
            SHOPOLOGIC_ROOT . '/storage/monitoring/logs'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                echo "Created directory: $dir\n";
            }
        }
        
        // Create monitoring configuration
        $configPath = SHOPOLOGIC_ROOT . '/storage/monitoring/config.json';
        
        if (!file_exists($configPath)) {
            $config = [
                'enabled' => true,
                'collect_interval' => 300, // 5 minutes
                'retention_days' => 30,
                'alert_channels' => ['log'],
                'collectors' => [
                    'system' => true,
                    'application' => true,
                    'database' => true,
                    'cache' => true,
                    'http' => true,
                    'business' => true
                ],
                'thresholds' => [
                    'memory_usage' => 80,
                    'disk_usage' => 85,
                    'cpu_usage' => 90,
                    'response_time' => 2000,
                    'error_rate' => 5
                ]
            ];
            
            file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
            echo "Created monitoring configuration: $configPath\n";
        }
        
        // Setup default alert rules
        setupDefaultAlerts();
        
        // Create systemd service file (optional)
        createSystemdService();
        
        echo "✅ Monitoring system setup completed!\n";
        echo "Run 'php cli/monitor.php collect' to start collecting metrics.\n";
        
    } catch (Exception $e) {
        echo "❌ Setup failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Collect metrics
 */
function collectMetrics(): void
{
    echo "Collecting metrics...\n";
    
    try {
        $config = new ConfigurationManager();
        $events = new EventDispatcher();
        $cache = new CacheManager($config);
        $logger = new Logger();
        
        $monitoring = new MonitoringManager($config, $events, $cache, $logger);
        
        $startTime = microtime(true);
        $metrics = $monitoring->collectMetrics();
        $collectTime = (microtime(true) - $startTime) * 1000;
        
        // Store metrics with timestamp
        $timestamp = time();
        $metricsFile = SHOPOLOGIC_ROOT . "/storage/monitoring/metrics/metrics_{$timestamp}.json";
        
        $metricsData = [
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s'),
            'collection_time_ms' => round($collectTime, 2),
            'metrics' => $metrics
        ];
        
        file_put_contents($metricsFile, json_encode($metricsData, JSON_PRETTY_PRINT));
        
        // Update latest metrics
        $latestFile = SHOPOLOGIC_ROOT . '/storage/monitoring/metrics/latest.json';
        file_put_contents($latestFile, json_encode($metricsData, JSON_PRETTY_PRINT));
        
        echo "✅ Metrics collected successfully in " . round($collectTime, 2) . "ms\n";
        echo "Stored in: $metricsFile\n";
        
        // Show summary
        showMetricsSummary($metrics);
        
    } catch (Exception $e) {
        echo "❌ Metrics collection failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Check system health
 */
function checkHealth(): void
{
    echo "Checking system health...\n\n";
    
    try {
        $config = new ConfigurationManager();
        $events = new EventDispatcher();
        $cache = new CacheManager($config);
        $logger = new Logger();
        
        $monitoring = new MonitoringManager($config, $events, $cache, $logger);
        
        $healthStatus = $monitoring->checkHealth();
        
        // Display overall status
        $statusIcon = $healthStatus->isHealthy() ? '✅' : '❌';
        $statusColor = $healthStatus->isHealthy() ? 'green' : 'red';
        
        echo "{$statusIcon} Overall Status: " . strtoupper($healthStatus->getStatus()) . "\n\n";
        
        // Display individual checks
        foreach ($healthStatus->getChecks() as $component => $check) {
            $icon = $check['status'] === 'healthy' ? '✅' : 
                   ($check['status'] === 'warning' ? '⚠️' : '❌');
            
            echo "{$icon} {$component}: {$check['status']}\n";
            echo "   Message: {$check['message']}\n";
            
            if (isset($check['response_time'])) {
                echo "   Response Time: " . round($check['response_time'], 2) . "ms\n";
            }
            
            if (isset($check['disk_usage'])) {
                echo "   Disk Usage: {$check['disk_usage']}%\n";
            }
            
            if (isset($check['usage_percent'])) {
                echo "   Usage: {$check['usage_percent']}%\n";
            }
            
            echo "\n";
        }
        
        // Exit with appropriate code
        exit($healthStatus->isHealthy() ? 0 : 1);
        
    } catch (Exception $e) {
        echo "❌ Health check failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Manage alerts
 */
function manageAlerts(string $action, array $args): void
{
    switch ($action) {
        case 'list':
            listAlerts();
            break;
            
        case 'add':
            addAlert($args);
            break;
            
        case 'remove':
            removeAlert($args[0] ?? '');
            break;
            
        case 'test':
            testAlert($args[0] ?? '');
            break;
            
        default:
            echo "Available alert actions: list, add, remove, test\n";
            break;
    }
}

/**
 * Export metrics
 */
function exportMetrics(string $format): void
{
    echo "Exporting metrics in {$format} format...\n";
    
    try {
        $latestFile = SHOPOLOGIC_ROOT . '/storage/monitoring/metrics/latest.json';
        
        if (!file_exists($latestFile)) {
            echo "❌ No metrics found. Run 'php cli/monitor.php collect' first.\n";
            exit(1);
        }
        
        $metricsData = json_decode(file_get_contents($latestFile), true);
        
        switch ($format) {
            case 'prometheus':
                exportPrometheus($metricsData['metrics']);
                break;
                
            case 'json':
                exportJson($metricsData);
                break;
                
            case 'csv':
                exportCsv($metricsData);
                break;
                
            default:
                echo "❌ Unsupported format: {$format}\n";
                echo "Available formats: prometheus, json, csv\n";
                exit(1);
        }
        
    } catch (Exception $e) {
        echo "❌ Export failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Launch monitoring dashboard
 */
function launchDashboard(): void
{
    echo "Launching monitoring dashboard...\n";
    
    $dashboardPath = SHOPOLOGIC_ROOT . '/public/monitoring.php';
    
    if (!file_exists($dashboardPath)) {
        createDashboard();
    }
    
    $port = 8080;
    $host = 'localhost';
    
    echo "Dashboard will be available at: http://{$host}:{$port}/monitoring.php\n";
    echo "Starting development server...\n";
    echo "Press Ctrl+C to stop\n\n";
    
    system("php -S {$host}:{$port} -t " . SHOPOLOGIC_ROOT . "/public");
}

/**
 * Test monitoring system
 */
function testMonitoring(): void
{
    echo "Running monitoring system tests...\n\n";
    
    $tests = [
        'Configuration' => testConfiguration(),
        'Metrics Collection' => testMetricsCollection(),
        'Health Checks' => testHealthChecks(),
        'Alert System' => testAlertSystem(),
        'Storage' => testStorage()
    ];
    
    $passed = 0;
    $total = count($tests);
    
    foreach ($tests as $testName => $result) {
        $icon = $result['passed'] ? '✅' : '❌';
        echo "{$icon} {$testName}: {$result['message']}\n";
        
        if ($result['passed']) {
            $passed++;
        }
    }
    
    echo "\n";
    echo "Tests passed: {$passed}/{$total}\n";
    
    if ($passed === $total) {
        echo "✅ All tests passed!\n";
        exit(0);
    } else {
        echo "❌ Some tests failed.\n";
        exit(1);
    }
}

/**
 * Clean old metrics
 */
function cleanOldMetrics(): void
{
    echo "Cleaning old metrics...\n";
    
    $metricsDir = SHOPOLOGIC_ROOT . '/storage/monitoring/metrics';
    $retentionDays = 30;
    $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
    
    $deleted = 0;
    
    if (is_dir($metricsDir)) {
        $files = glob($metricsDir . '/metrics_*.json');
        
        foreach ($files as $file) {
            $timestamp = (int)str_replace(
                [$metricsDir . '/metrics_', '.json'],
                '',
                $file
            );
            
            if ($timestamp < $cutoffTime) {
                unlink($file);
                $deleted++;
            }
        }
    }
    
    echo "✅ Deleted {$deleted} old metric files\n";
}

/**
 * Show monitoring status
 */
function showMonitoringStatus(): void
{
    echo "Shopologic Monitoring Status\n";
    echo "==========================\n\n";
    
    // Check if monitoring is set up
    $configPath = SHOPOLOGIC_ROOT . '/storage/monitoring/config.json';
    
    if (!file_exists($configPath)) {
        echo "❌ Monitoring not set up. Run 'php cli/monitor.php setup' first.\n";
        exit(1);
    }
    
    $config = json_decode(file_get_contents($configPath), true);
    
    echo "Configuration: " . ($config['enabled'] ? '✅ Enabled' : '❌ Disabled') . "\n";
    echo "Collection Interval: {$config['collect_interval']} seconds\n";
    echo "Retention: {$config['retention_days']} days\n";
    echo "Alert Channels: " . implode(', ', $config['alert_channels']) . "\n\n";
    
    // Check latest metrics
    $latestFile = SHOPOLOGIC_ROOT . '/storage/monitoring/metrics/latest.json';
    
    if (file_exists($latestFile)) {
        $latest = json_decode(file_get_contents($latestFile), true);
        $age = time() - $latest['timestamp'];
        
        echo "Last Collection: " . date('Y-m-d H:i:s', $latest['timestamp']) . " ({$age}s ago)\n";
        echo "Collection Time: {$latest['collection_time_ms']}ms\n\n";
    } else {
        echo "❌ No metrics collected yet\n\n";
    }
    
    // Show collector status
    echo "Collectors:\n";
    foreach ($config['collectors'] as $collector => $enabled) {
        $icon = $enabled ? '✅' : '❌';
        echo "  {$icon} {$collector}\n";
    }
}

/**
 * Show help
 */
function showHelp(): void
{
    echo "Shopologic Monitoring CLI Tool\n";
    echo "============================\n\n";
    echo "Usage: php cli/monitor.php <command> [options]\n\n";
    echo "Commands:\n";
    echo "  setup           Set up monitoring system\n";
    echo "  collect         Collect metrics now\n";
    echo "  health          Check system health\n";
    echo "  alerts <action> Manage alerts (list, add, remove, test)\n";
    echo "  export <format> Export metrics (prometheus, json, csv)\n";
    echo "  dashboard       Launch monitoring dashboard\n";
    echo "  test            Run monitoring system tests\n";
    echo "  clean           Clean old metrics\n";
    echo "  status          Show monitoring status\n";
    echo "  help            Show this help\n\n";
    echo "Examples:\n";
    echo "  php cli/monitor.php setup\n";
    echo "  php cli/monitor.php collect\n";
    echo "  php cli/monitor.php health\n";
    echo "  php cli/monitor.php export prometheus\n";
    echo "  php cli/monitor.php alerts list\n";
    echo "  php cli/monitor.php dashboard\n";
}

/**
 * Setup default alert rules
 */
function setupDefaultAlerts(): void
{
    $alertsFile = SHOPOLOGIC_ROOT . '/storage/monitoring/alerts/rules.json';
    
    if (!file_exists($alertsFile)) {
        $defaultRules = [
            [
                'name' => 'High Memory Usage',
                'metric_pattern' => 'system.memory.usage_percent',
                'operator' => '>',
                'threshold' => 90,
                'severity' => 'warning',
                'channels' => ['log']
            ],
            [
                'name' => 'High Disk Usage',
                'metric_pattern' => 'system.disk.usage_percent',
                'operator' => '>',
                'threshold' => 85,
                'severity' => 'critical',
                'channels' => ['log']
            ],
            [
                'name' => 'High CPU Usage',
                'metric_pattern' => 'system.cpu.usage_percent',
                'operator' => '>',
                'threshold' => 95,
                'severity' => 'warning',
                'channels' => ['log']
            ],
            [
                'name' => 'Database Connection Failed',
                'metric_pattern' => 'database.connection.connected',
                'operator' => '==',
                'threshold' => false,
                'severity' => 'critical',
                'channels' => ['log']
            ],
            [
                'name' => 'Low Cache Hit Ratio',
                'metric_pattern' => 'cache.redis.stats.hit_ratio',
                'operator' => '<',
                'threshold' => 80,
                'severity' => 'warning',
                'channels' => ['log']
            ]
        ];
        
        file_put_contents($alertsFile, json_encode($defaultRules, JSON_PRETTY_PRINT));
        echo "Created default alert rules: $alertsFile\n";
    }
}

/**
 * Create systemd service file
 */
function createSystemdService(): void
{
    $servicePath = '/tmp/shopologic-monitoring.service';
    
    $serviceContent = <<<EOT
[Unit]
Description=Shopologic Monitoring Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=" . SHOPOLOGIC_ROOT . "
ExecStart=/usr/bin/php " . SHOPOLOGIC_ROOT . "/cli/monitor.php collect
Restart=always
RestartSec=300

[Install]
WantedBy=multi-user.target
EOT;
    
    file_put_contents($servicePath, $serviceContent);
    echo "Created systemd service file: $servicePath\n";
    echo "To install: sudo mv $servicePath /etc/systemd/system/ && sudo systemctl enable shopologic-monitoring\n";
}

/**
 * Show metrics summary
 */
function showMetricsSummary(array $metrics): void
{
    echo "\nMetrics Summary:\n";
    echo "===============\n";
    
    // System metrics
    if (isset($metrics['system'])) {
        echo "System:\n";
        if (isset($metrics['system']['memory']['usage_percent'])) {
            echo "  Memory Usage: " . round($metrics['system']['memory']['usage_percent'], 1) . "%\n";
        }
        if (isset($metrics['system']['disk']['usage_percent'])) {
            echo "  Disk Usage: " . round($metrics['system']['disk']['usage_percent'], 1) . "%\n";
        }
        if (isset($metrics['system']['cpu']['usage_percent'])) {
            echo "  CPU Usage: " . round($metrics['system']['cpu']['usage_percent'], 1) . "%\n";
        }
    }
    
    // Database metrics
    if (isset($metrics['database']['connection']['connected'])) {
        $dbStatus = $metrics['database']['connection']['connected'] ? 'Connected' : 'Disconnected';
        echo "\nDatabase: $dbStatus\n";
    }
    
    // Cache metrics
    if (isset($metrics['cache']['redis']['connected'])) {
        $cacheStatus = $metrics['cache']['redis']['connected'] ? 'Connected' : 'Disconnected';
        echo "Cache: $cacheStatus\n";
    }
    
    // Business metrics
    if (isset($metrics['business']['sales']['today']['total_sales'])) {
        echo "\nBusiness:\n";
        echo "  Today's Sales: $" . number_format($metrics['business']['sales']['today']['total_sales'], 2) . "\n";
        echo "  Today's Orders: " . $metrics['business']['sales']['today']['order_count'] . "\n";
    }
}

/**
 * Test functions
 */
function testConfiguration(): array
{
    try {
        $configPath = SHOPOLOGIC_ROOT . '/storage/monitoring/config.json';
        
        if (!file_exists($configPath)) {
            return ['passed' => false, 'message' => 'Configuration file not found'];
        }
        
        $config = json_decode(file_get_contents($configPath), true);
        
        if (!$config) {
            return ['passed' => false, 'message' => 'Invalid configuration file'];
        }
        
        return ['passed' => true, 'message' => 'Configuration is valid'];
        
    } catch (Exception $e) {
        return ['passed' => false, 'message' => $e->getMessage()];
    }
}

function testMetricsCollection(): array
{
    try {
        $config = new ConfigurationManager();
        $events = new EventDispatcher();
        $cache = new CacheManager($config);
        $logger = new Logger();
        
        $monitoring = new MonitoringManager($config, $events, $cache, $logger);
        $metrics = $monitoring->collectMetrics();
        
        if (empty($metrics)) {
            return ['passed' => false, 'message' => 'No metrics collected'];
        }
        
        return ['passed' => true, 'message' => 'Metrics collection working'];
        
    } catch (Exception $e) {
        return ['passed' => false, 'message' => $e->getMessage()];
    }
}

function testHealthChecks(): array
{
    try {
        $config = new ConfigurationManager();
        $events = new EventDispatcher();
        $cache = new CacheManager($config);
        $logger = new Logger();
        
        $monitoring = new MonitoringManager($config, $events, $cache, $logger);
        $health = $monitoring->checkHealth();
        
        return ['passed' => true, 'message' => 'Health checks working'];
        
    } catch (Exception $e) {
        return ['passed' => false, 'message' => $e->getMessage()];
    }
}

function testAlertSystem(): array
{
    try {
        $alertsFile = SHOPOLOGIC_ROOT . '/storage/monitoring/alerts/rules.json';
        
        if (!file_exists($alertsFile)) {
            return ['passed' => false, 'message' => 'Alert rules file not found'];
        }
        
        return ['passed' => true, 'message' => 'Alert system configured'];
        
    } catch (Exception $e) {
        return ['passed' => false, 'message' => $e->getMessage()];
    }
}

function testStorage(): array
{
    try {
        $storageDir = SHOPOLOGIC_ROOT . '/storage/monitoring';
        
        if (!is_dir($storageDir)) {
            return ['passed' => false, 'message' => 'Storage directory not found'];
        }
        
        if (!is_writable($storageDir)) {
            return ['passed' => false, 'message' => 'Storage directory not writable'];
        }
        
        return ['passed' => true, 'message' => 'Storage is accessible'];
        
    } catch (Exception $e) {
        return ['passed' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Export functions
 */
function exportPrometheus(array $metrics): void
{
    echo "# Shopologic Metrics\n";
    echo "# TYPE shopologic_info gauge\n";
    echo "shopologic_info{version=\"1.0.0\"} 1\n\n";
    
    foreach ($metrics as $category => $categoryMetrics) {
        if (is_array($categoryMetrics)) {
            exportPrometheusCategory($category, $categoryMetrics);
        }
    }
}

function exportPrometheusCategory(string $category, array $metrics, string $prefix = ''): void
{
    foreach ($metrics as $key => $value) {
        $metricName = $prefix ? "{$prefix}_{$key}" : "{$category}_{$key}";
        $metricName = preg_replace('/[^a-zA-Z0-9_]/', '_', $metricName);
        
        if (is_numeric($value)) {
            echo "shopologic_{$metricName} {$value}\n";
        } elseif (is_array($value) && !empty($value)) {
            exportPrometheusCategory($category, $value, $metricName);
        }
    }
}

function exportJson(array $data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT);
}

function exportCsv(array $data): void
{
    $flatMetrics = flattenArray($data['metrics']);
    
    echo "timestamp,metric,value\n";
    
    foreach ($flatMetrics as $metric => $value) {
        if (is_numeric($value)) {
            echo "{$data['timestamp']},{$metric},{$value}\n";
        }
    }
}

function flattenArray(array $array, string $prefix = ''): array
{
    $result = [];
    
    foreach ($array as $key => $value) {
        $newKey = $prefix ? "{$prefix}.{$key}" : $key;
        
        if (is_array($value)) {
            $result = array_merge($result, flattenArray($value, $newKey));
        } else {
            $result[$newKey] = $value;
        }
    }
    
    return $result;
}

function listAlerts(): void
{
    $alertsFile = SHOPOLOGIC_ROOT . '/storage/monitoring/alerts/rules.json';
    
    if (!file_exists($alertsFile)) {
        echo "No alerts configured.\n";
        return;
    }
    
    $rules = json_decode(file_get_contents($alertsFile), true);
    
    echo "Configured Alerts:\n";
    echo "==================\n\n";
    
    foreach ($rules as $index => $rule) {
        echo ($index + 1) . ". {$rule['name']}\n";
        echo "   Metric: {$rule['metric_pattern']}\n";
        echo "   Condition: {$rule['operator']} {$rule['threshold']}\n";
        echo "   Severity: {$rule['severity']}\n";
        echo "   Channels: " . implode(', ', $rule['channels']) . "\n\n";
    }
}

function addAlert(array $args): void
{
    if (count($args) < 5) {
        echo "Usage: php cli/monitor.php alerts add <name> <metric> <operator> <threshold> <severity>\n";
        echo "Example: php cli/monitor.php alerts add \"High CPU\" \"system.cpu.usage_percent\" \">\" 90 warning\n";
        return;
    }
    
    $rule = [
        'name' => $args[0],
        'metric_pattern' => $args[1],
        'operator' => $args[2],
        'threshold' => is_numeric($args[3]) ? (float)$args[3] : $args[3],
        'severity' => $args[4],
        'channels' => ['log']
    ];
    
    $alertsFile = SHOPOLOGIC_ROOT . '/storage/monitoring/alerts/rules.json';
    $rules = [];
    
    if (file_exists($alertsFile)) {
        $rules = json_decode(file_get_contents($alertsFile), true) ?: [];
    }
    
    $rules[] = $rule;
    
    file_put_contents($alertsFile, json_encode($rules, JSON_PRETTY_PRINT));
    
    echo "✅ Alert rule added successfully\n";
}

function removeAlert(string $name): void
{
    if (empty($name)) {
        echo "Usage: php cli/monitor.php alerts remove <name>\n";
        return;
    }
    
    $alertsFile = SHOPOLOGIC_ROOT . '/storage/monitoring/alerts/rules.json';
    
    if (!file_exists($alertsFile)) {
        echo "No alerts configured.\n";
        return;
    }
    
    $rules = json_decode(file_get_contents($alertsFile), true);
    $found = false;
    
    foreach ($rules as $index => $rule) {
        if ($rule['name'] === $name) {
            unset($rules[$index]);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "Alert rule '{$name}' not found.\n";
        return;
    }
    
    $rules = array_values($rules);
    file_put_contents($alertsFile, json_encode($rules, JSON_PRETTY_PRINT));
    
    echo "✅ Alert rule '{$name}' removed successfully\n";
}

function testAlert(string $name): void
{
    echo "Testing alert: {$name}\n";
    echo "This would trigger a test alert...\n";
}

function createDashboard(): void
{
    $dashboardPath = SHOPOLOGIC_ROOT . '/public/monitoring.php';
    
    $dashboardContent = <<<'EOT'
<?php
// Simple monitoring dashboard
header('Content-Type: text/html; charset=UTF-8');

$latestFile = dirname(__DIR__) . '/storage/monitoring/metrics/latest.json';
$metrics = [];

if (file_exists($latestFile)) {
    $data = json_decode(file_get_contents($latestFile), true);
    $metrics = $data['metrics'] ?? [];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Shopologic Monitoring Dashboard</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .metric { display: inline-block; margin: 10px; padding: 10px; background: #e9ecef; border-radius: 4px; }
        .metric-value { font-size: 1.5em; font-weight: bold; color: #007bff; }
        .status-ok { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        h1, h2 { color: #333; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Shopologic Monitoring Dashboard</h1>
        
        <?php if (empty($metrics)): ?>
            <div class="card">
                <p>No metrics available. Run <code>php cli/monitor.php collect</code> to collect metrics.</p>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php if (isset($metrics['system'])): ?>
                    <div class="card">
                        <h2>System Metrics</h2>
                        <?php if (isset($metrics['system']['memory']['usage_percent'])): ?>
                            <div class="metric">
                                <div>Memory Usage</div>
                                <div class="metric-value"><?= round($metrics['system']['memory']['usage_percent'], 1) ?>%</div>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($metrics['system']['disk']['usage_percent'])): ?>
                            <div class="metric">
                                <div>Disk Usage</div>
                                <div class="metric-value"><?= round($metrics['system']['disk']['usage_percent'], 1) ?>%</div>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($metrics['system']['cpu']['usage_percent'])): ?>
                            <div class="metric">
                                <div>CPU Usage</div>
                                <div class="metric-value"><?= round($metrics['system']['cpu']['usage_percent'], 1) ?>%</div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($metrics['business'])): ?>
                    <div class="card">
                        <h2>Business Metrics</h2>
                        <?php if (isset($metrics['business']['sales']['today'])): ?>
                            <div class="metric">
                                <div>Today's Sales</div>
                                <div class="metric-value">$<?= number_format($metrics['business']['sales']['today']['total_sales'], 2) ?></div>
                            </div>
                            <div class="metric">
                                <div>Today's Orders</div>
                                <div class="metric-value"><?= $metrics['business']['sales']['today']['order_count'] ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($metrics['database'])): ?>
                    <div class="card">
                        <h2>Database</h2>
                        <div class="metric">
                            <div>Status</div>
                            <div class="metric-value <?= $metrics['database']['connection']['connected'] ? 'status-ok' : 'status-error' ?>">
                                <?= $metrics['database']['connection']['connected'] ? 'Connected' : 'Disconnected' ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($metrics['cache'])): ?>
                    <div class="card">
                        <h2>Cache</h2>
                        <div class="metric">
                            <div>Redis Status</div>
                            <div class="metric-value <?= $metrics['cache']['redis']['connected'] ? 'status-ok' : 'status-error' ?>">
                                <?= $metrics['cache']['redis']['connected'] ? 'Connected' : 'Disconnected' ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <p><small>Auto-refresh every 30 seconds</small></p>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>
EOT;
    
    file_put_contents($dashboardPath, $dashboardContent);
    echo "Created monitoring dashboard: $dashboardPath\n";
}