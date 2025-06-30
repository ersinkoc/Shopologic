# microservices-manager Hooks Documentation

## Overview

Hooks provided by the microservices-manager plugin.

## Actions

### `service.registered`

Description: TODO - Add action description

Example:
```php
add_action('service.registered', function($data) {
    // Your code here
});
```

### `service.deregistered`

Description: TODO - Add action description

Example:
```php
add_action('service.deregistered', function($data) {
    // Your code here
});
```

### `service.health_changed`

Description: TODO - Add action description

Example:
```php
add_action('service.health_changed', function($data) {
    // Your code here
});
```

### `circuit.opened`

Description: TODO - Add action description

Example:
```php
add_action('circuit.opened', function($data) {
    // Your code here
});
```

### `circuit.closed`

Description: TODO - Add action description

Example:
```php
add_action('circuit.closed', function($data) {
    // Your code here
});
```

### `request.routed`

Description: TODO - Add action description

Example:
```php
add_action('request.routed', function($data) {
    // Your code here
});
```

## Filters

### `service.endpoints`

Description: TODO - Add filter description

Example:
```php
add_filter('service.endpoints', function($value) {
    return $value;
});
```

### `routing.rules`

Description: TODO - Add filter description

Example:
```php
add_filter('routing.rules', function($value) {
    return $value;
});
```

### `circuit.thresholds`

Description: TODO - Add filter description

Example:
```php
add_filter('circuit.thresholds', function($value) {
    return $value;
});
```

### `retry.policies`

Description: TODO - Add filter description

Example:
```php
add_filter('retry.policies', function($value) {
    return $value;
});
```

### `trace.sampling`

Description: TODO - Add filter description

Example:
```php
add_filter('trace.sampling', function($value) {
    return $value;
});
```

