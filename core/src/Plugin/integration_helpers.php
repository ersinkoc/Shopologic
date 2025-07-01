<?php
/**
 * Plugin Integration Helper Functions
 * Include this file in your controllers to use plugin hooks
 */

declare(strict_types=1);

use Shopologic\Core\Plugin\HookSystem;

// Global helper functions for plugin hooks
if (!function_exists("do_plugin_action")) {
    function do_plugin_action(string $hook, ...$args): void {
        HookSystem::doAction($hook, ...$args);
    }
}

if (!function_exists("apply_plugin_filter")) {
    function apply_plugin_filter(string $hook, $value, ...$args) {
        return HookSystem::applyFilters($hook, $value, ...$args);
    }
}

// E-commerce specific helper functions
if (!function_exists("track_product_view")) {
    function track_product_view(int $productId): void {
        do_plugin_action("product.viewed", $productId);
    }
}

if (!function_exists("track_cart_action")) {
    function track_cart_action(string $action, array $data): void {
        do_plugin_action("cart.{$action}", $data);
    }
}

if (!function_exists("apply_price_filters")) {
    function apply_price_filters(float $price, int $productId): float {
        return apply_plugin_filter("product.price", $price, $productId);
    }
}

if (!function_exists("track_order_event")) {
    function track_order_event(string $event, string $orderId, array $data = []): void {
        do_plugin_action("order.{$event}", $orderId, $data);
    }
}

if (!function_exists("track_search")) {
    function track_search(string $query, int $resultCount): void {
        do_plugin_action("search.query", $query, $resultCount);
    }
}

if (!function_exists("track_customer_action")) {
    function track_customer_action(string $action, int $customerId, array $data = []): void {
        do_plugin_action("customer.{$action}", $customerId, $data);
    }
}
