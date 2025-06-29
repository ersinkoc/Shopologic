<?php

namespace Shopologic\Plugins\CoreCommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Relations\BelongsTo;

class CartItem extends Model
{
    protected string $table = 'cart_items';
    
    protected array $fillable = [
        'cart_id', 'product_id', 'variant_id',
        'quantity', 'price', 'options', 'metadata'
    ];
    
    protected array $casts = [
        'cart_id' => 'integer',
        'product_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'options' => 'json',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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
    
    public function getName(): string
    {
        $name = $this->product->name;
        
        if ($this->variant) {
            $name .= ' - ' . $this->variant->name;
        }
        
        return $name;
    }
    
    public function getSku(): string
    {
        if ($this->variant && $this->variant->sku) {
            return $this->variant->sku;
        }
        
        return $this->product->sku;
    }
    
    public function getImage(): ?string
    {
        if ($this->variant && $this->variant->image) {
            return $this->variant->image;
        }
        
        $primaryImage = $this->product->getPrimaryImage();
        return $primaryImage ? $primaryImage->url : null;
    }
    
    public function updatePrice(): void
    {
        if ($this->variant) {
            $this->price = $this->variant->price ?? $this->product->getEffectivePrice();
        } else {
            $this->price = $this->product->getEffectivePrice();
        }
        
        $this->save();
    }
    
    public function hasStock(): bool
    {
        if ($this->variant) {
            return $this->variant->hasStock($this->quantity);
        }
        
        return $this->product->hasStock($this->quantity);
    }
}