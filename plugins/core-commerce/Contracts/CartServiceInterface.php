<?php

namespace Shopologic\Plugins\CoreCommerce\Contracts;

use Shopologic\Plugins\CoreCommerce\Models\Cart;
use Shopologic\Plugins\CoreCommerce\Models\CartItem;
use Shopologic\Core\Database\Collection;

interface CartServiceInterface
{
    public function getCart(?string $sessionId = null): Cart;
    
    public function addItem(int $productId, int $quantity = 1, array $options = []): CartItem;
    
    public function updateItem(string $itemId, int $quantity): CartItem;
    
    public function removeItem(string $itemId): bool;
    
    public function clear(): bool;
    
    public function getItems(): Collection;
    
    public function getItemCount(): int;
    
    public function getSubtotal(): float;
    
    public function getTax(): float;
    
    public function getShipping(): float;
    
    public function getDiscount(): float;
    
    public function getTotal(): float;
    
    public function applyPromoCode(string $code): bool;
    
    public function removePromoCode(string $code): bool;
    
    public function calculateShipping(array $address): array;
    
    public function setShippingMethod(string $methodId): bool;
    
    public function merge(Cart $guestCart, Cart $userCart): Cart;
    
    public function cleanupAbandoned(int $hours = 24): int;
}