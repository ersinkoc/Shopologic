# Customer Segmentation Engine Hooks Documentation

## Overview

Hooks provided by the Customer Segmentation Engine plugin.

## Actions

### `customer.registered`

Description: TODO - Add action description

Example:
```php
add_action('customer.registered', function($data) {
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

### `customer.login`

Description: TODO - Add action description

Example:
```php
add_action('customer.login', function($data) {
    // Your code here
});
```

### `product.reviewed`

Description: TODO - Add action description

Example:
```php
add_action('product.reviewed', function($data) {
    // Your code here
});
```

### `segment.customer_moved`

Description: TODO - Add action description

Example:
```php
add_action('segment.customer_moved', function($data) {
    // Your code here
});
```

### `segment.at_risk_detected`

Description: TODO - Add action description

Example:
```php
add_action('segment.at_risk_detected', function($data) {
    // Your code here
});
```

### `segment.high_value_identified`

Description: TODO - Add action description

Example:
```php
add_action('segment.high_value_identified', function($data) {
    // Your code here
});
```

## Filters

### `product.recommendations`

Description: TODO - Add filter description

Example:
```php
add_filter('product.recommendations', function($value) {
    return $value;
});
```

### `email.template`

Description: TODO - Add filter description

Example:
```php
add_filter('email.template', function($value) {
    return $value;
});
```

### `promotion.eligibility`

Description: TODO - Add filter description

Example:
```php
add_filter('promotion.eligibility', function($value) {
    return $value;
});
```

### `pricing.discount`

Description: TODO - Add filter description

Example:
```php
add_filter('pricing.discount', function($value) {
    return $value;
});
```

### `analytics.customer_metrics`

Description: TODO - Add filter description

Example:
```php
add_filter('analytics.customer_metrics', function($value) {
    return $value;
});
```

