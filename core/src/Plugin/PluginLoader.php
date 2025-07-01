<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

use Shopologic\Core\Exceptions\PluginException;

class PluginLoader
{
    private string $pluginsPath;
    private array $loadedPlugins = [];
    private array $pluginManifests = [];
    
    public function __construct(string $pluginsPath)
    {
        $this->pluginsPath = $pluginsPath;
        
        if (!is_dir($this->pluginsPath)) {
            throw new PluginException("Plugins directory not found: {$this->pluginsPath}");
        }
    }
    
    /**
     * Scan and load all plugins from the plugins directory
     */
    public function scanPlugins(): array
    {
        $plugins = [];
        
        // Get all plugin directories
        $directories = glob($this->pluginsPath . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $pluginDir) {
            try {
                $manifest = $this->loadPluginManifest($pluginDir);
                if ($manifest) {
                    $plugins[basename($pluginDir)] = $manifest;
                }
            } catch (\Exception $e) {
                // Log error but continue loading other plugins
                error_log("Failed to load plugin from {$pluginDir}: " . $e->getMessage());
            }
        }
        
        return $plugins;
    }
    
    /**
     * Load a specific plugin by its directory name
     */
    public function loadPlugin(string $pluginName): ?array
    {
        $pluginDir = $this->pluginsPath . '/' . $pluginName;
        
        if (!is_dir($pluginDir)) {
            throw new PluginException("Plugin directory not found: {$pluginName}");
        }
        
        return $this->loadPluginManifest($pluginDir);
    }
    
    /**
     * Load plugin manifest from plugin.json
     */
    private function loadPluginManifest(string $pluginDir): ?array
    {
        $manifestPath = $pluginDir . '/plugin.json';
        
        if (!file_exists($manifestPath)) {
            return null; // Skip directories without plugin.json
        }
        
        $manifestContent = file_get_contents($manifestPath);
        $manifest = json_decode($manifestContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PluginException("Invalid plugin.json in {$pluginDir}: " . json_last_error_msg());
        }
        
        // Validate required fields
        $requiredFields = ['name', 'version', 'main_class'];
        foreach ($requiredFields as $field) {
            if (!isset($manifest[$field])) {
                throw new PluginException("Missing required field '{$field}' in plugin.json for {$pluginDir}");
            }
        }
        
        // Add plugin directory to manifest
        $manifest['directory'] = $pluginDir;
        $manifest['plugin_name'] = basename($pluginDir);
        
        // Store in cache
        $this->pluginManifests[basename($pluginDir)] = $manifest;
        
        return $manifest;
    }
    
    /**
     * Instantiate a plugin class
     */
    public function instantiatePlugin(array $manifest): AbstractPlugin
    {
        $className = $manifest['main_class'];
        
        // Check if class file exists
        $classFile = $manifest['directory'] . '/' . str_replace('\\', '/', $className) . '.php';
        if (!file_exists($classFile)) {
            // Try alternative path
            $parts = explode('\\', $className);
            $classFile = $manifest['directory'] . '/' . end($parts) . '.php';
            
            if (!file_exists($classFile)) {
                throw new PluginException("Plugin class file not found: {$classFile}");
            }
        }
        
        // Include the file if not already loaded
        require_once $classFile;
        
        // Create fully qualified class name
        $fullClassName = 'Shopologic\\Plugins\\' . str_replace('-', '', ucwords($manifest['plugin_name'], '-')) . '\\' . $className;
        
        if (!class_exists($fullClassName)) {
            // Try without namespace conversion
            $fullClassName = $className;
            
            if (!class_exists($fullClassName)) {
                throw new PluginException("Plugin class not found: {$className}");
            }
        }
        
        // Instantiate plugin
        $plugin = new $fullClassName();
        
        if (!$plugin instanceof AbstractPlugin) {
            throw new PluginException("Plugin class must extend AbstractPlugin: {$className}");
        }
        
        // Set manifest data
        $plugin->setManifest($manifest);
        
        // Mark as loaded
        $this->loadedPlugins[$manifest['plugin_name']] = $plugin;
        
        return $plugin;
    }
    
    /**
     * Get all loaded plugins
     */
    public function getLoadedPlugins(): array
    {
        return $this->loadedPlugins;
    }
    
    /**
     * Get all plugin manifests
     */
    public function getPluginManifests(): array
    {
        return $this->pluginManifests;
    }
    
    /**
     * Check if a plugin is loaded
     */
    public function isLoaded(string $pluginName): bool
    {
        return isset($this->loadedPlugins[$pluginName]);
    }
    
    /**
     * Get dependencies for a plugin
     */
    public function getPluginDependencies(string $pluginName): array
    {
        if (!isset($this->pluginManifests[$pluginName])) {
            return [];
        }
        
        $manifest = $this->pluginManifests[$pluginName];
        return $manifest['dependencies'] ?? [];
    }
    
    /**
     * Sort plugins by dependencies
     */
    public function sortByDependencies(array $plugins): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];
        
        foreach ($plugins as $pluginName => $manifest) {
            if (!isset($visited[$pluginName])) {
                $this->visitPlugin($pluginName, $plugins, $visited, $visiting, $sorted);
            }
        }
        
        return $sorted;
    }
    
    /**
     * Recursive dependency resolution
     */
    private function visitPlugin(string $pluginName, array $plugins, array &$visited, array &$visiting, array &$sorted): void
    {
        if (isset($visiting[$pluginName])) {
            throw new PluginException("Circular dependency detected: {$pluginName}");
        }
        
        if (isset($visited[$pluginName])) {
            return;
        }
        
        $visiting[$pluginName] = true;
        
        if (isset($plugins[$pluginName])) {
            $dependencies = $plugins[$pluginName]['dependencies'] ?? [];
            
            foreach ($dependencies as $dep) {
                if (isset($plugins[$dep])) {
                    $this->visitPlugin($dep, $plugins, $visited, $visiting, $sorted);
                }
            }
            
            $sorted[$pluginName] = $plugins[$pluginName];
        }
        
        $visited[$pluginName] = true;
        unset($visiting[$pluginName]);
    }
}