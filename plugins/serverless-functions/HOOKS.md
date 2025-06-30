# serverless-functions Hooks Documentation

## Overview

Hooks provided by the serverless-functions plugin.

## Actions

### `function.deployed`

Description: TODO - Add action description

Example:
```php
add_action('function.deployed', function($data) {
    // Your code here
});
```

### `function.invoked`

Description: TODO - Add action description

Example:
```php
add_action('function.invoked', function($data) {
    // Your code here
});
```

### `function.scaled`

Description: TODO - Add action description

Example:
```php
add_action('function.scaled', function($data) {
    // Your code here
});
```

### `trigger.fired`

Description: TODO - Add action description

Example:
```php
add_action('trigger.fired', function($data) {
    // Your code here
});
```

### `cold_start.detected`

Description: TODO - Add action description

Example:
```php
add_action('cold_start.detected', function($data) {
    // Your code here
});
```

### `cost_threshold.exceeded`

Description: TODO - Add action description

Example:
```php
add_action('cost_threshold.exceeded', function($data) {
    // Your code here
});
```

## Filters

### `function.code`

Description: TODO - Add filter description

Example:
```php
add_filter('function.code', function($value) {
    return $value;
});
```

### `function.environment`

Description: TODO - Add filter description

Example:
```php
add_filter('function.environment', function($value) {
    return $value;
});
```

### `trigger.conditions`

Description: TODO - Add filter description

Example:
```php
add_filter('trigger.conditions', function($value) {
    return $value;
});
```

### `scaling.rules`

Description: TODO - Add filter description

Example:
```php
add_filter('scaling.rules', function($value) {
    return $value;
});
```

### `cost.allocation`

Description: TODO - Add filter description

Example:
```php
add_filter('cost.allocation', function($value) {
    return $value;
});
```

