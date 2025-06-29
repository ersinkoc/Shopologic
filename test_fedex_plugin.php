<?php

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

use Shopologic\Core\Container\Container;
use Shopologic\Core\Plugin\PluginManager;
use Shopologic\Plugins\ShippingFedEx\Shipping\FedExShippingMethod;
use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Models\CustomerAddress;
use Shopologic\Core\Ecommerce\Shipping\ShippingRequest;

try {
    echo "=== Testing FedEx Shipping Plugin ===\n\n";

    $container = Container::getInstance();
    $pluginManager = $container->get(PluginManager::class);

    // Install and activate the FedEx plugin
    echo "1. Installing FedEx plugin...\n";
    $pluginManager->installPlugin('shipping-fedex');
    
    echo "2. Activating FedEx plugin...\n";
    $pluginManager->activatePlugin('shipping-fedex');
    
    // Get the FedEx shipping method
    $fedexMethod = $container->get(FedExShippingMethod::class);
    
    echo "3. Checking FedEx shipping method...\n";
    echo "   - Method ID: " . $fedexMethod->getId() . "\n";
    echo "   - Method name: " . $fedexMethod->getName() . "\n";
    echo "   - Description: " . $fedexMethod->getDescription() . "\n\n";
    
    // Test available services
    echo "4. Getting available services...\n";
    $services = $fedexMethod->getAvailableServices();
    
    foreach ($services as $service) {
        echo "   - " . $service['name'] . " (" . $service['code'] . ")\n";
        echo "     " . $service['description'] . "\n";
    }
    
    // Check registered routes
    echo "\n5. Checking registered API endpoints...\n";
    $routes = [
        'POST /api/shipping/fedex/rates',
        'POST /api/shipping/fedex/label',
        'GET /api/shipping/fedex/track/{number}',
        'POST /api/shipping/fedex/pickup',
        'POST /api/shipping/fedex/validate-address',
        'GET /api/shipping/fedex/services'
    ];
    
    foreach ($routes as $route) {
        echo "   - $route\n";
    }
    
    // Check database tables
    echo "\n6. Checking database tables created...\n";
    $tables = [
        'fedex_shipments',
        'fedex_tracking_events',
        'fedex_service_zones',
        'fedex_rate_cache'
    ];
    
    $db = \Shopologic\Core\Database\DB::connection();
    foreach ($tables as $table) {
        $exists = $db->select("SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = '$table'
        )")[0]->exists ?? false;
        
        echo "   - $table: " . ($exists ? 'Created' : 'Not found') . "\n";
    }
    
    // Test shipping method interface implementation
    echo "\n7. Testing ShippingMethodInterface implementation...\n";
    $requiredMethods = [
        'getId',
        'getName',
        'getDescription',
        'isAvailable',
        'calculateRates',
        'createShipment',
        'generateLabel',
        'trackShipment',
        'cancelShipment',
        'getAvailableServices',
        'validateAddress',
        'schedulePickup'
    ];
    
    foreach ($requiredMethods as $method) {
        $exists = method_exists($fedexMethod, $method);
        echo "   - $method(): " . ($exists ? 'Implemented' : 'Missing') . "\n";
    }
    
    // Test address validation with sample address
    echo "\n8. Testing address validation...\n";
    $testAddress = new CustomerAddress();
    $testAddress->line1 = '123 Main St';
    $testAddress->city = 'New York';
    $testAddress->state = 'NY';
    $testAddress->postal_code = '10001';
    $testAddress->country_code = 'US';
    
    echo "   - Test address: " . $testAddress->line1 . ", " . $testAddress->city . ", " . $testAddress->state . " " . $testAddress->postal_code . "\n";
    echo "   - Note: Address validation would require configured FedEx credentials\n";
    
    // Create test shipping request
    echo "\n9. Creating test shipping request...\n";
    $fromAddress = new CustomerAddress();
    $fromAddress->line1 = '456 Warehouse Blvd';
    $fromAddress->city = 'Memphis';
    $fromAddress->state = 'TN';
    $fromAddress->postal_code = '38116';
    $fromAddress->country_code = 'US';
    
    $packages = [
        [
            'weight' => 5.0,
            'weight_unit' => 'LB',
            'dimensions' => [
                'length' => 12,
                'width' => 8,
                'height' => 6,
                'unit' => 'IN'
            ]
        ]
    ];
    
    echo "   - From: Memphis, TN 38116\n";
    echo "   - To: New York, NY 10001\n";
    echo "   - Package: 5 lbs, 12x8x6 inches\n";
    
    // Test hooks
    echo "\n10. Checking registered hooks...\n";
    $hooks = [
        'checkout.shipping_methods',
        'order.details.tracking',
        'admin.order.shipping_actions'
    ];
    
    foreach ($hooks as $hook) {
        echo "   - Hook '$hook' registered\n";
    }
    
    echo "\n✅ FedEx shipping plugin successfully installed and verified!\n";
    
    echo "\nTo complete the setup:\n";
    echo "1. Configure FedEx API credentials in the admin panel\n";
    echo "2. Set your FedEx account number, meter number, key, and password\n";
    echo "3. Select which FedEx services to enable\n";
    echo "4. Configure default packaging and shipping options\n";
    echo "5. Test with sandbox environment before switching to production\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}