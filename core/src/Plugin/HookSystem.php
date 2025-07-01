<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

use Closure;
use Shopologic\Core\Events\EventManager;

/**
 * Enhanced WordPress-style hook system with conditional hooks and async support
 */
class HookSystem
{
    protected static array $actions = [];
    protected static array $filters = [];
    protected static array $conditionalActions = [];
    protected static array $conditionalFilters = [];
    protected static array $currentFilter = [];
    protected static ?EventManager $eventManager = null;
    protected static array $didActions = [];
    protected static array $asyncActions = [];

    /**
     * Set the event manager for async processing
     */
    public static function setEventManager(EventManager $eventManager): void
    {
        self::$eventManager = $eventManager;
    }

    /**
     * Add an action hook
     */
    public static function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        self::$actions[$hook][$priority][] = [
            'callback' => $callback,
            'acceptedArgs' => $acceptedArgs,
            'id' => self::getCallbackId($callback),
        ];
    }

    /**
     * Add an async action hook (processed in background)
     */
    public static function addAsyncAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        self::$asyncActions[$hook][$priority][] = [
            'callback' => $callback,
            'acceptedArgs' => $acceptedArgs,
            'id' => self::getCallbackId($callback),
        ];
    }

    /**
     * Add a conditional action hook
     */
    public static function addConditionalAction(string $hook, callable $condition, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        self::$conditionalActions[$hook][$priority][] = [
            'condition' => $condition,
            'callback' => $callback,
            'acceptedArgs' => $acceptedArgs,
            'id' => self::getCallbackId($callback),
        ];
    }

    /**
     * Remove an action hook
     */
    public static function removeAction(string $hook, callable $callback, int $priority = 10): bool
    {
        $callbackId = self::getCallbackId($callback);
        
        // Check regular actions
        if (isset(self::$actions[$hook][$priority])) {
            foreach (self::$actions[$hook][$priority] as $index => $action) {
                if ($action['id'] === $callbackId) {
                    unset(self::$actions[$hook][$priority][$index]);
                    return true;
                }
            }
        }

        // Check async actions
        if (isset(self::$asyncActions[$hook][$priority])) {
            foreach (self::$asyncActions[$hook][$priority] as $index => $action) {
                if ($action['id'] === $callbackId) {
                    unset(self::$asyncActions[$hook][$priority][$index]);
                    return true;
                }
            }
        }

        // Check conditional actions
        if (isset(self::$conditionalActions[$hook][$priority])) {
            foreach (self::$conditionalActions[$hook][$priority] as $index => $action) {
                if ($action['id'] === $callbackId) {
                    unset(self::$conditionalActions[$hook][$priority][$index]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Execute action hooks
     */
    public static function doAction(string $hook, mixed ...$args): void
    {
        self::$currentFilter[] = $hook;
        self::$didActions[$hook] = (self::$didActions[$hook] ?? 0) + 1;

        // Execute regular actions
        self::executeActions(self::$actions[$hook] ?? [], $args);

        // Execute conditional actions
        self::executeConditionalActions(self::$conditionalActions[$hook] ?? [], $args);

        // Queue async actions for background processing
        self::queueAsyncActions(self::$asyncActions[$hook] ?? [], $args);

        array_pop(self::$currentFilter);
    }

    /**
     * Execute regular actions
     */
    protected static function executeActions(array $actions, array $args): void
    {
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
     * Execute conditional actions
     */
    protected static function executeConditionalActions(array $actions, array $args): void
    {
        ksort($actions);

        foreach ($actions as $priority => $callbacks) {
            foreach ($callbacks as $action) {
                $condition = $action['condition'];
                
                // Check if condition is met
                if (!$condition(...$args)) {
                    continue;
                }

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
     * Queue async actions for background processing
     */
    protected static function queueAsyncActions(array $actions, array $args): void
    {
        if (!self::$eventManager) {
            // Fallback to sync execution if no event manager
            self::executeActions($actions, $args);
            return;
        }

        ksort($actions);

        foreach ($actions as $priority => $callbacks) {
            foreach ($callbacks as $action) {
                // Queue the action for async processing
                self::$eventManager->dispatch(new AsyncHookEvent(
                    $action['callback'],
                    array_slice($args, 0, $action['acceptedArgs'])
                ));
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
            'id' => self::getCallbackId($callback),
        ];
    }

    /**
     * Add a conditional filter hook
     */
    public static function addConditionalFilter(string $hook, callable $condition, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        self::$conditionalFilters[$hook][$priority][] = [
            'condition' => $condition,
            'callback' => $callback,
            'acceptedArgs' => $acceptedArgs,
            'id' => self::getCallbackId($callback),
        ];
    }

    /**
     * Remove a filter hook
     */
    public static function removeFilter(string $hook, callable $callback, int $priority = 10): bool
    {
        $callbackId = self::getCallbackId($callback);

        // Check regular filters
        if (isset(self::$filters[$hook][$priority])) {
            foreach (self::$filters[$hook][$priority] as $index => $filter) {
                if ($filter['id'] === $callbackId) {
                    unset(self::$filters[$hook][$priority][$index]);
                    return true;
                }
            }
        }

        // Check conditional filters
        if (isset(self::$conditionalFilters[$hook][$priority])) {
            foreach (self::$conditionalFilters[$hook][$priority] as $index => $filter) {
                if ($filter['id'] === $callbackId) {
                    unset(self::$conditionalFilters[$hook][$priority][$index]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Apply filter hooks
     */
    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        self::$currentFilter[] = $hook;

        // Apply regular filters
        $value = self::applyRegularFilters(self::$filters[$hook] ?? [], $value, $args);

        // Apply conditional filters
        $value = self::applyConditionalFilters(self::$conditionalFilters[$hook] ?? [], $value, $args);

        array_pop(self::$currentFilter);

        return $value;
    }

    /**
     * Apply regular filters
     */
    protected static function applyRegularFilters(array $filters, mixed $value, array $args): mixed
    {
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
     * Apply conditional filters
     */
    protected static function applyConditionalFilters(array $filters, mixed $value, array $args): mixed
    {
        ksort($filters);

        foreach ($filters as $priority => $callbacks) {
            foreach ($callbacks as $filter) {
                $condition = $filter['condition'];
                
                // Check if condition is met
                $conditionArgs = array_merge([$value], $args);
                if (!$condition(...$conditionArgs)) {
                    continue;
                }

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
        if ($callback === null) {
            return isset(self::$actions[$hook]) || 
                   isset(self::$asyncActions[$hook]) || 
                   isset(self::$conditionalActions[$hook]);
        }

        $callbackId = self::getCallbackId($callback);

        // Check all action types
        foreach ([self::$actions, self::$asyncActions, self::$conditionalActions] as $actionType) {
            if (isset($actionType[$hook])) {
                foreach ($actionType[$hook] as $priority => $callbacks) {
                    foreach ($callbacks as $action) {
                        if ($action['id'] === $callbackId) {
                            return true;
                        }
                    }
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
        if ($callback === null) {
            return isset(self::$filters[$hook]) || isset(self::$conditionalFilters[$hook]);
        }

        $callbackId = self::getCallbackId($callback);

        // Check all filter types
        foreach ([self::$filters, self::$conditionalFilters] as $filterType) {
            if (isset($filterType[$hook])) {
                foreach ($filterType[$hook] as $priority => $callbacks) {
                    foreach ($callbacks as $filter) {
                        if ($filter['id'] === $callbackId) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get current filter/action being processed
     */
    public static function currentFilter(): ?string
    {
        return end(self::$currentFilter) ?: null;
    }

    /**
     * Get number of times an action has been executed
     */
    public static function didAction(string $hook): int
    {
        return self::$didActions[$hook] ?? 0;
    }

    /**
     * Clear all hooks
     */
    public static function clearAll(): void
    {
        self::$actions = [];
        self::$filters = [];
        self::$conditionalActions = [];
        self::$conditionalFilters = [];
        self::$asyncActions = [];
        self::$currentFilter = [];
        self::$didActions = [];
    }

    /**
     * Clear specific hook
     */
    public static function clear(string $hook, string $type = 'both'): void
    {
        if ($type === 'action' || $type === 'both') {
            unset(self::$actions[$hook]);
            unset(self::$asyncActions[$hook]);
            unset(self::$conditionalActions[$hook]);
        }

        if ($type === 'filter' || $type === 'both') {
            unset(self::$filters[$hook]);
            unset(self::$conditionalFilters[$hook]);
        }
    }

    /**
     * Get all registered actions
     */
    public static function getActions(?string $hook = null): array
    {
        if ($hook !== null) {
            return [
                'regular' => self::$actions[$hook] ?? [],
                'async' => self::$asyncActions[$hook] ?? [],
                'conditional' => self::$conditionalActions[$hook] ?? [],
            ];
        }

        return [
            'regular' => self::$actions,
            'async' => self::$asyncActions,
            'conditional' => self::$conditionalActions,
        ];
    }

    /**
     * Get all registered filters
     */
    public static function getFilters(?string $hook = null): array
    {
        if ($hook !== null) {
            return [
                'regular' => self::$filters[$hook] ?? [],
                'conditional' => self::$conditionalFilters[$hook] ?? [],
            ];
        }

        return [
            'regular' => self::$filters,
            'conditional' => self::$conditionalFilters,
        ];
    }

    /**
     * Get unique ID for callback
     */
    protected static function getCallbackId(callable $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback)) {
            if (is_object($callback[0])) {
                return spl_object_hash($callback[0]) . '::' . $callback[1];
            }
            return $callback[0] . '::' . $callback[1];
        }

        if ($callback instanceof Closure) {
            return spl_object_hash($callback);
        }

        return 'unknown';
    }

    /**
     * Create a hook reference array (WordPress-style)
     */
    public static function createHookReference(string $hook, callable $callback, int $priority = 10): array
    {
        return [
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'id' => self::getCallbackId($callback),
        ];
    }

    /**
     * Register multiple hooks at once
     */
    public static function registerHooks(array $hooks): void
    {
        foreach ($hooks as $hook) {
            $type = $hook['type'] ?? 'action';
            $hookName = $hook['hook'];
            $callback = $hook['callback'];
            $priority = $hook['priority'] ?? 10;
            $acceptedArgs = $hook['accepted_args'] ?? 1;
            $async = $hook['async'] ?? false;
            $condition = $hook['condition'] ?? null;

            if ($type === 'filter') {
                if ($condition) {
                    self::addConditionalFilter($hookName, $condition, $callback, $priority, $acceptedArgs);
                } else {
                    self::addFilter($hookName, $callback, $priority, $acceptedArgs);
                }
            } else {
                if ($condition) {
                    self::addConditionalAction($hookName, $condition, $callback, $priority, $acceptedArgs);
                } elseif ($async) {
                    self::addAsyncAction($hookName, $callback, $priority, $acceptedArgs);
                } else {
                    self::addAction($hookName, $callback, $priority, $acceptedArgs);
                }
            }
        }
    }
}

/**
 * Event for async hook processing
 */
class AsyncHookEvent
{
    private $callback; // Cannot use callable as property type in PHP 8.0
    private array $args;
    
    public function __construct(
        callable $callback,
        array $args
    ) {
        $this->callback = $callback;
        $this->args = $args;
    }

    public function execute(): void
    {
        ($this->callback)(...$this->args);
    }
}