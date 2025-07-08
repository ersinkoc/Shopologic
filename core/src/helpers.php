<?php

declare(strict_types=1);

if (!function_exists('app')) {
    /**
     * Get the application instance or resolve a service from the container
     */
    function app($abstract = null) {
        // Always get fresh instance from global
        global $SHOPOLOGIC_APP;
        
        if (!isset($SHOPOLOGIC_APP) || $SHOPOLOGIC_APP === null) {
            throw new \RuntimeException('Application has not been bootstrapped yet.');
        }
        
        if ($abstract === null) {
            return $SHOPOLOGIC_APP;
        }
        
        return $SHOPOLOGIC_APP->getContainer()->get($abstract);
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable
     */
    function env(string $key, mixed $default = null): mixed {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert string representations to actual types
        if (is_string($value)) {
            switch (strtolower($value)) {
                case 'true':
                case '(true)':
                    return true;
                case 'false':
                case '(false)':
                    return false;
                case 'empty':
                case '(empty)':
                    return '';
                case 'null':
                case '(null)':
                    return null;
            }
            
            // Handle quoted strings
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                return $matches[1];
            }
        }
        
        return $value;
    }
}

if (!function_exists('database_path')) {
    /**
     * Get path to database file
     */
    function database_path(string $path): string {
        return dirname(__DIR__, 3) . '/storage/' . $path;
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get path to storage directory
     */
    function storage_path(string $path = ''): string {
        $storagePath = dirname(__DIR__, 3) . '/storage';
        return $path ? $storagePath . '/' . ltrim($path, '/') : $storagePath;
    }
}


if (!function_exists('camel_case')) {
    function camel_case(string $value): string {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value))));
    }
}

if (!function_exists('snake_case')) {
    function snake_case(string $value): string {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}

if (!function_exists('studly_case')) {
    function studly_case(string $value): string {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}

if (!function_exists('plural_case')) {
    function plural_case(string $value): string {
        if (substr($value, -1) === 'y') {
            return substr($value, 0, -1) . 'ies';
        }
        if (substr($value, -1) === 's') {
            return $value . 'es';
        }
        return $value . 's';
    }
}

if (!function_exists('class_basename')) {
    function class_basename(string|object $class): string {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}