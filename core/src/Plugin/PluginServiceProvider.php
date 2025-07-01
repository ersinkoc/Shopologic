<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\Events\EventManager;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register hook system as singleton first (static class wrapper)
        $this->container->singleton(HookSystem::class, function() {
            return new HookSystem();
        });
        
        // Register plugin manager as singleton
        $this->container->singleton(PluginManager::class, function($container) {
            $app = $container->get(\Shopologic\Core\Kernel\Application::class);
            return new PluginManager(
                $container,
                $container->get(EventManager::class),
                $app->getBasePath() . '/plugins'
            );
        });
        
        // Register aliases
        $this->container->alias('plugins', PluginManager::class);
        $this->container->alias('hooks', HookSystem::class);
    }

    public function boot(): void
    {
        // Set event manager on HookSystem for async processing
        $eventManager = $this->container->get(EventManager::class);
        HookSystem::setEventManager($eventManager);
        
        // Register WordPress-style global functions
        $this->registerGlobalFunctions();
        
        // Plugin loading will be done after application boot
        // to ensure all services are available
        // $pluginManager = $this->container->get(PluginManager::class);
        // $pluginManager->loadAll();
        // $pluginManager->bootAll();
    }

    /**
     * Register WordPress-style global hook functions
     */
    protected function registerGlobalFunctions(): void
    {
        if (!function_exists('add_action')) {
            function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
                HookSystem::addAction($hook, $callback, $priority, $accepted_args);
            }
        }
        
        if (!function_exists('add_filter')) {
            function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
                HookSystem::addFilter($hook, $callback, $priority, $accepted_args);
            }
        }
        
        if (!function_exists('do_action')) {
            function do_action($hook, ...$args) {
                HookSystem::doAction($hook, ...$args);
            }
        }
        
        if (!function_exists('apply_filters')) {
            function apply_filters($hook, $value, ...$args) {
                return HookSystem::applyFilters($hook, $value, ...$args);
            }
        }
        
        if (!function_exists('remove_action')) {
            function remove_action($hook, $callback, $priority = 10) {
                return HookSystem::removeAction($hook, $callback, $priority);
            }
        }
        
        if (!function_exists('remove_filter')) {
            function remove_filter($hook, $callback, $priority = 10) {
                return HookSystem::removeFilter($hook, $callback, $priority);
            }
        }
        
        if (!function_exists('has_action')) {
            function has_action($hook, $callback = null) {
                return HookSystem::hasAction($hook, $callback);
            }
        }
        
        if (!function_exists('has_filter')) {
            function has_filter($hook, $callback = null) {
                return HookSystem::hasFilter($hook, $callback);
            }
        }
        
        if (!function_exists('current_filter')) {
            function current_filter() {
                return HookSystem::currentFilter();
            }
        }
        
        if (!function_exists('did_action')) {
            function did_action($hook) {
                return HookSystem::didAction($hook);
            }
        }
    }
}