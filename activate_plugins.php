<?php

/**
 * Simple Plugin Activation Script
 * Activates plugins by creating an activation file
 */

declare(strict_types=1);

$pluginsDir = __DIR__ . '/plugins';
$storageDir = __DIR__ . '/storage/plugins';

echo "🚀 Shopologic Plugin Activation\n";
echo "===============================\n\n";

// Ensure storage directory exists
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

// Load existing active plugins
$activePluginsFile = $storageDir . '/active_plugins.json';
$activePlugins = [];

if (file_exists($activePluginsFile)) {
    $activePlugins = json_decode(file_get_contents($activePluginsFile), true) ?: [];
}

// Discover all plugins
$plugins = [];
$dirs = scandir($pluginsDir);

foreach ($dirs as $dir) {
    if ($dir === '.' || $dir === '..' || $dir === 'shared') continue;
    
    $pluginPath = $pluginsDir . '/' . $dir;
    $manifestPath = $pluginPath . '/plugin.json';
    
    if (is_dir($pluginPath) && file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        if ($manifest) {
            $plugins[$dir] = [
                'name' => $manifest['name'] ?? $dir,
                'version' => $manifest['version'] ?? '1.0.0',
                'description' => $manifest['description'] ?? '',
                'path' => $pluginPath,
                'active' => in_array($dir, $activePlugins)
            ];
        }
    }
}

echo "📦 Found " . count($plugins) . " plugins\n\n";

// Activate essential plugins first
$essentialPlugins = [
    'core-commerce',
    'payment-stripe',
    'payment-paypal',
    'shipping-fedex',
    'inventory-management',
    'analytics-google',
    'seo-optimizer',
    'email-marketing'
];

$activated = 0;
$alreadyActive = 0;

// Activate essential plugins
echo "⚡ Activating essential plugins...\n";
foreach ($essentialPlugins as $pluginKey) {
    if (isset($plugins[$pluginKey])) {
        if (!$plugins[$pluginKey]['active']) {
            $activePlugins[] = $pluginKey;
            $plugins[$pluginKey]['active'] = true;
            echo "   ✅ Activated: {$plugins[$pluginKey]['name']}\n";
            $activated++;
        } else {
            echo "   ✔️  Already active: {$plugins[$pluginKey]['name']}\n";
            $alreadyActive++;
        }
    }
}

// Activate remaining plugins
echo "\n⚡ Activating remaining plugins...\n";
foreach ($plugins as $pluginKey => $plugin) {
    if (!$plugin['active'] && !in_array($pluginKey, $essentialPlugins)) {
        $activePlugins[] = $pluginKey;
        $plugins[$pluginKey]['active'] = true;
        echo "   ✅ Activated: {$plugin['name']}\n";
        $activated++;
    }
}

// Remove duplicates
$activePlugins = array_unique($activePlugins);

// Save active plugins list
file_put_contents($activePluginsFile, json_encode($activePlugins, JSON_PRETTY_PRINT));

// Create plugin registry
$registry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_plugins' => count($plugins),
    'active_plugins' => count($activePlugins),
    'plugins' => $plugins
];

file_put_contents($storageDir . '/plugins.json', json_encode($registry, JSON_PRETTY_PRINT));

// Summary
echo "\n📊 ACTIVATION SUMMARY\n";
echo "====================\n";
echo "Total plugins: " . count($plugins) . "\n";
echo "Newly activated: $activated\n";
echo "Already active: $alreadyActive\n";
echo "Total active: " . count($activePlugins) . "\n";

echo "\n✅ All plugins are now active!\n";

// Create a simple test page
$testPage = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Shopologic - E-commerce Platform</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        .status { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .plugins { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 30px; }
        .plugin { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; }
        .plugin h3 { margin: 0 0 10px 0; color: #495057; font-size: 16px; }
        .plugin .version { color: #6c757d; font-size: 12px; }
        .active { background: #e7f3ff; border-color: #b8daff; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛒 Shopologic E-commerce Platform</h1>
        
        <div class="status">
            <h2>✅ System Status: Operational</h2>
            <p>The Shopologic e-commerce platform is running successfully!</p>
        </div>
        
        <h2>📦 Active Plugins (<?php echo count($activePlugins); ?>)</h2>
        
        <div class="plugins">
            <?php foreach ($plugins as $key => $plugin): ?>
                <?php if ($plugin['active']): ?>
                <div class="plugin active">
                    <h3><?php echo htmlspecialchars($plugin['name']); ?></h3>
                    <p class="version">v<?php echo htmlspecialchars($plugin['version']); ?></p>
                    <p><?php echo htmlspecialchars(substr($plugin['description'], 0, 100)); ?>...</p>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <a href="/admin.php" class="btn">Access Admin Panel</a>
        <a href="/api.php" class="btn">API Documentation</a>
    </div>
</body>
</html>
HTML;

// Save test page
file_put_contents(__DIR__ . '/public/test.php', $testPage);

echo "\n🌐 Test page created: public/test.php\n";
echo "\n🚀 To start the system:\n";
echo "   php -S localhost:8000 -t public/\n";
echo "\n📋 Then visit:\n";
echo "   http://localhost:8000/test.php - Test page\n";
echo "   http://localhost:8000/ - Main storefront\n";
echo "   http://localhost:8000/admin.php - Admin panel\n";
echo "   http://localhost:8000/api.php - API endpoint\n";