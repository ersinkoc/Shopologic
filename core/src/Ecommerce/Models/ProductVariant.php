<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;

class ProductVariant extends Model
{
    protected string $table = 'product_variants';
    
    protected array $fillable = [
        'product_id',
        'sku',
        'name',
        'price',
        'compare_price',
        'cost',
        'weight',
        'quantity',
        'position',
        'is_active',
    ];
    
    protected array $casts = [
        'product_id' => 'integer',
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:3',
        'quantity' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get variant options
     */
    public function options()
    {
        return $this->hasMany(ProductVariantOption::class, 'variant_id');
    }

    /**
     * Get formatted name with options
     */
    public function getFullName(): string
    {
        $optionValues = [];
        
        foreach ($this->options as $option) {
            $optionValues[] = $option->value;
        }
        
        if (empty($optionValues)) {
            return $this->name ?? $this->product->name;
        }
        
        return $this->product->name . ' - ' . implode(' / ', $optionValues);
    }

    /**
     * Check if variant is in stock
     */
    public function inStock(): bool
    {
        $product = $this->product;
        
        if (!$product->track_quantity) {
            return true;
        }
        
        return $this->quantity > 0 || $product->allow_backorder;
    }

    /**
     * Get available quantity
     */
    public function getAvailableQuantity(): int
    {
        if (!$this->product->track_quantity) {
            return PHP_INT_MAX;
        }
        
        return max(0, $this->quantity);
    }

    /**
     * Decrease stock
     */
    public function decreaseStock(int $quantity): bool
    {
        if (!$this->product->track_quantity) {
            return true;
        }
        
        if ($this->quantity < $quantity && !$this->product->allow_backorder) {
            return false;
        }
        
        $this->quantity -= $quantity;
        return $this->save();
    }

    /**
     * Increase stock
     */
    public function increaseStock(int $quantity): bool
    {
        if (!$this->product->track_quantity) {
            return true;
        }
        
        $this->quantity += $quantity;
        return $this->save();
    }
}