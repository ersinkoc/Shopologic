<?php

declare(strict_types=1);

namespace Shopologic\Core\Monitoring;

/**
 * System Metrics Collector
 * 
 * Collects system-level metrics like CPU, memory, disk usage
 */
class SystemMetricsCollector implements MetricsCollectorInterface
{
    /**
     * Collect system metrics
     */
    public function collect(): array
    {
        return [
            'cpu' => $this->getCpuMetrics(),
            'memory' => $this->getMemoryMetrics(),
            'disk' => $this->getDiskMetrics(),
            'load' => $this->getLoadMetrics(),
            'network' => $this->getNetworkMetrics(),
            'uptime' => $this->getUptimeMetrics()
        ];
    }
    
    /**
     * Get CPU metrics
     */
    private function getCpuMetrics(): array
    {
        $metrics = [
            'usage_percent' => 0,
            'cores' => 1,
            'load_average' => []
        ];
        
        // Try to get CPU usage on Linux
        if (PHP_OS_FAMILY === 'Linux') {
            // Get number of CPU cores
            $cpuInfo = file_get_contents('/proc/cpuinfo');
            if ($cpuInfo) {
                $metrics['cores'] = substr_count($cpuInfo, 'processor');
            }
            
            // Get load average
            $loadavg = sys_getloadavg();
            if ($loadavg) {
                $metrics['load_average'] = [
                    '1_min' => $loadavg[0],
                    '5_min' => $loadavg[1],
                    '15_min' => $loadavg[2]
                ];
                
                // Calculate approximate CPU usage from load average
                $metrics['usage_percent'] = min(100, ($loadavg[0] / $metrics['cores']) * 100);
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get memory metrics
     */
    private function getMemoryMetrics(): array
    {
        $metrics = [
            'total' => 0,
            'used' => 0,
            'free' => 0,
            'usage_percent' => 0,
            'php_memory_usage' => memory_get_usage(true),
            'php_memory_peak' => memory_get_peak_usage(true),
            'php_memory_limit' => $this->parseBytes(ini_get('memory_limit'))
        ];
        
        // Calculate PHP memory usage percentage
        if ($metrics['php_memory_limit'] > 0) {
            $metrics['php_usage_percent'] = ($metrics['php_memory_usage'] / $metrics['php_memory_limit']) * 100;
        }
        
        // Try to get system memory info on Linux
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            
            if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches)) {
                $metrics['total'] = (int)$matches[1] * 1024;
            }
            
            if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matches)) {
                $metrics['free'] = (int)$matches[1] * 1024;
                $metrics['used'] = $metrics['total'] - $metrics['free'];
                
                if ($metrics['total'] > 0) {
                    $metrics['usage_percent'] = ($metrics['used'] / $metrics['total']) * 100;
                }
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get disk metrics
     */
    private function getDiskMetrics(): array
    {
        $rootPath = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT : '/';
        
        $metrics = [
            'total' => 0,
            'used' => 0,
            'free' => 0,
            'usage_percent' => 0,
            'inodes_total' => 0,
            'inodes_used' => 0,
            'inodes_free' => 0
        ];
        
        try {
            $totalBytes = disk_total_space($rootPath);
            $freeBytes = disk_free_space($rootPath);
            
            if ($totalBytes && $freeBytes) {
                $metrics['total'] = $totalBytes;
                $metrics['free'] = $freeBytes;
                $metrics['used'] = $totalBytes - $freeBytes;
                $metrics['usage_percent'] = (($totalBytes - $freeBytes) / $totalBytes) * 100;
            }
            
            // Get inode information on Linux
            if (PHP_OS_FAMILY === 'Linux') {
                $dfOutput = shell_exec("df -i " . escapeshellarg($rootPath) . " 2>/dev/null");
                if ($dfOutput && preg_match('/\s+(\d+)\s+(\d+)\s+(\d+)\s+\d+%/', $dfOutput, $matches)) {
                    $metrics['inodes_total'] = (int)$matches[1];
                    $metrics['inodes_used'] = (int)$matches[2];
                    $metrics['inodes_free'] = (int)$matches[3];
                }
            }
            
        } catch (\Exception $e) {
            // Disk metrics not available
        }
        
        return $metrics;
    }
    
    /**
     * Get load metrics
     */
    private function getLoadMetrics(): array
    {
        $metrics = [
            'average' => [],
            'processes' => [
                'running' => 0,
                'total' => 0
            ]
        ];
        
        // Load average
        $loadavg = sys_getloadavg();
        if ($loadavg) {
            $metrics['average'] = [
                '1_min' => $loadavg[0],
                '5_min' => $loadavg[1],
                '15_min' => $loadavg[2]
            ];
        }
        
        // Process information on Linux
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/loadavg')) {
            $loadavgFile = file_get_contents('/proc/loadavg');
            if (preg_match('/[\d\.]+\s+[\d\.]+\s+[\d\.]+\s+(\d+)\/(\d+)/', $loadavgFile, $matches)) {
                $metrics['processes']['running'] = (int)$matches[1];
                $metrics['processes']['total'] = (int)$matches[2];
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get network metrics
     */
    private function getNetworkMetrics(): array
    {
        $metrics = [
            'interfaces' => [],
            'connections' => []
        ];
        
        // Network interface statistics on Linux
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/net/dev')) {
            $netDev = file_get_contents('/proc/net/dev');
            $lines = explode("\n", $netDev);
            
            foreach ($lines as $line) {
                if (preg_match('/^\s*([^:]+):\s*(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $matches)) {
                    $interface = trim($matches[1]);
                    $rxBytes = (int)$matches[2];
                    $txBytes = (int)$matches[3];
                    
                    if ($interface !== 'lo') { // Skip loopback
                        $metrics['interfaces'][$interface] = [
                            'rx_bytes' => $rxBytes,
                            'tx_bytes' => $txBytes,
                            'total_bytes' => $rxBytes + $txBytes
                        ];
                    }
                }
            }
        }
        
        // Connection counts
        if (PHP_OS_FAMILY === 'Linux') {
            $netstat = shell_exec('netstat -an 2>/dev/null | grep -E "^tcp|^udp" | wc -l');
            if ($netstat) {
                $metrics['connections']['total'] = (int)trim($netstat);
            }
            
            $established = shell_exec('netstat -an 2>/dev/null | grep ESTABLISHED | wc -l');
            if ($established) {
                $metrics['connections']['established'] = (int)trim($established);
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get uptime metrics
     */
    private function getUptimeMetrics(): array
    {
        $metrics = [
            'system_uptime' => 0,
            'php_uptime' => 0
        ];
        
        // System uptime on Linux
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            if ($uptime && preg_match('/^([\d\.]+)/', $uptime, $matches)) {
                $metrics['system_uptime'] = (float)$matches[1];
            }
        }
        
        // PHP process uptime (approximate)
        if (function_exists('getmyinode')) {
            $startTime = filectime('/proc/' . getmypid());
            if ($startTime) {
                $metrics['php_uptime'] = time() - $startTime;
            }
        }
        
        return $metrics;
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

/**
 * Metrics Collector Interface
 */
interface MetricsCollectorInterface
{
    public function collect(): array;
}