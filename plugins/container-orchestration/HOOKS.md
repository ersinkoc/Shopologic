# container-orchestration Hooks Documentation

## Overview

Hooks provided by the container-orchestration plugin.

## Actions

### `container.deployed`

Description: TODO - Add action description

Example:
```php
add_action('container.deployed', function($data) {
    // Your code here
});
```

### `container.scaled`

Description: TODO - Add action description

Example:
```php
add_action('container.scaled', function($data) {
    // Your code here
});
```

### `pod.created`

Description: TODO - Add action description

Example:
```php
add_action('pod.created', function($data) {
    // Your code here
});
```

### `service.updated`

Description: TODO - Add action description

Example:
```php
add_action('service.updated', function($data) {
    // Your code here
});
```

### `cluster.health_changed`

Description: TODO - Add action description

Example:
```php
add_action('cluster.health_changed', function($data) {
    // Your code here
});
```

### `deployment.rolled_out`

Description: TODO - Add action description

Example:
```php
add_action('deployment.rolled_out', function($data) {
    // Your code here
});
```

## Filters

### `container.config`

Description: TODO - Add filter description

Example:
```php
add_filter('container.config', function($value) {
    return $value;
});
```

### `deployment.strategy`

Description: TODO - Add filter description

Example:
```php
add_filter('deployment.strategy', function($value) {
    return $value;
});
```

### `service.mesh_policy`

Description: TODO - Add filter description

Example:
```php
add_filter('service.mesh_policy', function($value) {
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

### `health.checks`

Description: TODO - Add filter description

Example:
```php
add_filter('health.checks', function($value) {
    return $value;
});
```

