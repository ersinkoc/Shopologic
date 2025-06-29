<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Customer;

use Shopologic\Core\Auth\Models\User;
use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Shipping\Address;

class Customer extends User
{
    protected string $table = 'users'; // Same table as User
    
    /**
     * Get customer's orders
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id')->orderBy('created_at', 'desc');
    }

    /**
     * Get customer's addresses
     */
    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class, 'user_id');
    }

    /**
     * Get customer's wishlist items
     */
    public function wishlist()
    {
        return $this->hasMany(WishlistItem::class, 'user_id');
    }

    /**
     * Get default billing address
     */
    public function getDefaultBillingAddress(): ?CustomerAddress
    {
        return $this->addresses()
            ->where('type', 'billing')
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get default shipping address
     */
    public function getDefaultShippingAddress(): ?CustomerAddress
    {
        return $this->addresses()
            ->where('type', 'shipping')
            ->where('is_default', true)
            ->first();
    }

    /**
     * Add address
     */
    public function addAddress(Address $address, string $type = 'shipping', bool $isDefault = false): CustomerAddress
    {
        // If setting as default, unset other defaults
        if ($isDefault) {
            $this->addresses()
                ->where('type', $type)
                ->update(['is_default' => false]);
        }
        
        return $this->addresses()->create(array_merge(
            $address->toArray(),
            [
                'type' => $type,
                'is_default' => $isDefault,
            ]
        ));
    }

    /**
     * Get total spent
     */
    public function getTotalSpent(): float
    {
        return $this->orders()
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_PROCESSING])
            ->sum('total_amount');
    }

    /**
     * Get order count
     */
    public function getOrderCount(): int
    {
        return $this->orders()
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_PROCESSING])
            ->count();
    }

    /**
     * Get average order value
     */
    public function getAverageOrderValue(): float
    {
        $count = $this->getOrderCount();
        
        if ($count === 0) {
            return 0;
        }
        
        return $this->getTotalSpent() / $count;
    }

    /**
     * Get customer group
     */
    public function getCustomerGroup(): string
    {
        $totalSpent = $this->getTotalSpent();
        
        if ($totalSpent >= 10000) {
            return 'vip';
        } elseif ($totalSpent >= 5000) {
            return 'gold';
        } elseif ($totalSpent >= 1000) {
            return 'silver';
        }
        
        return 'regular';
    }

    /**
     * Check if customer is VIP
     */
    public function isVip(): bool
    {
        return $this->getCustomerGroup() === 'vip';
    }

    /**
     * Add item to wishlist
     */
    public function addToWishlist(int $productId): WishlistItem
    {
        // Check if already in wishlist
        $existing = $this->wishlist()
            ->where('product_id', $productId)
            ->first();
        
        if ($existing) {
            return $existing;
        }
        
        return $this->wishlist()->create([
            'product_id' => $productId,
        ]);
    }

    /**
     * Remove from wishlist
     */
    public function removeFromWishlist(int $productId): bool
    {
        return $this->wishlist()
            ->where('product_id', $productId)
            ->delete() > 0;
    }

    /**
     * Check if product is in wishlist
     */
    public function hasInWishlist(int $productId): bool
    {
        return $this->wishlist()
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * Get recently viewed products
     */
    public function getRecentlyViewed(int $limit = 10): array
    {
        // In a real implementation, this would track product views
        return [];
    }

    /**
     * Get recommended products
     */
    public function getRecommendedProducts(int $limit = 10): array
    {
        // In a real implementation, this would use purchase history and ML
        return [];
    }

    /**
     * Check if customer can review product
     */
    public function canReviewProduct(int $productId): bool
    {
        // Check if customer has purchased this product
        return $this->orders()
            ->whereIn('status', [Order::STATUS_COMPLETED])
            ->whereHas('items', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->exists();
    }

    /**
     * Get customer statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_orders' => $this->getOrderCount(),
            'total_spent' => $this->getTotalSpent(),
            'average_order_value' => $this->getAverageOrderValue(),
            'customer_group' => $this->getCustomerGroup(),
            'member_since' => $this->created_at,
            'last_order_date' => $this->orders()->first()?->created_at,
        ];
    }
}