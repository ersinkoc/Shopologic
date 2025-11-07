<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

use Shopologic\Core\Container\Container;
use Shopologic\Core\Events\EventManager;
use Shopologic\Core\Plugin\Exception\PluginException;
use Shopologic\Core\Plugin\Exception\DependencyException;
use Shopologic\Core\Plugin\Events\PluginLoadedEvent;
use Shopologic\Core\Plugin\Events\PluginActivatedEvent;
use Shopologic\Core\Plugin\Events\PluginDeactivatedEvent;
use Shopologic\Core\Plugin\Events\PluginBootedEvent;
use Shopologic\Core\Plugin\Events\PluginInstalledEvent;
use Shopologic\Core\Plugin\Events\PluginUninstalledEvent;
use Shopologic\Core\Plugin\Events\PluginUpdatedEvent;

class PluginManager
{
    protected Container $container;
    protected EventManager $events;
    protected array $plugins = [];
    protected array $booted = [];
    protected array $activated = [];
    protected string $pluginPath;
    protected PluginRepository $repository;

    public function __construct(Container $container, EventManager $events, string $pluginPath)
    {
        $this->container = $container;
        $this->events = $events;
        $this->pluginPath = $pluginPath;
        $this->repository = new PluginRepository();
    }

    /**
     * Discover all plugins in the plugin directory
     */
    public function discover(): array
    {
        $discovered = [];
        
        if (!is_dir($this->pluginPath)) {
            return $discovered;
        }
        
        $directories = scandir($this->pluginPath);
        
        foreach ($directories as $directory) {
            if ($directory === '.' || $directory === '..') {
                continue;
            }
            
            $pluginDir = $this->pluginPath . '/' . $directory;
            
            if (!is_dir($pluginDir)) {
                continue;
            }
            
            $manifestFile = $pluginDir . '/plugin.json';
            
            if (!file_exists($manifestFile)) {
                continue;
            }
            
            $manifest = json_decode(file_get_contents($manifestFile), true);
            
            if (!$manifest) {
                continue;
            }
            
            // Validate required fields
            if (!$this->validateManifest($manifest, $directory)) {
                continue;
            }
            
            $discovered[$directory] = $manifest;
        }
        
        return $discovered;
    }

    /**
     * Load a plugin
     */
    public function load(string $name, array $manifest): void
    {
        if ($this->isLoaded($name)) {
            return;
        }
        
        // Load plugin autoloader if exists
        $autoloadFile = $this->pluginPath . '/' . $name . '/vendor/autoload.php';
        if (file_exists($autoloadFile)) {
            require_once $autoloadFile;
        }
        
        // Register PSR-4 autoloading from manifest
        if (isset($manifest['autoload']['psr-4'])) {
            $autoloader = spl_autoload_functions()[0] ?? null;
            if ($autoloader && is_array($autoloader) && $autoloader[0] instanceof \Shopologic\Core\Autoloader) {
                foreach ($manifest['autoload']['psr-4'] as $namespace => $path) {
                    $fullPath = $this->pluginPath . '/' . $name . '/' . $path;
                    $autoloader[0]->addNamespace(rtrim($namespace, '\\'), rtrim($fullPath, '/'));
                }
            }
        }
        
        // Determine plugin class file and class name
        $pluginInfo = $this->resolvePluginClass($name, $manifest);
        $classFile = $pluginInfo['file'];
        $className = $pluginInfo['class'];
        
        if (!file_exists($classFile)) {
            throw new PluginException("Plugin class file not found: {$classFile}");
        }
        
        require_once $classFile;
        
        if (!class_exists($className)) {
            throw new PluginException("Plugin class not found: {$className}");
        }
        
        $pluginPath = $this->pluginPath . '/' . $name;
        $plugin = new $className($this->container, $pluginPath);
        
        if (!$plugin instanceof PluginInterface) {
            throw new PluginException("Plugin must implement PluginInterface: {$className}");
        }
        
        $this->plugins[$name] = [
            'instance' => $plugin,
            'manifest' => $manifest,
        ];
        
        $this->events->dispatch(new PluginLoadedEvent($name, $plugin));
    }

    /**
     * Load all discovered plugins
     */
    public function loadAll(): void
    {
        $discovered = $this->discover();
        
        foreach ($discovered as $name => $manifest) {
            try {
                $this->load($name, $manifest);
            } catch (PluginException $e) {
                // Log error but continue loading other plugins
                error_log("Failed to load plugin {$name}: " . $e->getMessage());
            }
        }
    }

    /**
     * Activate a plugin
     */
    public function activate(string $name): void
    {
        if (!$this->isLoaded($name)) {
            throw new PluginException("Plugin not loaded: {$name}");
        }
        
        if ($this->isActivated($name)) {
            return;
        }
        
        $plugin = $this->getPlugin($name);
        
        // Check dependencies
        $this->checkDependencies($plugin);
        
        // Activate the plugin
        $plugin->activate();
        
        $this->activated[$name] = true;
        $this->repository->setActivated($name, true);
        
        $this->events->dispatch(new PluginActivatedEvent($name, $plugin));
    }

    /**
     * Deactivate a plugin
     */
    public function deactivate(string $name): void
    {
        if (!$this->isActivated($name)) {
            return;
        }
        
        $plugin = $this->getPlugin($name);
        
        // Check if other plugins depend on this one
        $this->checkDependents($name);
        
        $plugin->deactivate();
        
        unset($this->activated[$name]);
        $this->repository->setActivated($name, false);
        
        $this->events->dispatch(new PluginDeactivatedEvent($name, $plugin));
    }

    /**
     * Boot a plugin
     */
    public function boot(string $name): void
    {
        if (!$this->isActivated($name)) {
            throw new PluginException("Plugin not activated: {$name}");
        }
        
        if ($this->isBooted($name)) {
            return;
        }
        
        $plugin = $this->getPlugin($name);
        
        // Boot dependencies first
        foreach ($plugin->getDependencies() as $dependency => $version) {
            if ($this->isLoaded($dependency) && $this->isActivated($dependency)) {
                $this->boot($dependency);
            }
        }
        
        $plugin->boot();
        
        $this->booted[$name] = true;
        
        $this->events->dispatch(new PluginBootedEvent($name, $plugin));
    }

    /**
     * Boot all activated plugins
     */
    public function bootAll(): void
    {
        $activated = $this->repository->getActivated();
        
        foreach ($activated as $name) {
            if ($this->isLoaded($name)) {
                try {
                    $this->boot($name);
                } catch (PluginException $e) {
                    error_log("Failed to boot plugin {$name}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Install a plugin
     */
    public function install(string $name): void
    {
        if (!$this->isLoaded($name)) {
            throw new PluginException("Plugin not loaded: {$name}");
        }
        
        $plugin = $this->getPlugin($name);
        
        $plugin->install();
        
        $this->repository->setInstalled($name, true);
        $this->repository->setVersion($name, $plugin->getVersion());
        
        $this->events->dispatch(new PluginInstalledEvent($name, $plugin));
    }

    /**
     * Uninstall a plugin
     */
    public function uninstall(string $name): void
    {
        if ($this->isActivated($name)) {
            $this->deactivate($name);
        }
        
        $plugin = $this->getPlugin($name);
        
        $plugin->uninstall();
        
        $this->repository->setInstalled($name, false);
        
        unset($this->plugins[$name]);
        
        $this->events->dispatch(new PluginUninstalledEvent($name, $plugin));
    }

    /**
     * Update a plugin
     */
    public function update(string $name): void
    {
        if (!$this->isLoaded($name)) {
            throw new PluginException("Plugin not loaded: {$name}");
        }
        
        $plugin = $this->getPlugin($name);
        $previousVersion = $this->repository->getVersion($name) ?? '0.0.0';
        
        $plugin->update($previousVersion);
        
        $this->repository->setVersion($name, $plugin->getVersion());
        
        $this->events->dispatch(new PluginUpdatedEvent($name, $plugin, $previousVersion));
    }

    /**
     * Get a plugin instance
     */
    public function getPlugin(string $name): PluginInterface
    {
        if (!$this->isLoaded($name)) {
            throw new PluginException("Plugin not loaded: {$name}");
        }
        
        return $this->plugins[$name]['instance'];
    }

    /**
     * Get all loaded plugins
     */
    public function getPlugins(): array
    {
        $plugins = [];
        
        foreach ($this->plugins as $name => $data) {
            $plugins[$name] = $data['instance'];
        }
        
        return $plugins;
    }

    /**
     * Check if a plugin is loaded
     */
    public function isLoaded(string $name): bool
    {
        return isset($this->plugins[$name]);
    }

    /**
     * Check if a plugin is activated
     */
    public function isActivated(string $name): bool
    {
        return isset($this->activated[$name]);
    }

    /**
     * Check if a plugin is booted
     */
    public function isBooted(string $name): bool
    {
        return isset($this->booted[$name]);
    }
    
    /**
     * Check if plugin is installed
     */
    public function isInstalled(string $name): bool
    {
        return $this->repository->isInstalled($name);
    }

    /**
     * Check plugin dependencies
     */
    protected function checkDependencies(PluginInterface $plugin): void
    {
        foreach ($plugin->getDependencies() as $dependency => $version) {
            if (!$this->isLoaded($dependency)) {
                throw new DependencyException("Required dependency not found: {$dependency}");
            }
            
            if (!$this->isActivated($dependency)) {
                throw new DependencyException("Required dependency not activated: {$dependency}");
            }
            
            $dependencyPlugin = $this->getPlugin($dependency);
            
            if (!$this->checkVersion($dependencyPlugin->getVersion(), $version)) {
                throw new DependencyException(
                    "Dependency version mismatch: {$dependency} requires {$version}, found {$dependencyPlugin->getVersion()}"
                );
            }
        }
    }

    /**
     * Check if other plugins depend on this one
     */
    protected function checkDependents(string $name): void
    {
        foreach ($this->plugins as $pluginName => $data) {
            if (!$this->isActivated($pluginName)) {
                continue;
            }
            
            $plugin = $data['instance'];
            $dependencies = $plugin->getDependencies();
            
            if (isset($dependencies[$name])) {
                throw new DependencyException(
                    "Cannot deactivate {$name}: {$pluginName} depends on it"
                );
            }
        }
    }

    /**
     * Check version constraint
     */
    protected function checkVersion(string $version, string $constraint): bool
    {
        // Simple version checking - can be enhanced with semantic versioning
        if ($constraint === '*') {
            return true;
        }
        
        if (str_starts_with($constraint, '>=')) {
            $required = substr($constraint, 2);
            return version_compare($version, $required, '>=');
        }
        
        if (str_starts_with($constraint, '>')) {
            $required = substr($constraint, 1);
            return version_compare($version, $required, '>');
        }
        
        if (str_starts_with($constraint, '^')) {
            // Caret constraint - compatible with given version
            $required = substr($constraint, 1);
            return version_compare($version, $required, '>=') && 
                   version_compare($version, $this->getNextMajorVersion($required), '<');
        }
        
        return $version === $constraint;
    }

    /**
     * Get next major version
     */
    protected function getNextMajorVersion(string $version): string
    {
        $parts = explode('.', $version);

        // Ensure we have at least 3 parts (major.minor.patch)
        while (count($parts) < 3) {
            $parts[] = '0';
        }

        $parts[0] = (string)((int)$parts[0] + 1);
        $parts[1] = '0';
        $parts[2] = '0';

        return implode('.', $parts);
    }

    /**
     * Validate plugin manifest
     */
    protected function validateManifest(array $manifest, string $directory): bool
    {
        // Check for required fields
        if (!isset($manifest['name']) || !isset($manifest['version'])) {
            error_log("Plugin {$directory}: Missing required fields (name or version)");
            return false;
        }
        
        // Check for class definition in various formats
        $hasClassDefinition = isset($manifest['class']) || 
                             isset($manifest['main']) || 
                             isset($manifest['main_class']) || 
                             (isset($manifest['config']['main_class'])) ||
                             isset($manifest['bootstrap']);
        
        if (!$hasClassDefinition) {
            error_log("Plugin {$directory}: No class definition found in manifest");
            return false;
        }
        
        return true;
    }

    /**
     * Resolve plugin class file and name from manifest
     */
    protected function resolvePluginClass(string $name, array $manifest): array
    {
        $pluginDir = $this->pluginPath . '/' . $name;
        
        // Priority 1: Bootstrap field (common in many plugin systems)
        if (isset($manifest['bootstrap'])) {
            $bootstrap = $manifest['bootstrap'];
            if (isset($bootstrap['file']) && isset($bootstrap['class'])) {
                $className = $bootstrap['class'];
                
                // If class name doesn't have namespace, try to construct it
                if (strpos($className, '\\') === false && isset($manifest['autoload']['psr-4'])) {
                    $namespace = array_key_first($manifest['autoload']['psr-4']);
                    if ($namespace) {
                        $className = rtrim($namespace, '\\') . '\\' . $className;
                    }
                }
                
                return [
                    'file' => $pluginDir . '/' . $bootstrap['file'],
                    'class' => $className
                ];
            }
            // If bootstrap is just a string, assume it's the class name
            if (is_string($bootstrap)) {
                $possibleFiles = $this->findClassFile($pluginDir, $bootstrap);
                if (!empty($possibleFiles)) {
                    return [
                        'file' => $possibleFiles[0],
                        'class' => $bootstrap
                    ];
                }
            }
        }
        
        // Priority 2: New standardized 'main' field (file path)
        if (isset($manifest['main'])) {
            $classFile = $pluginDir . '/' . $manifest['main'];
            $className = $this->extractClassNameFromFile($classFile);
            if (!$className && isset($manifest['namespace'])) {
                // Try to construct class name from namespace and file
                $fileName = basename($manifest['main'], '.php');
                $className = $manifest['namespace'] . '\\' . $fileName;
            }
            return ['file' => $classFile, 'class' => $className];
        }
        
        // Priority 2: Legacy 'class' + 'file' combination
        if (isset($manifest['class']) && isset($manifest['file'])) {
            return [
                'file' => $pluginDir . '/' . $manifest['file'],
                'class' => $manifest['class']
            ];
        }
        
        // Priority 3: Only 'class' field - try to find the file
        if (isset($manifest['class'])) {
            $className = $manifest['class'];
            $possibleFiles = $this->findClassFile($pluginDir, $className);
            if (!empty($possibleFiles)) {
                return [
                    'file' => $possibleFiles[0],
                    'class' => $className
                ];
            }
        }
        
        // Priority 4: 'main_class' field
        if (isset($manifest['main_class'])) {
            $className = $manifest['main_class'];
            $possibleFiles = $this->findClassFile($pluginDir, $className);
            if (!empty($possibleFiles)) {
                return [
                    'file' => $possibleFiles[0],
                    'class' => $className
                ];
            }
        }
        
        // Priority 5: Nested config.main_class
        if (isset($manifest['config']['main_class'])) {
            $className = $manifest['config']['main_class'];
            $possibleFiles = $this->findClassFile($pluginDir, $className);
            if (!empty($possibleFiles)) {
                return [
                    'file' => $possibleFiles[0],
                    'class' => $className
                ];
            }
        }
        
        throw new PluginException("Could not resolve plugin class for {$name}");
    }

    /**
     * Find class file in plugin directory
     */
    protected function findClassFile(string $pluginDir, string $className): array
    {
        $files = [];
        
        // Extract class name without namespace
        $parts = explode('\\', $className);
        $simpleClassName = end($parts);
        
        // Common locations to check
        $locations = [
            $simpleClassName . '.php',
            'src/' . $simpleClassName . '.php',
            'lib/' . $simpleClassName . '.php',
            strtolower($simpleClassName) . '.php',
            'src/' . strtolower($simpleClassName) . '.php',
        ];
        
        // Also check based on PSR-4 namespace structure
        if (strpos($className, '\\') !== false) {
            $namespaceParts = explode('\\', $className);
            // Remove vendor and package parts (e.g., Shopologic\Plugins\CoreCommerce)
            if (count($namespaceParts) >= 4) {
                $relativePath = implode('/', array_slice($namespaceParts, 3)) . '.php';
                $locations[] = 'src/' . $relativePath;
            }
        }
        
        foreach ($locations as $location) {
            $file = $pluginDir . '/' . $location;
            if (file_exists($file)) {
                $files[] = $file;
            }
        }
        
        // If not found, scan directory recursively
        if (empty($files)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($pluginDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    if (preg_match('/class\s+' . preg_quote($simpleClassName) . '\s/', $content)) {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }
        
        return $files;
    }

    /**
     * Extract class name from file
     */
    protected function extractClassNameFromFile(string $file): ?string
    {
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        
        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }
        
        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];
            return $namespace ? $namespace . '\\' . $className : $className;
        }
        
        return null;
    }
}