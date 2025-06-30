# Smart Pricing Intelligence Hooks Documentation

## Overview

Hooks provided by the Smart Pricing Intelligence plugin.

## Actions

### `product.before_save`

Description: TODO - Add action description

Example:
```php
add_action('product.before_save', function($data) {
    // Your code here
});
```

### `competitor.price_change`

Description: TODO - Add action description

Example:
```php
add_action('competitor.price_change', function($data) {
    // Your code here
});
```

### `inventory.stock_level_change`

Description: TODO - Add action description

Example:
```php
add_action('inventory.stock_level_change', function($data) {
    // Your code here
});
```

### `order.completed`

Description: TODO - Add action description

Example:
```php
add_action('order.completed', function($data) {
    // Your code here
});
```

### `pricing.rule_triggered`

Description: TODO - Add action description

Example:
```php
add_action('pricing.rule_triggered', function($data) {
    // Your code here
});
```

### `pricing.margin_alert`

Description: TODO - Add action description

Example:
```php
add_action('pricing.margin_alert', function($data) {
    // Your code here
});
```

## Filters

### `product.price`

Description: TODO - Add filter description

Example:
```php
add_filter('product.price', function($value) {
    return $value;
});
```

### `product.sale_price`

Description: TODO - Add filter description

Example:
```php
add_filter('product.sale_price', function($value) {
    return $value;
});
```

### `cart.discount`

Description: TODO - Add filter description

Example:
```php
add_filter('cart.discount', function($value) {
    return $value;
});
```

### `pricing.rules`

Description: TODO - Add filter description

Example:
```php
add_filter('pricing.rules', function($value) {
    return $value;
});
```

### `analytics.pricing_metrics`

Description: TODO - Add filter description

Example:
```php
add_filter('analytics.pricing_metrics', function($value) {
    return $value;
});
```

### `catalog.price_display`

Description: TODO - Add filter description

Example:
```php
add_filter('catalog.price_display', function($value) {
    return $value;
});
```

