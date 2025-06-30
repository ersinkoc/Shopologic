# Fraud Detection System Hooks Documentation

## Overview

Hooks provided by the Fraud Detection System plugin.

## Actions

### `checkout.before_payment`

Description: TODO - Add action description

Example:
```php
add_action('checkout.before_payment', function($data) {
    // Your code here
});
```

### `order.before_create`

Description: TODO - Add action description

Example:
```php
add_action('order.before_create', function($data) {
    // Your code here
});
```

### `customer.login_attempt`

Description: TODO - Add action description

Example:
```php
add_action('customer.login_attempt', function($data) {
    // Your code here
});
```

### `payment.before_process`

Description: TODO - Add action description

Example:
```php
add_action('payment.before_process', function($data) {
    // Your code here
});
```

### `fraud.high_risk_detected`

Description: TODO - Add action description

Example:
```php
add_action('fraud.high_risk_detected', function($data) {
    // Your code here
});
```

### `fraud.transaction_blocked`

Description: TODO - Add action description

Example:
```php
add_action('fraud.transaction_blocked', function($data) {
    // Your code here
});
```

## Filters

### `order.can_proceed`

Description: TODO - Add filter description

Example:
```php
add_filter('order.can_proceed', function($value) {
    return $value;
});
```

### `payment.risk_score`

Description: TODO - Add filter description

Example:
```php
add_filter('payment.risk_score', function($value) {
    return $value;
});
```

### `customer.trust_level`

Description: TODO - Add filter description

Example:
```php
add_filter('customer.trust_level', function($value) {
    return $value;
});
```

### `checkout.validation_rules`

Description: TODO - Add filter description

Example:
```php
add_filter('checkout.validation_rules', function($value) {
    return $value;
});
```

### `api.rate_limits`

Description: TODO - Add filter description

Example:
```php
add_filter('api.rate_limits', function($value) {
    return $value;
});
```

