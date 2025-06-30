<?php

/**
 * Plugin Refactoring Tool
 * Automatically refactors plugins to follow code quality standards
 */

declare(strict_types=1);

class PluginRefactor
{
    private string $pluginsDir;
    private array $plugins = [];
    private int $filesFixed = 0;
    private int $pluginsFixed = 0;
    
    public function __construct()
    {
        $this->pluginsDir = __DIR__;
    }
    
    public function refactor(): void
    {
        echo "🔨 Shopologic Plugin Refactoring Tool\n";
        echo "=====================================\n\n";
        
        $this->discoverPlugins();
        $this->refactorPlugins();
        $this->generateReport();
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
        
        echo "✅ Found " . count($this->plugins) . " plugins to refactor\n\n";
    }
    
    private function refactorPlugins(): void
    {
        foreach ($this->plugins as $pluginName => $plugin) {
            echo "📦 Refactoring: $pluginName\n";
            
            $this->createDirectoryStructure($pluginName, $plugin);
            $this->refactorBootstrapClass($pluginName, $plugin);
            $this->addStrictTypesToPhpFiles($pluginName, $plugin);
            $this->createDocumentation($pluginName, $plugin);
            
            $this->pluginsFixed++;
            echo "   ✅ Completed\n\n";
        }
    }
    
    private function createDirectoryStructure(string $pluginName, array $plugin): void
    {
        $basePath = $plugin['path'];
        
        // Create standard directories
        $directories = [
            '/src',
            '/src/Controllers',
            '/src/Models',
            '/src/Services',
            '/src/Repositories',
            '/templates',
            '/assets',
            '/assets/css',
            '/assets/js',
            '/assets/images',
            '/migrations',
            '/tests',
            '/docs'
        ];
        
        foreach ($directories as $dir) {
            $fullPath = $basePath . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                echo "   📁 Created: $dir\n";
            }
        }
    }
    
    private function refactorBootstrapClass(string $pluginName, array $plugin): void
    {
        if (!isset($plugin['manifest']['bootstrap'])) return;
        
        $bootstrapFile = $plugin['path'] . '/' . $plugin['manifest']['bootstrap']['file'];
        if (!file_exists($bootstrapFile)) return;
        
        $content = file_get_contents($bootstrapFile);
        $className = basename(str_replace('\\', '/', $plugin['manifest']['bootstrap']['class']));
        
        // Check if already extends AbstractPlugin
        if (preg_match("/class\s+$className\s+extends\s+AbstractPlugin/", $content)) {
            return;
        }
        
        // Add use statement if not present
        if (!preg_match("/use\s+Shopologic\\\\Core\\\\Plugin\\\\AbstractPlugin/", $content)) {
            $content = preg_replace(
                "/(namespace\s+[^;]+;)/",
                "$1\n\nuse Shopologic\\Core\\Plugin\\AbstractPlugin;",
                $content
            );
        }
        
        // Update class declaration
        $content = preg_replace(
            "/class\s+$className(\s+implements\s+\w+)?/",
            "class $className extends AbstractPlugin",
            $content
        );
        
        // Add constructor if not present
        if (!preg_match("/public\s+function\s+__construct/", $content)) {
            $constructorCode = <<<'PHP'

    /**
     * Initialize plugin dependencies
     */
    public function __construct(Container $container, string $pluginPath)
    {
        parent::__construct($container, $pluginPath);
    }
PHP;
            $content = preg_replace(
                "/(class\s+$className\s+extends\s+AbstractPlugin\s*\{)/",
                "$1$constructorCode",
                $content
            );
        }
        
        // Add required method stubs if not present
        $requiredMethods = [
            'registerServices',
            'registerEventListeners',
            'registerHooks',
            'registerRoutes',
            'registerPermissions',
            'registerScheduledJobs'
        ];
        
        foreach ($requiredMethods as $method) {
            if (!preg_match("/protected\s+function\s+$method/", $content)) {
                $methodCode = <<<PHP

    /**
     * Register $method
     */
    protected function $method(): void
    {
        // TODO: Implement $method
    }
PHP;
                // Add before the last closing brace
                $content = preg_replace('/(\}\s*)$/', "$methodCode\n$1", $content);
            }
        }
        
        file_put_contents($bootstrapFile, $content);
        echo "   🔧 Updated bootstrap class\n";
        $this->filesFixed++;
    }
    
    private function addStrictTypesToPhpFiles(string $pluginName, array $plugin): void
    {
        $phpFiles = $this->findPhpFiles($plugin['path']);
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Skip if already has strict types
            if (preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $content)) {
                continue;
            }
            
            // Add strict types after <?php
            $content = preg_replace(
                '/<\?php\s*/',
                "<?php\n\ndeclare(strict_types=1);\n",
                $content,
                1
            );
            
            // Fix direct superglobal access
            $content = preg_replace(
                '/\$_(GET|POST|REQUEST)\s*\[/',
                '$request->input(',
                $content
            );
            
            // Fix generic Exception catches
            $content = preg_replace(
                '/catch\s*\(\s*Exception\s+/',
                'catch (\RuntimeException ',
                $content
            );
            
            file_put_contents($file, $content);
            $this->filesFixed++;
        }
        
        if ($this->filesFixed > 0) {
            echo "   🔧 Added strict types to PHP files\n";
        }
    }
    
    private function createDocumentation(string $pluginName, array $plugin): void
    {
        $this->createApiDoc($pluginName, $plugin);
        $this->createHooksDoc($pluginName, $plugin);
    }
    
    private function createApiDoc(string $pluginName, array $plugin): void
    {
        $apiDocPath = $plugin['path'] . '/API.md';
        if (file_exists($apiDocPath)) return;
        
        $manifest = $plugin['manifest'];
        $apiContent = "# {$manifest['name']} API Documentation\n\n";
        $apiContent .= "## Overview\n\n";
        $apiContent .= "{$manifest['description']}\n\n";
        
        if (isset($manifest['api']['endpoints'])) {
            $apiContent .= "## REST Endpoints\n\n";
            foreach ($manifest['api']['endpoints'] as $endpoint) {
                $apiContent .= "### `{$endpoint['method']} {$endpoint['path']}`\n\n";
                $apiContent .= "Handler: `{$endpoint['handler']}`\n\n";
                $apiContent .= "Description: TODO\n\n";
                $apiContent .= "#### Request\n\n```json\n// TODO: Add request example\n```\n\n";
                $apiContent .= "#### Response\n\n```json\n// TODO: Add response example\n```\n\n";
            }
        }
        
        file_put_contents($apiDocPath, $apiContent);
        echo "   📝 Created API.md\n";
    }
    
    private function createHooksDoc(string $pluginName, array $plugin): void
    {
        $hooksDocPath = $plugin['path'] . '/HOOKS.md';
        if (file_exists($hooksDocPath)) return;
        
        $manifest = $plugin['manifest'];
        $hooksContent = "# {$manifest['name']} Hooks Documentation\n\n";
        $hooksContent .= "## Overview\n\n";
        $hooksContent .= "This document describes all hooks (actions and filters) provided by the {$manifest['name']} plugin.\n\n";
        
        if (isset($manifest['hooks']['actions'])) {
            $hooksContent .= "## Actions\n\n";
            foreach ($manifest['hooks']['actions'] as $action) {
                $hooksContent .= "### `$action`\n\n";
                $hooksContent .= "Description: TODO\n\n";
                $hooksContent .= "Parameters:\n- TODO\n\n";
                $hooksContent .= "Example:\n```php\nadd_action('$action', function(\$param) {\n    // Your code here\n});\n```\n\n";
            }
        }
        
        if (isset($manifest['hooks']['filters'])) {
            $hooksContent .= "## Filters\n\n";
            foreach ($manifest['hooks']['filters'] as $filter) {
                $hooksContent .= "### `$filter`\n\n";
                $hooksContent .= "Description: TODO\n\n";
                $hooksContent .= "Parameters:\n- TODO\n\n";
                $hooksContent .= "Example:\n```php\nadd_filter('$filter', function(\$value) {\n    // Modify \$value\n    return \$value;\n});\n```\n\n";
            }
        }
        
        file_put_contents($hooksDocPath, $hooksContent);
        echo "   📝 Created HOOKS.md\n";
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
        echo "\n📊 REFACTORING COMPLETE\n";
        echo "======================\n";
        echo "Plugins refactored: {$this->pluginsFixed}\n";
        echo "Files modified: {$this->filesFixed}\n";
        echo "\n✅ All plugins have been standardized!\n";
    }
}

// Run the refactoring
$refactor = new PluginRefactor();
$refactor->refactor();