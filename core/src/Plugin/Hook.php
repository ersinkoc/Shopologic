<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

class Hook
{
    protected static array $actions = [];
    protected static array $filters = [];

    /**
     * Add an action hook
     */
    public static function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        self::$actions[$hook][$priority][] = [
            'callback' => $callback,
            'acceptedArgs' => $acceptedArgs,
        ];
    }

    /**
     * Remove an action hook
     */
    public static function removeAction(string $hook, callable $callback, int $priority = 10): bool
    {
        if (!isset(self::$actions[$hook][$priority])) {
            return false;
        }

        foreach (self::$actions[$hook][$priority] as $index => $action) {
            if ($action['callback'] === $callback) {
                unset(self::$actions[$hook][$priority][$index]);
                return true;
            }
        }

        return false;
    }

    /**
     * Execute action hooks
     */
    public static function doAction(string $hook, mixed ...$args): void
    {
        if (!isset(self::$actions[$hook])) {
            return;
        }

        $actions = self::$actions[$hook];
        ksort($actions);

        foreach ($actions as $priority => $callbacks) {
            foreach ($callbacks as $action) {
                $callback = $action['callback'];
                $acceptedArgs = $action['acceptedArgs'];
                
                if ($acceptedArgs === 0) {
                    $callback();
                } else {
                    $callback(...array_slice($args, 0, $acceptedArgs));
                }
            }
        }
    }

    /**
     * Add a filter hook
     */
    public static function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        self::$filters[$hook][$priority][] = [
            'callback' => $callback,
            'acceptedArgs' => $acceptedArgs,
        ];
    }

    /**
     * Remove a filter hook
     */
    public static function removeFilter(string $hook, callable $callback, int $priority = 10): bool
    {
        if (!isset(self::$filters[$hook][$priority])) {
            return false;
        }

        foreach (self::$filters[$hook][$priority] as $index => $filter) {
            if ($filter['callback'] === $callback) {
                unset(self::$filters[$hook][$priority][$index]);
                return true;
            }
        }

        return false;
    }

    /**
     * Apply filter hooks
     */
    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (!isset(self::$filters[$hook])) {
            return $value;
        }

        $filters = self::$filters[$hook];
        ksort($filters);

        foreach ($filters as $priority => $callbacks) {
            foreach ($callbacks as $filter) {
                $callback = $filter['callback'];
                $acceptedArgs = $filter['acceptedArgs'];
                
                if ($acceptedArgs === 1) {
                    $value = $callback($value);
                } else {
                    $callArgs = array_merge([$value], array_slice($args, 0, $acceptedArgs - 1));
                    $value = $callback(...$callArgs);
                }
            }
        }

        return $value;
    }

    /**
     * Check if action exists
     */
    public static function hasAction(string $hook, ?callable $callback = null): bool
    {
        if (!isset(self::$actions[$hook])) {
            return false;
        }

        if ($callback === null) {
            return true;
        }

        foreach (self::$actions[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $action) {
                if ($action['callback'] === $callback) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if filter exists
     */
    public static function hasFilter(string $hook, ?callable $callback = null): bool
    {
        if (!isset(self::$filters[$hook])) {
            return false;
        }

        if ($callback === null) {
            return true;
        }

        foreach (self::$filters[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $filter) {
                if ($filter['callback'] === $callback) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Clear all hooks
     */
    public static function clearAll(): void
    {
        self::$actions = [];
        self::$filters = [];
    }

    /**
     * Clear specific hook
     */
    public static function clear(string $hook, string $type = 'both'): void
    {
        if ($type === 'action' || $type === 'both') {
            unset(self::$actions[$hook]);
        }

        if ($type === 'filter' || $type === 'both') {
            unset(self::$filters[$hook]);
        }
    }

    /**
     * Get all registered actions
     */
    public static function getActions(?string $hook = null): array
    {
        if ($hook !== null) {
            return self::$actions[$hook] ?? [];
        }

        return self::$actions;
    }

    /**
     * Get all registered filters
     */
    public static function getFilters(?string $hook = null): array
    {
        if ($hook !== null) {
            return self::$filters[$hook] ?? [];
        }

        return self::$filters;
    }
}