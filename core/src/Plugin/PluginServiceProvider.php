<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

use Shopologic\Core\ServiceProvider\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register plugin manager as singleton
        $this->container->singleton(PluginManager::class, function($container) {
            $pluginPath = base_path('plugins');
            $eventManager = $container->get('events');
            
            return new PluginManager($container, $eventManager, $pluginPath);
        });
        
        // Register plugin manager alias
        $this->container->alias('plugins', PluginManager::class);
    }

    public function boot(): void
    {
        $pluginManager = $this->container->get(PluginManager::class);
        
        // Load all plugins
        $pluginManager->loadAll();
        
        // Boot all activated plugins
        $pluginManager->bootAll();
        
        // Register plugin console commands
        if ($this->container->has('console')) {
            $this->registerCommands();
        }
    }

    protected function registerCommands(): void
    {
        $console = $this->container->get('console');
        
        // Register plugin management commands
        $commands = [
            'plugin:list' => Commands\ListCommand::class,
            'plugin:activate' => Commands\ActivateCommand::class,
            'plugin:deactivate' => Commands\DeactivateCommand::class,
            'plugin:install' => Commands\InstallCommand::class,
            'plugin:uninstall' => Commands\UninstallCommand::class,
            'plugin:update' => Commands\UpdateCommand::class,
            'plugin:create' => Commands\CreateCommand::class,
        ];
        
        foreach ($commands as $name => $class) {
            if (class_exists($class)) {
                $console->add($name, new $class($this->container));
            }
        }
    }
}