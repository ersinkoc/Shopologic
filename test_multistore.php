<?php

require_once __DIR__ . '/bootstrap.php';

use Shopologic\Core\MultiStore\Store;
use Shopologic\Core\MultiStore\StoreManager;
use Shopologic\Core\Ecommerce\Models\Product;

echo "Testing Multi-Store Functionality\n";
echo "=================================\n\n";

try {
    // Get store manager
    $storeManager = $container->get(StoreManager::class);
    
    // Create test stores
    echo "1. Creating test stores...\n";
    
    // Main store
    $mainStore = $storeManager->createStore([
        'code' => 'main',
        'name' => 'Main Store',
        'domain' => 'shop.example.com',
        'is_active' => true,
        'is_default' => true,
        'locale' => 'en',
        'currency' => 'USD',
        'timezone' => 'America/New_York',
        'theme' => 'default'
    ]);
    echo "   - Created main store: {$mainStore->name}\n";
    
    // European store
    $euStore = $storeManager->createStore([
        'code' => 'eu',
        'name' => 'European Store',
        'subdomain' => 'eu',
        'is_active' => true,
        'locale' => 'de',
        'currency' => 'EUR',
        'timezone' => 'Europe/Berlin',
        'theme' => 'default'
    ]);
    echo "   - Created EU store: {$euStore->name}\n";
    
    // Wholesale store
    $wholesaleStore = $storeManager->createStore([
        'code' => 'wholesale',
        'name' => 'Wholesale Store',
        'path_prefix' => 'wholesale',
        'is_active' => true,
        'locale' => 'en',
        'currency' => 'USD',
        'timezone' => 'America/New_York',
        'theme' => 'default',
        'config' => [
            'minimum_order' => 500,
            'tax_exempt' => true
        ]
    ]);
    echo "   - Created wholesale store: {$wholesaleStore->name}\n\n";
    
    // Test store detection
    echo "2. Testing store detection...\n";
    
    // Simulate requests
    $requests = [
        ['host' => 'shop.example.com', 'path' => '/'],
        ['host' => 'eu.example.com', 'path' => '/'],
        ['host' => 'example.com', 'path' => '/wholesale/products']
    ];
    
    foreach ($requests as $req) {
        $request = new \Shopologic\Core\Http\Request();
        $request->server['HTTP_HOST'] = $req['host'];
        $request->server['REQUEST_URI'] = $req['path'];
        
        $detectedStore = $storeManager->detectStore($request);
        echo "   - Request to {$req['host']}{$req['path']} => ";
        echo $detectedStore ? "Store: {$detectedStore->name}\n" : "No store detected\n";
    }
    echo "\n";
    
    // Test product sharing
    echo "3. Testing product sharing across stores...\n";
    
    // Create a test product
    $product = new Product([
        'name' => 'Multi-Store Test Product',
        'slug' => 'multi-store-test-product',
        'sku' => 'MST-001',
        'description' => 'This product is shared across multiple stores',
        'price' => 99.99,
        'quantity' => 100,
        'is_active' => true
    ]);
    $product->save();
    echo "   - Created product: {$product->name}\n";
    
    // Share with stores with different pricing
    $product->shareWithStore($mainStore->id, [
        'price' => 99.99,
        'stock' => 50,
        'is_active' => true
    ]);
    echo "   - Shared with main store at $99.99\n";
    
    $product->shareWithStore($euStore->id, [
        'price' => 89.99, // EUR price
        'stock' => 30,
        'is_active' => true
    ]);
    echo "   - Shared with EU store at €89.99\n";
    
    $product->shareWithStore($wholesaleStore->id, [
        'price' => 75.00, // Wholesale price
        'stock' => 20,
        'is_active' => true
    ]);
    echo "   - Shared with wholesale store at $75.00\n\n";
    
    // Test store-specific pricing
    echo "4. Testing store-specific product data...\n";
    foreach ([$mainStore, $euStore, $wholesaleStore] as $store) {
        $storeManager->switchToStore($store->id);
        
        $price = $product->getStorePrice($store->id);
        $stock = $product->getStoreStock($store->id);
        $currency = $store->currency;
        
        echo "   - {$store->name}: {$currency} {$price} (Stock: {$stock})\n";
    }
    echo "\n";
    
    // Test store settings
    echo "5. Testing store settings...\n";
    
    // Set some store-specific settings
    \Shopologic\Core\MultiStore\StoreSettings::setValue($wholesaleStore->id, 'checkout.minimum_order', 500);
    \Shopologic\Core\MultiStore\StoreSettings::setValue($wholesaleStore->id, 'checkout.tax_exempt', true);
    \Shopologic\Core\MultiStore\StoreSettings::setValue($euStore->id, 'shipping.default_country', 'DE');
    
    // Read settings
    $minOrder = \Shopologic\Core\MultiStore\StoreSettings::getValue($wholesaleStore->id, 'checkout.minimum_order');
    $taxExempt = \Shopologic\Core\MultiStore\StoreSettings::getValue($wholesaleStore->id, 'checkout.tax_exempt');
    $defaultCountry = \Shopologic\Core\MultiStore\StoreSettings::getValue($euStore->id, 'shipping.default_country');
    
    echo "   - Wholesale minimum order: \${$minOrder}\n";
    echo "   - Wholesale tax exempt: " . ($taxExempt ? 'Yes' : 'No') . "\n";
    echo "   - EU default country: {$defaultCountry}\n\n";
    
    // Test store isolation
    echo "6. Testing store isolation...\n";
    
    // Switch to main store
    $storeManager->switchToStore($mainStore->id);
    
    // Create an order in main store
    $order = new \Shopologic\Core\Ecommerce\Models\Order([
        'order_number' => 'MAIN-001',
        'customer_email' => 'customer@example.com',
        'customer_name' => 'John Doe',
        'status' => 'pending',
        'subtotal' => 99.99,
        'tax_amount' => 8.99,
        'shipping_amount' => 10.00,
        'grand_total' => 118.98
    ]);
    $order->save();
    echo "   - Created order in main store: {$order->order_number}\n";
    
    // Switch to EU store and check if order is visible
    $storeManager->switchToStore($euStore->id);
    $euOrders = \Shopologic\Core\Ecommerce\Models\Order::all();
    echo "   - Orders visible in EU store: " . count($euOrders) . "\n";
    
    // Switch back to main store
    $storeManager->switchToStore($mainStore->id);
    $mainOrders = \Shopologic\Core\Ecommerce\Models\Order::all();
    echo "   - Orders visible in main store: " . count($mainOrders) . "\n\n";
    
    // Summary
    echo "7. Multi-Store Summary:\n";
    echo "   - Total stores created: " . Store::count() . "\n";
    echo "   - Active stores: " . Store::where('is_active', true)->count() . "\n";
    echo "   - Products shared across stores: " . Product::allStores()->count() . "\n";
    echo "   - Store isolation working: " . ($euOrders->count() === 0 ? 'Yes' : 'No') . "\n";
    
    echo "\nMulti-store functionality test completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}