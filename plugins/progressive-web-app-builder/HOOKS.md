# progressive-web-app-builder Hooks Documentation

## Overview

Hooks provided by the progressive-web-app-builder plugin.

## Actions

### `pwa.installed`

Description: TODO - Add action description

Example:
```php
add_action('pwa.installed', function($data) {
    // Your code here
});
```

### `pwa.updated`

Description: TODO - Add action description

Example:
```php
add_action('pwa.updated', function($data) {
    // Your code here
});
```

### `notification.sent`

Description: TODO - Add action description

Example:
```php
add_action('notification.sent', function($data) {
    // Your code here
});
```

### `cache.updated`

Description: TODO - Add action description

Example:
```php
add_action('cache.updated', function($data) {
    // Your code here
});
```

### `offline.sync`

Description: TODO - Add action description

Example:
```php
add_action('offline.sync', function($data) {
    // Your code here
});
```

### `app.launched`

Description: TODO - Add action description

Example:
```php
add_action('app.launched', function($data) {
    // Your code here
});
```

## Filters

### `pwa.manifest`

Description: TODO - Add filter description

Example:
```php
add_filter('pwa.manifest', function($value) {
    return $value;
});
```

### `service_worker.config`

Description: TODO - Add filter description

Example:
```php
add_filter('service_worker.config', function($value) {
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

### `notification.payload`

Description: TODO - Add filter description

Example:
```php
add_filter('notification.payload', function($value) {
    return $value;
});
```

### `offline.fallback`

Description: TODO - Add filter description

Example:
```php
add_filter('offline.fallback', function($value) {
    return $value;
});
```

