# code-quality-analyzer Hooks Documentation

## Overview

Hooks provided by the code-quality-analyzer plugin.

## Actions

### `code.analyzed`

Description: TODO - Add action description

Example:
```php
add_action('code.analyzed', function($data) {
    // Your code here
});
```

### `issue.detected`

Description: TODO - Add action description

Example:
```php
add_action('issue.detected', function($data) {
    // Your code here
});
```

### `security.vulnerability_found`

Description: TODO - Add action description

Example:
```php
add_action('security.vulnerability_found', function($data) {
    // Your code here
});
```

### `quality.threshold_failed`

Description: TODO - Add action description

Example:
```php
add_action('quality.threshold_failed', function($data) {
    // Your code here
});
```

### `refactoring.suggested`

Description: TODO - Add action description

Example:
```php
add_action('refactoring.suggested', function($data) {
    // Your code here
});
```

### `standards.violation`

Description: TODO - Add action description

Example:
```php
add_action('standards.violation', function($data) {
    // Your code here
});
```

## Filters

### `code.before_commit`

Description: TODO - Add filter description

Example:
```php
add_filter('code.before_commit', function($value) {
    return $value;
});
```

### `code.analysis_rules`

Description: TODO - Add filter description

Example:
```php
add_filter('code.analysis_rules', function($value) {
    return $value;
});
```

### `quality.thresholds`

Description: TODO - Add filter description

Example:
```php
add_filter('quality.thresholds', function($value) {
    return $value;
});
```

### `security.scan_rules`

Description: TODO - Add filter description

Example:
```php
add_filter('security.scan_rules', function($value) {
    return $value;
});
```

### `report.format`

Description: TODO - Add filter description

Example:
```php
add_filter('report.format', function($value) {
    return $value;
});
```

