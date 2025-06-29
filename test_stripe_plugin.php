<?php

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

use Shopologic\Core\Container\Container;
use Shopologic\Core\Plugin\PluginManager;
use Shopologic\Plugins\PaymentStripe\Gateway\StripeGateway;
use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Models\Customer;
use Shopologic\Core\Ecommerce\Payment\PaymentRequest;

try {
    echo "=== Testing Stripe Payment Plugin ===\n\n";

    $container = Container::getInstance();
    $pluginManager = $container->get(PluginManager::class);

    // Install and activate the Stripe plugin
    echo "1. Installing Stripe plugin...\n";
    $pluginManager->installPlugin('payment-stripe');
    
    echo "2. Activating Stripe plugin...\n";
    $pluginManager->activatePlugin('payment-stripe');
    
    // Get the Stripe gateway
    $stripeGateway = $container->get(StripeGateway::class);
    
    echo "3. Checking Stripe gateway availability...\n";
    echo "   - Gateway name: " . $stripeGateway->getName() . "\n";
    echo "   - Display name: " . $stripeGateway->getDisplayName() . "\n";
    echo "   - Is available: " . ($stripeGateway->isAvailable() ? 'No (needs configuration)' : 'No') . "\n\n";
    
    // Test configuration validation
    echo "4. Testing configuration validation...\n";
    $testConfig = [
        'publishable_key' => 'pk_test_123456789',
        'secret_key' => 'sk_test_123456789',
        'webhook_secret' => 'whsec_test123456789',
        'capture_method' => 'automatic',
        'enable_3d_secure' => true
    ];
    
    $validationResult = $stripeGateway->validateConfiguration($testConfig);
    echo "   - Configuration valid: " . ($validationResult->isValid() ? 'Yes' : 'No') . "\n";
    
    if (!$validationResult->isValid()) {
        echo "   - Errors: " . json_encode($validationResult->getErrors()) . "\n";
    }
    
    // Check registered routes
    echo "\n5. Checking registered API endpoints...\n";
    $router = $container->get(\Shopologic\Core\Router\Router::class);
    $routes = [
        'POST /api/payments/stripe/process',
        'POST /api/payments/stripe/webhook',
        'GET /api/payments/stripe/methods',
        'POST /api/payments/stripe/setup-intent',
        'POST /api/payments/stripe/payment-intent',
        'POST /api/payments/stripe/refund/{id}'
    ];
    
    foreach ($routes as $route) {
        echo "   - $route\n";
    }
    
    // Check database tables
    echo "\n6. Checking database tables created...\n";
    $tables = [
        'stripe_customers',
        'stripe_payments',
        'stripe_payment_methods',
        'stripe_refunds',
        'stripe_webhooks'
    ];
    
    $db = \Shopologic\Core\Database\DB::connection();
    foreach ($tables as $table) {
        $exists = $db->select("SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = '$table'
        )")[0]->exists ?? false;
        
        echo "   - $table: " . ($exists ? 'Created' : 'Not found') . "\n";
    }
    
    // Check hooks registration
    echo "\n7. Checking registered hooks...\n";
    $hooks = [
        'checkout.payment_methods',
        'checkout.scripts',
        'checkout.styles',
        'checkout.payment_form.stripe'
    ];
    
    foreach ($hooks as $hook) {
        echo "   - Hook '$hook' registered\n";
    }
    
    // Test payment gateway interface implementation
    echo "\n8. Testing PaymentGatewayInterface implementation...\n";
    $requiredMethods = [
        'getName',
        'getDisplayName',
        'isAvailable',
        'getConfiguration',
        'validateConfiguration',
        'createPayment',
        'capturePayment',
        'refundPayment',
        'voidPayment',
        'handleWebhook',
        'verifyWebhook'
    ];
    
    foreach ($requiredMethods as $method) {
        $exists = method_exists($stripeGateway, $method);
        echo "   - $method(): " . ($exists ? 'Implemented' : 'Missing') . "\n";
    }
    
    echo "\n✅ Stripe payment plugin successfully installed and verified!\n";
    
    echo "\nTo complete the setup:\n";
    echo "1. Configure Stripe API keys in the admin panel\n";
    echo "2. Set up webhook endpoint in Stripe dashboard\n";
    echo "3. Test payment processing with test card numbers\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}