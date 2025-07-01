<?php
/**
 * Integrate Plugin System with E-commerce Platform
 * 
 * This script integrates the plugin system with the existing e-commerce functionality
 */

declare(strict_types=1);

// Define constants
define('SHOPOLOGIC_START', microtime(true));
define('SHOPOLOGIC_ROOT', __DIR__);

// Autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Kernel\Application;
use Shopologic\Core\Plugin\HookSystem;

// Register autoloader
$autoloader = new Autoloader();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\PSR', SHOPOLOGIC_ROOT . '/core/src/PSR');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');
$autoloader->register();

// Load helper functions
if (file_exists(SHOPOLOGIC_ROOT . '/core/src/helpers.php')) {
    require_once SHOPOLOGIC_ROOT . '/core/src/helpers.php';
}

try {
    echo "Shopologic Plugin Integration\n";
    echo "=============================\n\n";

    // Create application instance
    $app = new Application(SHOPOLOGIC_ROOT);
    
    // Store app instance globally for helper functions
    $GLOBALS['SHOPOLOGIC_APP'] = $app;
    
    // Register plugin service provider
    $app->register(\Shopologic\Core\Plugin\PluginServiceProvider::class);
    
    // Boot application
    $app->boot();
    
    // Get services
    $container = $app->getContainer();
    $pluginManager = $container->get('plugins');
    
    echo "Setting up plugin integration hooks...\n\n";
    
    // Product-related hooks
    HookSystem::addAction('product.viewed', function($productId) {
        echo "📊 Product {$productId} viewed - tracking analytics\n";
        // This would integrate with analytics plugins
    });
    
    HookSystem::addAction('product.added_to_cart', function($productId, $quantity) {
        echo "🛒 Product {$productId} added to cart (qty: {$quantity})\n";
        // This would integrate with recommendation and analytics plugins
    });
    
    HookSystem::addFilter('product.price', function($price, $productId) {
        // Allow plugins to modify product pricing
        echo "💰 Price filter applied for product {$productId}: ${$price}\n";
        return $price;
    }, 10, 2);
    
    // Cart-related hooks
    HookSystem::addAction('cart.updated', function($cartData) {
        echo "🛒 Cart updated - items: " . count($cartData['items'] ?? []) . "\n";
        // This would trigger recommendations, abandonment tracking, etc.
    });
    
    HookSystem::addFilter('cart.shipping_methods', function($methods, $cartData) {
        echo "🚚 Calculating shipping methods for cart\n";
        // Allow shipping plugins to add methods
        return $methods;
    }, 10, 2);
    
    // Order-related hooks
    HookSystem::addAction('order.created', function($orderId, $orderData) {
        echo "📦 Order {$orderId} created - processing integrations\n";
        // This would trigger email notifications, inventory updates, analytics
    });
    
    HookSystem::addAction('order.status_changed', function($orderId, $oldStatus, $newStatus) {
        echo "📈 Order {$orderId} status changed: {$oldStatus} → {$newStatus}\n";
        // This would trigger notifications, fulfillment processes
    });
    
    // Search-related hooks
    HookSystem::addAction('search.query', function($query, $results) {
        echo "🔍 Search performed: '{$query}' - {$results} results\n";
        // This would integrate with analytics and AI recommendation plugins
    });
    
    HookSystem::addFilter('search.results', function($products, $query) {
        echo "🎯 Search results filtered for query: '{$query}'\n";
        // Allow plugins to modify search results (boost, reorder, etc.)
        return $products;
    }, 10, 2);
    
    // Customer-related hooks
    HookSystem::addAction('customer.login', function($customerId) {
        echo "👤 Customer {$customerId} logged in\n";
        // This would trigger personalization, recommendations, analytics
    });
    
    HookSystem::addAction('customer.register', function($customerId, $customerData) {
        echo "🆕 New customer {$customerId} registered\n";
        // This would trigger welcome emails, loyalty program enrollment
    });
    
    // Template hooks for theme integration
    HookSystem::addAction('template.header.after', function() {
        echo "<!-- Plugin header integrations -->\n";
        // Plugins can add scripts, analytics code, etc.
    });
    
    HookSystem::addAction('template.product.after_title', function($product) {
        echo "<!-- Product {$product['id']} plugin integrations -->\n";
        // Plugins can add reviews, social proof, badges, etc.
    });
    
    HookSystem::addAction('template.cart.after_items', function($cart) {
        echo "<!-- Cart plugin integrations -->\n";
        // Plugins can add recommendations, upsells, shipping calculators
    });
    
    HookSystem::addAction('template.footer.before', function() {
        echo "<!-- Plugin footer integrations -->\n";
        // Plugins can add chat widgets, analytics, etc.
    });
    
    echo "Core integration hooks registered ✅\n\n";
    
    // Test the integration
    echo "Testing Plugin Integration:\n";
    echo "===========================\n";
    
    // Simulate product view
    echo "Simulating product view...\n";
    HookSystem::doAction('product.viewed', 123);
    
    // Simulate adding to cart
    echo "Simulating add to cart...\n";
    HookSystem::doAction('product.added_to_cart', 123, 2);
    
    // Simulate price calculation
    echo "Simulating price calculation...\n";
    $price = HookSystem::applyFilters('product.price', 99.99, 123);
    echo "Final price: ${$price}\n";
    
    // Simulate search
    echo "Simulating search...\n";
    HookSystem::doAction('search.query', 'laptop', 15);
    $searchResults = ['product1', 'product2', 'product3'];
    $filteredResults = HookSystem::applyFilters('search.results', $searchResults, 'laptop');
    echo "Search results: " . json_encode($filteredResults) . "\n";
    
    // Simulate cart update
    echo "Simulating cart update...\n";
    HookSystem::doAction('cart.updated', [
        'items' => [
            ['product_id' => 123, 'quantity' => 2],
            ['product_id' => 456, 'quantity' => 1]
        ],
        'total' => 249.97
    ]);
    
    // Simulate order creation
    echo "Simulating order creation...\n";
    HookSystem::doAction('order.created', 'ORD-001', [
        'customer_id' => 456,
        'total' => 249.97,
        'items' => [
            ['product_id' => 123, 'quantity' => 2],
            ['product_id' => 456, 'quantity' => 1]
        ]
    ]);
    
    echo "\n";
    
    // Show hook statistics
    echo "Hook System Statistics:\n";
    echo "=======================\n";
    echo "Actions registered: " . count(HookSystem::getActions()) . "\n";
    echo "Filters registered: " . count(HookSystem::getFilters()) . "\n";
    
    // Create plugin configuration for the core system
    echo "\nCreating plugin configuration...\n";
    
    $pluginConfig = [
        'enabled_plugins' => [
            'smart-search',
            'analytics-google',
            'reviews-ratings',
            'inventory-management',
            'email-marketing',
            'multi-currency',
            'seo-optimizer',
            'loyalty-rewards'
        ],
        'plugin_settings' => [
            'analytics-google' => [
                'tracking_id' => 'GA_MEASUREMENT_ID',
                'enhanced_ecommerce' => true,
                'anonymize_ip' => true
            ],
            'multi-currency' => [
                'default_currency' => 'USD',
                'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD'],
                'auto_detect_currency' => true
            ],
            'inventory-management' => [
                'track_stock' => true,
                'low_stock_threshold' => 10,
                'notify_on_low_stock' => true
            ],
            'email-marketing' => [
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'from_email' => 'noreply@shopologic.com',
                'from_name' => 'Shopologic Store'
            ]
        ],
        'hook_priorities' => [
            'product.price' => 10,
            'cart.shipping_methods' => 5,
            'search.results' => 10,
            'template.header.after' => 10
        ]
    ];
    
    // Save configuration
    $configPath = __DIR__ . '/storage/config/plugins.json';
    $configDir = dirname($configPath);
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }
    
    file_put_contents($configPath, json_encode($pluginConfig, JSON_PRETTY_PRINT));
    echo "Plugin configuration saved to: {$configPath}\n";
    
    // Create a simple plugin registry for the frontend
    echo "\nCreating frontend plugin registry...\n";
    
    $frontendRegistry = [
        'analytics' => [
            'google_analytics' => ['enabled' => true, 'id' => 'GA_MEASUREMENT_ID']
        ],
        'features' => [
            'product_reviews' => true,
            'wishlist' => true,
            'live_chat' => false,
            'social_proof' => true,
            'multi_currency' => true
        ],
        'integrations' => [
            'search_autocomplete' => true,
            'recommendation_engine' => true,
            'email_marketing' => true,
            'inventory_tracking' => true
        ]
    ];
    
    $frontendPath = __DIR__ . '/themes/default/assets/js/plugin-registry.js';
    $jsContent = "// Shopologic Plugin Registry\n";
    $jsContent .= "window.ShopologicPlugins = " . json_encode($frontendRegistry, JSON_PRETTY_PRINT) . ";\n";
    
    file_put_contents($frontendPath, $jsContent);
    echo "Frontend plugin registry saved to: {$frontendPath}\n";
    
    echo "\n🎉 Plugin integration setup complete!\n\n";
    
    echo "Next Steps:\n";
    echo "===========\n";
    echo "1. Run 'php activate_core_plugins.php' to activate essential plugins\n";
    echo "2. Configure plugin settings in storage/config/plugins.json\n";
    echo "3. Plugins will automatically integrate with the e-commerce platform\n";
    echo "4. Check the plugin hooks in your controllers and templates\n";
    echo "5. Monitor plugin performance and functionality\n";
    
    echo "\nPlugin System Features Now Available:\n";
    echo "=====================================\n";
    echo "✅ Hook system (actions and filters)\n";
    echo "✅ Plugin discovery and management\n";
    echo "✅ E-commerce integration points\n";
    echo "✅ Template hook system\n";
    echo "✅ Configuration management\n";
    echo "✅ Frontend plugin registry\n";
    echo "✅ Analytics integration ready\n";
    echo "✅ Search enhancement ready\n";
    echo "✅ Customer experience plugins ready\n";
    echo "✅ Performance optimization ready\n";
    
} catch (Exception $e) {
    echo "\n❌ Fatal error during plugin integration:\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}