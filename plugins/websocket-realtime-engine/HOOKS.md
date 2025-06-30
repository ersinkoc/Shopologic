# websocket-realtime-engine Hooks Documentation

## Overview

Hooks provided by the websocket-realtime-engine plugin.

## Actions

### `websocket.client_connected`

Description: TODO - Add action description

Example:
```php
add_action('websocket.client_connected', function($data) {
    // Your code here
});
```

### `websocket.client_disconnected`

Description: TODO - Add action description

Example:
```php
add_action('websocket.client_disconnected', function($data) {
    // Your code here
});
```

### `websocket.message_received`

Description: TODO - Add action description

Example:
```php
add_action('websocket.message_received', function($data) {
    // Your code here
});
```

### `websocket.channel_joined`

Description: TODO - Add action description

Example:
```php
add_action('websocket.channel_joined', function($data) {
    // Your code here
});
```

### `websocket.channel_left`

Description: TODO - Add action description

Example:
```php
add_action('websocket.channel_left', function($data) {
    // Your code here
});
```

### `websocket.broadcast_sent`

Description: TODO - Add action description

Example:
```php
add_action('websocket.broadcast_sent', function($data) {
    // Your code here
});
```

## Filters

### `websocket.authenticate`

Description: TODO - Add filter description

Example:
```php
add_filter('websocket.authenticate', function($value) {
    return $value;
});
```

### `websocket.authorize_channel`

Description: TODO - Add filter description

Example:
```php
add_filter('websocket.authorize_channel', function($value) {
    return $value;
});
```

### `websocket.message_format`

Description: TODO - Add filter description

Example:
```php
add_filter('websocket.message_format', function($value) {
    return $value;
});
```

### `websocket.broadcast_channels`

Description: TODO - Add filter description

Example:
```php
add_filter('websocket.broadcast_channels', function($value) {
    return $value;
});
```

### `websocket.rate_limit`

Description: TODO - Add filter description

Example:
```php
add_filter('websocket.rate_limit', function($value) {
    return $value;
});
```

