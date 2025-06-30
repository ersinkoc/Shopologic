# feature-flag-manager Hooks Documentation

## Overview

Hooks provided by the feature-flag-manager plugin.

## Actions

### `flag.created`

Description: TODO - Add action description

Example:
```php
add_action('flag.created', function($data) {
    // Your code here
});
```

### `flag.updated`

Description: TODO - Add action description

Example:
```php
add_action('flag.updated', function($data) {
    // Your code here
});
```

### `flag.toggled`

Description: TODO - Add action description

Example:
```php
add_action('flag.toggled', function($data) {
    // Your code here
});
```

### `experiment.started`

Description: TODO - Add action description

Example:
```php
add_action('experiment.started', function($data) {
    // Your code here
});
```

### `experiment.completed`

Description: TODO - Add action description

Example:
```php
add_action('experiment.completed', function($data) {
    // Your code here
});
```

### `rollout.updated`

Description: TODO - Add action description

Example:
```php
add_action('rollout.updated', function($data) {
    // Your code here
});
```

## Filters

### `feature.enabled`

Description: TODO - Add filter description

Example:
```php
add_filter('feature.enabled', function($value) {
    return $value;
});
```

### `experiment.variant`

Description: TODO - Add filter description

Example:
```php
add_filter('experiment.variant', function($value) {
    return $value;
});
```

### `targeting.rules`

Description: TODO - Add filter description

Example:
```php
add_filter('targeting.rules', function($value) {
    return $value;
});
```

### `rollout.percentage`

Description: TODO - Add filter description

Example:
```php
add_filter('rollout.percentage', function($value) {
    return $value;
});
```

### `flag.evaluation`

Description: TODO - Add filter description

Example:
```php
add_filter('flag.evaluation', function($value) {
    return $value;
});
```

