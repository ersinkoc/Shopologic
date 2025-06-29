<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Cart;

use Shopologic\Core\Ecommerce\Models\Product;
use Shopologic\Core\Ecommerce\Models\ProductVariant;

class CartItem
{
    public Product $product;
    public ?ProductVariant $variant;
    public int $quantity;
    public array $customData = [];

    public function __construct(Product $product, int $quantity = 1, ?ProductVariant $variant = null)
    {
        $this->product = $product;
        $this->quantity = $quantity;
        $this->variant = $variant;
    }

    /**
     * Get the price
     */
    public function getPrice(): float
    {
        if ($this->variant) {
            return $this->variant->price;
        }
        
        return $this->product->price;
    }

    /**
     * Get the original price (before discount)
     */
    public function getOriginalPrice(): float
    {
        if ($this->variant && $this->variant->compare_price) {
            return $this->variant->compare_price;
        }
        
        return $this->product->compare_price ?: $this->product->price;
    }

    /**
     * Get the total
     */
    public function getTotal(): float
    {
        return $this->getPrice() * $this->quantity;
    }

    /**
     * Get the original total
     */
    public function getOriginalTotal(): float
    {
        return $this->getOriginalPrice() * $this->quantity;
    }

    /**
     * Get the discount amount
     */
    public function getDiscountAmount(): float
    {
        return $this->getOriginalTotal() - $this->getTotal();
    }

    /**
     * Get the name
     */
    public function getName(): string
    {
        if ($this->variant) {
            return $this->variant->getFullName();
        }
        
        return $this->product->name;
    }

    /**
     * Get the SKU
     */
    public function getSku(): string
    {
        if ($this->variant && $this->variant->sku) {
            return $this->variant->sku;
        }
        
        return $this->product->sku;
    }

    /**
     * Get the weight
     */
    public function getWeight(): float
    {
        if ($this->variant && $this->variant->weight !== null) {
            return $this->variant->weight * $this->quantity;
        }
        
        return ($this->product->weight ?? 0) * $this->quantity;
    }

    /**
     * Check if item is in stock
     */
    public function inStock(): bool
    {
        if ($this->variant) {
            return $this->variant->inStock();
        }
        
        return $this->product->inStock();
    }

    /**
     * Get available quantity
     */
    public function getAvailableQuantity(): int
    {
        if ($this->variant) {
            return $this->variant->getAvailableQuantity();
        }
        
        return $this->product->getAvailableQuantity();
    }

    /**
     * Set custom data
     */
    public function setCustomData(string $key, mixed $value): void
    {
        $this->customData[$key] = $value;
    }

    /**
     * Get custom data
     */
    public function getCustomData(string $key, mixed $default = null): mixed
    {
        return $this->customData[$key] ?? $default;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->product->id,
            'variant_id' => $this->variant?->id,
            'name' => $this->getName(),
            'sku' => $this->getSku(),
            'price' => $this->getPrice(),
            'quantity' => $this->quantity,
            'total' => $this->getTotal(),
            'weight' => $this->getWeight(),
            'in_stock' => $this->inStock(),
            'custom_data' => $this->customData,
        ];
    }
}