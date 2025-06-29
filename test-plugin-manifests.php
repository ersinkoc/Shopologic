<?php
/**
 * Simple test to validate plugin manifest files
 */

echo "\n\033[1m=== Shopologic Plugin Manifest Validation ===\033[0m\n\n";

$pluginDir = __DIR__ . '/plugins';
$plugins = [];

// Discover plugins
$directories = scandir($pluginDir);
foreach ($directories as $dir) {
    if ($dir === '.' || $dir === '..') continue;
    
    $manifestFile = $pluginDir . '/' . $dir . '/plugin.json';
    if (file_exists($manifestFile)) {
        $content = file_get_contents($manifestFile);
        $manifest = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            $plugins[$dir] = $manifest;
        } else {
            echo "\033[31m✗\033[0m Failed to parse {$dir}/plugin.json: " . json_last_error_msg() . "\n";
        }
    }
}

echo "Found " . count($plugins) . " plugins\n\n";

// Validate each plugin
foreach ($plugins as $dir => $manifest) {
    echo "\033[1m{$dir}\033[0m\n";
    
    // Required fields
    $required = ['name', 'version', 'description', 'author', 'requirements', 'config'];
    $valid = true;
    
    foreach ($required as $field) {
        if (isset($manifest[$field])) {
            echo "  \033[32m✓\033[0m {$field}\n";
        } else {
            echo "  \033[31m✗\033[0m {$field} (missing)\n";
            $valid = false;
        }
    }
    
    // Additional info
    if (isset($manifest['api_endpoints'])) {
        echo "  ℹ️  " . count($manifest['api_endpoints']) . " API endpoints\n";
    }
    
    if (isset($manifest['hooks'])) {
        echo "  ℹ️  " . count($manifest['hooks']) . " hooks\n";
    }
    
    if (isset($manifest['config_schema'])) {
        echo "  ℹ️  " . count($manifest['config_schema']) . " config options\n";
    }
    
    if (isset($manifest['database_tables'])) {
        echo "  ℹ️  " . count($manifest['database_tables']) . " database tables\n";
    }
    
    echo "  " . ($valid ? "\033[32m✓ Valid\033[0m" : "\033[31m✗ Invalid\033[0m") . "\n\n";
}

// Summary
echo "\033[1m=== Summary ===\033[0m\n";
echo "Total plugins: " . count($plugins) . "\n";

// Feature count
$features = [
    'Payment Gateways' => 0,
    'Shipping' => 0,
    'Marketing' => 0,
    'Customer Experience' => 0,
    'Operations' => 0
];

foreach ($plugins as $dir => $manifest) {
    if (strpos($dir, 'payment-') === 0) $features['Payment Gateways']++;
    elseif (strpos($dir, 'shipping-') === 0) $features['Shipping']++;
    elseif (in_array($dir, ['analytics-google', 'email-marketing', 'seo-optimizer'])) $features['Marketing']++;
    elseif (in_array($dir, ['reviews-ratings', 'live-chat', 'multi-currency', 'loyalty-rewards'])) $features['Customer Experience']++;
    elseif (in_array($dir, ['inventory-management', 'core-commerce'])) $features['Operations']++;
}

echo "\nPlugin Categories:\n";
foreach ($features as $category => $count) {
    echo "  - {$category}: {$count}\n";
}

// Check for specific plugins
echo "\n\033[1mCore Plugins Status:\033[0m\n";
$corePlugins = [
    'core-commerce' => 'Core Commerce',
    'payment-stripe' => 'Stripe Payments',
    'payment-paypal' => 'PayPal Payments',
    'shipping-fedex' => 'FedEx Shipping',
    'analytics-google' => 'Google Analytics',
    'reviews-ratings' => 'Reviews & Ratings',
    'seo-optimizer' => 'SEO Optimizer',
    'live-chat' => 'Live Chat',
    'multi-currency' => 'Multi-Currency',
    'email-marketing' => 'Email Marketing',
    'loyalty-rewards' => 'Loyalty Program',
    'inventory-management' => 'Inventory Management'
];

foreach ($corePlugins as $key => $name) {
    $status = isset($plugins[$key]) ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
    echo "  {$status} {$name}\n";
}

echo "\n";