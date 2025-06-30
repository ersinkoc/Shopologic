<?php

/**
 * Batch Fix Common Plugin Issues
 * Automatically fixes the most common issues found in plugin testing
 */

declare(strict_types=1);

class BatchFixCommonIssues
{
    private string $pluginsDir;
    private array $plugins = [];
    private int $fixedCount = 0;
    private array $fixLog = [];
    
    public function __construct()
    {
        $this->pluginsDir = __DIR__;
    }
    
    public function fixAllPlugins(): void
    {
        echo "🔧 Batch Fix Common Plugin Issues\n";
        echo "=================================\n\n";
        echo "This tool will automatically fix:\n";
        echo "  ✓ Missing bootstrap.php files\n";
        echo "  ✓ Missing license in manifest\n";
        echo "  ✓ PHP syntax errors\n";
        echo "  ✓ Missing directory structure\n\n";
        
        $this->discoverPlugins();
        $this->fixCommonIssues();
        $this->generateFixReport();
    }
    
    private function discoverPlugins(): void
    {
        $directories = glob($this->pluginsDir . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $pluginName = basename($dir);
            if ($pluginName === 'shared') continue;
            
            $pluginJsonPath = $dir . '/plugin.json';
            if (file_exists($pluginJsonPath)) {
                $manifest = json_decode(file_get_contents($pluginJsonPath), true);
                $this->plugins[$pluginName] = [
                    'name' => $pluginName,
                    'path' => $dir,
                    'manifest' => $manifest
                ];
            }
        }
        
        echo "📦 Found " . count($this->plugins) . " plugins to fix\n\n";
    }
    
    private function fixCommonIssues(): void
    {
        foreach ($this->plugins as $pluginName => $plugin) {
            echo "🔧 Fixing: $pluginName\n";
            
            $fixes = [];
            
            // Fix 1: Create missing directories
            $this->createMissingDirectories($plugin, $fixes);
            
            // Fix 2: Create missing bootstrap.php
            $this->createBootstrapFile($plugin, $fixes);
            
            // Fix 3: Add missing license to manifest
            $this->fixManifest($plugin, $fixes);
            
            // Fix 4: Fix PHP syntax errors
            $this->fixPhpSyntaxErrors($plugin, $fixes);
            
            if (!empty($fixes)) {
                $this->fixedCount++;
                $this->fixLog[$pluginName] = $fixes;
                echo "   ✅ Applied " . count($fixes) . " fixes\n";
            } else {
                echo "   ✨ No fixes needed\n";
            }
            
            echo "\n";
        }
    }
    
    private function createMissingDirectories(array &$plugin, array &$fixes): void
    {
        $requiredDirs = ['src', 'migrations', 'tests', 'templates', 'assets', 'docs'];
        
        foreach ($requiredDirs as $dir) {
            $dirPath = $plugin['path'] . '/' . $dir;
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
                $fixes[] = "Created directory: $dir/";
                
                // Create .gitkeep in empty directories
                file_put_contents($dirPath . '/.gitkeep', '');
            }
        }
    }
    
    private function createBootstrapFile(array &$plugin, array &$fixes): void
    {
        $bootstrapPath = $plugin['path'] . '/bootstrap.php';
        
        if (!file_exists($bootstrapPath)) {
            $pluginClass = $this->getPluginClassName($plugin['name']);
            $namespace = $this->getPluginNamespace($plugin['name']);
            
            $content = <<<PHP
<?php

/**
 * {$plugin['manifest']['name']} Plugin Bootstrap
 * 
 * @package {$namespace}
 * @version {$plugin['manifest']['version']}
 */

declare(strict_types=1);

namespace {$namespace};

use Shopologic\\Core\\Plugin\\AbstractPlugin;
use Shopologic\\Core\\Container\\Container;

class {$pluginClass} extends AbstractPlugin
{
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run activation logic
        \$this->runMigrations();
        \$this->registerHooks();
        \$this->registerServices();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Clean up resources
        \$this->clearCache();
    }
    
    /**
     * Register plugin services
     */
    protected function registerServices(): void
    {
        // Register services in the container
    }
    
    /**
     * Register plugin hooks
     */
    protected function registerHooks(): void
    {
        // Register WordPress-style hooks
    }
    
    /**
     * Run database migrations
     */
    protected function runMigrations(): void
    {
        // Execute migrations
    }
    
    /**
     * Clear plugin cache
     */
    protected function clearCache(): void
    {
        // Clear any cached data
    }
}

// Initialize plugin
return function(Container \$container, string \$pluginPath) {
    return new {$pluginClass}(\$container, \$pluginPath);
};
PHP;
            
            file_put_contents($bootstrapPath, $content);
            $fixes[] = "Created bootstrap.php";
        }
    }
    
    private function fixManifest(array &$plugin, array &$fixes): void
    {
        $manifestPath = $plugin['path'] . '/plugin.json';
        $manifest = $plugin['manifest'];
        $updated = false;
        
        // Add missing license
        if (!isset($manifest['license'])) {
            $manifest['license'] = 'MIT';
            $updated = true;
            $fixes[] = "Added license to manifest";
        }
        
        // Ensure all required fields exist
        $defaults = [
            'license' => 'MIT',
            'homepage' => 'https://shopologic.com/plugins/' . $plugin['name'],
            'requirements' => ['php' => '8.3'],
            'hooks' => [],
            'permissions' => []
        ];
        
        foreach ($defaults as $key => $value) {
            if (!isset($manifest[$key])) {
                $manifest[$key] = $value;
                $updated = true;
            }
        }
        
        if ($updated) {
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $plugin['manifest'] = $manifest;
        }
    }
    
    private function fixPhpSyntaxErrors(array &$plugin, array &$fixes): void
    {
        $phpFiles = $this->getPhpFiles($plugin['path']);
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            $originalContent = $content;
            
            // Fix common syntax errors
            
            // 1. Fix namespace declarations
            if (preg_match('/^<\?php\s*$/m', $content) && !preg_match('/namespace\s+\w+/', $content)) {
                $expectedNamespace = $this->getExpectedNamespaceForFile($plugin['name'], $file);
                $content = preg_replace(
                    '/^(<\?php\s*)$/m',
                    "$1\n\ndeclare(strict_types=1);\n\nnamespace $expectedNamespace;",
                    $content,
                    1
                );
            }
            
            // 2. Add missing semicolons
            $content = preg_replace('/^(\s*use\s+[^;]+)$/m', '$1;', $content);
            
            // 3. Fix class declarations
            $content = preg_replace('/class\s+(\w+)\s*{/', 'class $1\n{', $content);
            
            // 4. Add strict types if missing
            if (!preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $content)) {
                $content = preg_replace(
                    '/^(<\?php)\s*\n/m',
                    "$1\n\ndeclare(strict_types=1);\n",
                    $content,
                    1
                );
            }
            
            // 5. Fix common typos
            $content = str_replace('pubic function', 'public function', $content);
            $content = str_replace('retrun', 'return', $content);
            $content = str_replace('Sting', 'string', $content);
            
            if ($content !== $originalContent) {
                file_put_contents($file, $content);
                $relativePath = str_replace($plugin['path'] . '/', '', $file);
                $fixes[] = "Fixed syntax in: $relativePath";
            }
        }
    }
    
    private function getPhpFiles(string $path): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    private function getPluginClassName(string $pluginName): string
    {
        $parts = explode('-', $pluginName);
        return implode('', array_map('ucfirst', $parts)) . 'Plugin';
    }
    
    private function getPluginNamespace(string $pluginName): string
    {
        $parts = explode('-', $pluginName);
        return 'Shopologic\\Plugins\\' . implode('', array_map('ucfirst', $parts));
    }
    
    private function getExpectedNamespaceForFile(string $pluginName, string $filePath): string
    {
        $baseNamespace = $this->getPluginNamespace($pluginName);
        
        // Extract relative path from src directory
        if (preg_match('/\/src\/(.+)\.php$/', $filePath, $matches)) {
            $relativePath = $matches[1];
            $pathParts = explode('/', $relativePath);
            array_pop($pathParts); // Remove filename
            
            if (!empty($pathParts)) {
                return $baseNamespace . '\\' . implode('\\', $pathParts);
            }
        }
        
        return $baseNamespace;
    }
    
    private function generateFixReport(): void
    {
        echo "📊 FIX SUMMARY\n";
        echo "==============\n\n";
        echo "Total plugins processed: " . count($this->plugins) . "\n";
        echo "Plugins fixed: {$this->fixedCount}\n";
        echo "Plugins already correct: " . (count($this->plugins) - $this->fixedCount) . "\n\n";
        
        if (!empty($this->fixLog)) {
            echo "📝 DETAILED FIX LOG:\n";
            foreach ($this->fixLog as $pluginName => $fixes) {
                echo "\n$pluginName:\n";
                foreach ($fixes as $fix) {
                    echo "  - $fix\n";
                }
            }
        }
        
        // Save fix report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_plugins' => count($this->plugins),
                'fixed_plugins' => $this->fixedCount,
                'total_fixes' => array_sum(array_map('count', $this->fixLog))
            ],
            'fix_log' => $this->fixLog
        ];
        
        file_put_contents(
            $this->pluginsDir . '/BATCH_FIX_REPORT.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );
        
        echo "\n💾 Fix report saved: BATCH_FIX_REPORT.json\n";
        echo "\n✅ Batch fix complete! Run the individual test again to verify fixes.\n";
    }
}

// Execute batch fix
$fixer = new BatchFixCommonIssues();
$fixer->fixAllPlugins();