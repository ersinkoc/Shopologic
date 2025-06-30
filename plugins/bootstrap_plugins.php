<?php

/**
 * Plugin Bootstrap Loader
 * Initializes and loads all active plugins in the correct order
 */

declare(strict_types=1);

class PluginBootstrap
{
    private string $pluginsDir;
    private array $plugins = [];
    private array $loadOrder = [];
    private array $errors = [];
    
    public function __construct()
    {
        $this->pluginsDir = __DIR__;
    }
    
    public function bootstrapAllPlugins(): void
    {
        echo "🚀 Shopologic Plugin Bootstrap\n";
        echo "==============================\n\n";
        
        $this->discoverPlugins();
        $this->resolveDependencies();
        $this->loadPlugins();
        $this->reportStatus();
    }
    
    private function discoverPlugins(): void
    {
        $directories = glob($this->pluginsDir . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $pluginName = basename($dir);
            if ($pluginName === 'shared') continue;
            
            $pluginJsonPath = $dir . '/plugin.json';
            $bootstrapPath = $dir . '/bootstrap.php';
            
            if (file_exists($pluginJsonPath) && file_exists($bootstrapPath)) {
                $manifest = json_decode(file_get_contents($pluginJsonPath), true);
                
                $this->plugins[$pluginName] = [
                    'name' => $pluginName,
                    'path' => $dir,
                    'bootstrap' => $bootstrapPath,
                    'manifest' => $manifest,
                    'dependencies' => $manifest['dependencies'] ?? [],
                    'loaded' => false
                ];
            }
        }
        
        echo "📦 Found " . count($this->plugins) . " plugins\n\n";
    }
    
    private function resolveDependencies(): void
    {
        echo "🔍 Resolving plugin dependencies...\n";
        
        $resolved = [];
        $unresolved = array_keys($this->plugins);
        
        while (!empty($unresolved)) {
            $progress = false;
            
            foreach ($unresolved as $key => $pluginName) {
                $plugin = $this->plugins[$pluginName];
                $canLoad = true;
                
                // Check if all dependencies are resolved
                foreach ($plugin['dependencies'] as $dep) {
                    if (!in_array($dep, $resolved)) {
                        $canLoad = false;
                        break;
                    }
                }
                
                if ($canLoad) {
                    $resolved[] = $pluginName;
                    unset($unresolved[$key]);
                    $progress = true;
                }
            }
            
            // Check for circular dependencies
            if (!$progress && !empty($unresolved)) {
                $this->errors[] = "Circular dependency detected for plugins: " . implode(', ', $unresolved);
                break;
            }
        }
        
        $this->loadOrder = $resolved;
        echo "✅ Dependencies resolved. Load order determined.\n\n";
    }
    
    private function loadPlugins(): void
    {
        echo "⚡ Loading plugins...\n\n";
        
        foreach ($this->loadOrder as $pluginName) {
            $plugin = $this->plugins[$pluginName];
            
            echo "📌 Loading: {$plugin['manifest']['name']} v{$plugin['manifest']['version']}\n";
            
            try {
                // Validate plugin structure
                if (!$this->validatePlugin($plugin)) {
                    throw new Exception("Plugin validation failed");
                }
                
                // Check PHP version requirement
                if (isset($plugin['manifest']['requirements']['php'])) {
                    $requiredPhp = $plugin['manifest']['requirements']['php'];
                    if (version_compare(PHP_VERSION, $requiredPhp, '<')) {
                        throw new Exception("PHP {$requiredPhp} or higher required");
                    }
                }
                
                // Load the plugin bootstrap file
                require_once $plugin['bootstrap'];
                
                $this->plugins[$pluginName]['loaded'] = true;
                echo "   ✅ Loaded successfully\n";
                
            } catch (Exception $e) {
                $this->errors[] = "Failed to load {$pluginName}: " . $e->getMessage();
                echo "   ❌ Failed: " . $e->getMessage() . "\n";
            }
            
            echo "\n";
        }
    }
    
    private function validatePlugin(array $plugin): bool
    {
        // Check required manifest fields
        $requiredFields = ['name', 'version', 'description', 'author'];
        foreach ($requiredFields as $field) {
            if (!isset($plugin['manifest'][$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        // Check required directories
        $requiredDirs = ['src', 'migrations'];
        foreach ($requiredDirs as $dir) {
            if (!is_dir($plugin['path'] . '/' . $dir)) {
                throw new Exception("Missing required directory: {$dir}");
            }
        }
        
        return true;
    }
    
    private function reportStatus(): void
    {
        $loadedCount = count(array_filter($this->plugins, fn($p) => $p['loaded']));
        $totalCount = count($this->plugins);
        
        echo "📊 Bootstrap Summary\n";
        echo "===================\n";
        echo "Total plugins: {$totalCount}\n";
        echo "Successfully loaded: {$loadedCount}\n";
        echo "Failed: " . ($totalCount - $loadedCount) . "\n\n";
        
        if (!empty($this->errors)) {
            echo "❌ Errors encountered:\n";
            foreach ($this->errors as $error) {
                echo "   - {$error}\n";
            }
            echo "\n";
        }
        
        // Save bootstrap report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_plugins' => $totalCount,
            'loaded_plugins' => $loadedCount,
            'load_order' => $this->loadOrder,
            'errors' => $this->errors,
            'plugins' => array_map(function($plugin) {
                return [
                    'name' => $plugin['manifest']['name'],
                    'version' => $plugin['manifest']['version'],
                    'loaded' => $plugin['loaded']
                ];
            }, $this->plugins)
        ];
        
        file_put_contents(
            $this->pluginsDir . '/BOOTSTRAP_REPORT.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );
        
        echo "💾 Bootstrap report saved to BOOTSTRAP_REPORT.json\n";
        
        if ($loadedCount === $totalCount) {
            echo "\n🎉 All plugins loaded successfully!\n";
        } else {
            echo "\n⚠️  Some plugins failed to load. Check the errors above.\n";
        }
    }
}

// Execute bootstrap
$bootstrap = new PluginBootstrap();
$bootstrap->bootstrapAllPlugins();