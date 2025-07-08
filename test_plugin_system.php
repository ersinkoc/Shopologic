<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

// Create the application
global $SHOPOLOGIC_APP;
$SHOPOLOGIC_APP = new Shopologic\Core\Kernel\Application(__DIR__);

// Register plugin service provider
$SHOPOLOGIC_APP->register(\Shopologic\Core\Plugin\PluginServiceProvider::class);

$SHOPOLOGIC_APP->boot();

// Get plugin manager using the alias
$pluginManager = $SHOPOLOGIC_APP->getContainer()->get('plugins');

// Colors for output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

echo "\n{$yellow}=== Testing Plugin System ==={$reset}\n\n";

// Test 1: Check if plugin manifest exists and is valid
echo "1. Plugin Manifest Validation:\n";
$pluginPath = __DIR__ . '/plugins/core-commerce';
$manifestPath = $pluginPath . '/plugin.json';

if (!file_exists($manifestPath)) {
    echo "   Plugin manifest not found... {$red}✗ FAILED{$reset}\n";
    exit(1);
}

$manifest = json_decode(file_get_contents($manifestPath), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "   Invalid plugin manifest JSON... {$red}✗ FAILED{$reset}\n";
    exit(1);
}

echo "   Plugin manifest valid... {$green}✓ PASSED{$reset}\n";
echo "   - Name: {$manifest['name']}\n";
echo "   - Version: {$manifest['version']}\n";

// Test 2: Check if plugin loads
echo "\n2. Plugin Loading:\n";
try {
    // First discover all plugins
    $discovered = $pluginManager->discover();
    if (isset($discovered['core-commerce'])) {
        echo "   Plugin discovered... {$green}✓ PASSED{$reset}\n";
        
        // Load the plugin
        $pluginManager->load('core-commerce', $discovered['core-commerce']);
        echo "   Plugin loaded successfully... {$green}✓ PASSED{$reset}\n";
    } else {
        echo "   Plugin not discovered... {$red}✗ FAILED{$reset}\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   Plugin loading error: {$e->getMessage()}... {$red}✗ FAILED{$reset}\n";
    exit(1);
}

// Test 3: Check if plugin activates
echo "\n3. Plugin Activation:\n";
try {
    $pluginManager->activate('core-commerce');
    echo "   Plugin activated successfully... {$green}✓ PASSED{$reset}\n";
    
    // Boot the plugin to register services
    $pluginManager->boot('core-commerce');
    echo "   Plugin booted successfully... {$green}✓ PASSED{$reset}\n";
} catch (Exception $e) {
    echo "   Plugin activation/boot error: {$e->getMessage()}... {$red}✗ FAILED{$reset}\n";
    exit(1);
}

// Test 4: Check if services are registered
echo "\n4. Service Registration:\n";
$container = $SHOPOLOGIC_APP->getContainer();

$services = [
    'ProductRepositoryInterface' => Shopologic\Plugins\CoreCommerce\Contracts\ProductRepositoryInterface::class,
    'CategoryRepositoryInterface' => Shopologic\Plugins\CoreCommerce\Contracts\CategoryRepositoryInterface::class,
    'CartServiceInterface' => Shopologic\Plugins\CoreCommerce\Contracts\CartServiceInterface::class,
    'OrderServiceInterface' => Shopologic\Plugins\CoreCommerce\Contracts\OrderServiceInterface::class,
    'CustomerServiceInterface' => Shopologic\Plugins\CoreCommerce\Contracts\CustomerServiceInterface::class,
    'ProductService' => Shopologic\Plugins\CoreCommerce\Services\ProductService::class,
    'CategoryService' => Shopologic\Plugins\CoreCommerce\Services\CategoryService::class,
];

$allPassed = true;
foreach ($services as $name => $interface) {
    try {
        $service = $container->get($interface);
        echo "   {$name} registered... {$green}✓ PASSED{$reset}\n";
    } catch (Exception $e) {
        echo "   {$name} not found: {$e->getMessage()}... {$red}✗ FAILED{$reset}\n";
        $allPassed = false;
    }
}

// Test 5: Check if hooks are registered
echo "\n5. Hook Registration:\n";

$expectedHooks = [
    'init' => 'Core initialization hook',
    'product.created' => 'Product creation hook',
    'cart.updated' => 'Cart update hook',
    'order.created' => 'Order creation hook'
];

foreach ($expectedHooks as $hook => $description) {
    $actions = \Shopologic\Core\Plugin\HookSystem::getActions($hook);
    if (!empty($actions['regular']) || !empty($actions['async']) || !empty($actions['conditional'])) {
        echo "   {$description} registered... {$green}✓ PASSED{$reset}\n";
    } else {
        echo "   {$description} missing... {$red}✗ FAILED{$reset}\n";
        $allPassed = false;
    }
}

// Test 6: Test hook execution
echo "\n6. Hook Execution:\n";
$testData = ['test' => true];
$filtered = apply_filters('product.price', 100.00, ['id' => 1]);
if ($filtered === 100.00) {
    echo "   Filter hook execution... {$green}✓ PASSED{$reset}\n";
} else {
    echo "   Filter hook execution... {$red}✗ FAILED{$reset}\n";
    $allPassed = false;
}

// Test 7: Check plugin status
echo "\n7. Plugin Status:\n";
if ($pluginManager->isActivated('core-commerce')) {
    echo "   Plugin is active... {$green}✓ PASSED{$reset}\n";
} else {
    echo "   Plugin is not active... {$red}✗ FAILED{$reset}\n";
    $allPassed = false;
}

// Summary
echo "\n{$yellow}=== Test Summary ==={$reset}\n";
if ($allPassed) {
    echo "{$green}All tests passed!{$reset}\n";
} else {
    echo "{$red}Some tests failed. Please check the output above.{$reset}\n";
    exit(1);
}

echo "\nPlugin system is working correctly! 🎉\n";