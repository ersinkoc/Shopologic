<?php

declare(strict_types=1);

use Shopologic\Core\Plugin\HookSystem;

// WordPress-style global hook functions

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        HookSystem::addAction($hook, $callback, $priority, $accepted_args);
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        HookSystem::addFilter($hook, $callback, $priority, $accepted_args);
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        HookSystem::doAction($hook, ...$args);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        return HookSystem::applyFilters($hook, $value, ...$args);
    }
}

if (!function_exists('remove_action')) {
    function remove_action($hook, $callback, $priority = 10) {
        return HookSystem::removeAction($hook, $callback, $priority);
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($hook, $callback, $priority = 10) {
        return HookSystem::removeFilter($hook, $callback, $priority);
    }
}

if (!function_exists('has_action')) {
    function has_action($hook, $callback = null) {
        return HookSystem::hasAction($hook, $callback);
    }
}

if (!function_exists('has_filter')) {
    function has_filter($hook, $callback = null) {
        return HookSystem::hasFilter($hook, $callback);
    }
}

if (!function_exists('current_filter')) {
    function current_filter() {
        return HookSystem::currentFilter();
    }
}

if (!function_exists('did_action')) {
    function did_action($hook) {
        return HookSystem::didAction($hook);
    }
}