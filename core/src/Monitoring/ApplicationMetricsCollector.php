<?php

declare(strict_types=1);

namespace Shopologic\Core\Monitoring;

/**
 * Application Metrics Collector
 * 
 * Collects application-specific metrics
 */
class ApplicationMetricsCollector implements MetricsCollectorInterface
{
    /**
     * Collect application metrics
     */
    public function collect(): array
    {
        return [
            'php' => $this->getPhpMetrics(),
            'framework' => $this->getFrameworkMetrics(),
            'plugins' => $this->getPluginMetrics(),
            'sessions' => $this->getSessionMetrics(),
            'errors' => $this->getErrorMetrics(),
            'performance' => $this->getPerformanceMetrics()
        ];
    }
    
    /**
     * Get PHP-specific metrics
     */
    private function getPhpMetrics(): array
    {
        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'extensions' => $this->getLoadedExtensions(),
            'opcache' => $this->getOpcacheMetrics(),
            'memory' => [
                'usage' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => $this->parseBytes(ini_get('memory_limit'))
            ],
            'execution_time' => [
                'max' => (int)ini_get('max_execution_time'),
                'current' => $this->getCurrentExecutionTime()
            ],
            'file_uploads' => [
                'max_size' => $this->parseBytes(ini_get('upload_max_filesize')),
                'post_max_size' => $this->parseBytes(ini_get('post_max_size')),
                'max_files' => (int)ini_get('max_file_uploads')
            ]
        ];
    }
    
    /**
     * Get framework-specific metrics
     */
    private function getFrameworkMetrics(): array
    {
        $metrics = [
            'version' => $this->getFrameworkVersion(),
            'environment' => getenv('APP_ENV') ?: 'unknown',
            'debug_mode' => getenv('APP_DEBUG') === 'true',
            'timezone' => date_default_timezone_get(),
            'locale' => setlocale(LC_ALL, '0')
        ];
        
        // Request metrics (if in HTTP context)
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $metrics['request'] = [
                'method' => $_SERVER['REQUEST_METHOD'],
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $this->getClientIp(),
                'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
            ];
        }
        
        return $metrics;
    }
    
    /**
     * Get plugin metrics
     */
    private function getPluginMetrics(): array
    {
        $metrics = [
            'total_installed' => 0,
            'total_active' => 0,
            'plugins' => []
        ];
        
        $pluginsDir = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT . '/plugins' : null;
        
        if ($pluginsDir && is_dir($pluginsDir)) {
            $plugins = array_diff(scandir($pluginsDir), ['.', '..']);
            
            foreach ($plugins as $plugin) {
                $pluginPath = $pluginsDir . '/' . $plugin;
                
                if (is_dir($pluginPath)) {
                    $manifestFile = $pluginPath . '/plugin.json';
                    
                    if (file_exists($manifestFile)) {
                        $manifest = json_decode(file_get_contents($manifestFile), true);
                        
                        if ($manifest) {
                            $metrics['total_installed']++;
                            
                            $pluginInfo = [
                                'name' => $manifest['name'] ?? $plugin,
                                'version' => $manifest['version'] ?? 'unknown',
                                'active' => $this->isPluginActive($plugin)
                            ];
                            
                            if ($pluginInfo['active']) {
                                $metrics['total_active']++;
                            }
                            
                            $metrics['plugins'][] = $pluginInfo;
                        }
                    }
                }
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get session metrics
     */
    private function getSessionMetrics(): array
    {
        $metrics = [
            'handler' => ini_get('session.save_handler'),
            'path' => ini_get('session.save_path'),
            'name' => ini_get('session.name'),
            'lifetime' => (int)ini_get('session.gc_maxlifetime'),
            'probability' => ini_get('session.gc_probability'),
            'divisor' => ini_get('session.gc_divisor')
        ];
        
        // Active session info
        if (session_status() === PHP_SESSION_ACTIVE) {
            $metrics['active'] = [
                'id' => session_id(),
                'started' => true,
                'data_size' => strlen(serialize($_SESSION ?? []))
            ];
        } else {
            $metrics['active'] = ['started' => false];
        }
        
        // Session file count (if using file handler)
        if ($metrics['handler'] === 'files' && is_dir($metrics['path'])) {
            $sessionFiles = glob($metrics['path'] . '/sess_*');
            $metrics['file_count'] = count($sessionFiles);
            
            // Calculate total session data size
            $totalSize = 0;
            foreach ($sessionFiles as $file) {
                $totalSize += filesize($file);
            }
            $metrics['total_size'] = $totalSize;
        }
        
        return $metrics;
    }
    
    /**
     * Get error metrics
     */
    private function getErrorMetrics(): array
    {
        $metrics = [
            'reporting_level' => error_reporting(),
            'display_errors' => ini_get('display_errors'),
            'log_errors' => ini_get('log_errors'),
            'error_log' => ini_get('error_log')
        ];
        
        // Try to get recent error count from log
        $errorLog = $metrics['error_log'];
        if ($errorLog && file_exists($errorLog)) {
            $metrics['log_size'] = filesize($errorLog);
            $metrics['recent_errors'] = $this->countRecentErrors($errorLog);
        }
        
        // Application log metrics
        $appLogPath = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT . '/storage/logs' : null;
        if ($appLogPath && is_dir($appLogPath)) {
            $logFiles = glob($appLogPath . '/*.log');
            $metrics['app_logs'] = [
                'file_count' => count($logFiles),
                'total_size' => array_sum(array_map('filesize', $logFiles))
            ];
        }
        
        return $metrics;
    }
    
    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        $metrics = [
            'request_start_time' => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
            'current_time' => microtime(true),
            'included_files' => count(get_included_files()),
            'declared_classes' => count(get_declared_classes()),
            'declared_functions' => count(get_defined_functions()['user'])
        ];
        
        $metrics['elapsed_time'] = $metrics['current_time'] - $metrics['request_start_time'];
        
        // OPcache performance
        if (function_exists('opcache_get_status')) {
            $opcacheStatus = opcache_get_status();
            if ($opcacheStatus) {
                $metrics['opcache_hit_rate'] = $opcacheStatus['opcache_statistics']['opcache_hit_rate'] ?? 0;
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get loaded PHP extensions
     */
    private function getLoadedExtensions(): array
    {
        $extensions = get_loaded_extensions();
        $extensionInfo = [];
        
        foreach ($extensions as $extension) {
            $extensionInfo[$extension] = phpversion($extension) ?: true;
        }
        
        return $extensionInfo;
    }
    
    /**
     * Get OPcache metrics
     */
    private function getOpcacheMetrics(): array
    {
        if (!function_exists('opcache_get_status')) {
            return ['enabled' => false];
        }
        
        $status = opcache_get_status();
        
        if (!$status) {
            return ['enabled' => false];
        }
        
        return [
            'enabled' => true,
            'memory_usage' => $status['memory_usage'] ?? [],
            'statistics' => $status['opcache_statistics'] ?? [],
            'configuration' => opcache_get_configuration()['directives'] ?? []
        ];
    }
    
    /**
     * Get current execution time
     */
    private function getCurrentExecutionTime(): float
    {
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        }
        
        return 0.0;
    }
    
    /**
     * Get framework version
     */
    private function getFrameworkVersion(): string
    {
        // Try to get version from various sources
        $versionFile = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT . '/VERSION' : null;
        
        if ($versionFile && file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }
        
        $composerFile = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT . '/composer.json' : null;
        
        if ($composerFile && file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            if (isset($composer['version'])) {
                return $composer['version'];
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Check if plugin is active
     */
    private function isPluginActive(string $plugin): bool
    {
        $pluginsFile = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT . '/storage/plugins/plugins.json' : null;
        
        if ($pluginsFile && file_exists($pluginsFile)) {
            $pluginsData = json_decode(file_get_contents($pluginsFile), true);
            return in_array($plugin, $pluginsData['active'] ?? []);
        }
        
        return false;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Count recent errors in log file
     */
    private function countRecentErrors(string $logFile): int
    {
        if (!file_exists($logFile)) {
            return 0;
        }
        
        $count = 0;
        $oneDayAgo = time() - 86400; // 24 hours ago
        
        $handle = fopen($logFile, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                // Simple check for recent timestamps in common log formats
                if (preg_match('/\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                    $timestamp = strtotime($matches[1]);
                    if ($timestamp && $timestamp > $oneDayAgo) {
                        $count++;
                    }
                }
            }
            fclose($handle);
        }
        
        return $count;
    }
    
    /**
     * Parse memory size string to bytes
     */
    private function parseBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '-1') {
            return -1; // No limit
        }
        
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