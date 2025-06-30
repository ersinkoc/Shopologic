# headless-commerce-api Hooks Documentation

## Overview

Hooks provided by the headless-commerce-api plugin.

## Actions

### `api.request`

Description: TODO - Add action description

Example:
```php
add_action('api.request', function($data) {
    // Your code here
});
```

### `api.response`

Description: TODO - Add action description

Example:
```php
add_action('api.response', function($data) {
    // Your code here
});
```

### `webhook.triggered`

Description: TODO - Add action description

Example:
```php
add_action('webhook.triggered', function($data) {
    // Your code here
});
```

### `token.created`

Description: TODO - Add action description

Example:
```php
add_action('token.created', function($data) {
    // Your code here
});
```

### `rate_limit.exceeded`

Description: TODO - Add action description

Example:
```php
add_action('rate_limit.exceeded', function($data) {
    // Your code here
});
```

### `api.error`

Description: TODO - Add action description

Example:
```php
add_action('api.error', function($data) {
    // Your code here
});
```

## Filters

### `api.endpoints`

Description: TODO - Add filter description

Example:
```php
add_filter('api.endpoints', function($value) {
    return $value;
});
```

### `api.response_format`

Description: TODO - Add filter description

Example:
```php
add_filter('api.response_format', function($value) {
    return $value;
});
```

### `api.authentication`

Description: TODO - Add filter description

Example:
```php
add_filter('api.authentication', function($value) {
    return $value;
});
```

### `api.rate_limits`

Description: TODO - Add filter description

Example:
```php
add_filter('api.rate_limits', function($value) {
    return $value;
});
```

### `api.cors_settings`

Description: TODO - Add filter description

Example:
```php
add_filter('api.cors_settings', function($value) {
    return $value;
});
```

