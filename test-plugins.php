<?php

declare(strict_types=1);

/**
 * Shopologic Plugin System Test
 * Tests plugin loading, activation, and functionality
 */

define('SHOPOLOGIC_START', microtime(true));
define('SHOPOLOGIC_ROOT', __DIR__);

require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Kernel\Application;
use Shopologic\Core\Plugin\PluginManager;

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Terminal colors
class Colors {
    const GREEN = "\033[32m";
    const RED = "\033[31m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

// Test utilities
function test(string $name, callable $fn): bool {
    echo Colors::YELLOW . "Testing: " . Colors::RESET . $name . "... ";
    
    try {
        $result = $fn();
        if ($result === true) {
            echo Colors::GREEN . "✓ PASSED" . Colors::RESET . "\n";
            return true;
        } else {
            echo Colors::RED . "✗ FAILED" . Colors::RESET . " - $result\n";
            return false;
        }
    } catch (\Exception $e) {
        echo Colors::RED . "✗ ERROR" . Colors::RESET . " - " . $e->getMessage() . "\n";
        if (getenv('DEBUG')) {
            echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        }
        return false;
    }
}

function section(string $name): void {
    echo "\n" . Colors::BLUE . Colors::BOLD . "═══ $name ═══" . Colors::RESET . "\n\n";
}

// Banner
echo Colors::CYAN . "
╔════════════════════════════════════════════════╗
║        Shopologic Plugin System Test           ║
╚════════════════════════════════════════════════╝
" . Colors::RESET . "\n";

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\PSR', SHOPOLOGIC_ROOT . '/core/src/PSR');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');
$autoloader->register();

// Load helper functions
if (file_exists(SHOPOLOGIC_ROOT . '/core/src/helpers.php')) {
    require_once SHOPOLOGIC_ROOT . '/core/src/helpers.php';
}

// Track results
$passed = 0;
$failed = 0;

// Initialize application
section('Application Bootstrap');

$app = null;
$pluginManager = null;

if (test('Application initialization', function() use (&$app) {
    $app = new Application(SHOPOLOGIC_ROOT);
    $GLOBALS['SHOPOLOGIC_APP'] = $app;
    return $app instanceof Application;
})) $passed++; else $failed++;

if (test('Plugin service provider registration', function() use ($app) {
    $app->register(\Shopologic\Core\Plugin\PluginServiceProvider::class);
    return true;
})) $passed++; else $failed++;

if (test('Application boot', function() use ($app) {
    $app->boot();
    return true;
})) $passed++; else $failed++;

if (test('Get PluginManager instance', function() use ($app, &$pluginManager) {
    $pluginManager = $app->getContainer()->get(PluginManager::class);
    return $pluginManager instanceof PluginManager;
})) $passed++; else $failed++;

// Plugin discovery
section('Plugin Discovery');

$discovered = [];

if (test('Discover available plugins', function() use ($pluginManager, &$discovered) {
    $discovered = $pluginManager->discover();
    return count($discovered) > 0;
})) $passed++; else $failed++;

if (test('Core-commerce plugin found', function() use ($discovered) {
    return isset($discovered['core-commerce']);
})) $passed++; else $failed++;

if (test('Plugin manifest valid', function() use ($discovered) {
    $manifest = $discovered['core-commerce'] ?? null;
    if (!$manifest) return "No manifest found";
    
    if (!isset($manifest['name'])) return "Missing name";
    if (!isset($manifest['version'])) return "Missing version";
    if (!isset($manifest['bootstrap'])) return "Missing bootstrap";
    
    return true;
})) $passed++; else $failed++;

// Plugin loading
section('Plugin Loading');

if (test('Load core-commerce plugin', function() use ($pluginManager, $discovered) {
    $pluginManager->load('core-commerce', $discovered['core-commerce']);
    return true;
})) $passed++; else $failed++;

if (test('Plugin class exists', function() {
    return class_exists('\\Shopologic\\Plugins\\CoreCommerce\\CoreCommercePlugin');
})) $passed++; else $failed++;

if (test('Get loaded plugin instance', function() use ($pluginManager) {
    $plugin = $pluginManager->getPlugin('core-commerce');
    return $plugin instanceof \Shopologic\Core\Plugin\AbstractPlugin;
})) $passed++; else $failed++;

// Plugin information
section('Plugin Information');

$plugin = null;

if (test('Get plugin metadata', function() use ($pluginManager, &$plugin) {
    $plugin = $pluginManager->getPlugin('core-commerce');
    
    $name = $plugin->getName();
    $version = $plugin->getVersion();
    $description = $plugin->getDescription();
    
    if ($name !== 'Core Commerce') return "Wrong name: $name";
    if ($version !== '1.0.0') return "Wrong version: $version";
    if (empty($description)) return "Empty description";
    
    return true;
})) $passed++; else $failed++;

// Plugin installation
section('Plugin Installation');

if (test('Install plugin', function() use ($pluginManager) {
    $pluginManager->install('core-commerce');
    return true;
})) $passed++; else $failed++;

if (test('Check plugin installed status', function() use ($pluginManager) {
    return $pluginManager->isInstalled('core-commerce');
})) $passed++; else $failed++;

// Plugin activation
section('Plugin Activation');

if (test('Activate plugin', function() use ($pluginManager) {
    $pluginManager->activate('core-commerce');
    return true;
})) $passed++; else $failed++;

if (test('Check plugin activated status', function() use ($pluginManager) {
    return $pluginManager->isActivated('core-commerce');
})) $passed++; else $failed++;

if (test('Boot plugin', function() use ($pluginManager) {
    $pluginManager->boot('core-commerce');
    return true;
})) $passed++; else $failed++;

// Service registration
section('Service Registration');

$container = $app->getContainer();

if (test('ProductRepositoryInterface registered', function() use ($container) {
    return $container->has('\\Shopologic\\Plugins\\CoreCommerce\\Contracts\\ProductRepositoryInterface');
})) $passed++; else $failed++;

if (test('CategoryRepositoryInterface registered', function() use ($container) {
    return $container->has('\\Shopologic\\Plugins\\CoreCommerce\\Contracts\\CategoryRepositoryInterface');
})) $passed++; else $failed++;

if (test('CartServiceInterface registered', function() use ($container) {
    return $container->has('\\Shopologic\\Plugins\\CoreCommerce\\Contracts\\CartServiceInterface');
})) $passed++; else $failed++;

if (test('OrderServiceInterface registered', function() use ($container) {
    return $container->has('\\Shopologic\\Plugins\\CoreCommerce\\Contracts\\OrderServiceInterface');
})) $passed++; else $failed++;

if (test('CustomerServiceInterface registered', function() use ($container) {
    return $container->has('\\Shopologic\\Plugins\\CoreCommerce\\Contracts\\CustomerServiceInterface');
})) $passed++; else $failed++;

// Hook registration
section('Hook Registration');

if (test('Plugin hooks registered', function() {
    // Check if common commerce hooks are registered
    $hooks = ['product.created', 'order.created', 'cart.updated'];
    
    foreach ($hooks as $hook) {
        if (!has_action($hook)) {
            return "Missing hook: $hook";
        }
    }
    
    return true;
})) $passed++; else $failed++;

if (test('Test hook execution', function() {
    $executed = false;
    
    // Add a test listener
    add_action('product.created', function($product) use (&$executed) {
        $executed = true;
    });
    
    // Trigger the hook
    do_action('product.created', ['id' => 1, 'name' => 'Test Product']);
    
    return $executed;
})) $passed++; else $failed++;

// Models
section('Plugin Models');

if (test('Product model exists', function() {
    return class_exists('\\Shopologic\\Plugins\\CoreCommerce\\Models\\Product');
})) $passed++; else $failed++;

if (test('Category model exists', function() {
    return class_exists('\\Shopologic\\Plugins\\CoreCommerce\\Models\\Category');
})) $passed++; else $failed++;

if (test('Order model exists', function() {
    return class_exists('\\Shopologic\\Plugins\\CoreCommerce\\Models\\Order');
})) $passed++; else $failed++;

if (test('Cart model exists', function() {
    return class_exists('\\Shopologic\\Plugins\\CoreCommerce\\Models\\Cart');
})) $passed++; else $failed++;

if (test('Customer model exists', function() {
    return class_exists('\\Shopologic\\Plugins\\CoreCommerce\\Models\\Customer');
})) $passed++; else $failed++;

// Integration test
section('Integration Tests');

if (test('Create product through service', function() use ($container) {
    // This would normally interact with the database
    // For now, just test that we can get the service
    try {
        $productRepo = $container->get('\\Shopologic\\Plugins\\CoreCommerce\\Contracts\\ProductRepositoryInterface');
        return $productRepo !== null;
    } catch (\Exception $e) {
        return "Could not resolve ProductRepository: " . $e->getMessage();
    }
})) $passed++; else $failed++;

// Summary
echo "\n" . Colors::BLUE . "╔════════════════════════════════════════════════╗" . Colors::RESET . "\n";
echo Colors::BLUE . "║                   SUMMARY                      ║" . Colors::RESET . "\n";
echo Colors::BLUE . "╚════════════════════════════════════════════════╝" . Colors::RESET . "\n\n";

$total = $passed + $failed;
echo "Total Tests: $total\n";
echo Colors::GREEN . "Passed: $passed" . Colors::RESET . "\n";
echo Colors::RED . "Failed: $failed" . Colors::RESET . "\n\n";

// Performance metrics
$elapsed = round((microtime(true) - SHOPOLOGIC_START) * 1000, 2);
$memory = round(memory_get_peak_usage() / 1024 / 1024, 2);

echo Colors::CYAN . "Performance:" . Colors::RESET . "\n";
echo "  Execution time: {$elapsed}ms\n";
echo "  Peak memory: {$memory}MB\n\n";

// Final result
if ($failed === 0) {
    echo Colors::GREEN . "✅ All plugin tests passed!" . Colors::RESET . "\n";
    exit(0);
} else {
    echo Colors::RED . "❌ Some tests failed." . Colors::RESET . "\n";
    exit(1);
}