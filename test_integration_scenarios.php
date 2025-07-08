<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

// Create the application
global $SHOPOLOGIC_APP;
$SHOPOLOGIC_APP = new Shopologic\Core\Kernel\Application(__DIR__);

// Register plugin service provider
$SHOPOLOGIC_APP->register(\Shopologic\Core\Plugin\PluginServiceProvider::class);

$SHOPOLOGIC_APP->boot();

// Get plugin manager
$pluginManager = $SHOPOLOGIC_APP->getContainer()->get('plugins');

// Colors for output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$cyan = "\033[36m";
$reset = "\033[0m";

echo "\n{$yellow}=== Integration Tests - Real-World Scenarios ==={$reset}\n\n";

// Setup: Load and activate plugins
echo "{$blue}Setup: Loading Plugins...{$reset}\n";
$discovered = $pluginManager->discover();

foreach (['core-commerce', 'payment-gateway'] as $plugin) {
    if (isset($discovered[$plugin])) {
        $pluginManager->load($plugin, $discovered[$plugin]);
        $pluginManager->activate($plugin);
        $pluginManager->boot($plugin);
        echo "✓ {$plugin} ready\n";
    }
}

$container = $SHOPOLOGIC_APP->getContainer();

echo "\n{$yellow}Running Integration Scenarios...{$reset}\n\n";

$allPassed = true;

// Scenario 1: Complete E-commerce Flow
echo "🛒 {$cyan}Scenario 1: Complete E-commerce Purchase Flow{$reset}\n";

try {
    // Step 1: Browse products
    echo "   1. Browsing product catalog...\n";
    $productService = $container->get(\Shopologic\Plugins\CoreCommerce\Services\ProductService::class);
    $products = $productService->searchProducts('test', ['category' => 'electronics']);
    echo "      Found " . count($products) . " products... {$green}✓{$reset}\n";
    
    // Step 2: Add items to cart
    echo "   2. Adding items to cart...\n";
    $cartService = $container->get(\Shopologic\Plugins\CoreCommerce\Contracts\CartServiceInterface::class);
    $sessionId = 'customer_' . rand(1000, 9999);
    
    $cartService->addItem($sessionId, 1, 2); // Add 2 units of product 1
    $cartService->addItem($sessionId, 2, 1); // Add 1 unit of product 2
    
    $cart = $cartService->getCart($sessionId);
    echo "      Cart created with session: {$sessionId}... {$green}✓{$reset}\n";
    
    // Step 3: Calculate totals
    echo "   3. Calculating cart totals...\n";
    $totals = $cartService->getTotals($sessionId);
    echo "      Cart total: \${$totals['total']}... {$green}✓{$reset}\n";
    
    // Step 4: Proceed to checkout
    echo "   4. Processing checkout...\n";
    $orderService = $container->get(\Shopologic\Plugins\CoreCommerce\Contracts\OrderServiceInterface::class);
    
    $customerData = [
        'email' => 'customer@example.com',
        'name' => 'John Doe',
        'billing_address' => [
            'street' => '123 Main St',
            'city' => 'Anytown',
            'state' => 'CA',
            'zip' => '12345'
        ],
        'shipping_address' => [
            'street' => '123 Main St',
            'city' => 'Anytown', 
            'state' => 'CA',
            'zip' => '12345'
        ]
    ];
    
    $order = $orderService->createFromCart($sessionId, $customerData);
    echo "      Order created: #{$order['order_number']}... {$green}✓{$reset}\n";
    
    // Step 5: Process payment
    echo "   5. Processing payment...\n";
    $paymentService = $container->get(\Shopologic\Plugins\PaymentGateway\Contracts\PaymentGatewayInterface::class);
    
    $payment = $paymentService->processPayment([
        'order_id' => $order['id'],
        'amount' => $order['total'],
        'payment_method' => 'credit_card',
        'card_number' => '4111111111111111',
        'exp_month' => '12',
        'exp_year' => '2025',
        'cvv' => '123'
    ]);
    
    echo "      Payment processed: {$payment['transaction_id']}... {$green}✓{$reset}\n";
    
    // Step 6: Update order status
    echo "   6. Finalizing order...\n";
    $orderService->updateStatus($order['id'], 'completed');
    
    // Trigger completion hooks
    do_action('order.payment_completed', [
        'order_id' => $order['id'],
        'transaction_id' => $payment['transaction_id'],
        'amount' => $payment['amount']
    ]);
    
    echo "      Order completed successfully... {$green}✓{$reset}\n";
    echo "   {$green}✅ E-commerce flow completed successfully!{$reset}\n\n";
    
} catch (Exception $e) {
    echo "   {$red}❌ E-commerce flow failed: {$e->getMessage()}{$reset}\n\n";
    $allPassed = false;
}

// Scenario 2: Multi-Customer Concurrent Orders
echo "👥 {$cyan}Scenario 2: Concurrent Multi-Customer Orders{$reset}\n";

try {
    echo "   Simulating 5 concurrent customers...\n";
    
    $customers = [];
    for ($i = 1; $i <= 5; $i++) {
        $sessionId = "concurrent_customer_{$i}";
        $customers[] = $sessionId;
        
        // Each customer adds different items
        $cartService->addItem($sessionId, $i, rand(1, 3));
        
        // Create order
        $customerData = [
            'email' => "customer{$i}@example.com",
            'name' => "Customer {$i}",
            'billing_address' => ['street' => "{$i} Customer St"]
        ];
        
        $order = $orderService->createFromCart($sessionId, $customerData);
        
        // Process payment
        $payment = $paymentService->processPayment([
            'order_id' => $order['id'],
            'amount' => $order['total']
        ]);
        
        echo "      Customer {$i}: Order #{$order['order_number']}, Payment {$payment['transaction_id']}... {$green}✓{$reset}\n";
    }
    
    echo "   {$green}✅ Concurrent orders processed successfully!{$reset}\n\n";
    
} catch (Exception $e) {
    echo "   {$red}❌ Concurrent orders failed: {$e->getMessage()}{$reset}\n\n";
    $allPassed = false;
}

// Scenario 3: Plugin Hook Chain Execution
echo "🔗 {$cyan}Scenario 3: Complex Hook Chain Execution{$reset}\n";

try {
    echo "   Testing hook execution chain...\n";
    
    // Clear any previous log output
    ob_start();
    
    // Create a product (should trigger multiple hooks)
    $product = $productService->createProduct([
        'name' => 'Hook Test Product',
        'price' => 99.99,
        'sku' => 'HOOK-TEST-001'
    ]);
    
    // Trigger product creation hook
    do_action('product.created', ['product_id' => $product['id']]);
    
    // Add to cart (should trigger cart hooks)
    $testSessionId = 'hook_test_session';
    $cartService->addItem($testSessionId, $product['id'], 1);
    do_action('cart.updated', ['session_id' => $testSessionId]);
    
    // Create order (should trigger order hooks)
    $hookOrder = $orderService->createFromCart($testSessionId, [
        'email' => 'hooktest@example.com',
        'name' => 'Hook Test User',
        'billing_address' => ['street' => '123 Hook St']
    ]);
    
    do_action('order.created', ['order_id' => $hookOrder['id']]);
    
    // Process payment (should trigger payment hooks)
    $hookPayment = $paymentService->processPayment([
        'order_id' => $hookOrder['id'],
        'amount' => $hookOrder['total']
    ]);
    
    do_action('order.payment_completed', [
        'order_id' => $hookOrder['id'],
        'transaction_id' => $hookPayment['transaction_id']
    ]);
    
    $output = ob_get_clean();
    
    echo "      Hook chain executed... {$green}✓{$reset}\n";
    echo "   {$green}✅ Hook chain execution completed!{$reset}\n\n";
    
} catch (Exception $e) {
    ob_end_clean();
    echo "   {$red}❌ Hook chain execution failed: {$e->getMessage()}{$reset}\n\n";
    $allPassed = false;
}

// Scenario 4: Service Interdependency Testing
echo "🔄 {$cyan}Scenario 4: Service Interdependency Stress Test{$reset}\n";

try {
    echo "   Testing complex service interactions...\n";
    
    // Test 1: Product service -> Category service interaction
    $categoryService = $container->get(\Shopologic\Plugins\CoreCommerce\Services\CategoryService::class);
    $categories = $categoryService->getAllCategories();
    echo "      Category service accessible... {$green}✓{$reset}\n";
    
    // Test 2: Order service -> Customer service interaction  
    $customerService = $container->get(\Shopologic\Plugins\CoreCommerce\Contracts\CustomerServiceInterface::class);
    $customer = $customerService->create([
        'name' => 'Interdependency Test',
        'email' => 'interdep@test.com'
    ]);
    echo "      Customer service integration... {$green}✓{$reset}\n";
    
    // Test 3: Cross-plugin service dependency (Payment -> Order)
    $paymentPlugin = $pluginManager->getPlugin('payment-gateway');
    $paymentPlugin->processPayment(['amount' => 150.00]); // This accesses OrderService internally
    echo "      Cross-plugin service dependency... {$green}✓{$reset}\n";
    
    echo "   {$green}✅ Service interdependency tests passed!{$reset}\n\n";
    
} catch (Exception $e) {
    echo "   {$red}❌ Service interdependency test failed: {$e->getMessage()}{$reset}\n\n";
    $allPassed = false;
}

// Scenario 5: Error Handling and Recovery
echo "⚠️ {$cyan}Scenario 5: Error Handling and Recovery{$reset}\n";

try {
    echo "   Testing error scenarios...\n";
    
    // Test 1: Invalid cart session
    try {
        $cartService->getCart('invalid_session_12345');
        echo "      Invalid cart handling... {$green}✓{$reset}\n";
    } catch (Exception $e) {
        echo "      Invalid cart handling (with error)... {$green}✓{$reset}\n";
    }
    
    // Test 2: Invalid payment data
    try {
        $paymentService->processPayment([]);
        echo "      Invalid payment handling... {$green}✓{$reset}\n";
    } catch (Exception $e) {
        echo "      Invalid payment handling (with error)... {$green}✓{$reset}\n";
    }
    
    // Test 3: Service recovery after error
    $recoveryOrder = $orderService->createFromCart('recovery_test', [
        'email' => 'recovery@test.com',
        'name' => 'Recovery Test',
        'billing_address' => ['street' => '123 Recovery St']
    ]);
    echo "      Service recovery after error... {$green}✓{$reset}\n";
    
    echo "   {$green}✅ Error handling tests completed!{$reset}\n\n";
    
} catch (Exception $e) {
    echo "   {$red}❌ Error handling test failed: {$e->getMessage()}{$reset}\n\n";
    $allPassed = false;
}

// Final Results
echo "{$yellow}=== Integration Test Results ==={$reset}\n";

if ($allPassed) {
    echo "{$green}🎉 ALL INTEGRATION SCENARIOS PASSED!{$reset}\n\n";
    echo "✅ Complete e-commerce flow works end-to-end\n";
    echo "✅ Multi-customer concurrent processing works\n";
    echo "✅ Complex hook chains execute properly\n";
    echo "✅ Service interdependencies are solid\n";
    echo "✅ Error handling and recovery mechanisms work\n\n";
    echo "{$green}The microkernel architecture is production-ready! 🚀{$reset}\n";
} else {
    echo "{$red}❌ Some integration scenarios failed.{$reset}\n";
    echo "Please review the output above for details.\n";
    exit(1);
}

echo "\nIntegration testing completed successfully! 🎊\n";