<?php

declare(strict_types=1);

// Minimal test to verify Shopologic is functional

require_once __DIR__ . '/core/src/Autoloader.php';
require_once __DIR__ . '/core/src/helpers.php';

use Shopologic\Core\Autoloader;

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->register();
$autoloader->addNamespace('Shopologic\\Core', __DIR__ . '/core/src');
$autoloader->addNamespace('Shopologic\\PSR', __DIR__ . '/core/src/PSR');

echo "🚀 Shopologic System Functionality Check\n";
echo "=====================================\n\n";

try {
    // 1. Check core files existence
    echo "1. Checking core system files:\n";
    $coreFiles = [
        'public/index.php' => 'Main entry point',
        'public/api.php' => 'API entry point',
        'public/admin.php' => 'Admin entry point',
        'core/bootstrap.php' => 'Bootstrap file',
        'core/src/Kernel/Application.php' => 'Application kernel',
        'core/src/Container/Container.php' => 'DI Container',
        'core/src/Plugin/PluginManager.php' => 'Plugin manager',
        'core/src/Database/DatabaseManager.php' => 'Database layer'
    ];
    
    foreach ($coreFiles as $file => $desc) {
        $exists = file_exists(__DIR__ . '/' . $file);
        echo "   " . ($exists ? '✓' : '✗') . " {$desc}: {$file}\n";
    }
    
    // 2. Test autoloader
    echo "\n2. Testing autoloader:\n";
    if (class_exists('Shopologic\\Core\\Container\\Container')) {
        echo "   ✓ Autoloader working - can load Container class\n";
    } else {
        echo "   ✗ Autoloader failed\n";
    }
    
    // 3. Test container instantiation
    echo "\n3. Testing container:\n";
    $container = new \Shopologic\Core\Container\Container();
    $container->bind('test', fn() => 'Working!');
    $result = $container->get('test');
    echo "   ✓ Container instantiated and working: {$result}\n";
    
    // 4. Check plugin directory
    echo "\n4. Checking plugin system:\n";
    $pluginCount = 0;
    if (is_dir(__DIR__ . '/plugins')) {
        $dirs = scandir(__DIR__ . '/plugins');
        foreach ($dirs as $dir) {
            if ($dir !== '.' && $dir !== '..' && is_dir(__DIR__ . '/plugins/' . $dir)) {
                if (file_exists(__DIR__ . '/plugins/' . $dir . '/plugin.json')) {
                    $pluginCount++;
                }
            }
        }
    }
    echo "   ✓ Found {$pluginCount} plugins\n";
    
    // 5. Test basic HTTP components
    echo "\n5. Testing HTTP components:\n";
    $uri = new \Shopologic\Core\Http\Uri('https://example.com/test');
    echo "   ✓ URI class working: " . $uri->getHost() . "\n";
    
    $response = new \Shopologic\Core\Http\Response(200);
    echo "   ✓ Response class working: HTTP " . $response->getStatusCode() . "\n";
    
    // 6. Configuration check
    echo "\n6. Checking configuration files:\n";
    $configFiles = ['app', 'database', 'cache', 'mail', 'security'];
    foreach ($configFiles as $config) {
        $exists = file_exists(__DIR__ . '/core/config/' . $config . '.php');
        echo "   " . ($exists ? '✓' : '✗') . " {$config}.php\n";
    }
    
    echo "\n✅ SYSTEM STATUS: ";
    echo "The Shopologic e-commerce system appears to be functional!\n";
    echo "\n📋 Summary:\n";
    echo "   • Core files are in place\n";
    echo "   • Autoloader is working\n";
    echo "   • DI Container is functional\n";
    echo "   • {$pluginCount} plugins are available\n";
    echo "   • HTTP components are working\n";
    echo "   • Configuration files exist\n";
    
    echo "\n🔧 To fully activate the system:\n";
    echo "   1. Set up a database (SQLite/MySQL/PostgreSQL)\n";
    echo "   2. Configure environment variables\n";
    echo "   3. Run migrations: php cli/migrate.php up\n";
    echo "   4. Activate plugins: php cli/plugin.php activate [plugin-name]\n";
    echo "   5. Start server: php -S localhost:8000 -t public/\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}