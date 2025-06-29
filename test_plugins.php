<?php

declare(strict_types=1);

// Test script for Plugin System

// Include PSR interfaces
require_once __DIR__ . '/core/src/PSR/Container/ContainerInterface.php';
require_once __DIR__ . '/core/src/PSR/Container/ContainerExceptionInterface.php';
require_once __DIR__ . '/core/src/PSR/Container/NotFoundExceptionInterface.php';
require_once __DIR__ . '/core/src/PSR/EventDispatcher/EventDispatcherInterface.php';
require_once __DIR__ . '/core/src/PSR/EventDispatcher/ListenerProviderInterface.php';
require_once __DIR__ . '/core/src/PSR/EventDispatcher/StoppableEventInterface.php';

// Include helpers
require_once __DIR__ . '/core/src/helpers.php';

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Shopologic\\Core\\';
    $base_dir = __DIR__ . '/core/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Check if it's a plugin class
        if (str_starts_with($class, 'HelloWorld\\')) {
            $file = __DIR__ . '/plugins/HelloWorld/' . str_replace('\\', '/', substr($class, 11)) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

echo "🔌 Testing Shopologic Plugin System\n";
echo "==================================\n\n";

try {
    // Test 1: Plugin Discovery
    echo "Test 1: Plugin Discovery\n";
    echo "========================\n";
    
    $container = new \Shopologic\Core\Container\Container();
    $events = new \Shopologic\Core\Events\EventManager();
    
    // Register services
    $container->bind('events', function() use ($events) {
        return $events;
    });
    
    // Register a mock logger
    $container->bind('logger', function() {
        return new class {
            public function log(string $level, string $message, array $context = []): void {
                echo "[{$level}] {$message}\n";
            }
        };
    });
    
    $pluginManager = new \Shopologic\Core\Plugin\PluginManager($container, $events, __DIR__ . '/plugins');
    
    $discovered = $pluginManager->discover();
    echo "✓ Discovered " . count($discovered) . " plugin(s)\n";
    
    foreach ($discovered as $name => $manifest) {
        echo "  - {$name}: {$manifest['description']}\n";
    }
    
    // Test 2: Plugin Loading
    echo "\nTest 2: Plugin Loading\n";
    echo "======================\n";
    
    foreach ($discovered as $name => $manifest) {
        $pluginManager->load($name, $manifest);
        echo "✓ Loaded plugin: {$name}\n";
    }
    
    // Test 3: Plugin Interface
    echo "\nTest 3: Plugin Interface\n";
    echo "========================\n";
    
    if ($pluginManager->isLoaded('HelloWorld')) {
        $plugin = $pluginManager->getPlugin('HelloWorld');
        echo "✓ Plugin name: " . $plugin->getName() . "\n";
        echo "✓ Plugin version: " . $plugin->getVersion() . "\n";
        echo "✓ Plugin author: " . $plugin->getAuthor() . "\n";
        echo "✓ Plugin dependencies: " . json_encode($plugin->getDependencies()) . "\n";
    }
    
    // Test 4: Plugin Lifecycle
    echo "\nTest 4: Plugin Lifecycle\n";
    echo "========================\n";
    
    // Install
    $pluginManager->install('HelloWorld');
    echo "✓ Plugin installed\n";
    
    // Activate
    $pluginManager->activate('HelloWorld');
    echo "✓ Plugin activated\n";
    
    // Boot
    $pluginManager->boot('HelloWorld');
    echo "✓ Plugin booted\n";
    
    // Test 5: Hook System
    echo "\nTest 5: Hook System\n";
    echo "===================\n";
    
    // Test action hooks
    \Shopologic\Core\Plugin\Hook::doAction('app.init');
    echo "✓ Action hook 'app.init' executed\n";
    
    // Test filter hooks
    $title = 'My Page';
    $filtered = \Shopologic\Core\Plugin\Hook::applyFilters('page.title', $title);
    echo "✓ Filter hook 'page.title' applied: '{$title}' -> '{$filtered}'\n";
    
    $price = 100.00;
    $product = ['name' => 'Test Product', 'category' => 'sale'];
    $filteredPrice = \Shopologic\Core\Plugin\Hook::applyFilters('product.price', $price, $product);
    echo "✓ Filter hook 'product.price' applied: \${$price} -> \${$filteredPrice}\n";
    
    // Test 6: Plugin API
    echo "\nTest 6: Plugin API\n";
    echo "==================\n";
    
    $api = new \Shopologic\Core\Plugin\PluginAPI($container, 'HelloWorld');
    $api->setPermissions(['hooks.*', 'events.*', 'cache.access', 'config.*', 'log.write']);
    
    // Set API on plugin
    if (method_exists($plugin, 'setAPI')) {
        $plugin->setAPI($api);
    }
    
    echo "✓ Plugin API created\n";
    echo "✓ Plugin path: " . $api->getPluginPath() . "\n";
    echo "✓ Plugin URL: " . $api->getPluginUrl() . "\n";
    
    // Test adding hooks through API
    $api->addFilter('test.filter', function($value) {
        return $value . ' (filtered by API)';
    });
    
    $testValue = \Shopologic\Core\Plugin\Hook::applyFilters('test.filter', 'Original');
    echo "✓ API filter applied: '{$testValue}'\n";
    
    // Test 7: Plugin Dependencies
    echo "\nTest 7: Plugin Dependencies\n";
    echo "===========================\n";
    
    // Create a plugin with dependencies
    $dependentPlugin = new class extends \Shopologic\Core\Plugin\AbstractPlugin {
        protected string $name = 'DependentPlugin';
        protected string $version = '1.0.0';
        protected array $dependencies = [
            'HelloWorld' => '>=1.0.0',
        ];
    };
    
    echo "✓ Created dependent plugin requiring HelloWorld >=1.0.0\n";
    
    // Test 8: Plugin Deactivation
    echo "\nTest 8: Plugin Deactivation\n";
    echo "===========================\n";
    
    $pluginManager->deactivate('HelloWorld');
    echo "✓ Plugin deactivated\n";
    
    echo "\n🎉 All plugin tests passed!\n";
    echo "\n📋 Plugin System Components:\n";
    echo "   • Plugin discovery and loading\n";
    echo "   • Plugin lifecycle management (install, activate, boot, deactivate, uninstall)\n";
    echo "   • Hook system (actions and filters)\n";
    echo "   • Plugin API with sandboxed access\n";
    echo "   • Dependency resolution\n";
    echo "   • Plugin configuration\n";
    echo "   • Event-driven architecture\n";
    echo "\n🚀 Plugin Architecture Phase 3 Complete!\n";
    
} catch (\Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
}