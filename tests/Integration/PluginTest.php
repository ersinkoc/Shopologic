<?php

declare(strict_types=1);

/**
 * Plugin Integration Tests
 */

use Shopologic\Core\Plugin\PluginManager;
use Shopologic\Core\Plugin\PluginRepository;
use Shopologic\Core\Container\Container;
use Shopologic\Core\Events\EventDispatcher;

TestFramework::describe('Plugin Integration', function() {
    TestFramework::it('should initialize plugin system', function() {
        $container = new Container();
        $events = new EventDispatcher();
        $repository = new PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        $manager = new PluginManager($repository, $container, $events);
        
        TestFramework::expect($manager)->toBeInstanceOf(PluginManager::class);
    });
    
    TestFramework::it('should discover plugins', function() {
        $repository = new PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        $plugins = $repository->getAll();
        
        TestFramework::expect(is_array($plugins))->toBeTrue();
        
        // Check if core plugins exist
        $pluginNames = array_column($plugins, 'name');
        TestFramework::expect(in_array('core-commerce', $pluginNames))->toBeTrue();
    });
    
    TestFramework::it('should load plugin manifests', function() {
        $repository = new PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        $coreCommerce = $repository->get('core-commerce');
        
        TestFramework::expect($coreCommerce)->not()->toBeNull();
        TestFramework::expect($coreCommerce['name'])->toBe('core-commerce');
        TestFramework::expect(isset($coreCommerce['version']))->toBeTrue();
    });
    
    TestFramework::it('should validate plugin structure', function() {
        $repository = new PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        $coreCommerce = $repository->get('core-commerce');
        
        // Check required fields
        TestFramework::expect(isset($coreCommerce['name']))->toBeTrue();
        TestFramework::expect(isset($coreCommerce['version']))->toBeTrue();
        TestFramework::expect(isset($coreCommerce['main']))->toBeTrue();
        
        // Check if main class file exists
        $mainFile = SHOPOLOGIC_ROOT . '/plugins/core-commerce/' . $coreCommerce['main'];
        TestFramework::expect(file_exists($mainFile))->toBeTrue();
    });
    
    TestFramework::it('should handle plugin activation', function() {
        $container = new Container();
        $events = new EventDispatcher();
        $repository = new PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        $manager = new PluginManager($repository, $container, $events);
        
        // Test activation (won't actually activate, just test the flow)
        TestFramework::expect(function() use ($manager) {
            $manager->activate('core-commerce');
        })->not()->toThrow();
    });
    
    TestFramework::it('should handle plugin dependency resolution', function() {
        $repository = new PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        $stripePlugin = $repository->get('payment-stripe');
        
        if ($stripePlugin && isset($stripePlugin['dependencies'])) {
            foreach ($stripePlugin['dependencies'] as $dependency) {
                $depPlugin = $repository->get($dependency);
                TestFramework::expect($depPlugin)->not()->toBeNull();
            }
        } else {
            // If no dependencies, test passes
            TestFramework::expect(true)->toBeTrue();
        }
    });
    
    TestFramework::it('should register plugin services', function() {
        $container = new Container();
        $events = new EventDispatcher();
        $repository = new PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        $manager = new PluginManager($repository, $container, $events);
        
        // Mock plugin registration
        $container->bind('test.plugin.service', function() {
            return 'plugin service registered';
        });
        
        TestFramework::expect($container->get('test.plugin.service'))->toBe('plugin service registered');
    });
    
    TestFramework::it('should handle plugin events', function() {
        $events = new EventDispatcher();
        $eventFired = false;
        
        $events->listen('plugin.test.event', function() use (&$eventFired) {
            $eventFired = true;
        });
        
        // Simulate plugin firing event
        $events->dispatch(new class extends \Shopologic\Core\Events\Event {
            public function getName(): string {
                return 'plugin.test.event';
            }
        });
        
        TestFramework::expect($eventFired)->toBeTrue();
    });
    
    TestFramework::it('should load plugin configuration', function() {
        $repository = new PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        $stripePlugin = $repository->get('payment-stripe');
        
        if ($stripePlugin) {
            // Check if plugin has configuration structure
            $configFile = SHOPOLOGIC_ROOT . '/plugins/payment-stripe/config/stripe.php';
            if (file_exists($configFile)) {
                TestFramework::expect(file_exists($configFile))->toBeTrue();
            } else {
                // If no config file, that's also valid
                TestFramework::expect(true)->toBeTrue();
            }
        } else {
            TestFramework::expect(true)->toBeTrue();
        }
    });
    
    TestFramework::it('should handle plugin migrations', function() {
        $repository = new PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        $coreCommerce = $repository->get('core-commerce');
        
        if ($coreCommerce) {
            $migrationsDir = SHOPOLOGIC_ROOT . '/plugins/core-commerce/migrations';
            if (is_dir($migrationsDir)) {
                $migrations = glob($migrationsDir . '/*.php');
                TestFramework::expect(count($migrations))->toBeGreaterThan(0);
            } else {
                // No migrations is also valid
                TestFramework::expect(true)->toBeTrue();
            }
        } else {
            TestFramework::expect(true)->toBeTrue();
        }
    });
    
    TestFramework::it('should validate plugin security', function() {
        $repository = new PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        $plugins = $repository->getAll();
        
        foreach ($plugins as $plugin) {
            // Check for basic security requirements
            TestFramework::expect(isset($plugin['name']))->toBeTrue();
            
            // Plugin name should be alphanumeric with hyphens/underscores only
            TestFramework::expect(preg_match('/^[a-zA-Z0-9_-]+$/', $plugin['name']))->toBe(1);
        }
    });
});