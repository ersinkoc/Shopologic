<?php

declare(strict_types=1);
namespace Shopologic\Plugins\CoreCommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Relations\HasMany;
use Shopologic\Core\Database\Relations\BelongsTo;

class Cart extends Model
{
    protected string $table = 'carts';
    
    protected array $fillable = [
        'session_id', 'customer_id', 'currency',
        'subtotal', 'tax_amount', 'shipping_amount',
        'discount_amount', 'total', 'promo_codes',
        'shipping_method', 'shipping_address', 'billing_address',
        'customer_notes', 'metadata'
    ];
    
    protected array $casts = [
        'customer_id' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'promo_codes' => 'json',
        'shipping_address' => 'json',
        'billing_address' => 'json',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
    
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    
    public function getItemCount(): int
    {
        return $this->items->sum('quantity');
    }
    
    public function isEmpty(): bool
    {
        return $this->items->count() === 0;
    }
    
    public function calculateSubtotal(): float
    {
        $subtotal = 0;
        
        foreach ($this->items as $item) {
            $subtotal += $item->price * $item->quantity;
        }
        
        return $subtotal;
    }
    
    public function calculateTax(): float
    {
        // Simple tax calculation - can be enhanced with tax rules
        $taxRate = config('commerce.tax_rate', 0.10); // 10% default
        return $this->calculateSubtotal() * $taxRate;
    }
    
    public function calculateTotal(): float
    {
        $total = $this->calculateSubtotal();
        $total += $this->tax_amount ?? $this->calculateTax();
        $total += $this->shipping_amount ?? 0;
        $total -= $this->discount_amount ?? 0;
        
        return max(0, $total);
    }
    
    public function updateTotals(): void
    {
        $this->subtotal = $this->calculateSubtotal();
        $this->tax_amount = $this->calculateTax();
        $this->total = $this->calculateTotal();
        $this->save();
    }
    
    public function hasPromoCode(string $code): bool
    {
        $codes = $this->promo_codes ?? [];
        return in_array($code, $codes);
    }
    
    public function addPromoCode(string $code): void
    {
        $codes = $this->promo_codes ?? [];
        if (!in_array($code, $codes)) {
            $codes[] = $code;
            $this->promo_codes = $codes;
            $this->save();
        }
    }
    
    public function removePromoCode(string $code): void
    {
        $codes = $this->promo_codes ?? [];
        $codes = array_values(array_diff($codes, [$code]));
        $this->promo_codes = $codes;
        $this->save();
    }
    
    public function clear(): void
    {
        $this->items()->delete();
        $this->updateTotals();
    }
    
    public function isAbandoned(int $hours = 24): bool
    {
        if ($this->isEmpty()) {
            return false;
        }
        
        return $this->updated_at->diffInHours(now()) >= $hours;
    }
    
    public function merge(Cart $otherCart): void
    {
        foreach ($otherCart->items as $otherItem) {
            $existingItem = $this->items()
                ->where('product_id', $otherItem->product_id)
                ->where('variant_id', $otherItem->variant_id)
                ->first();
            
            if ($existingItem) {
                $existingItem->quantity += $otherItem->quantity;
                $existingItem->save();
            } else {
                $newItem = $otherItem->replicate();
                $newItem->cart_id = $this->id;
                $newItem->save();
            }
        }
        
        // Merge promo codes
        $promoCodes = array_unique(array_merge(
            $this->promo_codes ?? [],
            $otherCart->promo_codes ?? []
        ));
        $this->promo_codes = array_values($promoCodes);
        
        $this->updateTotals();
    }
}