<?php

declare(strict_types=1);
namespace Shopologic\Plugins\CoreCommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Relations\HasMany;
use Shopologic\Core\Database\Relations\BelongsToMany;
use Shopologic\Core\Database\Builder;

class Customer extends Model
{
    protected string $table = 'customers';
    
    protected array $fillable = [
        'email', 'password', 'first_name', 'last_name',
        'phone', 'date_of_birth', 'gender', 'status',
        'email_verified_at', 'phone_verified_at',
        'accepts_marketing', 'last_login_at',
        'total_spent', 'order_count', 'average_order_value',
        'metadata', 'preferences'
    ];
    
    protected array $casts = [
        'date_of_birth' => 'date',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'accepts_marketing' => 'boolean',
        'total_spent' => 'decimal:2',
        'order_count' => 'integer',
        'average_order_value' => 'decimal:2',
        'metadata' => 'json',
        'preferences' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    protected array $hidden = [
        'password'
    ];
    
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy('created_at', 'desc');
    }
    
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }
    
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }
    
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'customer_group_members')
                    ->withTimestamps();
    }
    
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }
    
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
    
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
    
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at');
    }
    
    public function scopeAcceptsMarketing(Builder $query): Builder
    {
        return $query->where('accepts_marketing', true);
    }
    
    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
    
    public function getDefaultBillingAddress(): ?CustomerAddress
    {
        return $this->addresses()
                    ->where('type', 'billing')
                    ->where('is_default', true)
                    ->first();
    }
    
    public function getDefaultShippingAddress(): ?CustomerAddress
    {
        return $this->addresses()
                    ->where('type', 'shipping')
                    ->where('is_default', true)
                    ->first();
    }
    
    public function hasCompletedOrders(): bool
    {
        return $this->orders()
                    ->whereIn('status', ['completed', 'delivered'])
                    ->exists();
    }
    
    public function getLifetimeValue(): float
    {
        return (float) $this->total_spent;
    }
    
    public function updateStatistics(): void
    {
        $stats = $this->orders()
                      ->whereIn('status', ['completed', 'delivered'])
                      ->selectRaw('COUNT(*) as count, SUM(total) as total')
                      ->first();
        
        $this->order_count = $stats->count ?? 0;
        $this->total_spent = $stats->total ?? 0;
        $this->average_order_value = $this->order_count > 0 
            ? $this->total_spent / $this->order_count 
            : 0;
        
        $this->save();
    }
    
    public function isInGroup(string $groupCode): bool
    {
        return $this->groups()->where('code', $groupCode)->exists();
    }
    
    public function hasActiveCart(): bool
    {
        return $this->carts()
                    ->whereDate('updated_at', '>=', now()->subDays(30))
                    ->exists();
    }
    
    public function getActiveCart(): ?Cart
    {
        return $this->carts()
                    ->whereDate('updated_at', '>=', now()->subDays(30))
                    ->latest()
                    ->first();
    }
    
    public function canReceiveMarketing(): bool
    {
        return $this->accepts_marketing && 
               $this->status === 'active' &&
               $this->email_verified_at !== null;
    }
}