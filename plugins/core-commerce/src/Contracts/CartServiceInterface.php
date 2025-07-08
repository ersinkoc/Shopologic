<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Contracts;

interface CartServiceInterface
{
    /**
     * Get cart by session ID
     */
    public function getCart(string $sessionId): array;
    
    /**
     * Add item to cart
     */
    public function addItem(string $sessionId, int $productId, int $quantity = 1, array $options = []): bool;
    
    /**
     * Update cart item
     */
    public function updateItem(string $sessionId, int $itemId, int $quantity): bool;
    
    /**
     * Remove item from cart
     */
    public function removeItem(string $sessionId, int $itemId): bool;
    
    /**
     * Clear cart
     */
    public function clear(string $sessionId): bool;
    
    /**
     * Get cart totals
     */
    public function getTotals(string $sessionId): array;
    
    /**
     * Clean up abandoned carts
     */
    public function cleanupAbandoned(int $hours = 24): int;
}