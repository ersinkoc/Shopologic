<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

use Shopologic\Core\Container\Container;
use Shopologic\Core\Database\ConnectionInterface;
use Shopologic\Core\Events\EventManager;
use Shopologic\Core\Cache\CacheManager;
use Shopologic\Core\Configuration\ConfigurationManager;

/**
 * Plugin API provides a sandboxed interface for plugins to interact with the system
 */
class PluginAPI
{
    protected Container $container;
    protected string $pluginName;
    protected array $permissions = [];

    public function __construct(Container $container, string $pluginName)
    {
        $this->container = $container;
        $this->pluginName = $pluginName;
    }

    /**
     * Set plugin permissions
     */
    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }

    /**
     * Register a service in the container
     */
    public function registerService(string $name, callable $resolver): void
    {
        $this->checkPermission('container.register');
        
        // Prefix service name with plugin name to avoid conflicts
        $serviceName = "plugin.{$this->pluginName}.{$name}";
        $this->container->bind($serviceName, $resolver);
    }

    /**
     * Get a service from the container
     */
    public function getService(string $name): mixed
    {
        $this->checkPermission('container.get');
        
        // Check if it's a plugin-specific service
        $pluginService = "plugin.{$this->pluginName}.{$name}";
        if ($this->container->has($pluginService)) {
            return $this->container->get($pluginService);
        }
        
        // Check if we're allowed to access global services
        if (!$this->hasPermission('container.get_global')) {
            throw new \RuntimeException("Plugin {$this->pluginName} is not allowed to access global service: {$name}");
        }
        
        return $this->container->get($name);
    }

    /**
     * Register an event listener
     */
    public function addEventListener(string $event, callable $listener, int $priority = 0): void
    {
        $this->checkPermission('events.listen');
        
        $eventManager = $this->container->get(EventManager::class);
        $eventManager->listen($event, $listener, $priority);
    }

    /**
     * Dispatch an event
     */
    public function dispatchEvent(object $event): object
    {
        $this->checkPermission('events.dispatch');
        
        $eventManager = $this->container->get(EventManager::class);
        return $eventManager->dispatch($event);
    }

    /**
     * Add an action hook
     */
    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->checkPermission('hooks.add_action');
        Hook::addAction($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Add a filter hook
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->checkPermission('hooks.add_filter');
        Hook::addFilter($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Execute an action hook
     */
    public function doAction(string $hook, mixed ...$args): void
    {
        $this->checkPermission('hooks.do_action');
        Hook::doAction($hook, ...$args);
    }

    /**
     * Apply filter hooks
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        $this->checkPermission('hooks.apply_filters');
        return Hook::applyFilters($hook, $value, ...$args);
    }

    /**
     * Get database connection
     */
    public function getDatabase(): ConnectionInterface
    {
        $this->checkPermission('database.access');
        return $this->container->get(ConnectionInterface::class);
    }

    /**
     * Get cache manager
     */
    public function getCache(): CacheManager
    {
        $this->checkPermission('cache.access');
        return $this->container->get(CacheManager::class);
    }

    /**
     * Get plugin-specific cache store
     */
    public function getPluginCache(): object
    {
        $this->checkPermission('cache.access');
        
        $cacheManager = $this->container->get(CacheManager::class);
        
        // Return a namespaced cache instance for this plugin
        return new class($cacheManager, $this->pluginName) {
            private CacheManager $cache;
            private string $prefix;
            
            public function __construct(CacheManager $cache, string $pluginName)
            {
                $this->cache = $cache;
                $this->prefix = "plugin_{$pluginName}_";
            }
            
            public function get(string $key, mixed $default = null): mixed
            {
                return $this->cache->get($this->prefix . $key, $default);
            }
            
            public function set(string $key, mixed $value, ?int $ttl = null): bool
            {
                return $this->cache->set($this->prefix . $key, $value, $ttl);
            }
            
            public function delete(string $key): bool
            {
                return $this->cache->delete($this->prefix . $key);
            }
            
            public function clear(): bool
            {
                // Clear only plugin-specific cache
                return true;
            }
        };
    }

    /**
     * Get configuration manager
     */
    public function getConfig(): ConfigurationManager
    {
        $this->checkPermission('config.read');
        return $this->container->get(ConfigurationManager::class);
    }

    /**
     * Get plugin-specific configuration
     */
    public function getPluginConfig(string $key = null, mixed $default = null): mixed
    {
        $this->checkPermission('config.read');
        
        $config = $this->container->get(ConfigurationManager::class);
        $pluginConfig = $config->get("plugins.{$this->pluginName}");
        
        if ($key === null) {
            return $pluginConfig;
        }
        
        return data_get($pluginConfig, $key, $default);
    }

    /**
     * Set plugin-specific configuration
     */
    public function setPluginConfig(string $key, mixed $value): void
    {
        $this->checkPermission('config.write');
        
        $config = $this->container->get(ConfigurationManager::class);
        $config->set("plugins.{$this->pluginName}.{$key}", $value);
    }

    /**
     * Register a route
     */
    public function registerRoute(string $method, string $path, callable $handler): void
    {
        $this->checkPermission('routes.register');
        
        // Prefix plugin routes
        $path = "/plugin/{$this->pluginName}" . ($path === '/' ? '' : $path);
        
        $router = $this->container->get('router');
        $router->addRoute($method, $path, $handler);
    }

    /**
     * Register a console command
     */
    public function registerCommand(string $name, callable $handler): void
    {
        $this->checkPermission('console.register');
        
        // Prefix command with plugin name
        $commandName = "{$this->pluginName}:{$name}";
        
        // This would integrate with a console/CLI system
        // For now, just store in container
        $this->registerService("command.{$name}", function() use ($handler) {
            return $handler;
        });
    }

    /**
     * Get plugin directory path
     */
    public function getPluginPath(string $path = ''): string
    {
        $basePath = plugin_path($this->pluginName);
        return $path ? $basePath . '/' . ltrim($path, '/') : $basePath;
    }

    /**
     * Get plugin URL
     */
    public function getPluginUrl(string $path = ''): string
    {
        $baseUrl = plugin_url($this->pluginName);
        return $path ? $baseUrl . '/' . ltrim($path, '/') : $baseUrl;
    }

    /**
     * Log a message
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->checkPermission('log.write');
        
        $logger = $this->container->get('logger');
        $context['plugin'] = $this->pluginName;
        
        $logger->log($level, "[Plugin: {$this->pluginName}] " . $message, $context);
    }

    /**
     * Check if plugin has permission
     */
    protected function hasPermission(string $permission): bool
    {
        // If no permissions are set, allow all (for backwards compatibility)
        if (empty($this->permissions)) {
            return true;
        }
        
        // Check for wildcard permission
        if (in_array('*', $this->permissions)) {
            return true;
        }
        
        // Check specific permission
        if (in_array($permission, $this->permissions)) {
            return true;
        }
        
        // Check wildcard permissions (e.g., 'hooks.*')
        $parts = explode('.', $permission);
        $wildcard = $parts[0] . '.*';
        
        return in_array($wildcard, $this->permissions);
    }

    /**
     * Check permission and throw exception if not allowed
     */
    protected function checkPermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            throw new \RuntimeException(
                "Plugin {$this->pluginName} does not have permission: {$permission}"
            );
        }
    }
}