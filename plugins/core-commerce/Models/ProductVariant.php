<?php

declare(strict_types=1);
namespace Shopologic\Plugins\CoreCommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected string $table = 'product_variants';
    
    protected array $fillable = [
        'product_id', 'name', 'sku', 'price',
        'cost_price', 'weight', 'dimensions',
        'stock_quantity', 'manage_stock', 'is_active',
        'attributes', 'image', 'sort_order'
    ];
    
    protected array $casts = [
        'product_id' => 'integer',
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:3',
        'dimensions' => 'json',
        'stock_quantity' => 'integer',
        'manage_stock' => 'boolean',
        'is_active' => 'boolean',
        'attributes' => 'json',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    public function hasStock(int $quantity = 1): bool
    {
        if (!$this->manage_stock) {
            return true;
        }
        
        return $this->stock_quantity >= $quantity;
    }
}