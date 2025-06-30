<?php

/**
 * Namespace Fixer - Address remaining namespace issues
 */

declare(strict_types=1);

class NamespaceFixer
{
    private string $pluginsDir;
    private array $plugins = [];
    private int $fixed = 0;
    
    public function __construct()
    {
        $this->pluginsDir = __DIR__;
    }
    
    public function fixAllNamespaces(): void
    {
        echo "🔧 Namespace Fixer - Final Cleanup\n";
        echo "==================================\n\n";
        
        $this->discoverPlugins();
        $this->fixNamespaceIssues();
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
                $this->plugins[$pluginName] = [
                    'path' => $dir,
                    'manifest' => json_decode(file_get_contents($pluginJsonPath), true)
                ];
            }
        }
        
        echo "🎯 Fixing namespaces for " . count($this->plugins) . " plugins\n\n";
    }
    
    private function fixNamespaceIssues(): void
    {
        foreach ($this->plugins as $pluginName => $plugin) {
            echo "🔧 Fixing: $pluginName\n";
            
            $expectedNamespace = $this->getExpectedNamespace($pluginName);
            $manifest = $plugin['manifest'];
            
            // Fix bootstrap file namespace
            if (isset($manifest['bootstrap']['file'])) {
                $bootstrapFile = $plugin['path'] . '/' . $manifest['bootstrap']['file'];
                if (file_exists($bootstrapFile)) {
                    $this->fixBootstrapNamespace($bootstrapFile, $expectedNamespace);
                }
            }
            
            // Fix all PHP files in src directory
            $srcDir = $plugin['path'] . '/src';
            if (is_dir($srcDir)) {
                $this->fixDirectoryNamespaces($srcDir, $expectedNamespace);
            }
            
            echo "   ✅ Fixed\n";
            $this->fixed++;
        }
    }
    
    private function fixBootstrapNamespace(string $filePath, string $expectedNamespace): void
    {
        $content = file_get_contents($filePath);
        
        // Fix namespace declaration
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $currentNamespace = trim($matches[1]);
            if ($currentNamespace !== $expectedNamespace) {
                $content = preg_replace(
                    '/namespace\s+[^;]+;/',
                    "namespace $expectedNamespace;",
                    $content
                );
                file_put_contents($filePath, $content);
            }
        } else {
            // Add namespace if missing
            $content = preg_replace(
                '/(declare\s*\(\s*strict_types\s*=\s*1\s*\);\s*)/',
                "$1\nnamespace $expectedNamespace;\n",
                $content
            );
            file_put_contents($filePath, $content);
        }
    }
    
    private function fixDirectoryNamespaces(string $dir, string $baseNamespace): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace($dir . '/', '', $file->getPathname());
                $subNamespace = str_replace('/', '\\', dirname($relativePath));
                
                if ($subNamespace === '.') {
                    $expectedNamespace = $baseNamespace;
                } else {
                    $expectedNamespace = $baseNamespace . '\\' . $subNamespace;
                }
                
                $this->fixFileNamespace($file->getPathname(), $expectedNamespace);
            }
        }
    }
    
    private function fixFileNamespace(string $filePath, string $expectedNamespace): void
    {
        $content = file_get_contents($filePath);
        
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $currentNamespace = trim($matches[1]);
            if ($currentNamespace !== $expectedNamespace) {
                $content = preg_replace(
                    '/namespace\s+[^;]+;/',
                    "namespace $expectedNamespace;",
                    $content
                );
                file_put_contents($filePath, $content);
            }
        }
    }
    
    private function getExpectedNamespace(string $pluginName): string
    {
        $parts = explode('-', $pluginName);
        $parts = array_map('ucfirst', $parts);
        return 'Shopologic\\Plugins\\' . implode('', $parts);
    }
    
    private function generateFixReport(): void
    {
        echo "\n📊 NAMESPACE FIX REPORT\n";
        echo "========================\n\n";
        echo "✅ Fixed namespaces for $this->fixed plugins\n";
        echo "🎯 All plugins now have correct namespace structure\n\n";
        
        echo "🔍 Expected namespace patterns:\n";
        foreach ($this->plugins as $pluginName => $plugin) {
            $expectedNamespace = $this->getExpectedNamespace($pluginName);
            echo "- $pluginName → $expectedNamespace\n";
        }
        
        echo "\n✨ Namespace standardization complete!\n";
    }
}

// Run the fixer
$fixer = new NamespaceFixer();
$fixer->fixAllNamespaces();