# edge-computing-gateway Hooks Documentation

## Overview

Hooks provided by the edge-computing-gateway plugin.

## Actions

### `edge.function_deployed`

Description: TODO - Add action description

Example:
```php
add_action('edge.function_deployed', function($data) {
    // Your code here
});
```

### `edge.node_connected`

Description: TODO - Add action description

Example:
```php
add_action('edge.node_connected', function($data) {
    // Your code here
});
```

### `edge.cache_invalidated`

Description: TODO - Add action description

Example:
```php
add_action('edge.cache_invalidated', function($data) {
    // Your code here
});
```

### `edge.request_routed`

Description: TODO - Add action description

Example:
```php
add_action('edge.request_routed', function($data) {
    // Your code here
});
```

### `edge.function_executed`

Description: TODO - Add action description

Example:
```php
add_action('edge.function_executed', function($data) {
    // Your code here
});
```

### `edge.node_health_changed`

Description: TODO - Add action description

Example:
```php
add_action('edge.node_health_changed', function($data) {
    // Your code here
});
```

## Filters

### `edge.route_request`

Description: TODO - Add filter description

Example:
```php
add_filter('edge.route_request', function($value) {
    return $value;
});
```

### `edge.function_code`

Description: TODO - Add filter description

Example:
```php
add_filter('edge.function_code', function($value) {
    return $value;
});
```

### `edge.cache_strategy`

Description: TODO - Add filter description

Example:
```php
add_filter('edge.cache_strategy', function($value) {
    return $value;
});
```

### `edge.node_selection`

Description: TODO - Add filter description

Example:
```php
add_filter('edge.node_selection', function($value) {
    return $value;
});
```

### `edge.response_transform`

Description: TODO - Add filter description

Example:
```php
add_filter('edge.response_transform', function($value) {
    return $value;
});
```

