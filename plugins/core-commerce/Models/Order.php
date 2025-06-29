<?php

namespace Shopologic\Plugins\CoreCommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Relations\HasMany;
use Shopologic\Core\Database\Relations\BelongsTo;
use Shopologic\Core\Database\Builder;

class Order extends Model
{
    protected string $table = 'orders';
    
    protected array $fillable = [
        'order_number', 'customer_id', 'customer_email',
        'customer_name', 'status', 'payment_status',
        'payment_method', 'payment_transaction_id',
        'shipping_method', 'tracking_number',
        'currency', 'subtotal', 'tax_amount',
        'shipping_amount', 'discount_amount', 'total',
        'promo_codes', 'shipping_address', 'billing_address',
        'customer_notes', 'admin_notes', 'metadata',
        'paid_at', 'shipped_at', 'delivered_at', 'cancelled_at'
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
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }
    
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
    
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }
    
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
    
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid');
    }
    
    public function scopeDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
    
    public function generateOrderNumber(): string
    {
        $prefix = config('commerce.order_number_prefix', 'ORD');
        $timestamp = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -4));
        
        return "{$prefix}-{$timestamp}-{$random}";
    }
    
    public function getItemCount(): int
    {
        return $this->items->sum('quantity');
    }
    
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }
    
    public function canBeRefunded(): bool
    {
        return $this->payment_status === 'paid' && 
               !in_array($this->status, ['cancelled', 'refunded']);
    }
    
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
    
    public function isShipped(): bool
    {
        return in_array($this->status, ['shipped', 'delivered']);
    }
    
    public function isCompleted(): bool
    {
        return $this->status === 'completed' || $this->status === 'delivered';
    }
    
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
    
    public function addStatusHistory(string $status, ?string $comment = null, ?int $userId = null): void
    {
        $this->statusHistory()->create([
            'from_status' => $this->status,
            'to_status' => $status,
            'comment' => $comment,
            'user_id' => $userId,
            'created_at' => now()
        ]);
    }
    
    public function updateStatus(string $status, ?string $comment = null): void
    {
        $oldStatus = $this->status;
        $this->status = $status;
        
        // Update status timestamps
        switch ($status) {
            case 'paid':
                $this->paid_at = now();
                break;
            case 'shipped':
                $this->shipped_at = now();
                break;
            case 'delivered':
                $this->delivered_at = now();
                break;
            case 'cancelled':
                $this->cancelled_at = now();
                break;
        }
        
        $this->save();
        
        // Add to history
        $this->addStatusHistory($status, $comment);
        
        // Dispatch event
        event('order.status_changed', [
            'order' => $this,
            'old_status' => $oldStatus,
            'new_status' => $status
        ]);
    }
    
    public function getFormattedAddress(string $type = 'shipping'): string
    {
        $address = $type === 'billing' ? $this->billing_address : $this->shipping_address;
        
        if (!$address) {
            return '';
        }
        
        $parts = [];
        
        if (!empty($address['name'])) {
            $parts[] = $address['name'];
        }
        
        if (!empty($address['company'])) {
            $parts[] = $address['company'];
        }
        
        if (!empty($address['line1'])) {
            $parts[] = $address['line1'];
        }
        
        if (!empty($address['line2'])) {
            $parts[] = $address['line2'];
        }
        
        $cityLine = [];
        if (!empty($address['city'])) {
            $cityLine[] = $address['city'];
        }
        if (!empty($address['state'])) {
            $cityLine[] = $address['state'];
        }
        if (!empty($address['postal_code'])) {
            $cityLine[] = $address['postal_code'];
        }
        
        if (!empty($cityLine)) {
            $parts[] = implode(', ', $cityLine);
        }
        
        if (!empty($address['country'])) {
            $parts[] = $address['country'];
        }
        
        return implode("\n", $parts);
    }
}