<?php

/**
 * Plugin Health Monitoring System
 * Continuous monitoring and health checks for all plugins
 */

declare(strict_types=1);

class PluginHealthMonitor
{
    private string $pluginsDir;
    private array $plugins = [];
    private array $healthMetrics = [];
    private array $alerts = [];
    
    public function __construct()
    {
        $this->pluginsDir = __DIR__;
    }
    
    public function runHealthCheck(): void
    {
        echo "🔍 Plugin Health Monitoring System\n";
        echo "==================================\n\n";
        
        $this->discoverPlugins();
        $this->performHealthChecks();
        $this->generateHealthReport();
        $this->createHealthDashboard();
        $this->setupContinuousMonitoring();
    }
    
    private function discoverPlugins(): void
    {
        $directories = glob($this->pluginsDir . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $pluginName = basename($dir);
            if ($pluginName === 'shared') continue;
            
            $pluginJsonPath = $dir . '/plugin.json';
            if (file_exists($pluginJsonPath)) {
                $this->plugins[$pluginName] = [
                    'path' => $dir,
                    'manifest' => json_decode(file_get_contents($pluginJsonPath), true)
                ];
            }
        }
        
        echo "🎯 Monitoring " . count($this->plugins) . " plugins\n\n";
    }
    
    private function performHealthChecks(): void
    {
        foreach ($this->plugins as $pluginName => $plugin) {
            echo "🏥 Health Check: $pluginName\n";
            
            $health = [
                'plugin' => $pluginName,
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'healthy',
                'score' => 0,
                'checks' => [],
                'warnings' => [],
                'errors' => [],
                'performance' => []
            ];
            
            // Core health checks
            $health['checks']['structure'] = $this->checkPluginStructure($plugin);
            $health['checks']['bootstrap'] = $this->checkBootstrapHealth($plugin);
            $health['checks']['dependencies'] = $this->checkDependencies($plugin);
            $health['checks']['security'] = $this->checkSecurityHealth($plugin);
            $health['checks']['performance'] = $this->checkPerformanceHealth($plugin);
            $health['checks']['documentation'] = $this->checkDocumentationHealth($plugin);
            $health['checks']['compatibility'] = $this->checkCompatibility($plugin);
            
            // Calculate overall health score
            $totalChecks = count($health['checks']);
            $passedChecks = count(array_filter($health['checks'], fn($check) => $check['status'] === 'pass'));
            $health['score'] = round(($passedChecks / $totalChecks) * 100);
            
            // Determine overall status
            if ($health['score'] >= 90) {
                $health['status'] = 'excellent';
            } elseif ($health['score'] >= 75) {
                $health['status'] = 'good';
            } elseif ($health['score'] >= 60) {
                $health['status'] = 'warning';
            } else {
                $health['status'] = 'critical';
            }
            
            // Collect warnings and errors
            foreach ($health['checks'] as $checkName => $check) {
                if ($check['status'] === 'warning') {
                    $health['warnings'][] = "$checkName: {$check['message']}";
                } elseif ($check['status'] === 'fail') {
                    $health['errors'][] = "$checkName: {$check['message']}";
                }
            }
            
            $this->healthMetrics[$pluginName] = $health;
            
            $statusIcon = match($health['status']) {
                'excellent' => '🟢',
                'good' => '🟡',
                'warning' => '🟠',
                'critical' => '🔴',
                default => '⚪'
            };
            
            echo "   $statusIcon Health: {$health['score']}% ({$health['status']})\n";
            
            if (!empty($health['warnings']) || !empty($health['errors'])) {
                foreach ($health['warnings'] as $warning) {
                    echo "   ⚠️  $warning\n";
                }
                foreach ($health['errors'] as $error) {
                    echo "   ❌ $error\n";
                }
            }
            echo "\n";
        }
    }
    
    private function checkPluginStructure(array $plugin): array
    {
        $requiredFiles = [
            'plugin.json', 'README.md', 'API.md', 'HOOKS.md'
        ];
        
        $requiredDirs = [
            'src', 'templates', 'assets', 'migrations', 'tests', 'docs'
        ];
        
        $missing = [];
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($plugin['path'] . '/' . $file)) {
                $missing[] = $file;
            }
        }
        
        foreach ($requiredDirs as $dir) {
            if (!is_dir($plugin['path'] . '/' . $dir)) {
                $missing[] = $dir . '/';
            }
        }
        
        if (empty($missing)) {
            return ['status' => 'pass', 'message' => 'All required files and directories present'];
        } else {
            return ['status' => 'fail', 'message' => 'Missing: ' . implode(', ', $missing)];
        }
    }
    
    private function checkBootstrapHealth(array $plugin): array
    {
        $manifest = $plugin['manifest'];
        
        if (!isset($manifest['bootstrap']['file'])) {
            return ['status' => 'fail', 'message' => 'No bootstrap file specified'];
        }
        
        $bootstrapFile = $plugin['path'] . '/' . $manifest['bootstrap']['file'];
        
        if (!file_exists($bootstrapFile)) {
            return ['status' => 'fail', 'message' => 'Bootstrap file not found'];
        }
        
        $content = file_get_contents($bootstrapFile);
        $issues = [];
        
        // Check for basic syntax issues
        $tokens = token_get_all($content);
        if (empty($tokens)) {
            $issues[] = 'syntax errors';
        }
        
        // Check for strict types
        if (!preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $content)) {
            $issues[] = 'missing strict_types';
        }
        
        // Check for AbstractPlugin extension
        if (!preg_match('/extends\s+AbstractPlugin/', $content)) {
            $issues[] = 'not extending AbstractPlugin';
        }
        
        if (empty($issues)) {
            return ['status' => 'pass', 'message' => 'Bootstrap file healthy'];
        } else {
            return ['status' => 'warning', 'message' => 'Issues: ' . implode(', ', $issues)];
        }
    }
    
    private function checkDependencies(array $plugin): array
    {
        $manifest = $plugin['manifest'];
        
        // Check PHP version requirement
        if (isset($manifest['requirements']['php'])) {
            $requiredPhp = $manifest['requirements']['php'];
            $currentPhp = PHP_VERSION;
            
            if (!version_compare($currentPhp, str_replace('>=', '', $requiredPhp), '>=')) {
                return ['status' => 'fail', 'message' => "PHP $requiredPhp required, $currentPhp found"];
            }
        }
        
        // Check plugin dependencies
        if (isset($manifest['dependencies'])) {
            $missingDeps = [];
            foreach ($manifest['dependencies'] as $dep => $version) {
                // This would check if dependent plugins are available
                // For now, we'll assume they're available
            }
            
            if (!empty($missingDeps)) {
                return ['status' => 'warning', 'message' => 'Missing dependencies: ' . implode(', ', $missingDeps)];
            }
        }
        
        return ['status' => 'pass', 'message' => 'All dependencies satisfied'];
    }
    
    private function checkSecurityHealth(array $plugin): array
    {
        $phpFiles = $this->findPhpFiles($plugin['path']);
        $securityIssues = [];
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Check for common security issues
            if (preg_match('/\$_(GET|POST|REQUEST|COOKIE)\s*\[/', $content)) {
                $securityIssues[] = 'direct superglobal access in ' . basename($file);
            }
            
            if (preg_match('/eval\s*\(/', $content)) {
                $securityIssues[] = 'eval() usage in ' . basename($file);
            }
            
            if (preg_match('/shell_exec|exec|system|passthru/', $content)) {
                $securityIssues[] = 'shell execution in ' . basename($file);
            }
        }
        
        if (empty($securityIssues)) {
            return ['status' => 'pass', 'message' => 'No security issues detected'];
        } else {
            return ['status' => 'fail', 'message' => 'Security issues: ' . implode(', ', array_slice($securityIssues, 0, 3))];
        }
    }
    
    private function checkPerformanceHealth(array $plugin): array
    {
        $issues = [];
        $phpFiles = $this->findPhpFiles($plugin['path']);
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Check for performance anti-patterns
            if (preg_match_all('/for\s*\([^{]*\{[^}]*\$[^}]*\}/', $content)) {
                $issues[] = 'potential N+1 queries in ' . basename($file);
            }
            
            if (preg_match('/sleep\s*\(/', $content)) {
                $issues[] = 'blocking sleep() calls in ' . basename($file);
            }
            
            // Check file size
            $fileSize = filesize($file);
            if ($fileSize > 100000) { // 100KB
                $issues[] = 'large file ' . basename($file) . ' (' . round($fileSize/1024) . 'KB)';
            }
        }
        
        if (empty($issues)) {
            return ['status' => 'pass', 'message' => 'No performance issues detected'];
        } else {
            return ['status' => 'warning', 'message' => 'Performance concerns: ' . implode(', ', array_slice($issues, 0, 2))];
        }
    }
    
    private function checkDocumentationHealth(array $plugin): array
    {
        $requiredDocs = ['README.md', 'API.md', 'HOOKS.md'];
        $issues = [];
        
        foreach ($requiredDocs as $doc) {
            $docPath = $plugin['path'] . '/' . $doc;
            if (!file_exists($docPath)) {
                $issues[] = "missing $doc";
                continue;
            }
            
            $content = file_get_contents($docPath);
            $lines = count(explode("\n", $content));
            
            if ($lines < 10) {
                $issues[] = "$doc too short ($lines lines)";
            }
            
            if (strpos($content, 'TODO') !== false) {
                $issues[] = "$doc contains TODOs";
            }
        }
        
        if (empty($issues)) {
            return ['status' => 'pass', 'message' => 'Documentation complete and healthy'];
        } else {
            return ['status' => 'warning', 'message' => 'Documentation issues: ' . implode(', ', $issues)];
        }
    }
    
    private function checkCompatibility(array $plugin): array
    {
        $manifest = $plugin['manifest'];
        $issues = [];
        
        // Check for deprecated features
        if (isset($manifest['deprecated'])) {
            $issues[] = 'contains deprecated features';
        }
        
        // Check autoload configuration
        if (!isset($manifest['autoload']['psr-4'])) {
            $issues[] = 'missing PSR-4 autoload';
        }
        
        // Check version format
        if (!preg_match('/^\d+\.\d+\.\d+$/', $manifest['version'] ?? '')) {
            $issues[] = 'invalid version format';
        }
        
        if (empty($issues)) {
            return ['status' => 'pass', 'message' => 'Fully compatible'];
        } else {
            return ['status' => 'warning', 'message' => 'Compatibility issues: ' . implode(', ', $issues)];
        }
    }
    
    private function findPhpFiles(string $dir): array
    {
        $files = [];
        if (!is_dir($dir)) return $files;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    private function generateHealthReport(): void
    {
        echo "\n📊 HEALTH MONITORING REPORT\n";
        echo "===========================\n\n";
        
        $totalPlugins = count($this->healthMetrics);
        $excellentCount = count(array_filter($this->healthMetrics, fn($h) => $h['status'] === 'excellent'));
        $goodCount = count(array_filter($this->healthMetrics, fn($h) => $h['status'] === 'good'));
        $warningCount = count(array_filter($this->healthMetrics, fn($h) => $h['status'] === 'warning'));
        $criticalCount = count(array_filter($this->healthMetrics, fn($h) => $h['status'] === 'critical'));
        
        $averageScore = round(array_sum(array_column($this->healthMetrics, 'score')) / $totalPlugins);
        
        echo "📈 ECOSYSTEM HEALTH SUMMARY:\n";
        echo "- Total plugins monitored: $totalPlugins\n";
        echo "- Average health score: $averageScore%\n";
        echo "- Excellent health: $excellentCount plugins\n";
        echo "- Good health: $goodCount plugins\n";
        echo "- Warning status: $warningCount plugins\n";
        echo "- Critical status: $criticalCount plugins\n\n";
        
        // Health distribution
        echo "🏥 HEALTH STATUS DISTRIBUTION:\n";
        echo "- 🟢 Excellent (90%+): $excellentCount plugins\n";
        echo "- 🟡 Good (75-89%): $goodCount plugins\n";
        echo "- 🟠 Warning (60-74%): $warningCount plugins\n";
        echo "- 🔴 Critical (<60%): $criticalCount plugins\n\n";
        
        // Critical issues
        $criticalPlugins = array_filter($this->healthMetrics, fn($h) => $h['status'] === 'critical');
        if (!empty($criticalPlugins)) {
            echo "🚨 CRITICAL ISSUES REQUIRING IMMEDIATE ATTENTION:\n";
            foreach ($criticalPlugins as $pluginName => $health) {
                echo "- $pluginName (Score: {$health['score']}%):\n";
                foreach ($health['errors'] as $error) {
                    echo "  • $error\n";
                }
                echo "\n";
            }
        }
        
        // Save detailed report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ecosystem_health' => [
                'total_plugins' => $totalPlugins,
                'average_score' => $averageScore,
                'status_distribution' => [
                    'excellent' => $excellentCount,
                    'good' => $goodCount,
                    'warning' => $warningCount,
                    'critical' => $criticalCount
                ]
            ],
            'plugins' => $this->healthMetrics
        ];
        
        file_put_contents($this->pluginsDir . '/HEALTH_REPORT.json', json_encode($report, JSON_PRETTY_PRINT));
        echo "💾 Detailed health report saved to: HEALTH_REPORT.json\n";
    }
    
    private function createHealthDashboard(): void
    {
        echo "\n📊 CREATING HEALTH DASHBOARD\n";
        echo "============================\n\n";
        
        $dashboardHtml = $this->generateHealthDashboardHtml();
        file_put_contents($this->pluginsDir . '/health_dashboard.html', $dashboardHtml);
        
        echo "✅ Health dashboard created: health_dashboard.html\n";
        echo "🌐 Open in browser to view interactive health monitoring\n\n";
    }
    
    private function generateHealthDashboardHtml(): string
    {
        $totalPlugins = count($this->healthMetrics);
        $averageScore = round(array_sum(array_column($this->healthMetrics, 'score')) / $totalPlugins);
        $excellentCount = count(array_filter($this->healthMetrics, fn($h) => $h['status'] === 'excellent'));
        $criticalCount = count(array_filter($this->healthMetrics, fn($h) => $h['status'] === 'critical'));
        $currentTime = date('Y-m-d H:i:s');
        
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopologic Plugin Health Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .plugin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .plugin-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-excellent { border-left: 5px solid #27ae60; }
        .status-good { border-left: 5px solid #f39c12; }
        .status-warning { border-left: 5px solid #e67e22; }
        .status-critical { border-left: 5px solid #e74c3c; }
        .health-score { font-size: 1.5em; font-weight: bold; }
        .issues { margin-top: 10px; }
        .error { color: #e74c3c; }
        .warning { color: #f39c12; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏥 Shopologic Plugin Health Dashboard</h1>
        <p>Real-time monitoring of all plugin health metrics</p>
        <p>Last updated: ' . $currentTime . '</p>
    </div>
    
    <div class="stats">
        <div class="stat-card">
            <div class="stat-value">' . $totalPlugins . '</div>
            <div>Total Plugins</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">' . $averageScore . '%</div>
            <div>Average Health Score</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">' . $excellentCount . '</div>
            <div>Excellent Health</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">' . $criticalCount . '</div>
            <div>Critical Issues</div>
        </div>
    </div>
    
    <div class="plugin-grid">';
        
        foreach ($this->healthMetrics as $pluginName => $health) {
            $statusClass = "status-{$health['status']}";
            $html .= '<div class="plugin-card ' . $statusClass . '">
            <h3>' . htmlspecialchars($pluginName) . '</h3>
            <div class="health-score">' . $health['score'] . '% - ' . ucfirst($health['status']) . '</div>
            <div class="issues">';
            
            foreach ($health['errors'] as $error) {
                $html .= '<div class="error">❌ ' . htmlspecialchars($error) . '</div>';
            }
            
            foreach ($health['warnings'] as $warning) {
                $html .= '<div class="warning">⚠️ ' . htmlspecialchars($warning) . '</div>';
            }
            
            $html .= '</div>
        </div>';
        }
        
        $html .= '    </div>
    
    <script>
        // Auto-refresh every 5 minutes
        setTimeout(() => location.reload(), 300000);
    </script>
</body>
</html>';
        
        return $html;
    }
    
    private function setupContinuousMonitoring(): void
    {
        echo "🔄 SETTING UP CONTINUOUS MONITORING\n";
        echo "===================================\n\n";
        
        // Create monitoring script
        $monitorScript = <<<'SCRIPT'
#!/bin/bash
# Shopologic Plugin Health Monitor
# Run this script via cron for continuous monitoring

cd "$(dirname "$0")"
php plugin_monitor.php

# Send alerts if critical issues found
if grep -q '"critical"' HEALTH_REPORT.json; then
    echo "Critical plugin health issues detected!" | mail -s "Shopologic Plugin Alert" admin@yoursite.com
fi
SCRIPT;
        
        file_put_contents($this->pluginsDir . '/monitor.sh', $monitorScript);
        chmod($this->pluginsDir . '/monitor.sh', 0755);
        
        echo "✅ Monitoring script created: monitor.sh\n";
        echo "📝 Add to crontab for automated monitoring:\n";
        echo "   */15 * * * * /path/to/plugins/monitor.sh\n\n";
        
        echo "🎯 HEALTH MONITORING SETUP COMPLETE!\n";
        echo "Dashboard available at: health_dashboard.html\n";
        echo "Continuous monitoring ready for deployment.\n";
    }
    
    public function assessPluginHealth(string $pluginName, array $plugin): array
    {
        $health = [
            'structure' => $this->checkPluginStructure($plugin),
            'bootstrap' => $this->checkBootstrapHealth($plugin),
            'dependencies' => $this->checkDependencies($plugin),
            'security' => $this->checkSecurityHealth($plugin),
            'performance' => $this->checkPerformanceHealth($plugin),
            'documentation' => $this->checkDocumentationHealth($plugin),
            'compatibility' => $this->checkCompatibility($plugin)
        ];
        
        $totalChecks = count($health);
        $passedChecks = count(array_filter($health, fn($check) => $check['status'] === 'pass'));
        $score = round(($passedChecks / $totalChecks) * 100);
        
        return [
            'plugin' => $pluginName,
            'health' => $health,
            'score' => $score,
            'status' => $score >= 90 ? 'excellent' : ($score >= 75 ? 'good' : ($score >= 60 ? 'warning' : 'critical'))
        ];
    }
    
    public function monitorAllPlugins(): void
    {
        $this->discoverPlugins();
        $this->performHealthChecks();
        $this->generateHealthReport();
        $this->saveHealthReports();
    }
}

// Run the health monitor
$monitor = new PluginHealthMonitor();
$monitor->runHealthCheck();