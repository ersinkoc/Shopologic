# blue-green-deployment Hooks Documentation

## Overview

Hooks provided by the blue-green-deployment plugin.

## Actions

### `deployment.started`

Description: TODO - Add action description

Example:
```php
add_action('deployment.started', function($data) {
    // Your code here
});
```

### `deployment.completed`

Description: TODO - Add action description

Example:
```php
add_action('deployment.completed', function($data) {
    // Your code here
});
```

### `deployment.failed`

Description: TODO - Add action description

Example:
```php
add_action('deployment.failed', function($data) {
    // Your code here
});
```

### `deployment.rolled_back`

Description: TODO - Add action description

Example:
```php
add_action('deployment.rolled_back', function($data) {
    // Your code here
});
```

### `traffic.switched`

Description: TODO - Add action description

Example:
```php
add_action('traffic.switched', function($data) {
    // Your code here
});
```

### `environment.validated`

Description: TODO - Add action description

Example:
```php
add_action('environment.validated', function($data) {
    // Your code here
});
```

## Filters

### `deployment.checks`

Description: TODO - Add filter description

Example:
```php
add_filter('deployment.checks', function($value) {
    return $value;
});
```

### `traffic.rules`

Description: TODO - Add filter description

Example:
```php
add_filter('traffic.rules', function($value) {
    return $value;
});
```

### `rollback.conditions`

Description: TODO - Add filter description

Example:
```php
add_filter('rollback.conditions', function($value) {
    return $value;
});
```

### `environment.config`

Description: TODO - Add filter description

Example:
```php
add_filter('environment.config', function($value) {
    return $value;
});
```

### `deployment.pipeline`

Description: TODO - Add filter description

Example:
```php
add_filter('deployment.pipeline', function($value) {
    return $value;
});
```

