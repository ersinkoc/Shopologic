# database-query-optimizer Hooks Documentation

## Overview

Hooks provided by the database-query-optimizer plugin.

## Actions

### `query.executed`

Description: TODO - Add action description

Example:
```php
add_action('query.executed', function($data) {
    // Your code here
});
```

### `query.optimized`

Description: TODO - Add action description

Example:
```php
add_action('query.optimized', function($data) {
    // Your code here
});
```

### `index.suggested`

Description: TODO - Add action description

Example:
```php
add_action('index.suggested', function($data) {
    // Your code here
});
```

### `index.created`

Description: TODO - Add action description

Example:
```php
add_action('index.created', function($data) {
    // Your code here
});
```

### `slow_query.detected`

Description: TODO - Add action description

Example:
```php
add_action('slow_query.detected', function($data) {
    // Your code here
});
```

### `optimization.completed`

Description: TODO - Add action description

Example:
```php
add_action('optimization.completed', function($data) {
    // Your code here
});
```

### `maintenance.performed`

Description: TODO - Add action description

Example:
```php
add_action('maintenance.performed', function($data) {
    // Your code here
});
```

## Filters

### `database.query`

Description: TODO - Add filter description

Example:
```php
add_filter('database.query', function($value) {
    return $value;
});
```

### `query.execution_plan`

Description: TODO - Add filter description

Example:
```php
add_filter('query.execution_plan', function($value) {
    return $value;
});
```

### `index.recommendations`

Description: TODO - Add filter description

Example:
```php
add_filter('index.recommendations', function($value) {
    return $value;
});
```

### `query.rewrite`

Description: TODO - Add filter description

Example:
```php
add_filter('query.rewrite', function($value) {
    return $value;
});
```

### `cache.strategy`

Description: TODO - Add filter description

Example:
```php
add_filter('cache.strategy', function($value) {
    return $value;
});
```

