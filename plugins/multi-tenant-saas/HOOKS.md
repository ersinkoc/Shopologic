# multi-tenant-saas Hooks Documentation

## Overview

Hooks provided by the multi-tenant-saas plugin.

## Actions

### `tenant.created`

Description: TODO - Add action description

Example:
```php
add_action('tenant.created', function($data) {
    // Your code here
});
```

### `tenant.activated`

Description: TODO - Add action description

Example:
```php
add_action('tenant.activated', function($data) {
    // Your code here
});
```

### `tenant.suspended`

Description: TODO - Add action description

Example:
```php
add_action('tenant.suspended', function($data) {
    // Your code here
});
```

### `tenant.deleted`

Description: TODO - Add action description

Example:
```php
add_action('tenant.deleted', function($data) {
    // Your code here
});
```

### `subscription.created`

Description: TODO - Add action description

Example:
```php
add_action('subscription.created', function($data) {
    // Your code here
});
```

### `subscription.upgraded`

Description: TODO - Add action description

Example:
```php
add_action('subscription.upgraded', function($data) {
    // Your code here
});
```

### `subscription.downgraded`

Description: TODO - Add action description

Example:
```php
add_action('subscription.downgraded', function($data) {
    // Your code here
});
```

### `subscription.cancelled`

Description: TODO - Add action description

Example:
```php
add_action('subscription.cancelled', function($data) {
    // Your code here
});
```

### `payment.processed`

Description: TODO - Add action description

Example:
```php
add_action('payment.processed', function($data) {
    // Your code here
});
```

### `quota.exceeded`

Description: TODO - Add action description

Example:
```php
add_action('quota.exceeded', function($data) {
    // Your code here
});
```

### `domain.verified`

Description: TODO - Add action description

Example:
```php
add_action('domain.verified', function($data) {
    // Your code here
});
```

## Filters

### `tenant.access`

Description: TODO - Add filter description

Example:
```php
add_filter('tenant.access', function($value) {
    return $value;
});
```

### `tenant.data`

Description: TODO - Add filter description

Example:
```php
add_filter('tenant.data', function($value) {
    return $value;
});
```

### `subscription.features`

Description: TODO - Add filter description

Example:
```php
add_filter('subscription.features', function($value) {
    return $value;
});
```

### `quota.limits`

Description: TODO - Add filter description

Example:
```php
add_filter('quota.limits', function($value) {
    return $value;
});
```

### `billing.amount`

Description: TODO - Add filter description

Example:
```php
add_filter('billing.amount', function($value) {
    return $value;
});
```

