<?php

/**
 * Plugin and Hook System Demo
 * 
 * This demonstrates how plugins can use the WordPress-style hook system
 * to extend functionality without modifying core code.
 */

use Shopologic\Core\Plugin\HookSystem;

// Example 1: Simple action hooks
// ------------------------------

// Register a hook when an order is created
HookSystem::addAction('order_created', function($order) {
    echo "Order #{$order->id} was created\n";
});

// Add another action with higher priority (runs first)
HookSystem::addAction('order_created', function($order) {
    echo "Sending order confirmation email for order #{$order->id}\n";
}, 5); // Priority 5 runs before priority 10

// Trigger the action
$order = (object)['id' => 12345, 'total' => 99.99];
HookSystem::doAction('order_created', $order);


// Example 2: Filter hooks to modify values
// ----------------------------------------

// Add filter to modify product price
HookSystem::addFilter('product_price', function($price, $product) {
    // Apply 10% discount for VIP products
    if ($product->is_vip) {
        return $price * 0.9;
    }
    return $price;
}, 10, 2);

// Add another filter to apply tax
HookSystem::addFilter('product_price', function($price) {
    // Add 8% tax
    return $price * 1.08;
}, 20); // Runs after discount

// Apply filters
$product = (object)['price' => 100, 'is_vip' => true];
$finalPrice = HookSystem::applyFilters('product_price', $product->price, $product);
echo "Final price: $" . number_format($finalPrice, 2) . "\n";


// Example 3: Conditional hooks
// ----------------------------

// Only send SMS for high-value orders
HookSystem::addConditionalAction(
    'order_completed',
    function($order) { return $order->total > 500; }, // Condition
    function($order) { // Action
        echo "Sending VIP SMS notification for order #{$order->id} (${$order->total})\n";
    }
);

// Test with different order values
$smallOrder = (object)['id' => 1, 'total' => 50];
$largeOrder = (object)['id' => 2, 'total' => 750];

HookSystem::doAction('order_completed', $smallOrder); // No SMS
HookSystem::doAction('order_completed', $largeOrder); // SMS sent


// Example 4: Async actions (background processing)
// -----------------------------------------------

HookSystem::addAsyncAction('import_products', function($file) {
    echo "Processing product import from {$file} in background\n";
    // This would be processed asynchronously
});

HookSystem::doAction('import_products', 'products.csv');


// Example 5: Plugin integration example
// ------------------------------------

class EmailMarketingPlugin {
    public function register() {
        // Subscribe to multiple hooks
        HookSystem::addAction('user_registered', [$this, 'addToMailingList']);
        HookSystem::addAction('order_completed', [$this, 'trackPurchase']);
        HookSystem::addFilter('email_footer', [$this, 'addMarketingFooter']);
    }
    
    public function addToMailingList($user) {
        echo "Adding {$user->email} to mailing list\n";
    }
    
    public function trackPurchase($order) {
        echo "Tracking purchase analytics for order #{$order->id}\n";
    }
    
    public function addMarketingFooter($footer) {
        return $footer . "\n\nFollow us on social media!";
    }
}

// Register the plugin
$emailPlugin = new EmailMarketingPlugin();
$emailPlugin->register();

// Test the hooks
$user = (object)['email' => 'user@example.com'];
HookSystem::doAction('user_registered', $user);

$footer = "Thank you for your order!";
$newFooter = HookSystem::applyFilters('email_footer', $footer);
echo "Email footer: {$newFooter}\n";


// Example 6: Hook introspection
// -----------------------------

echo "\nRegistered hooks:\n";
echo "Actions: " . count(HookSystem::getActions()['regular']) . " regular, " . 
     count(HookSystem::getActions()['async']) . " async, " .
     count(HookSystem::getActions()['conditional']) . " conditional\n";

echo "Has 'order_created' action: " . (HookSystem::hasAction('order_created') ? 'Yes' : 'No') . "\n";
echo "Times 'order_created' was called: " . HookSystem::didAction('order_created') . "\n";