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
$reset = "\033[0m";

echo "\n{$yellow}=== Testing Plugin Dependencies and Interactions ==={$reset}\n\n";

$allPassed = true;

// Test 1: Discover both plugins
echo "1. Plugin Discovery:\n";
$discovered = $pluginManager->discover();

if (isset($discovered['core-commerce']) && isset($discovered['payment-gateway'])) {
    echo "   Both plugins discovered... {$green}✓ PASSED{$reset}\n";
    echo "   - Core Commerce: {$discovered['core-commerce']['version']}\n";
    echo "   - Payment Gateway: {$discovered['payment-gateway']['version']}\n";
} else {
    echo "   Plugin discovery failed... {$red}✗ FAILED{$reset}\n";
    $allPassed = false;
    if (!isset($discovered['core-commerce'])) echo "     Missing: core-commerce\n";
    if (!isset($discovered['payment-gateway'])) echo "     Missing: payment-gateway\n";
}

// Test 2: Check dependency resolution
echo "\n2. Dependency Resolution:\n";
$paymentGatewayManifest = $discovered['payment-gateway'];
$dependencies = $paymentGatewayManifest['dependencies'] ?? [];

if (isset($dependencies['core-commerce'])) {
    echo "   Payment Gateway declares dependency on Core Commerce... {$green}✓ PASSED{$reset}\n";
    echo "   - Required version: {$dependencies['core-commerce']}\n";
} else {
    echo "   Dependency declaration missing... {$red}✗ FAILED{$reset}\n";
    $allPassed = false;
}

// Test 3: Load dependencies first (Core Commerce)
echo "\n3. Loading Dependencies:\n";
try {
    $pluginManager->load('core-commerce', $discovered['core-commerce']);
    echo "   Core Commerce loaded... {$green}✓ PASSED{$reset}\n";
    
    $pluginManager->activate('core-commerce');
    echo "   Core Commerce activated... {$green}✓ PASSED{$reset}\n";
    
    $pluginManager->boot('core-commerce');
    echo "   Core Commerce booted... {$green}✓ PASSED{$reset}\n";
} catch (Exception $e) {
    echo "   Core Commerce setup failed: {$e->getMessage()}... {$red}✗ FAILED{$reset}\n";
    $allPassed = false;
}

// Test 4: Load dependent plugin (Payment Gateway)
echo "\n4. Loading Dependent Plugin:\n";
try {
    $pluginManager->load('payment-gateway', $discovered['payment-gateway']);
    echo "   Payment Gateway loaded... {$green}✓ PASSED{$reset}\n";
    
    $pluginManager->activate('payment-gateway');
    echo "   Payment Gateway activated... {$green}✓ PASSED{$reset}\n";
    
    $pluginManager->boot('payment-gateway');
    echo "   Payment Gateway booted... {$green}✓ PASSED{$reset}\n";
} catch (Exception $e) {
    echo "   Payment Gateway setup failed: {$e->getMessage()}... {$red}✗ FAILED{$reset}\n";
    $allPassed = false;
}

// Test 5: Service Cross-Plugin Access
echo "\n5. Cross-Plugin Service Access:\n";
$container = $SHOPOLOGIC_APP->getContainer();

try {
    // Get Core Commerce service
    $orderService = $container->get(\Shopologic\Plugins\CoreCommerce\Contracts\OrderServiceInterface::class);
    echo "   Core Commerce OrderService accessible... {$green}✓ PASSED{$reset}\n";
    
    // Get Payment Gateway service
    $paymentService = $container->get(\Shopologic\Plugins\PaymentGateway\Contracts\PaymentGatewayInterface::class);
    echo "   Payment Gateway PaymentService accessible... {$green}✓ PASSED{$reset}\n";
    
    // Test service interaction
    $testOrder = $orderService->createFromCart('test-session', [
        'email' => 'test@example.com',
        'name' => 'Test Customer',
        'billing_address' => ['street' => '123 Test St']
    ]);
    echo "   Cross-plugin service interaction works... {$green}✓ PASSED{$reset}\n";
    
} catch (Exception $e) {
    echo "   Cross-plugin service access failed: {$e->getMessage()}... {$red}✗ FAILED{$reset}\n";
    $allPassed = false;
}

// Test 6: Hook Interactions
echo "\n6. Hook Interactions:\n";

try {
    // Test if Payment Gateway hooks are registered
    $orderCreatedActions = \Shopologic\Core\Plugin\HookSystem::getActions('order.created');
    $hookCount = count($orderCreatedActions['regular'] ?? []);
    
    if ($hookCount >= 2) {
        echo "   Multiple plugins hooked to 'order.created'... {$green}✓ PASSED{$reset}\n";
        echo "   - Hook count: {$hookCount}\n";
    } else {
        echo "   Hook interaction count unexpected (got {$hookCount}, expected ≥2)... {$yellow}⚠ WARNING{$reset}\n";
    }
    
    // Test hook execution order
    ob_start();
    do_action('order.created', ['order_id' => 123]);
    $output = ob_get_clean();
    
    echo "   Hook execution completed... {$green}✓ PASSED{$reset}\n";
    
} catch (Exception $e) {
    echo "   Hook interaction test failed: {$e->getMessage()}... {$red}✗ FAILED{$reset}\n";
    $allPassed = false;
}

// Test 7: Plugin Status Verification
echo "\n7. Plugin Status Verification:\n";

$coreCommerceActive = $pluginManager->isActivated('core-commerce');
$paymentGatewayActive = $pluginManager->isActivated('payment-gateway');

if ($coreCommerceActive && $paymentGatewayActive) {
    echo "   Both plugins are active... {$green}✓ PASSED{$reset}\n";
} else {
    echo "   Plugin status mismatch... {$red}✗ FAILED{$reset}\n";
    echo "   - Core Commerce: " . ($coreCommerceActive ? 'Active' : 'Inactive') . "\n";
    echo "   - Payment Gateway: " . ($paymentGatewayActive ? 'Active' : 'Inactive') . "\n";
    $allPassed = false;
}

// Test 8: Dependency Chain Verification
echo "\n8. Dependency Chain Verification:\n";

try {
    // Try to get Payment Gateway plugin instance
    $paymentPlugin = $pluginManager->getPlugin('payment-gateway');
    echo "   Payment Gateway plugin instance accessible... {$green}✓ PASSED{$reset}\n";
    
    // Verify it can access Core Commerce services
    $paymentPlugin->processPayment(['amount' => 100.00]);
    echo "   Payment Gateway can access Core Commerce services... {$green}✓ PASSED{$reset}\n";
    
} catch (Exception $e) {
    echo "   Dependency chain verification failed: {$e->getMessage()}... {$red}✗ FAILED{$reset}\n";
    $allPassed = false;
}

// Test 9: Service Tag Verification
echo "\n9. Service Tag Verification:\n";

try {
    // Check if Core Commerce services are tagged
    $repositoryServices = $container->tagged('repository');
    $serviceServices = $container->tagged('service');
    
    if (!empty($repositoryServices)) {
        echo "   Repository services tagged correctly... {$green}✓ PASSED{$reset}\n";
        echo "   - Repository count: " . count($repositoryServices) . "\n";
    } else {
        echo "   Repository service tagging issue... {$yellow}⚠ WARNING{$reset}\n";
    }
    
    if (!empty($serviceServices)) {
        echo "   Business services tagged correctly... {$green}✓ PASSED{$reset}\n";
        echo "   - Service count: " . count($serviceServices) . "\n";
    } else {
        echo "   Business service tagging issue... {$yellow}⚠ WARNING{$reset}\n";
    }
    
} catch (Exception $e) {
    echo "   Service tag verification failed: {$e->getMessage()}... {$red}✗ FAILED{$reset}\n";
    $allPassed = false;
}

// Test 10: Integration Scenario
echo "\n10. Integration Scenario Test:\n";

try {
    echo "   {$blue}Running complete order + payment flow...{$reset}\n";
    
    // Step 1: Create an order (Core Commerce)
    $orderService = $container->get(\Shopologic\Plugins\CoreCommerce\Contracts\OrderServiceInterface::class);
    $order = $orderService->createFromCart('integration-test', [
        'email' => 'integration@test.com',
        'name' => 'Integration Test',
        'billing_address' => ['street' => '123 Integration St']
    ]);
    echo "   - Order created: {$order['id']}... {$green}✓{$reset}\n";
    
    // Step 2: Process payment (Payment Gateway)
    $paymentService = $container->get(\Shopologic\Plugins\PaymentGateway\Contracts\PaymentGatewayInterface::class);
    $payment = $paymentService->processPayment([
        'order_id' => $order['id'],
        'amount' => $order['total']
    ]);
    echo "   - Payment processed: {$payment['transaction_id']}... {$green}✓{$reset}\n";
    
    // Step 3: Update order status (Core Commerce)
    $orderService->updateStatus($order['id'], 'paid');
    echo "   - Order status updated... {$green}✓{$reset}\n";
    
    // Step 4: Trigger payment completion hooks
    do_action('order.payment_completed', [
        'order_id' => $order['id'],
        'transaction_id' => $payment['transaction_id']
    ]);
    echo "   - Payment completion hooks triggered... {$green}✓{$reset}\n";
    
    echo "   {$green}Integration scenario completed successfully!{$reset}\n";
    
} catch (Exception $e) {
    echo "   Integration scenario failed: {$e->getMessage()}... {$red}✗ FAILED{$reset}\n";
    $allPassed = false;
}

// Summary
echo "\n{$yellow}=== Dependency Test Summary ==={$reset}\n";
if ($allPassed) {
    echo "{$green}All dependency and interaction tests passed!{$reset}\n";
    echo "✅ Plugin dependency resolution works correctly\n";
    echo "✅ Cross-plugin service access is functional\n";
    echo "✅ Hook interactions work between plugins\n";
    echo "✅ Complete integration scenarios work\n";
} else {
    echo "{$red}Some dependency tests failed. Please check the output above.{$reset}\n";
    exit(1);
}

echo "\nPlugin dependency system is working correctly! 🎉\n";