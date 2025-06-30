# ml-model-deployment Hooks Documentation

## Overview

Hooks provided by the ml-model-deployment plugin.

## Actions

### `model.deployed`

Description: TODO - Add action description

Example:
```php
add_action('model.deployed', function($data) {
    // Your code here
});
```

### `model.version_created`

Description: TODO - Add action description

Example:
```php
add_action('model.version_created', function($data) {
    // Your code here
});
```

### `inference.completed`

Description: TODO - Add action description

Example:
```php
add_action('inference.completed', function($data) {
    // Your code here
});
```

### `model.retrained`

Description: TODO - Add action description

Example:
```php
add_action('model.retrained', function($data) {
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

### `alert.triggered`

Description: TODO - Add action description

Example:
```php
add_action('alert.triggered', function($data) {
    // Your code here
});
```

## Filters

### `model.preprocessing`

Description: TODO - Add filter description

Example:
```php
add_filter('model.preprocessing', function($value) {
    return $value;
});
```

### `model.postprocessing`

Description: TODO - Add filter description

Example:
```php
add_filter('model.postprocessing', function($value) {
    return $value;
});
```

### `inference.input`

Description: TODO - Add filter description

Example:
```php
add_filter('inference.input', function($value) {
    return $value;
});
```

### `inference.output`

Description: TODO - Add filter description

Example:
```php
add_filter('inference.output', function($value) {
    return $value;
});
```

### `model.selection`

Description: TODO - Add filter description

Example:
```php
add_filter('model.selection', function($value) {
    return $value;
});
```

