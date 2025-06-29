<?php

declare(strict_types=1);

namespace Shopologic\Core\Monitoring;

/**
 * HTTP Metrics Collector
 * 
 * Collects HTTP request/response metrics and performance data
 */
class HttpMetricsCollector implements MetricsCollectorInterface
{
    /**
     * Collect HTTP metrics
     */
    public function collect(): array
    {
        return [
            'request' => $this->getRequestMetrics(),
            'response' => $this->getResponseMetrics(),
            'performance' => $this->getPerformanceMetrics(),
            'errors' => $this->getErrorMetrics(),
            'security' => $this->getSecurityMetrics(),
            'statistics' => $this->getStatisticsMetrics()
        ];
    }
    
    /**
     * Get request metrics
     */
    private function getRequestMetrics(): array
    {
        $metrics = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'scheme' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
            'host' => $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'accept' => $_SERVER['HTTP_ACCEPT'] ?? '',
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
            'content_length' => isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0,
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'remote_addr' => $this->getClientIp(),
            'request_time' => $_SERVER['REQUEST_TIME'] ?? time(),
            'request_time_float' => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)
        ];
        
        // Parse URL components
        $urlParts = parse_url($metrics['uri']);
        $metrics['path'] = $urlParts['path'] ?? '/';
        $metrics['query'] = $urlParts['query'] ?? '';
        
        // Request headers
        $metrics['headers'] = $this->getRequestHeaders();
        
        // Body size for POST/PUT requests
        if (in_array($metrics['method'], ['POST', 'PUT', 'PATCH'])) {
            $metrics['body_size'] = $this->getRequestBodySize();
        }
        
        // Geographic info (if available)
        $metrics['geo'] = $this->getGeoInfo($metrics['remote_addr']);
        
        return $metrics;
    }
    
    /**
     * Get response metrics
     */
    private function getResponseMetrics(): array
    {
        $metrics = [
            'status_code' => http_response_code() ?: 200,
            'headers_sent' => headers_sent(),
            'content_type' => 'text/html', // Default
            'content_length' => 0,
            'compression' => false
        ];
        
        // Get response headers
        $headers = headers_list();
        $responseHeaders = [];
        
        foreach ($headers as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                $responseHeaders[strtolower($name)] = $value;
                
                // Extract specific metrics
                if (strtolower($name) === 'content-type') {
                    $metrics['content_type'] = $value;
                } elseif (strtolower($name) === 'content-length') {
                    $metrics['content_length'] = (int)$value;
                } elseif (strtolower($name) === 'content-encoding') {
                    $metrics['compression'] = in_array(strtolower($value), ['gzip', 'deflate', 'br']);
                }
            }
        }
        
        $metrics['headers'] = $responseHeaders;
        
        // Estimate response size if not set
        if ($metrics['content_length'] === 0 && ob_get_length() !== false) {
            $metrics['content_length'] = ob_get_length();
        }
        
        // Response time
        $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $metrics['response_time'] = (microtime(true) - $startTime) * 1000; // milliseconds
        
        return $metrics;
    }
    
    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $currentTime = microtime(true);
        
        $metrics = [
            'execution_time' => ($currentTime - $startTime) * 1000, // milliseconds
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'included_files' => count(get_included_files()),
            'declared_classes' => count(get_declared_classes()),
            'function_calls' => 0 // Would need xdebug or similar for accurate count
        ];
        
        // Database query metrics (if available)
        $metrics['database'] = $this->getDatabaseMetrics();
        
        // Cache metrics
        $metrics['cache'] = $this->getCacheMetrics();
        
        // File I/O metrics
        $metrics['file_io'] = $this->getFileIoMetrics();
        
        return $metrics;
    }
    
    /**
     * Get error metrics
     */
    private function getErrorMetrics(): array
    {
        $metrics = [
            'php_errors' => [],
            'exceptions' => [],
            'warnings' => [],
            'notices' => []
        ];
        
        // Check if error log exists and read recent errors
        $errorLog = ini_get('error_log');
        if ($errorLog && file_exists($errorLog)) {
            $metrics['error_log_size'] = filesize($errorLog);
            $metrics['recent_errors'] = $this->getRecentErrors($errorLog);
        }
        
        // Application error log
        $appErrorLog = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT . '/storage/logs/error.log' : null;
        if ($appErrorLog && file_exists($appErrorLog)) {
            $metrics['app_error_log_size'] = filesize($appErrorLog);
            $metrics['recent_app_errors'] = $this->getRecentErrors($appErrorLog);
        }
        
        return $metrics;
    }
    
    /**
     * Get security metrics
     */
    private function getSecurityMetrics(): array
    {
        $metrics = [
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'csrf_token' => isset($_POST['_token']) || isset($_GET['_token']),
            'suspicious_patterns' => [],
            'rate_limiting' => false,
            'authentication' => false
        ];
        
        // Check for suspicious patterns in request
        $suspicious = [];
        
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        
        // SQL injection patterns
        if (preg_match('/(\bunion\b|\bselect\b|\binsert\b|\bdelete\b|\bdrop\b)/i', $uri . $queryString)) {
            $suspicious[] = 'sql_injection_attempt';
        }
        
        // XSS patterns
        if (preg_match('/<script|javascript:|onload=|onerror=/i', $uri . $queryString)) {
            $suspicious[] = 'xss_attempt';
        }
        
        // Path traversal
        if (preg_match('/\.\.[\/\\\\]/', $uri)) {
            $suspicious[] = 'path_traversal_attempt';
        }
        
        // Suspicious user agents
        if (preg_match('/(bot|crawler|spider|scanner)/i', $userAgent) && 
            !preg_match('/(googlebot|bingbot|facebookexternalhit)/i', $userAgent)) {
            $suspicious[] = 'suspicious_user_agent';
        }
        
        $metrics['suspicious_patterns'] = $suspicious;
        
        // Check for authentication headers
        if (isset($_SERVER['HTTP_AUTHORIZATION']) || isset($_SERVER['HTTP_X_API_KEY'])) {
            $metrics['authentication'] = true;
        }
        
        // Check for rate limiting headers
        if (isset($_SERVER['HTTP_X_RATE_LIMIT']) || isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $metrics['rate_limiting'] = true;
        }
        
        return $metrics;
    }
    
    /**
     * Get statistics metrics
     */
    private function getStatisticsMetrics(): array
    {
        $metrics = [
            'requests_today' => 0,
            'requests_this_hour' => 0,
            'unique_ips_today' => 0,
            'top_endpoints' => [],
            'top_user_agents' => [],
            'response_codes' => []
        ];
        
        // Try to get statistics from access log or application cache
        $statsFile = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT . '/storage/logs/http_stats.json' : null;
        
        if ($statsFile && file_exists($statsFile)) {
            try {
                $stats = json_decode(file_get_contents($statsFile), true);
                if ($stats) {
                    $metrics = array_merge($metrics, $stats);
                }
            } catch (\Exception $e) {
                // Ignore stats file errors
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_CLIENT_IP',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get request headers
     */
    private function getRequestHeaders(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$headerName] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * Get request body size
     */
    private function getRequestBodySize(): int
    {
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            return (int)$_SERVER['CONTENT_LENGTH'];
        }
        
        // Try to get from php://input
        $input = file_get_contents('php://input');
        return strlen($input);
    }
    
    /**
     * Get geographic information for IP
     */
    private function getGeoInfo(string $ip): array
    {
        $geo = [
            'country' => 'unknown',
            'region' => 'unknown',
            'city' => 'unknown',
            'timezone' => 'unknown'
        ];
        
        // Basic geo detection (would normally use GeoIP database)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            // This is a placeholder - in production you'd use a service like:
            // - MaxMind GeoIP2
            // - ip-api.com
            // - ipstack.com
            $geo['country'] = 'detected'; // Placeholder
        }
        
        return $geo;
    }
    
    /**
     * Get database metrics for this request
     */
    private function getDatabaseMetrics(): array
    {
        return [
            'queries' => 0,      // Would be tracked by database layer
            'query_time' => 0,   // Total query time in milliseconds
            'slow_queries' => 0  // Queries over threshold
        ];
    }
    
    /**
     * Get cache metrics for this request
     */
    private function getCacheMetrics(): array
    {
        return [
            'hits' => 0,         // Cache hits during request
            'misses' => 0,       // Cache misses during request
            'sets' => 0,         // Cache sets during request
            'deletes' => 0       // Cache deletes during request
        ];
    }
    
    /**
     * Get file I/O metrics
     */
    private function getFileIoMetrics(): array
    {
        return [
            'reads' => 0,        // File reads during request
            'writes' => 0,       // File writes during request
            'uploaded_files' => count($_FILES ?? [])
        ];
    }
    
    /**
     * Get recent errors from log file
     */
    private function getRecentErrors(string $logFile): array
    {
        $errors = [];
        $maxErrors = 10;
        $oneDayAgo = time() - 86400;
        
        try {
            $handle = fopen($logFile, 'r');
            if ($handle) {
                $lines = [];
                
                // Read last few lines efficiently
                fseek($handle, -8192, SEEK_END); // Read last 8KB
                $content = fread($handle, 8192);
                $lines = array_filter(explode("\n", $content));
                $lines = array_slice($lines, -100); // Last 100 lines
                
                foreach (array_reverse($lines) as $line) {
                    if (count($errors) >= $maxErrors) {
                        break;
                    }
                    
                    // Parse log line for timestamp and error
                    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                        $timestamp = strtotime($matches[1]);
                        
                        if ($timestamp && $timestamp > $oneDayAgo) {
                            $errors[] = [
                                'timestamp' => $matches[1],
                                'message' => trim(substr($line, strpos($line, ']') + 1))
                            ];
                        }
                    }
                }
                
                fclose($handle);
            }
        } catch (\Exception $e) {
            // Ignore file read errors
        }
        
        return $errors;
    }
}