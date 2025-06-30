<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

use Shopologic\Core\Container\Container;
use Shopologic\Core\Database\DB;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for all Shopologic plugins
 * 
 * Provides standard lifecycle methods, dependency injection,
 * and common functionality for plugin development
 */
abstract class AbstractPlugin implements PluginInterface
{
    protected Container $container;
    protected LoggerInterface $logger;
    
    protected array $pluginInfo = [];
    protected string $pluginPath;
    protected bool $isActive = false;
    
    /**
     * Constructor - Dependency injection
     */
    public function __construct(Container $container, string $pluginPath)
    {
        $this->container = $container;
        $this->pluginPath = $pluginPath;
        $this->logger = $container->get(LoggerInterface::class);
        
        // Load plugin manifest
        $this->loadPluginInfo();
    }
    
    /**
     * Get plugin name
     */
    public function getName(): string
    {
        return $this->pluginInfo['name'] ?? $this->generateNameFromClass();
    }
    
    /**
     * Get plugin version
     */
    public function getVersion(): string
    {
        return $this->pluginInfo['version'] ?? '1.0.0';
    }
    
    /**
     * Get plugin description
     */
    public function getDescription(): string
    {
        return $this->pluginInfo['description'] ?? '';
    }
    
    /**
     * Get plugin author
     */
    public function getAuthor(): string
    {
        if (isset($this->pluginInfo['author'])) {
            return is_array($this->pluginInfo['author']) 
                ? ($this->pluginInfo['author']['name'] ?? '')
                : $this->pluginInfo['author'];
        }
        return '';
    }
    
    /**
     * Get plugin dependencies
     */
    public function getDependencies(): array
    {
        return $this->pluginInfo['dependencies'] ?? [];
    }
    
    /**
     * Initialize plugin - Called when plugin is loaded
     */
    public function boot(): void
    {
        $this->registerServices();
        $this->registerEventListeners();
        $this->registerHooks();
        $this->registerRoutes();
        $this->registerPermissions();
        $this->registerScheduledJobs();
        
        $this->logger->info("Plugin initialized: {$this->getName()} v{$this->getVersion()}");
    }
    
    /**
     * Lifecycle method - Install plugin
     */
    public function install(): void
    {
        $this->logger->info("Installing plugin: {$this->getName()}");
        
        // Run migrations
        $this->runMigrations('up');
        
        // Create default configuration
        $this->createDefaultConfiguration();
        
        // Set up initial data
        $this->seedInitialData();
        
        $this->logger->info("Plugin installed successfully: {$this->getName()}");
    }
    
    /**
     * Lifecycle method - Uninstall plugin
     */
    public function uninstall(): void
    {
        $this->logger->info("Uninstalling plugin: {$this->getName()}");
        
        // Run down migrations
        $this->runMigrations('down');
        
        // Remove configuration
        $this->removeConfiguration();
        
        $this->logger->info("Plugin uninstalled successfully: {$this->getName()}");
    }
    
    /**
     * Lifecycle method - Activate plugin
     */
    public function activate(): void
    {
        $this->logger->info("Activating plugin: {$this->getName()}");
        
        $this->isActive = true;
        
        // Perform activation tasks
        $this->onActivation();
        
        $this->logger->info("Plugin activated successfully: {$this->getName()}");
    }
    
    /**
     * Lifecycle method - Deactivate plugin
     */
    public function deactivate(): void
    {
        $this->logger->info("Deactivating plugin: {$this->getName()}");
        
        $this->isActive = false;
        
        // Perform deactivation tasks
        $this->onDeactivation();
        
        $this->logger->info("Plugin deactivated successfully: {$this->getName()}");
    }
    
    /**
     * Lifecycle method - Update plugin
     */
    public function update(string $previousVersion): void
    {
        $this->logger->info("Updating plugin: {$this->getName()} from $previousVersion to {$this->getVersion()}");
        
        // Run upgrade migrations
        $this->runUpgradeMigrations($previousVersion, $this->getVersion());
        
        // Perform upgrade tasks
        $this->onUpgrade($previousVersion, $this->getVersion());
        
        $this->logger->info("Plugin updated successfully: {$this->getName()}");
    }
    
    /**
     * Load plugin manifest
     */
    protected function loadPluginInfo(): void
    {
        $manifestPath = $this->pluginPath . '/plugin.json';
        
        if (!file_exists($manifestPath)) {
            return;
        }
        
        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid plugin manifest JSON: " . json_last_error_msg());
        }
        
        $this->pluginInfo = $manifest;
    }
    
    /**
     * Get plugin configuration
     */
    protected function getPluginConfig(string $key = null): mixed
    {
        $config = $this->container->get('config');
        $configKey = "plugins.{$this->getName()}";
        
        if ($key !== null) {
            $configKey .= ".$key";
        }
        
        return $config->get($configKey);
    }
    
    /**
     * Update plugin configuration
     */
    protected function setPluginConfig(string $key, mixed $value): void
    {
        $config = $this->container->get('config');
        $configKey = "plugins.{$this->getName()}.$key";
        $config->set($configKey, $value);
    }
    
    /**
     * Register a route
     */
    protected function registerRoute(string $method, string $path, callable $handler): void
    {
        $router = $this->container->get('router');
        $router->addRoute($method, $path, $handler, [
            'plugin' => $this->getName()
        ]);
    }
    
    /**
     * Add a hook action
     */
    protected function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $hookSystem = $this->container->get('hooks');
        $hookSystem->addAction($hook, $callback, $priority);
    }
    
    /**
     * Add a hook filter
     */
    protected function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $hookSystem = $this->container->get('hooks');
        $hookSystem->addFilter($hook, $callback, $priority);
    }
    
    /**
     * Run migrations
     */
    protected function runMigrations(string $direction = 'up'): void
    {
        $migrationsPath = $this->pluginPath . '/migrations';
        
        if (!is_dir($migrationsPath)) {
            return;
        }
        
        // TODO: Implement migration runner
    }
    
    /**
     * Run upgrade migrations
     */
    protected function runUpgradeMigrations(string $fromVersion, string $toVersion): void
    {
        $upgradesPath = $this->pluginPath . '/upgrades';
        
        if (!is_dir($upgradesPath)) {
            return;
        }
        
        // TODO: Implement upgrade runner
    }
    
    /**
     * Create default configuration
     */
    protected function createDefaultConfiguration(): void
    {
        if (!isset($this->pluginInfo['config']['schema'])) {
            return;
        }
        
        foreach ($this->pluginInfo['config']['schema'] as $key => $schema) {
            if (isset($schema['default'])) {
                $this->setPluginConfig($key, $schema['default']);
            }
        }
    }
    
    /**
     * Remove configuration
     */
    protected function removeConfiguration(): void
    {
        $config = $this->container->get('config');
        $configKey = "plugins.{$this->getName()}";
        $config->remove($configKey);
    }
    
    /**
     * Generate plugin name from class
     */
    protected function generateNameFromClass(): string
    {
        $className = get_class($this);
        $parts = explode('\\', $className);
        $name = end($parts);
        
        // Remove 'Plugin' suffix if present
        if (str_ends_with($name, 'Plugin')) {
            $name = substr($name, 0, -6);
        }
        
        return $name;
    }
    
    /**
     * Seed initial data - Override in child classes
     */
    protected function seedInitialData(): void
    {
        // Override in child classes to seed data
    }
    
    /**
     * Called when plugin is activated - Override in child classes
     */
    protected function onActivation(): void
    {
        // Override in child classes
    }
    
    /**
     * Called when plugin is deactivated - Override in child classes
     */
    protected function onDeactivation(): void
    {
        // Override in child classes
    }
    
    /**
     * Called when plugin is upgraded - Override in child classes
     */
    protected function onUpgrade(string $fromVersion, string $toVersion): void
    {
        // Override in child classes
    }
    
    // Methods that should be implemented by plugins
    protected function registerServices(): void
    {
        // Override in child classes to register services
    }
    
    protected function registerEventListeners(): void
    {
        // Override in child classes to register event listeners
    }
    
    protected function registerHooks(): void
    {
        // Override in child classes to register hooks
    }
    
    protected function registerRoutes(): void
    {
        // Override in child classes to register routes
    }
    
    protected function registerPermissions(): void
    {
        // Override in child classes to register permissions
    }
    
    protected function registerScheduledJobs(): void
    {
        // Override in child classes to register scheduled jobs
    }
}