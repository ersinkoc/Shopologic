<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards\Models;

use Shopologic\Core\Database\Model;

class Reward extends Model
{
    protected string $table = 'loyalty_rewards';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'name',
        'description',
        'type',
        'point_cost',
        'monetary_value',
        'discount_type',
        'discount_value',
        'minimum_order_amount',
        'maximum_discount',
        'category_ids',
        'product_ids',
        'tier_restrictions',
        'usage_limit_per_member',
        'total_usage_limit',
        'current_usage_count',
        'is_active',
        'starts_at',
        'expires_at',
        'terms_conditions',
        'image_url',
        'sort_order'
    ];

    protected array $casts = [
        'point_cost' => 'integer',
        'monetary_value' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
        'category_ids' => 'json',
        'product_ids' => 'json',
        'tier_restrictions' => 'json',
        'usage_limit_per_member' => 'integer',
        'total_usage_limit' => 'integer',
        'current_usage_count' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'sort_order' => 'integer'
    ];

    /**
     * Reward types
     */
    const TYPE_DISCOUNT_PERCENTAGE = 'discount_percentage';
    const TYPE_DISCOUNT_FIXED = 'discount_fixed';
    const TYPE_FREE_SHIPPING = 'free_shipping';
    const TYPE_GIFT_CARD = 'gift_card';
    const TYPE_PRODUCT = 'product';
    const TYPE_EXPERIENCE = 'experience';
    const TYPE_CHARITY = 'charity';
    const TYPE_EARLY_ACCESS = 'early_access';

    /**
     * Discount types
     */
    const DISCOUNT_PERCENTAGE = 'percentage';
    const DISCOUNT_FIXED = 'fixed';

    /**
     * Get reward redemptions
     */
    public function redemptions()
    {
        return $this->hasMany(RewardRedemption::class, 'reward_id');
    }

    /**
     * Get active redemptions
     */
    public function activeRedemptions()
    {
        return $this->redemptions()->where('status', 'active');
    }

    /**
     * Scope active rewards
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope available for tier
     */
    public function scopeForTier($query, int $tierId)
    {
        return $query->where(function($q) use ($tierId) {
            $q->whereNull('tier_restrictions')
              ->orWhereJsonContains('tier_restrictions', $tierId);
        });
    }

    /**
     * Check if reward is active
     */
    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if reward is available
     */
    public function isAvailable(): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        // Check total usage limit
        if ($this->total_usage_limit > 0 && $this->current_usage_count >= $this->total_usage_limit) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if member can redeem this reward
     */
    public function canBeRedeemedBy(LoyaltyMember $member): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        // Check if member has enough points
        if ($member->current_points < $this->point_cost) {
            return false;
        }
        
        // Check tier restrictions
        if (!$this->isAvailableForTier($member->current_tier_id)) {
            return false;
        }
        
        // Check member usage limit
        if ($this->usage_limit_per_member > 0) {
            $memberUsage = $this->redemptions()
                ->where('loyalty_member_id', $member->id)
                ->count();
            
            if ($memberUsage >= $this->usage_limit_per_member) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check if available for tier
     */
    public function isAvailableForTier(int $tierId): bool
    {
        $restrictions = $this->tier_restrictions ?? [];
        
        if (empty($restrictions)) {
            return true; // Available for all tiers
        }
        
        return in_array($tierId, $restrictions);
    }

    /**
     * Get remaining usage count
     */
    public function getRemainingUsage(): ?int
    {
        if ($this->total_usage_limit <= 0) {
            return null; // Unlimited
        }
        
        return max(0, $this->total_usage_limit - $this->current_usage_count);
    }

    /**
     * Get member usage count
     */
    public function getMemberUsageCount(int $memberId): int
    {
        return $this->redemptions()
            ->where('loyalty_member_id', $memberId)
            ->count();
    }

    /**
     * Get member remaining usage
     */
    public function getMemberRemainingUsage(int $memberId): ?int
    {
        if ($this->usage_limit_per_member <= 0) {
            return null; // Unlimited per member
        }
        
        $used = $this->getMemberUsageCount($memberId);
        return max(0, $this->usage_limit_per_member - $used);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->current_usage_count++;
        $this->save();
    }

    /**
     * Decrement usage count
     */
    public function decrementUsage(): void
    {
        if ($this->current_usage_count > 0) {
            $this->current_usage_count--;
            $this->save();
        }
    }

    /**
     * Get type label
     */
    public function getTypeLabel(): string
    {
        $labels = [
            self::TYPE_DISCOUNT_PERCENTAGE => 'Percentage Discount',
            self::TYPE_DISCOUNT_FIXED => 'Fixed Discount',
            self::TYPE_FREE_SHIPPING => 'Free Shipping',
            self::TYPE_GIFT_CARD => 'Gift Card',
            self::TYPE_PRODUCT => 'Free Product',
            self::TYPE_EXPERIENCE => 'Experience',
            self::TYPE_CHARITY => 'Charity Donation',
            self::TYPE_EARLY_ACCESS => 'Early Access'
        ];
        
        return $labels[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    /**
     * Get discount text
     */
    public function getDiscountText(): string
    {
        switch ($this->type) {
            case self::TYPE_DISCOUNT_PERCENTAGE:
                return $this->discount_value . '% off';
            case self::TYPE_DISCOUNT_FIXED:
                return '$' . number_format($this->discount_value, 2) . ' off';
            case self::TYPE_FREE_SHIPPING:
                return 'Free shipping';
            case self::TYPE_GIFT_CARD:
                return '$' . number_format($this->monetary_value, 2) . ' gift card';
            default:
                return $this->name;
        }
    }

    /**
     * Calculate discount amount for order
     */
    public function calculateDiscountAmount(float $orderAmount): float
    {
        if ($orderAmount < $this->minimum_order_amount) {
            return 0;
        }
        
        $discount = 0;
        
        switch ($this->type) {
            case self::TYPE_DISCOUNT_PERCENTAGE:
                $discount = $orderAmount * ($this->discount_value / 100);
                break;
            case self::TYPE_DISCOUNT_FIXED:
                $discount = $this->discount_value;
                break;
            case self::TYPE_FREE_SHIPPING:
                // This would be handled separately for shipping calculation
                return 0;
        }
        
        // Apply maximum discount limit
        if ($this->maximum_discount > 0) {
            $discount = min($discount, $this->maximum_discount);
        }
        
        return min($discount, $orderAmount);
    }

    /**
     * Get value display
     */
    public function getValueDisplay(): string
    {
        if ($this->monetary_value > 0) {
            return '$' . number_format($this->monetary_value, 2) . ' value';
        }
        
        return $this->getDiscountText();
    }

    /**
     * Get expiration status
     */
    public function getExpirationStatus(): array
    {
        if (!$this->expires_at) {
            return [
                'status' => 'never',
                'message' => 'Never expires'
            ];
        }
        
        $now = now();
        $daysUntilExpiry = $now->diffInDays($this->expires_at, false);
        
        if ($this->expires_at->isPast()) {
            return [
                'status' => 'expired',
                'message' => 'Expired'
            ];
        }
        
        if ($daysUntilExpiry <= 7) {
            return [
                'status' => 'expiring_soon',
                'message' => 'Expires in ' . $daysUntilExpiry . ' day' . ($daysUntilExpiry !== 1 ? 's' : '')
            ];
        }
        
        return [
            'status' => 'active',
            'message' => 'Expires ' . $this->expires_at->format('M j, Y')
        ];
    }

    /**
     * Get availability status
     */
    public function getAvailabilityStatus(): array
    {
        if (!$this->is_active) {
            return [
                'status' => 'inactive',
                'message' => 'Currently unavailable'
            ];
        }
        
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return [
                'status' => 'upcoming',
                'message' => 'Available from ' . $this->starts_at->format('M j, Y')
            ];
        }
        
        if ($this->total_usage_limit > 0) {
            $remaining = $this->getRemainingUsage();
            if ($remaining === 0) {
                return [
                    'status' => 'sold_out',
                    'message' => 'No longer available'
                ];
            }
            
            if ($remaining <= 10) {
                return [
                    'status' => 'limited',
                    'message' => 'Only ' . $remaining . ' remaining'
                ];
            }
        }
        
        return [
            'status' => 'available',
            'message' => 'Available now'
        ];
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        $expiration = $this->getExpirationStatus();
        $availability = $this->getAvailabilityStatus();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'point_cost' => $this->point_cost,
            'value_display' => $this->getValueDisplay(),
            'discount_text' => $this->getDiscountText(),
            'image_url' => $this->image_url,
            'minimum_order_amount' => $this->minimum_order_amount,
            'usage_limit_per_member' => $this->usage_limit_per_member,
            'total_usage_limit' => $this->total_usage_limit,
            'remaining_usage' => $this->getRemainingUsage(),
            'current_usage_count' => $this->current_usage_count,
            'tier_restrictions' => $this->tier_restrictions,
            'expiration' => $expiration,
            'availability' => $availability,
            'is_available' => $this->isAvailable(),
            'terms_conditions' => $this->terms_conditions
        ];
    }
}