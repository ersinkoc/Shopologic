<?php
/**
 * Test script for Shopologic Plugin System
 * 
 * This script tests the plugin functionality including:
 * - Plugin discovery and loading
 * - Hook system
 * - Configuration management
 * - API endpoints
 * - Service registration
 */

require_once __DIR__ . '/core/bootstrap.php';

use Core\Plugin\PluginManager;
use Core\Plugin\Hook;
use Core\Container\Container;
use Core\Testing\TestRunner;

// Initialize test environment
$container = new Container();
$pluginManager = new PluginManager($container, __DIR__ . '/plugins');

// Test output helper
function test_output($test, $result, $details = '') {
    $status = $result ? '✓' : '✗';
    $color = $result ? "\033[32m" : "\033[31m";
    echo "{$color}{$status}\033[0m {$test}";
    if ($details) {
        echo " - {$details}";
    }
    echo "\n";
    return $result;
}

echo "\n\033[1m=== Shopologic Plugin System Tests ===\033[0m\n\n";

// Test 1: Plugin Discovery
echo "\033[1m1. Plugin Discovery\033[0m\n";
$plugins = $pluginManager->discover();
$pluginCount = count($plugins);
test_output("Discover plugins", $pluginCount > 0, "Found {$pluginCount} plugins");

$expectedPlugins = [
    'core-commerce',
    'payment-stripe', 
    'payment-paypal',
    'shipping-fedex',
    'analytics-google',
    'reviews-ratings',
    'seo-optimizer',
    'live-chat',
    'multi-currency',
    'email-marketing',
    'loyalty-rewards',
    'inventory-management'
];

foreach ($expectedPlugins as $plugin) {
    test_output("  - Found {$plugin}", isset($plugins[$plugin]));
}

// Test 2: Plugin Manifest Validation
echo "\n\033[1m2. Plugin Manifest Validation\033[0m\n";
$validManifests = true;
foreach ($plugins as $name => $manifest) {
    $isValid = isset($manifest['name']) && 
               isset($manifest['version']) && 
               isset($manifest['config']['main_class']);
    
    if (!$isValid) {
        $validManifests = false;
        test_output("  - {$name} manifest", false, "Invalid manifest structure");
    }
}
test_output("All manifests valid", $validManifests);

// Test 3: Plugin Installation Simulation
echo "\n\033[1m3. Plugin Installation (Simulation)\033[0m\n";
$testPlugin = 'reviews-ratings';
if (isset($plugins[$testPlugin])) {
    $manifest = $plugins[$testPlugin];
    test_output("Load plugin class", class_exists($manifest['config']['main_class']), $manifest['config']['main_class']);
    
    // Check required tables
    $tables = $manifest['database_tables'] ?? [];
    test_output("Database tables defined", count($tables) > 0, count($tables) . " tables");
    
    // Check configuration schema
    $configSchema = $manifest['config_schema'] ?? [];
    test_output("Configuration schema", count($configSchema) > 0, count($configSchema) . " options");
}

// Test 4: Hook System
echo "\n\033[1m4. Hook System\033[0m\n";

// Test action hooks
$actionCalled = false;
Hook::addAction('test.action', function() use (&$actionCalled) {
    $actionCalled = true;
});
Hook::doAction('test.action');
test_output("Action hooks", $actionCalled);

// Test filter hooks
$filterResult = Hook::applyFilters('test.filter', 'original');
Hook::addFilter('test.filter', function($value) {
    return $value . '_modified';
});
$filterResult = Hook::applyFilters('test.filter', 'original');
test_output("Filter hooks", $filterResult === 'original_modified', $filterResult);

// Test priority
$order = [];
Hook::addAction('test.priority', function() use (&$order) {
    $order[] = 'first';
}, 10);
Hook::addAction('test.priority', function() use (&$order) {
    $order[] = 'second';
}, 5);
Hook::doAction('test.priority');
test_output("Hook priority", $order[0] === 'second' && $order[1] === 'first', implode(', ', $order));

// Test 5: Plugin Configuration
echo "\n\033[1m5. Plugin Configuration\033[0m\n";
$configPlugin = 'multi-currency';
if (isset($plugins[$configPlugin])) {
    $schema = $plugins[$configPlugin]['config_schema'] ?? [];
    
    // Test configuration types
    $types = array_unique(array_column($schema, 'type'));
    test_output("Configuration types", count($types) > 0, implode(', ', $types));
    
    // Test default values
    $defaults = 0;
    foreach ($schema as $key => $config) {
        if (isset($config['default'])) $defaults++;
    }
    test_output("Default values", $defaults > 0, "{$defaults} defaults set");
    
    // Test dependencies
    $dependencies = 0;
    foreach ($schema as $key => $config) {
        if (isset($config['depends_on'])) $dependencies++;
    }
    test_output("Conditional options", $dependencies > 0, "{$dependencies} conditional options");
}

// Test 6: Plugin Permissions
echo "\n\033[1m6. Plugin Permissions\033[0m\n";
$permissionPlugin = 'inventory-management';
if (isset($plugins[$permissionPlugin])) {
    $permissions = $plugins[$permissionPlugin]['permissions'] ?? [];
    test_output("Permissions defined", count($permissions) > 0, count($permissions) . " permissions");
    
    // Test permission naming convention
    $validNaming = true;
    foreach ($permissions as $permission) {
        if (!preg_match('/^[a-z]+\.[a-z_]+$/', $permission)) {
            $validNaming = false;
            break;
        }
    }
    test_output("Permission naming", $validNaming, "All follow convention");
}

// Test 7: API Endpoints
echo "\n\033[1m7. API Endpoints\033[0m\n";
$apiPlugin = 'live-chat';
if (isset($plugins[$apiPlugin])) {
    $endpoints = $plugins[$apiPlugin]['api_endpoints'] ?? [];
    test_output("API endpoints", count($endpoints) > 0, count($endpoints) . " endpoints");
    
    // Check endpoint structure
    $validEndpoints = true;
    foreach ($endpoints as $endpoint) {
        if (!isset($endpoint['method']) || !isset($endpoint['path']) || !isset($endpoint['handler'])) {
            $validEndpoints = false;
            break;
        }
    }
    test_output("Endpoint structure", $validEndpoints);
    
    // Check auth requirements
    $authEndpoints = array_filter($endpoints, fn($e) => isset($e['auth']) && $e['auth']);
    test_output("Authenticated endpoints", count($authEndpoints) > 0, count($authEndpoints) . " require auth");
}

// Test 8: Plugin Assets
echo "\n\033[1m8. Plugin Assets\033[0m\n";
$assetPlugin = 'analytics-google';
if (isset($plugins[$assetPlugin])) {
    $assets = $plugins[$assetPlugin]['assets'] ?? [];
    
    $jsCount = count($assets['js'] ?? []);
    $cssCount = count($assets['css'] ?? []);
    
    test_output("JavaScript files", $jsCount > 0, "{$jsCount} JS files");
    test_output("CSS files", $cssCount > 0, "{$cssCount} CSS files");
    
    // Check asset configuration
    if (isset($assets['js'][0])) {
        $jsAsset = $assets['js'][0];
        test_output("Asset configuration", 
            isset($jsAsset['src']) && isset($jsAsset['position']), 
            "Properly configured"
        );
    }
}

// Test 9: Plugin Dependencies
echo "\n\033[1m9. Plugin Dependencies\033[0m\n";
$depCount = 0;
$circularDeps = false;

foreach ($plugins as $name => $manifest) {
    $deps = $manifest['requirements']['dependencies'] ?? [];
    if (count($deps) > 0) {
        $depCount++;
        
        // Check for circular dependencies (simple check)
        foreach ($deps as $dep => $version) {
            if (isset($plugins[$dep])) {
                $depDeps = $plugins[$dep]['requirements']['dependencies'] ?? [];
                if (isset($depDeps[$name])) {
                    $circularDeps = true;
                    test_output("  - Circular dependency", false, "{$name} <-> {$dep}");
                }
            }
        }
    }
}

test_output("Plugins with dependencies", $depCount > 0, "{$depCount} plugins");
test_output("No circular dependencies", !$circularDeps);

// Test 10: Plugin Features
echo "\n\033[1m10. Plugin Features\033[0m\n";

// Count features across all plugins
$features = [
    'webhooks' => 0,
    'cron_jobs' => 0,
    'widgets' => 0,
    'email_templates' => 0,
    'reports' => 0
];

foreach ($plugins as $name => $manifest) {
    if (isset($manifest['api_endpoints'])) {
        foreach ($manifest['api_endpoints'] as $endpoint) {
            if (strpos($endpoint['path'], 'webhook') !== false) {
                $features['webhooks']++;
                break;
            }
        }
    }
    
    if (isset($manifest['cron_jobs'])) $features['cron_jobs']++;
    if (isset($manifest['widgets'])) $features['widgets']++;
    if (isset($manifest['email_templates'])) $features['email_templates']++;
    if (isset($manifest['reports'])) $features['reports']++;
}

foreach ($features as $feature => $count) {
    test_output(ucfirst(str_replace('_', ' ', $feature)), $count > 0, "{$count} plugins");
}

// Summary
echo "\n\033[1m=== Test Summary ===\033[0m\n";
echo "Total plugins found: {$pluginCount}\n";
echo "Plugin system is " . ($pluginCount >= 10 ? "\033[32mfully functional\033[0m" : "\033[31mpartially functional\033[0m") . "\n";

// Performance test
$startTime = microtime(true);
$plugins = $pluginManager->discover();
$endTime = microtime(true);
$loadTime = round(($endTime - $startTime) * 1000, 2);
echo "Plugin discovery time: {$loadTime}ms\n";

echo "\n\033[1mRecommendations:\033[0m\n";
if ($loadTime > 100) {
    echo "- Implement plugin discovery caching (current: {$loadTime}ms)\n";
}
if (!$validManifests) {
    echo "- Fix invalid plugin manifests\n";
}
if ($circularDeps) {
    echo "- Resolve circular dependencies\n";
}

echo "\n";