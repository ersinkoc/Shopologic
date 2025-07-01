<?php

declare(strict_types=1);

namespace Shopologic\Core\Cart;

use Shopologic\Core\Plugin\HookSystem;

class CartService
{
    private array $items = [];
    private array $totals = [];
    
    public function __construct()
    {
        $this->loadFromSession();
    }
    
    /**
     * Add item to cart
     */
    public function addItem(int $productId, int $quantity = 1, array $options = []): bool
    {
        try {
            // Apply filters to allow plugins to modify the add operation
            $productId = HookSystem::applyFilters('cart.add_item.product_id', $productId);
            $quantity = HookSystem::applyFilters('cart.add_item.quantity', $quantity);
            $options = HookSystem::applyFilters('cart.add_item.options', $options);
            
            // Generate unique cart key
            $cartKey = $this->generateCartKey($productId, $options);
            
            // Check if item already exists
            if (isset($this->items[$cartKey])) {
                $this->items[$cartKey]['quantity'] += $quantity;
            } else {
                // Get product data (would normally come from database)
                $product = $this->getProductData($productId);
                
                if (!$product) {
                    return false;
                }
                
                $this->items[$cartKey] = [
                    'product_id' => $productId,
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => $quantity,
                    'options' => $options,
                    'image' => $product['image'] ?? '',
                    'slug' => $product['slug'] ?? '',
                    'cart_key' => $cartKey
                ];
            }
            
            $this->calculateTotals();
            $this->saveToSession();
            
            // Fire action hook
            HookSystem::doAction('cart.item_added', $this->items[$cartKey]);
            
            return true;
            
        } catch (\Exception $e) {
            error_log('Cart add item error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update item quantity
     */
    public function updateItem(string $cartKey, int $quantity): bool
    {
        if (!isset($this->items[$cartKey])) {
            return false;
        }
        
        if ($quantity <= 0) {
            return $this->removeItem($cartKey);
        }
        
        $oldQuantity = $this->items[$cartKey]['quantity'];
        $this->items[$cartKey]['quantity'] = $quantity;
        
        $this->calculateTotals();
        $this->saveToSession();
        
        HookSystem::doAction('cart.item_updated', $this->items[$cartKey], $oldQuantity);
        
        return true;
    }
    
    /**
     * Remove item from cart
     */
    public function removeItem(string $cartKey): bool
    {
        if (!isset($this->items[$cartKey])) {
            return false;
        }
        
        $item = $this->items[$cartKey];
        unset($this->items[$cartKey]);
        
        $this->calculateTotals();
        $this->saveToSession();
        
        HookSystem::doAction('cart.item_removed', $item);
        
        return true;
    }
    
    /**
     * Clear all items from cart
     */
    public function clear(): void
    {
        $this->items = [];
        $this->totals = [];
        $this->saveToSession();
        
        HookSystem::doAction('cart.cleared');
    }
    
    /**
     * Get all cart items
     */
    public function getItems(): array
    {
        return $this->items;
    }
    
    /**
     * Get item count
     */
    public function getItemCount(): int
    {
        return array_sum(array_column($this->items, 'quantity'));
    }
    
    /**
     * Get unique item count
     */
    public function getUniqueItemCount(): int
    {
        return count($this->items);
    }
    
    /**
     * Get cart totals
     */
    public function getTotals(): array
    {
        if (empty($this->totals)) {
            $this->calculateTotals();
        }
        return $this->totals;
    }
    
    /**
     * Get subtotal
     */
    public function getSubtotal(): float
    {
        $totals = $this->getTotals();
        return $totals['subtotal'] ?? 0.0;
    }
    
    /**
     * Get total
     */
    public function getTotal(): float
    {
        $totals = $this->getTotals();
        return $totals['total'] ?? 0.0;
    }
    
    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }
    
    /**
     * Calculate cart totals
     */
    private function calculateTotals(): void
    {
        $subtotal = 0;
        $tax = 0;
        $shipping = 0;
        $discount = 0;
        
        foreach ($this->items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        // Apply filters to allow plugins to modify totals
        $subtotal = HookSystem::applyFilters('cart.subtotal', $subtotal, $this->items);
        $tax = HookSystem::applyFilters('cart.tax', $tax, $this->items, $subtotal);
        $shipping = HookSystem::applyFilters('cart.shipping', $shipping, $this->items, $subtotal);
        $discount = HookSystem::applyFilters('cart.discount', $discount, $this->items, $subtotal);
        
        $total = $subtotal + $tax + $shipping - $discount;
        
        $this->totals = [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'discount' => $discount,
            'total' => max(0, $total) // Ensure total is never negative
        ];
        
        HookSystem::doAction('cart.totals_calculated', $this->totals);
    }
    
    /**
     * Generate unique cart key for item
     */
    private function generateCartKey(int $productId, array $options = []): string
    {
        ksort($options); // Sort options for consistent key generation
        $optionsString = serialize($options);
        return md5($productId . '|' . $optionsString);
    }
    
    /**
     * Get product data (demo implementation)
     */
    private function getProductData(int $productId): ?array
    {
        // Demo products data - in real implementation this would come from database
        $products = [
            1 => ['name' => 'Premium Wireless Headphones', 'price' => 299.99, 'slug' => 'premium-wireless-headphones', 'image' => 'https://via.placeholder.com/300x300?text=Headphones'],
            2 => ['name' => 'Smart Fitness Watch', 'price' => 199.99, 'slug' => 'smart-fitness-watch', 'image' => 'https://via.placeholder.com/300x300?text=Watch'],
            3 => ['name' => 'Bluetooth Speaker', 'price' => 79.99, 'slug' => 'bluetooth-speaker', 'image' => 'https://via.placeholder.com/300x300?text=Speaker'],
            4 => ['name' => 'Laptop Stand', 'price' => 49.99, 'slug' => 'laptop-stand', 'image' => 'https://via.placeholder.com/300x300?text=Stand'],
            5 => ['name' => 'USB-C Hub', 'price' => 89.99, 'slug' => 'usb-c-hub', 'image' => 'https://via.placeholder.com/300x300?text=Hub'],
            6 => ['name' => 'Wireless Mouse', 'price' => 39.99, 'slug' => 'wireless-mouse', 'image' => 'https://via.placeholder.com/300x300?text=Mouse'],
            7 => ['name' => 'Mechanical Keyboard', 'price' => 159.99, 'slug' => 'mechanical-keyboard', 'image' => 'https://via.placeholder.com/300x300?text=Keyboard'],
            8 => ['name' => 'Webcam HD', 'price' => 69.99, 'slug' => 'webcam-hd', 'image' => 'https://via.placeholder.com/300x300?text=Webcam'],
            9 => ['name' => 'Phone Case', 'price' => 19.99, 'slug' => 'phone-case', 'image' => 'https://via.placeholder.com/300x300?text=Case'],
            10 => ['name' => 'Portable Charger', 'price' => 29.99, 'slug' => 'portable-charger', 'image' => 'https://via.placeholder.com/300x300?text=Charger'],
        ];
        
        return $products[$productId] ?? null;
    }
    
    /**
     * Load cart from session
     */
    private function loadFromSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->items = $_SESSION['cart_items'] ?? [];
        $this->totals = $_SESSION['cart_totals'] ?? [];
    }
    
    /**
     * Save cart to session
     */
    private function saveToSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['cart_items'] = $this->items;
        $_SESSION['cart_totals'] = $this->totals;
    }
}