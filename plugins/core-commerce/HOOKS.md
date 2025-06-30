# Core Commerce Hooks Documentation

## Overview

The Core Commerce plugin provides extensive hooks (actions and filters) that allow other plugins and themes to extend and customize e-commerce functionality.

## Actions

Actions allow you to execute custom code at specific points in the plugin's execution.

### System Initialization

#### `init`
Fired when the commerce system initializes.

```php
add_action('init', function() {
    // Your initialization code
}, 5);
```

#### `system.ready`
Fired when all commerce components are loaded and ready.

```php
add_action('system.ready', function() {
    // System is fully initialized
}, 10);
```

### Product Actions

#### `product.before_save`
Fired before a product is saved to the database.

```php
add_action('product.before_save', function($product) {
    // Validate or modify product before saving
    if (empty($product->sku)) {
        $product->sku = generate_sku($product);
    }
}, 10);
```

#### `product.after_save`
Fired after a product is successfully saved.

```php
add_action('product.after_save', function($product) {
    // Clear caches, update search index, etc.
    clear_product_cache($product->id);
}, 10);
```

#### `product.viewed`
Fired when a product page is viewed.

```php
add_action('product.viewed', function($product, $user) {
    // Track product view for analytics
    track_product_view($product->id, $user->id);
}, 10);
```

#### `product.created`
Fired when a new product is created.

```php
add_action('product.created', function($product) {
    // Send notification about new product
    notify_subscribers('new_product', $product);
}, 10);
```

#### `product.updated`
Fired when a product is updated.

```php
add_action('product.updated', function($product, $oldProduct) {
    // Log price changes
    if ($product->price !== $oldProduct->price) {
        log_price_change($product->id, $oldProduct->price, $product->price);
    }
}, 10);
```

#### `product.deleted`
Fired when a product is deleted.

```php
add_action('product.deleted', function($productId) {
    // Clean up related data
    delete_product_images($productId);
}, 10);
```

### Cart Actions

#### `cart.item_added`
Fired when an item is added to the cart.

```php
add_action('cart.item_added', function($cartItem, $cart) {
    // Track cart addition for analytics
    track_add_to_cart($cartItem->product_id, $cartItem->quantity);
}, 10);
```

#### `cart.item_removed`
Fired when an item is removed from the cart.

```php
add_action('cart.item_removed', function($cartItem, $cart) {
    // Update recommendations
    update_cart_recommendations($cart);
}, 10);
```

#### `cart.updated`
Fired when the cart is updated (quantity changes, etc.).

```php
add_action('cart.updated', function($cart) {
    // Recalculate shipping rates
    recalculate_shipping($cart);
}, 10);
```

### Order Actions

#### `order.placed`
Fired when a new order is placed.

```php
add_action('order.placed', function($order) {
    // Send order confirmation email
    send_order_confirmation($order);
    
    // Update inventory
    update_inventory_for_order($order);
}, 5);
```

#### `order.status_changed`
Fired when an order status changes.

```php
add_action('order.status_changed', function($order, $oldStatus, $newStatus) {
    // Handle status-specific logic
    if ($newStatus === 'shipped') {
        send_shipping_notification($order);
    }
}, 10);
```

#### `order.created`
Fired after an order is created and saved.

```php
add_action('order.created', function($order) {
    // Generate invoice
    generate_invoice($order);
}, 10);
```

#### `order.updated`
Fired when an order is updated.

```php
add_action('order.updated', function($order) {
    // Update order analytics
    update_order_analytics($order);
}, 10);
```

#### `order.completed`
Fired when an order is marked as completed.

```php
add_action('order.completed', function($order) {
    // Award loyalty points
    award_loyalty_points($order);
}, 10);
```

### Customer Actions

#### `customer.login`
Fired when a customer logs in.

```php
add_action('customer.login', function($customer) {
    // Update last login time
    $customer->last_login = now();
    $customer->save();
    
    // Load personalized content
    load_customer_preferences($customer);
}, 10);
```

#### `customer.behavior_tracked`
Fired when customer behavior is tracked.

```php
add_action('customer.behavior_tracked', function($customer, $behavior) {
    // Update customer segment
    update_customer_segment($customer, $behavior);
}, 10);
```

### Performance Actions

#### `page.load`
Fired on page load for performance optimization.

```php
add_action('page.load', function($page) {
    // Preload critical resources
    preload_critical_resources($page);
}, 5);
```

#### `cache.miss`
Fired when a cache miss occurs.

```php
add_action('cache.miss', function($key) {
    // Preload related data
    preload_related_cache_data($key);
}, 10);
```

### Security Actions

#### `request.before`
Fired before processing any request.

```php
add_action('request.before', function($request) {
    // Validate request security
    validate_request_security($request);
}, 5);
```

### Template Actions

#### `template.product.after_title`
Fired after the product title in templates.

```php
add_action('template.product.after_title', function($product) {
    // Display custom badge
    if ($product->is_new) {
        echo '<span class="badge-new">New!</span>';
    }
}, 10);
```

#### `template.cart.after_items`
Fired after cart items in templates.

```php
add_action('template.cart.after_items', function($cart) {
    // Display cross-sell products
    display_cross_sell_products($cart);
}, 10);
```

#### `template.checkout.before_payment`
Fired before payment section in checkout.

```php
add_action('template.checkout.before_payment', function($order) {
    // Display order summary
    display_order_summary($order);
}, 10);
```

## Filters

Filters allow you to modify data before it's used by the plugin.

### Product Filters

#### `product.price`
Filter product price before display.

```php
add_filter('product.price', function($price, $product) {
    // Apply member discount
    if (is_member()) {
        $price *= 0.9; // 10% discount
    }
    return $price;
}, 10);
```

#### `product.availability`
Filter product availability status.

```php
add_filter('product.availability', function($available, $product) {
    // Check real-time inventory
    return check_warehouse_stock($product->sku) > 0;
}, 10);
```

### Cart Filters

#### `cart.totals`
Filter cart totals calculation.

```php
add_filter('cart.totals', function($totals, $cart) {
    // Apply store credit
    if ($credit = get_user_credit()) {
        $totals['credit'] = -$credit;
        $totals['total'] -= $credit;
    }
    return $totals;
}, 10);
```

#### `cart.shipping_methods`
Filter available shipping methods.

```php
add_filter('cart.shipping_methods', function($methods, $cart) {
    // Add express shipping for premium members
    if (is_premium_member()) {
        $methods['express'] = [
            'label' => 'Express Shipping',
            'cost' => 0,
            'duration' => '1-2 days'
        ];
    }
    return $methods;
}, 10);
```

### Order Filters

#### `order.fulfillment`
Filter order fulfillment options.

```php
add_filter('order.fulfillment', function($options, $order) {
    // Optimize fulfillment based on location
    return optimize_fulfillment_location($options, $order->shipping_address);
}, 10);
```

### Customer Filters

#### `customer.pricing_tier`
Filter customer pricing tier.

```php
add_filter('customer.pricing_tier', function($tier, $customer) {
    // Upgrade tier based on purchase history
    if ($customer->total_purchases > 1000) {
        $tier = 'gold';
    }
    return $tier;
}, 10);
```

#### `user.permissions`
Filter user permissions.

```php
add_filter('user.permissions', function($permissions, $user) {
    // Add wholesale permissions
    if ($user->is_wholesale) {
        $permissions[] = 'view_wholesale_prices';
        $permissions[] = 'bulk_order';
    }
    return $permissions;
}, 10);
```

### Database Filters

#### `database.query`
Filter database queries for optimization.

```php
add_filter('database.query', function($query) {
    // Add query hints for performance
    if (str_contains($query, 'products')) {
        $query .= ' /*+ USE_INDEX(idx_status) */';
    }
    return $query;
}, 10);
```

### Global Filters

#### `commerce.product.price`
Global filter for all product prices.

```php
add_filter('commerce.product.price', function($price, $product) {
    // Apply tax if needed
    if (should_show_tax()) {
        $price *= 1.2; // 20% tax
    }
    return $price;
}, 10);
```

#### `commerce.cart.totals`
Global filter for cart totals.

```php
add_filter('commerce.cart.totals', function($totals, $cart) {
    // Apply global discounts
    $totals = apply_global_discounts($totals, $cart);
    return $totals;
}, 10);
```

## Best Practices

1. **Priority Management**: Use appropriate priorities (1-100) to control execution order
2. **Performance**: Keep hook callbacks lightweight to avoid performance issues
3. **Error Handling**: Always include error handling in your callbacks
4. **Naming**: Follow the naming convention: `entity.event` for consistency
5. **Documentation**: Document your custom hooks for other developers

## Creating Custom Hooks

You can create your own hooks in your plugin:

```php
// Create an action
do_action('my_plugin.custom_event', $data);

// Create a filter
$filtered_value = apply_filters('my_plugin.custom_filter', $value, $context);
```

## Hook Execution Order

1. Security hooks (priority 1-10)
2. System initialization hooks (priority 5-20)
3. Data processing hooks (priority 20-80)
4. Template/display hooks (priority 80-100)

## Debugging Hooks

To debug hooks, you can use:

```php
// List all callbacks for a hook
$callbacks = $hooks->getCallbacks('product.price');

// Check if hook has callbacks
if ($hooks->hasAction('order.created')) {
    // Hook has registered callbacks
}
```