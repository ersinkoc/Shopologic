<?php
/**
 * Activate Core E-commerce Plugins
 * 
 * This script activates essential plugins for Shopologic e-commerce platform
 */

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

use Shopologic\Core\Kernel\Application;

try {
    echo "Shopologic Plugin Activation\n";
    echo "============================\n\n";

    // Create application instance
    $app = new Application(__DIR__);
    $app->boot();
    
    // Get plugin manager
    $pluginManager = $app->getContainer()->get('plugins');
    
    // Core e-commerce plugins to activate
    $corePlugins = [
        'smart-search' => 'Enhanced search functionality with AI-powered suggestions',
        'analytics-google' => 'Google Analytics integration for tracking',
        'reviews-ratings' => 'Customer reviews and ratings system',
        'inventory-management' => 'Advanced inventory tracking and management',
        'email-marketing' => 'Email marketing and customer communication',
        'multi-currency' => 'Multi-currency support for international sales',
        'seo-optimizer' => 'SEO optimization for better search rankings',
        'loyalty-rewards' => 'Customer loyalty and rewards program',
    ];
    
    echo "Discovering available plugins...\n";
    $discovered = $pluginManager->discover();
    echo "Found " . count($discovered) . " plugins\n\n";
    
    echo "Loading essential plugins...\n";
    $loaded = 0;
    $activated = 0;
    $errors = [];
    
    foreach ($corePlugins as $pluginName => $description) {
        echo "Processing plugin: {$pluginName}\n";
        echo "  Description: {$description}\n";
        
        try {
            // Check if plugin exists
            if (!isset($discovered[$pluginName])) {
                echo "  ❌ Plugin not found\n\n";
                $errors[] = "{$pluginName}: Plugin not found";
                continue;
            }
            
            // Load plugin
            if (!$pluginManager->isLoaded($pluginName)) {
                $pluginManager->load($pluginName, $discovered[$pluginName]);
                $loaded++;
                echo "  ✅ Loaded\n";
            } else {
                echo "  ℹ️  Already loaded\n";
            }
            
            // Activate plugin
            if (!$pluginManager->isActivated($pluginName)) {
                $pluginManager->activate($pluginName);
                $activated++;
                echo "  ✅ Activated\n";
            } else {
                echo "  ℹ️  Already activated\n";
            }
            
            echo "  ✅ Success\n\n";
            
        } catch (Exception $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n\n";
            $errors[] = "{$pluginName}: " . $e->getMessage();
        }
    }
    
    // Additional useful plugins (activate if available)
    $optionalPlugins = [
        'ai-recommendations' => 'AI-powered product recommendations',
        'live-chat' => 'Live chat support for customers',
        'social-proof-engine' => 'Social proof and urgency features',
        'advanced-analytics-reporting' => 'Advanced analytics and reporting',
        'bundle-builder' => 'Product bundle builder',
        'gift-card-plus' => 'Gift card functionality',
        'smart-pricing' => 'Dynamic pricing optimization',
        'performance-optimizer' => 'Performance optimization tools',
    ];
    
    echo "Loading additional optional plugins...\n";
    
    foreach ($optionalPlugins as $pluginName => $description) {
        if (isset($discovered[$pluginName])) {
            echo "Processing optional plugin: {$pluginName}\n";
            
            try {
                if (!$pluginManager->isLoaded($pluginName)) {
                    $pluginManager->load($pluginName, $discovered[$pluginName]);
                    $loaded++;
                }
                
                if (!$pluginManager->isActivated($pluginName)) {
                    $pluginManager->activate($pluginName);
                    $activated++;
                }
                
                echo "  ✅ {$description}\n";
                
            } catch (Exception $e) {
                echo "  ⚠️  Skipped: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n";
    echo "Plugin Activation Summary\n";
    echo "=========================\n";
    echo "Plugins loaded: {$loaded}\n";
    echo "Plugins activated: {$activated}\n";
    echo "Errors encountered: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }
    
    // Show activated plugins
    echo "\nActivated Plugins:\n";
    $activatedPlugins = $pluginManager->getActivated();
    foreach ($activatedPlugins as $pluginName) {
        echo "  ✅ {$pluginName}\n";
    }
    
    echo "\n✅ Plugin activation complete!\n";
    
    // Test hook system
    echo "\nTesting Hook System:\n";
    echo "===================\n";
    
    // Test action hook
    add_action('test_action', function($message) {
        echo "Action hook working: {$message}\n";
    });
    
    do_action('test_action', 'Hello from Shopologic!');
    
    // Test filter hook
    add_filter('test_filter', function($value) {
        return strtoupper($value);
    });
    
    $filtered = apply_filters('test_filter', 'plugin system ready');
    echo "Filter hook working: {$filtered}\n";
    
    echo "\n🎉 Shopologic plugin system is ready for e-commerce!\n";
    
} catch (Exception $e) {
    echo "\n❌ Fatal error during plugin activation:\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}