# event-sourcing Hooks Documentation

## Overview

Hooks provided by the event-sourcing plugin.

## Actions

### `event.stored`

Description: TODO - Add action description

Example:
```php
add_action('event.stored', function($data) {
    // Your code here
});
```

### `projection.updated`

Description: TODO - Add action description

Example:
```php
add_action('projection.updated', function($data) {
    // Your code here
});
```

### `snapshot.created`

Description: TODO - Add action description

Example:
```php
add_action('snapshot.created', function($data) {
    // Your code here
});
```

### `stream.created`

Description: TODO - Add action description

Example:
```php
add_action('stream.created', function($data) {
    // Your code here
});
```

### `replay.started`

Description: TODO - Add action description

Example:
```php
add_action('replay.started', function($data) {
    // Your code here
});
```

### `replay.completed`

Description: TODO - Add action description

Example:
```php
add_action('replay.completed', function($data) {
    // Your code here
});
```

## Filters

### `event.metadata`

Description: TODO - Add filter description

Example:
```php
add_filter('event.metadata', function($value) {
    return $value;
});
```

### `projection.handlers`

Description: TODO - Add filter description

Example:
```php
add_filter('projection.handlers', function($value) {
    return $value;
});
```

### `snapshot.threshold`

Description: TODO - Add filter description

Example:
```php
add_filter('snapshot.threshold', function($value) {
    return $value;
});
```

### `stream.permissions`

Description: TODO - Add filter description

Example:
```php
add_filter('stream.permissions', function($value) {
    return $value;
});
```

### `event.serializer`

Description: TODO - Add filter description

Example:
```php
add_filter('event.serializer', function($value) {
    return $value;
});
```

