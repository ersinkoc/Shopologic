<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Cart;

use Shopologic\Core\Session\SessionManager;
use Shopologic\Core\Ecommerce\Models\Product;
use Shopologic\Core\Ecommerce\Models\ProductVariant;
use Shopologic\Core\Events\EventDispatcher;

class Cart
{
    protected SessionManager $session;
    protected EventDispatcher $events;
    protected string $sessionKey = 'cart';
    protected array $items = [];
    protected ?string $couponCode = null;

    public function __construct(SessionManager $session, EventDispatcher $events)
    {
        $this->session = $session;
        $this->events = $events;
        $this->loadFromSession();
    }

    /**
     * Add item to cart
     */
    public function add(Product $product, int $quantity = 1, ?ProductVariant $variant = null): CartItem
    {
        $cartKey = $this->getCartKey($product, $variant);
        
        if (isset($this->items[$cartKey])) {
            $this->items[$cartKey]->quantity += $quantity;
        } else {
            $this->items[$cartKey] = new CartItem($product, $quantity, $variant);
        }
        
        $this->events->dispatch(new Events\ItemAdded($this->items[$cartKey]));
        $this->saveToSession();
        
        return $this->items[$cartKey];
    }

    /**
     * Update item quantity
     */
    public function update(string $cartKey, int $quantity): ?CartItem
    {
        if (!isset($this->items[$cartKey])) {
            return null;
        }
        
        if ($quantity <= 0) {
            return $this->remove($cartKey);
        }
        
        $this->items[$cartKey]->quantity = $quantity;
        $this->events->dispatch(new Events\ItemUpdated($this->items[$cartKey]));
        $this->saveToSession();
        
        return $this->items[$cartKey];
    }

    /**
     * Remove item from cart
     */
    public function remove(string $cartKey): ?CartItem
    {
        if (!isset($this->items[$cartKey])) {
            return null;
        }
        
        $item = $this->items[$cartKey];
        unset($this->items[$cartKey]);
        
        $this->events->dispatch(new Events\ItemRemoved($item));
        $this->saveToSession();
        
        return $item;
    }

    /**
     * Clear the cart
     */
    public function clear(): void
    {
        $this->items = [];
        $this->couponCode = null;
        
        $this->events->dispatch(new Events\CartCleared());
        $this->saveToSession();
    }

    /**
     * Get all items
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Get item by key
     */
    public function get(string $cartKey): ?CartItem
    {
        return $this->items[$cartKey] ?? null;
    }

    /**
     * Check if cart has items
     */
    public function hasItems(): bool
    {
        return !empty($this->items);
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Get item count
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get total quantity
     */
    public function getTotalQuantity(): int
    {
        $total = 0;
        
        foreach ($this->items as $item) {
            $total += $item->quantity;
        }
        
        return $total;
    }

    /**
     * Get subtotal
     */
    public function getSubtotal(): float
    {
        $subtotal = 0;
        
        foreach ($this->items as $item) {
            $subtotal += $item->getTotal();
        }
        
        return $subtotal;
    }

    /**
     * Apply coupon
     */
    public function applyCoupon(string $code): bool
    {
        // In a real implementation, validate coupon
        $this->couponCode = $code;
        $this->events->dispatch(new Events\CouponApplied($code));
        $this->saveToSession();
        
        return true;
    }

    /**
     * Remove coupon
     */
    public function removeCoupon(): void
    {
        $this->couponCode = null;
        $this->events->dispatch(new Events\CouponRemoved());
        $this->saveToSession();
    }

    /**
     * Get coupon code
     */
    public function getCouponCode(): ?string
    {
        return $this->couponCode;
    }

    /**
     * Get discount amount
     */
    public function getDiscount(): float
    {
        if (!$this->couponCode) {
            return 0;
        }
        
        // In a real implementation, calculate discount based on coupon
        return $this->getSubtotal() * 0.1; // 10% discount for demo
    }

    /**
     * Get tax amount
     */
    public function getTax(): float
    {
        // In a real implementation, calculate tax based on location
        $taxableAmount = $this->getSubtotal() - $this->getDiscount();
        return $taxableAmount * 0.08; // 8% tax for demo
    }

    /**
     * Get shipping cost
     */
    public function getShipping(): float
    {
        // In a real implementation, calculate shipping based on weight/location
        if ($this->getSubtotal() >= 50) {
            return 0; // Free shipping over $50
        }
        
        return 10.00;
    }

    /**
     * Get total
     */
    public function getTotal(): float
    {
        return $this->getSubtotal() - $this->getDiscount() + $this->getTax() + $this->getShipping();
    }

    /**
     * Get cart summary
     */
    public function getSummary(): array
    {
        return [
            'items' => $this->getTotalQuantity(),
            'subtotal' => $this->getSubtotal(),
            'discount' => $this->getDiscount(),
            'tax' => $this->getTax(),
            'shipping' => $this->getShipping(),
            'total' => $this->getTotal(),
        ];
    }

    /**
     * Check if all items are in stock
     */
    public function validateStock(): array
    {
        $errors = [];
        
        foreach ($this->items as $key => $item) {
            if ($item->variant) {
                $available = $item->variant->getAvailableQuantity();
                if ($available < $item->quantity) {
                    $errors[$key] = "Only {$available} available";
                }
            } else {
                $available = $item->product->getAvailableQuantity();
                if ($available < $item->quantity) {
                    $errors[$key] = "Only {$available} available";
                }
            }
        }
        
        return $errors;
    }

    /**
     * Generate cart key
     */
    protected function getCartKey(Product $product, ?ProductVariant $variant = null): string
    {
        if ($variant) {
            return 'variant_' . $variant->id;
        }
        
        return 'product_' . $product->id;
    }

    /**
     * Load cart from session
     */
    protected function loadFromSession(): void
    {
        $data = $this->session->get($this->sessionKey, []);
        
        if (isset($data['items'])) {
            foreach ($data['items'] as $key => $itemData) {
                if (isset($itemData['product_id'])) {
                    $product = Product::find($itemData['product_id']);
                    if ($product) {
                        $variant = null;
                        if (isset($itemData['variant_id'])) {
                            $variant = ProductVariant::find($itemData['variant_id']);
                        }
                        
                        $this->items[$key] = new CartItem(
                            $product,
                            $itemData['quantity'],
                            $variant
                        );
                    }
                }
            }
        }
        
        $this->couponCode = $data['coupon_code'] ?? null;
    }

    /**
     * Save cart to session
     */
    protected function saveToSession(): void
    {
        $data = [
            'items' => [],
            'coupon_code' => $this->couponCode,
        ];
        
        foreach ($this->items as $key => $item) {
            $data['items'][$key] = [
                'product_id' => $item->product->id,
                'variant_id' => $item->variant?->id,
                'quantity' => $item->quantity,
            ];
        }
        
        $this->session->put($this->sessionKey, $data);
    }
}