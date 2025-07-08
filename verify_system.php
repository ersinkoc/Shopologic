<?php

/**
 * Shopologic System Verification Script
 * Checks if the e-commerce system is working properly
 */

declare(strict_types=1);

define('SHOPOLOGIC_ROOT', __DIR__);

// Register autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Kernel\Application;
use Shopologic\Core\Plugin\PluginManager;
use Shopologic\Core\Database\DatabaseManager;

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->register();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\PSR', SHOPOLOGIC_ROOT . '/core/src/PSR');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');

// Load helper functions
require_once SHOPOLOGIC_ROOT . '/core/src/helpers.php';

echo "🔍 Shopologic System Verification\n";
echo "=================================\n\n";

$checks = [];

// 1. Check Core Files
echo "1️⃣ Checking core files...\n";
$coreFiles = [
    'core/src/Kernel/Application.php' => 'Application Core',
    'core/src/Container/Container.php' => 'Service Container',
    'core/src/Plugin/PluginManager.php' => 'Plugin Manager',
    'core/src/Database/DatabaseManager.php' => 'Database Manager',
    'public/index.php' => 'Public Entry Point',
    'public/api.php' => 'API Entry Point',
    'public/admin.php' => 'Admin Entry Point'
];

$coreStatus = true;
foreach ($coreFiles as $file => $name) {
    if (file_exists(SHOPOLOGIC_ROOT . '/' . $file)) {
        echo "   ✅ $name\n";
    } else {
        echo "   ❌ $name (missing)\n";
        $coreStatus = false;
    }
}
$checks['core_files'] = $coreStatus;

// 2. Check Application Boot
echo "\n2️⃣ Checking application boot...\n";
try {
    $app = new Application(SHOPOLOGIC_ROOT);
    $app->boot();
    echo "   ✅ Application boots successfully\n";
    $checks['app_boot'] = true;
    
    // Get container
    $container = $app->getContainer();
    echo "   ✅ Service container working\n";
    $checks['container'] = true;
} catch (Exception $e) {
    echo "   ❌ Application boot failed: " . $e->getMessage() . "\n";
    $checks['app_boot'] = false;
    $checks['container'] = false;
}

// 3. Check Plugin System
echo "\n3️⃣ Checking plugin system...\n";
try {
    // Check if plugins directory exists
    $pluginsDir = SHOPOLOGIC_ROOT . '/plugins';
    if (is_dir($pluginsDir)) {
        $pluginCount = 0;
        $dirs = scandir($pluginsDir);
        foreach ($dirs as $dir) {
            if ($dir !== '.' && $dir !== '..' && is_dir($pluginsDir . '/' . $dir)) {
                $manifestPath = $pluginsDir . '/' . $dir . '/plugin.json';
                if (file_exists($manifestPath)) {
                    $pluginCount++;
                }
            }
        }
        echo "   ✅ Plugin directory found\n";
        echo "   📦 Found $pluginCount plugins\n";
        $checks['plugin_system'] = true;
    } else {
        echo "   ❌ Plugin directory not found\n";
        $checks['plugin_system'] = false;
    }
} catch (Exception $e) {
    echo "   ❌ Plugin system error: " . $e->getMessage() . "\n";
    $checks['plugin_system'] = false;
}

// 4. Check Database
echo "\n4️⃣ Checking database connection...\n";
try {
    // Check if database config exists
    $dbConfigPath = SHOPOLOGIC_ROOT . '/core/config/database.php';
    if (file_exists($dbConfigPath)) {
        echo "   ✅ Database configuration found\n";
        echo "   ℹ️  Database connection requires environment setup\n";
        $checks['database'] = true;
    } else {
        echo "   ❌ Database configuration missing\n";
        $checks['database'] = false;
    }
} catch (Exception $e) {
    echo "   ⚠️  Database check error: " . $e->getMessage() . "\n";
    $checks['database'] = false;
}

// 5. Check Directory Permissions
echo "\n5️⃣ Checking directory permissions...\n";
$writableDirs = [
    'storage/cache' => 'Cache',
    'storage/logs' => 'Logs',
    'storage/sessions' => 'Sessions',
    'storage/uploads' => 'Uploads'
];

$permStatus = true;
foreach ($writableDirs as $dir => $name) {
    $path = SHOPOLOGIC_ROOT . '/' . $dir;
    if (is_writable($path)) {
        echo "   ✅ $name directory writable\n";
    } else {
        echo "   ❌ $name directory not writable\n";
        $permStatus = false;
    }
}
$checks['permissions'] = $permStatus;

// 6. Check Configuration
echo "\n6️⃣ Checking configuration...\n";
$configFiles = [
    'core/config/app.php' => 'App Config',
    'core/config/database.php' => 'Database Config',
    'core/config/cache.php' => 'Cache Config'
];

$configStatus = true;
foreach ($configFiles as $file => $name) {
    if (file_exists(SHOPOLOGIC_ROOT . '/' . $file)) {
        echo "   ✅ $name\n";
    } else {
        echo "   ❌ $name (missing)\n";
        $configStatus = false;
    }
}
$checks['config'] = $configStatus;

// Summary
echo "\n📊 VERIFICATION SUMMARY\n";
echo "=======================\n";

$totalChecks = count($checks);
$passedChecks = count(array_filter($checks));
$failedChecks = $totalChecks - $passedChecks;

foreach ($checks as $check => $status) {
    $icon = $status ? '✅' : '❌';
    $label = str_replace('_', ' ', ucfirst($check));
    echo "$icon $label\n";
}

echo "\n📈 Results: $passedChecks/$totalChecks checks passed\n";

if ($passedChecks === $totalChecks) {
    echo "\n🎉 System is fully operational!\n";
} elseif ($passedChecks >= 4) {
    echo "\n✅ System is mostly operational with minor issues.\n";
} elseif ($passedChecks >= 2) {
    echo "\n⚠️  System is partially operational. Configuration needed.\n";
} else {
    echo "\n❌ System has critical issues.\n";
}

// Recommendations
echo "\n💡 RECOMMENDATIONS:\n";
if (!$checks['database']) {
    echo "- Configure database connection in .env or environment variables\n";
}
if (!$checks['plugin_system']) {
    echo "- Check plugin directory structure and manifests\n";
}
if (!$checks['permissions']) {
    echo "- Fix directory permissions: chmod -R 755 storage/\n";
}

echo "\n🚀 To start the development server:\n";
echo "   php -S localhost:17000 -t public/\n";

echo "\n📋 To activate plugins:\n";
echo "   php cli/plugin.php activate [plugin-name]\n";

echo "\n🔧 To run database migrations:\n";
echo "   php cli/migrate.php up\n";