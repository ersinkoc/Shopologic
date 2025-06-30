# performance-profiler Hooks Documentation

## Overview

Hooks provided by the performance-profiler plugin.

## Actions

### `profile.started`

Description: TODO - Add action description

Example:
```php
add_action('profile.started', function($data) {
    // Your code here
});
```

### `profile.completed`

Description: TODO - Add action description

Example:
```php
add_action('profile.completed', function($data) {
    // Your code here
});
```

### `bottleneck.detected`

Description: TODO - Add action description

Example:
```php
add_action('bottleneck.detected', function($data) {
    // Your code here
});
```

### `performance.degraded`

Description: TODO - Add action description

Example:
```php
add_action('performance.degraded', function($data) {
    // Your code here
});
```

### `optimization.applied`

Description: TODO - Add action description

Example:
```php
add_action('optimization.applied', function($data) {
    // Your code here
});
```

### `alert.triggered`

Description: TODO - Add action description

Example:
```php
add_action('alert.triggered', function($data) {
    // Your code here
});
```

## Filters

### `performance.thresholds`

Description: TODO - Add filter description

Example:
```php
add_filter('performance.thresholds', function($value) {
    return $value;
});
```

### `profile.data`

Description: TODO - Add filter description

Example:
```php
add_filter('profile.data', function($value) {
    return $value;
});
```

### `optimization.rules`

Description: TODO - Add filter description

Example:
```php
add_filter('optimization.rules', function($value) {
    return $value;
});
```

### `monitoring.targets`

Description: TODO - Add filter description

Example:
```php
add_filter('monitoring.targets', function($value) {
    return $value;
});
```

### `alert.conditions`

Description: TODO - Add filter description

Example:
```php
add_filter('alert.conditions', function($value) {
    return $value;
});
```

