<?php
/**
 * Setup Plugin Hook Integration
 * 
 * This script sets up the hook system for plugin integration
 */

declare(strict_types=1);

// Define constants
define('SHOPOLOGIC_START', microtime(true));
define('SHOPOLOGIC_ROOT', __DIR__);

// Autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
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
    echo "Shopologic Plugin Hook Integration\n";
    echo "==================================\n\n";

    // Initialize the hook system
    $hookSystem = new HookSystem();
    
    echo "Setting up e-commerce plugin integration hooks...\n\n";
    
    // Product-related hooks
    $hookSystem->addAction('product.viewed', function($productId = null) {
        echo "📊 Product {$productId} viewed - tracking analytics\n";
        // This would integrate with analytics plugins
    });
    
    $hookSystem->addAction('product.added_to_cart', function($productId = null, $quantity = 1) {
        echo "🛒 Product {$productId} added to cart (qty: {$quantity})\n";
        // This would integrate with recommendation and analytics plugins
    });
    
    $hookSystem->addFilter('product.price', function($price, $productId = null) {
        // Allow plugins to modify product pricing
        echo "💰 Price filter applied for product {$productId}: ${$price}\n";
        return $price;
    }, 10, 2);
    
    // Cart-related hooks
    $hookSystem->addAction('cart.updated', function($cartData = []) {
        echo "🛒 Cart updated - items: " . count($cartData['items'] ?? []) . "\n";
        // This would trigger recommendations, abandonment tracking, etc.
    });
    
    // Order-related hooks
    $hookSystem->addAction('order.created', function($orderId = null, $orderData = []) {
        echo "📦 Order {$orderId} created - processing integrations\n";
        // This would trigger email notifications, inventory updates, analytics
    });
    
    // Search-related hooks
    $hookSystem->addAction('search.query', function($query = '', $results = 0) {
        echo "🔍 Search performed: '{$query}' - {$results} results\n";
        // This would integrate with analytics and AI recommendation plugins
    });
    
    // Customer-related hooks
    $hookSystem->addAction('customer.login', function($customerId = null) {
        echo "👤 Customer {$customerId} logged in\n";
        // This would trigger personalization, recommendations, analytics
    });
    
    echo "Core integration hooks registered ✅\n\n";
    
    // Test the hook system
    echo "Testing Hook System:\n";
    echo "====================\n";
    
    // Simulate product view
    echo "Simulating product view...\n";
    $hookSystem->doAction('product.viewed', 123);
    
    // Simulate adding to cart
    echo "Simulating add to cart...\n";
    $hookSystem->doAction('product.added_to_cart', 123, 2);
    
    // Simulate price calculation
    echo "Simulating price calculation...\n";
    $price = $hookSystem->applyFilters('product.price', 99.99, 123);
    echo "Final price: ${$price}\n";
    
    // Simulate search
    echo "Simulating search...\n";
    $hookSystem->doAction('search.query', 'laptop', 15);
    
    // Simulate cart update
    echo "Simulating cart update...\n";
    $hookSystem->doAction('cart.updated', [
        'items' => [
            ['product_id' => 123, 'quantity' => 2],
            ['product_id' => 456, 'quantity' => 1]
        ],
        'total' => 249.97
    ]);
    
    // Simulate order creation
    echo "Simulating order creation...\n";
    $hookSystem->doAction('order.created', 'ORD-001', [
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
    echo "Actions registered: " . count($hookSystem->getActions()) . "\n";
    echo "Filters registered: " . count($hookSystem->getFilters()) . "\n";
    
    // Create plugin configuration
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
    
    // Create integration helper functions for controllers
    echo "\nCreating plugin integration helpers...\n";
    
    $helperCode = '<?php
/**
 * Plugin Integration Helper Functions
 * Include this file in your controllers to use plugin hooks
 */

declare(strict_types=1);

use Shopologic\Core\Plugin\HookSystem;

// Global helper functions for plugin hooks
if (!function_exists("do_plugin_action")) {
    function do_plugin_action(string $hook, ...$args): void {
        HookSystem::doAction($hook, ...$args);
    }
}

if (!function_exists("apply_plugin_filter")) {
    function apply_plugin_filter(string $hook, $value, ...$args) {
        return HookSystem::applyFilters($hook, $value, ...$args);
    }
}

// E-commerce specific helper functions
if (!function_exists("track_product_view")) {
    function track_product_view(int $productId): void {
        do_plugin_action("product.viewed", $productId);
    }
}

if (!function_exists("track_cart_action")) {
    function track_cart_action(string $action, array $data): void {
        do_plugin_action("cart.{$action}", $data);
    }
}

if (!function_exists("apply_price_filters")) {
    function apply_price_filters(float $price, int $productId): float {
        return apply_plugin_filter("product.price", $price, $productId);
    }
}

if (!function_exists("track_order_event")) {
    function track_order_event(string $event, string $orderId, array $data = []): void {
        do_plugin_action("order.{$event}", $orderId, $data);
    }
}

if (!function_exists("track_search")) {
    function track_search(string $query, int $resultCount): void {
        do_plugin_action("search.query", $query, $resultCount);
    }
}

if (!function_exists("track_customer_action")) {
    function track_customer_action(string $action, int $customerId, array $data = []): void {
        do_plugin_action("customer.{$action}", $customerId, $data);
    }
}
';
    
    $helperPath = __DIR__ . '/core/src/Plugin/integration_helpers.php';
    file_put_contents($helperPath, $helperCode);
    echo "Plugin integration helpers saved to: {$helperPath}\n";
    
    // Create frontend plugin registry
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
            'multi_currency' => true,
            'search_autocomplete' => true,
            'recommendation_engine' => true
        ],
        'integrations' => [
            'search_autocomplete' => true,
            'recommendation_engine' => true,
            'email_marketing' => true,
            'inventory_tracking' => true,
            'price_optimization' => true,
            'customer_segmentation' => true
        ],
        'hooks' => [
            'product.viewed',
            'product.added_to_cart',
            'cart.updated',
            'order.created',
            'search.query',
            'customer.login'
        ]
    ];
    
    $frontendPath = __DIR__ . '/themes/default/assets/js/plugin-registry.js';
    $jsContent = "// Shopologic Plugin Registry\n";
    $jsContent .= "window.ShopologicPlugins = " . json_encode($frontendRegistry, JSON_PRETTY_PRINT) . ";\n\n";
    $jsContent .= "// Plugin event tracking functions\n";
    $jsContent .= "window.ShopologicPlugins.trackEvent = function(event, data) {\n";
    $jsContent .= "    // Send AJAX request to track plugin events\n";
    $jsContent .= "    console.log('[Shopologic Plugin]', event, data);\n";
    $jsContent .= "};\n\n";
    $jsContent .= "window.ShopologicPlugins.trackProductView = function(productId) {\n";
    $jsContent .= "    this.trackEvent('product.viewed', {product_id: productId});\n";
    $jsContent .= "};\n\n";
    $jsContent .= "window.ShopologicPlugins.trackAddToCart = function(productId, quantity) {\n";
    $jsContent .= "    this.trackEvent('product.added_to_cart', {product_id: productId, quantity: quantity});\n";
    $jsContent .= "};\n";
    
    file_put_contents($frontendPath, $jsContent);
    echo "Frontend plugin registry saved to: {$frontendPath}\n";
    
    echo "\n🎉 Plugin hook integration setup complete!\n\n";
    
    echo "Integration Summary:\n";
    echo "===================\n";
    echo "✅ Hook system initialized\n";
    echo "✅ E-commerce integration hooks registered\n";
    echo "✅ Plugin configuration created\n";
    echo "✅ Integration helper functions created\n";
    echo "✅ Frontend plugin registry created\n";
    echo "✅ Hook system tested successfully\n";
    
    echo "\nHow to Use in Your Controllers:\n";
    echo "===============================\n";
    echo "1. Include the integration helpers:\n";
    echo "   require_once SHOPOLOGIC_ROOT . '/core/src/Plugin/integration_helpers.php';\n\n";
    echo "2. Track events in your code:\n";
    echo "   track_product_view(\$productId);\n";
    echo "   track_cart_action('updated', \$cartData);\n";
    echo "   track_order_event('created', \$orderId, \$orderData);\n\n";
    echo "3. Apply filters:\n";
    echo "   \$finalPrice = apply_price_filters(\$originalPrice, \$productId);\n\n";
    echo "4. Use in templates:\n";
    echo "   do_plugin_action('template.product.after_title', \$product);\n\n";
    
    echo "Next Steps:\n";
    echo "===========\n";
    echo "1. Add plugin hook calls to your controllers\n";
    echo "2. Add template hooks to your theme files\n";
    echo "3. Configure specific plugins in storage/config/plugins.json\n";
    echo "4. Test plugin functionality with real e-commerce actions\n";
    
} catch (Exception $e) {
    echo "\n❌ Fatal error during plugin hook setup:\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}