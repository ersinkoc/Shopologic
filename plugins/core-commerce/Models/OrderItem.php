<?php

namespace Shopologic\Plugins\CoreCommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Relations\BelongsTo;

class OrderItem extends Model
{
    protected string $table = 'order_items';
    
    protected array $fillable = [
        'order_id', 'product_id', 'variant_id',
        'product_name', 'product_sku', 'quantity',
        'price', 'total', 'tax_amount', 'discount_amount',
        'options', 'metadata'
    ];
    
    protected array $casts = [
        'order_id' => 'integer',
        'product_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'options' => 'json',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
    
    public function getSubtotal(): float
    {
        return $this->price * $this->quantity;
    }
    
    public function calculateTotal(): float
    {
        $total = $this->getSubtotal();
        $total += $this->tax_amount ?? 0;
        $total -= $this->discount_amount ?? 0;
        
        return max(0, $total);
    }
}