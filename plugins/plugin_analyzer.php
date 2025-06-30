<?php

/**
 * Plugin Analyzer - Comprehensive plugin audit tool
 * Analyzes all plugins for structure, code quality, and standards compliance
 */

declare(strict_types=1);

class PluginAnalyzer
{
    private array $plugins = [];
    private array $issues = [];
    private array $statistics = [];
    
    public function analyze(): void
    {
        echo "🔍 Shopologic Plugin Analyzer\n";
        echo "=============================\n\n";
        
        $this->discoverPlugins();
        $this->analyzePlugins();
        $this->generateReport();
    }
    
    private function discoverPlugins(): void
    {
        $pluginsDir = __DIR__;
        $directories = glob($pluginsDir . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            if (basename($dir) === 'shared') continue;
            
            $pluginJsonPath = $dir . '/plugin.json';
            if (file_exists($pluginJsonPath)) {
                $pluginName = basename($dir);
                $this->plugins[$pluginName] = [
                    'path' => $dir,
                    'manifest' => json_decode(file_get_contents($pluginJsonPath), true),
                    'issues' => [],
                    'stats' => []
                ];
            }
        }
        
        echo "✅ Found " . count($this->plugins) . " plugins\n\n";
    }
    
    private function analyzePlugins(): void
    {
        foreach ($this->plugins as $pluginName => &$plugin) {
            echo "📦 Analyzing: $pluginName\n";
            
            $this->validateManifest($pluginName, $plugin);
            $this->checkBootstrapClass($pluginName, $plugin);
            $this->analyzeNamespace($pluginName, $plugin);
            $this->checkFolderStructure($pluginName, $plugin);
            $this->analyzeCodeQuality($pluginName, $plugin);
            $this->checkDocumentation($pluginName, $plugin);
            
            echo "\n";
        }
    }
    
    private function validateManifest(string $pluginName, array &$plugin): void
    {
        $manifest = $plugin['manifest'];
        $requiredFields = ['name', 'version', 'description', 'bootstrap', 'requirements'];
        
        foreach ($requiredFields as $field) {
            if (!isset($manifest[$field])) {
                $plugin['issues'][] = "Missing required field: $field";
            }
        }
        
        // Check bootstrap configuration
        if (isset($manifest['bootstrap'])) {
            if (!isset($manifest['bootstrap']['class']) || !isset($manifest['bootstrap']['file'])) {
                $plugin['issues'][] = "Invalid bootstrap configuration";
            }
        }
        
        // Validate namespace if autoload is defined
        if (isset($manifest['autoload']['psr-4'])) {
            $namespaces = array_keys($manifest['autoload']['psr-4']);
            if (empty($namespaces)) {
                $plugin['issues'][] = "No PSR-4 namespace defined";
            }
        }
    }
    
    private function checkBootstrapClass(string $pluginName, array &$plugin): void
    {
        if (!isset($plugin['manifest']['bootstrap'])) return;
        
        $bootstrapFile = $plugin['path'] . '/' . $plugin['manifest']['bootstrap']['file'];
        $bootstrapClass = $plugin['manifest']['bootstrap']['class'];
        
        if (!file_exists($bootstrapFile)) {
            $plugin['issues'][] = "Bootstrap file not found: " . $plugin['manifest']['bootstrap']['file'];
            return;
        }
        
        // Check if class exists in file
        $content = file_get_contents($bootstrapFile);
        $className = basename(str_replace('\\', '/', $bootstrapClass));
        
        if (!preg_match("/class\s+$className/", $content)) {
            $plugin['issues'][] = "Bootstrap class '$className' not found in file";
        }
        
        // Check if extends AbstractPlugin
        if (!preg_match("/class\s+$className\s+extends\s+AbstractPlugin/", $content)) {
            $plugin['issues'][] = "Bootstrap class should extend AbstractPlugin";
        }
    }
    
    private function analyzeNamespace(string $pluginName, array &$plugin): void
    {
        if (!isset($plugin['manifest']['autoload']['psr-4'])) return;
        
        foreach ($plugin['manifest']['autoload']['psr-4'] as $namespace => $path) {
            $srcPath = $plugin['path'] . '/' . rtrim($path, '/');
            
            if (!is_dir($srcPath)) {
                $plugin['issues'][] = "PSR-4 source directory not found: $path";
                continue;
            }
            
            // Check PHP files have correct namespace
            $phpFiles = $this->findPhpFiles($srcPath);
            foreach ($phpFiles as $file) {
                $content = file_get_contents($file);
                $expectedNs = rtrim($namespace, '\\');
                
                if (!preg_match("/namespace\s+$expectedNs/", $content)) {
                    $relativePath = str_replace($plugin['path'] . '/', '', $file);
                    $plugin['issues'][] = "Incorrect namespace in: $relativePath";
                }
            }
        }
    }
    
    private function checkFolderStructure(string $pluginName, array &$plugin): void
    {
        $expectedDirs = ['src', 'templates', 'assets', 'migrations'];
        $recommendedDirs = ['tests', 'docs', 'config'];
        
        foreach ($expectedDirs as $dir) {
            if (!is_dir($plugin['path'] . '/' . $dir)) {
                $plugin['issues'][] = "Missing directory: $dir";
            }
        }
        
        // Check src subdirectories
        $srcPath = $plugin['path'] . '/src';
        if (is_dir($srcPath)) {
            $expectedSubDirs = ['Controllers', 'Models', 'Services', 'Repositories'];
            foreach ($expectedSubDirs as $subDir) {
                if (!is_dir($srcPath . '/' . $subDir)) {
                    $plugin['issues'][] = "Missing src subdirectory: $subDir";
                }
            }
        }
    }
    
    private function analyzeCodeQuality(string $pluginName, array &$plugin): void
    {
        $phpFiles = $this->findPhpFiles($plugin['path']);
        $plugin['stats']['php_files'] = count($phpFiles);
        $plugin['stats']['total_lines'] = 0;
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            $plugin['stats']['total_lines'] += count($lines);
            
            // Check for PHP 8.3 features
            if (!preg_match('/declare\(strict_types=1\)/', $content)) {
                $relativePath = str_replace($plugin['path'] . '/', '', $file);
                $plugin['issues'][] = "Missing strict_types declaration: $relativePath";
            }
            
            // Check for proper error handling
            if (preg_match('/catch\s*\(\s*\\\\?Exception\s+/', $content)) {
                $plugin['issues'][] = "Generic Exception catch found (use specific exceptions)";
            }
            
            // Check for security issues
            if (preg_match('/\$_(GET|POST|REQUEST)\[/', $content)) {
                $plugin['issues'][] = "Direct superglobal access found (use Request object)";
            }
        }
    }
    
    private function checkDocumentation(string $pluginName, array &$plugin): void
    {
        $docs = ['README.md', 'API.md', 'HOOKS.md'];
        
        foreach ($docs as $doc) {
            if (!file_exists($plugin['path'] . '/' . $doc)) {
                $plugin['issues'][] = "Missing documentation: $doc";
            }
        }
    }
    
    private function findPhpFiles(string $dir): array
    {
        $files = [];
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
    
    private function generateReport(): void
    {
        echo "\n📊 ANALYSIS REPORT\n";
        echo "==================\n\n";
        
        $totalIssues = 0;
        $pluginsWithIssues = 0;
        
        foreach ($this->plugins as $pluginName => $plugin) {
            $issueCount = count($plugin['issues']);
            $totalIssues += $issueCount;
            
            if ($issueCount > 0) {
                $pluginsWithIssues++;
                echo "❌ $pluginName ($issueCount issues)\n";
                foreach ($plugin['issues'] as $issue) {
                    echo "   - $issue\n";
                }
                echo "\n";
            } else {
                echo "✅ $pluginName (No issues)\n";
            }
        }
        
        echo "\n📈 SUMMARY\n";
        echo "==========\n";
        echo "Total plugins: " . count($this->plugins) . "\n";
        echo "Plugins with issues: $pluginsWithIssues\n";
        echo "Total issues found: $totalIssues\n";
        
        // Save detailed report
        $reportPath = __DIR__ . '/PLUGIN_ANALYSIS_REPORT.json';
        file_put_contents($reportPath, json_encode($this->plugins, JSON_PRETTY_PRINT));
        echo "\n💾 Detailed report saved to: PLUGIN_ANALYSIS_REPORT.json\n";
    }
}

// Run the analyzer
$analyzer = new PluginAnalyzer();
$analyzer->analyze();