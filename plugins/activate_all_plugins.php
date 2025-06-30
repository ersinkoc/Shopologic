<?php

/**
 * Activate All Plugins Script
 * Activates all plugins in the Shopologic system
 */

declare(strict_types=1);

// Bootstrap the application
require_once __DIR__ . '/../vendor/autoload.php';

use Shopologic\Core\Application;
use Shopologic\Core\Plugin\PluginManager;

// Create application instance
$app = new Application(dirname(__DIR__));
$app->bootstrap();

// Get plugin manager
$pluginManager = $app->make(PluginManager::class);

echo "🚀 Shopologic Plugin Activation Tool\n";
echo "====================================\n\n";

// Discover all plugins
$plugins = $pluginManager->discoverPlugins();
echo "📦 Found " . count($plugins) . " plugins\n\n";

$activated = 0;
$failed = 0;
$errors = [];

foreach ($plugins as $pluginName => $pluginInfo) {
    echo "⚡ Activating: $pluginName... ";
    
    try {
        // Check if already active
        if ($pluginManager->isActive($pluginName)) {
            echo "✅ Already active\n";
            $activated++;
            continue;
        }
        
        // Activate the plugin
        $result = $pluginManager->activatePlugin($pluginName);
        
        if ($result) {
            echo "✅ Activated successfully\n";
            $activated++;
        } else {
            echo "❌ Failed to activate\n";
            $failed++;
            $errors[$pluginName] = "Activation returned false";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $failed++;
        $errors[$pluginName] = $e->getMessage();
    }
}

echo "\n📊 ACTIVATION SUMMARY\n";
echo "====================\n";
echo "Total plugins: " . count($plugins) . "\n";
echo "✅ Activated: $activated\n";
echo "❌ Failed: $failed\n";

if (!empty($errors)) {
    echo "\n🚨 ERRORS:\n";
    foreach ($errors as $plugin => $error) {
        echo "- $plugin: $error\n";
    }
}

// Save activation status
$status = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_plugins' => count($plugins),
    'activated' => $activated,
    'failed' => $failed,
    'active_plugins' => array_keys(array_filter($plugins, fn($p) => $pluginManager->isActive($p['name']))),
    'errors' => $errors
];

file_put_contents(__DIR__ . '/PLUGIN_ACTIVATION_STATUS.json', json_encode($status, JSON_PRETTY_PRINT));

echo "\n💾 Activation status saved to PLUGIN_ACTIVATION_STATUS.json\n";

if ($failed === 0) {
    echo "\n🎉 All plugins activated successfully!\n";
} else {
    echo "\n⚠️  Some plugins failed to activate. Check the errors above.\n";
}