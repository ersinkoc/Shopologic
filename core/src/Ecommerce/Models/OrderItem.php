<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;

class OrderItem extends Model
{
    protected string $table = 'order_items';
    
    protected array $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'name',
        'sku',
        'price',
        'quantity',
        'total',
        'meta_data',
    ];
    
    protected array $casts = [
        'order_id' => 'integer',
        'product_id' => 'integer',
        'variant_id' => 'integer',
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'total' => 'decimal:2',
        'meta_data' => 'array',
    ];

    /**
     * Get the order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the variant
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Calculate total
     */
    public function calculateTotal(): void
    {
        $this->total = $this->price * $this->quantity;
    }
}