<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards\Models;

use Shopologic\Core\Database\Model;

class RewardRedemption extends Model
{
    protected string $table = 'reward_redemptions';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'loyalty_member_id',
        'reward_id',
        'points_used',
        'discount_amount',
        'order_id',
        'status',
        'redeemed_at',
        'used_at',
        'expired_at',
        'cancelled_at',
        'refunded_at',
        'refund_reason',
        'gift_card_code',
        'gift_card_balance',
        'metadata',
        'notes'
    ];

    protected array $casts = [
        'loyalty_member_id' => 'integer',
        'reward_id' => 'integer',
        'points_used' => 'integer',
        'discount_amount' => 'decimal:2',
        'order_id' => 'integer',
        'redeemed_at' => 'datetime',
        'used_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'gift_card_balance' => 'decimal:2',
        'metadata' => 'json'
    ];

    /**
     * Redemption statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_USED = 'used';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    /**
     * Get loyalty member
     */
    public function loyaltyMember()
    {
        return $this->belongsTo(LoyaltyMember::class, 'loyalty_member_id');
    }

    /**
     * Get reward
     */
    public function reward()
    {
        return $this->belongsTo(Reward::class, 'reward_id');
    }

    /**
     * Get order
     */
    public function order()
    {
        return $this->belongsTo('Shopologic\Core\Models\Order', 'order_id');
    }

    /**
     * Scope active redemptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope used redemptions
     */
    public function scopeUsed($query)
    {
        return $query->where('status', self::STATUS_USED);
    }

    /**
     * Scope by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope expired redemptions
     */
    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED)
            ->orWhere(function($q) {
                $q->where('status', self::STATUS_ACTIVE)
                  ->where('expired_at', '<=', now());
            });
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('redeemed_at', [$startDate, $endDate]);
    }

    /**
     * Check if redemption is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && 
               (!$this->expired_at || $this->expired_at->isFuture());
    }

    /**
     * Check if redemption is used
     */
    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    /**
     * Check if redemption is expired
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED ||
               ($this->expired_at && $this->expired_at->isPast());
    }

    /**
     * Check if redemption can be used
     */
    public function canBeUsed(): bool
    {
        return $this->isActive() && !$this->isExpired();
    }

    /**
     * Check if redemption can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACTIVE]) &&
               !$this->isExpired();
    }

    /**
     * Check if redemption can be refunded
     */
    public function canBeRefunded(): bool
    {
        return $this->status === self::STATUS_USED &&
               $this->used_at &&
               $this->used_at->gt(now()->subDays(30)); // 30-day refund window
    }

    /**
     * Mark as used
     */
    public function markAsUsed(int $orderId = null): void
    {
        $this->status = self::STATUS_USED;
        $this->used_at = now();
        
        if ($orderId) {
            $this->order_id = $orderId;
        }
        
        $this->save();
    }

    /**
     * Cancel redemption
     */
    public function cancel(string $reason = null): void
    {
        if (!$this->canBeCancelled()) {
            throw new \Exception('Redemption cannot be cancelled');
        }
        
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        
        if ($reason) {
            $this->notes = $reason;
        }
        
        $this->save();
        
        // Refund points to member
        $this->loyaltyMember->addPoints(
            $this->points_used,
            'refund',
            'Cancelled reward redemption #' . $this->id
        );
    }

    /**
     * Refund redemption
     */
    public function refund(string $reason): void
    {
        if (!$this->canBeRefunded()) {
            throw new \Exception('Redemption cannot be refunded');
        }
        
        $this->status = self::STATUS_REFUNDED;
        $this->refunded_at = now();
        $this->refund_reason = $reason;
        $this->save();
        
        // Refund points to member
        $this->loyaltyMember->addPoints(
            $this->points_used,
            'refund',
            'Refunded reward redemption #' . $this->id
        );
    }

    /**
     * Expire redemption
     */
    public function expire(): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->save();
        
        // Refund points to member for expired redemptions
        $this->loyaltyMember->addPoints(
            $this->points_used,
            'refund',
            'Expired reward redemption #' . $this->id
        );
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->expired_at) {
            return null;
        }
        
        return max(0, now()->diffInDays($this->expired_at, false));
    }

    /**
     * Check if expires soon
     */
    public function expiresSoon(int $days = 7): bool
    {
        if (!$this->expired_at) {
            return false;
        }
        
        return $this->expired_at->isBefore(now()->addDays($days));
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_USED => 'Used',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded'
        ];
        
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get status color
     */
    public function getStatusColor(): string
    {
        $colors = [
            self::STATUS_PENDING => 'orange',
            self::STATUS_ACTIVE => 'green',
            self::STATUS_USED => 'blue',
            self::STATUS_EXPIRED => 'red',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_REFUNDED => 'purple'
        ];
        
        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        $metadata = $this->metadata ?? [];
        return $metadata[$key] ?? $default;
    }

    /**
     * Set metadata value
     */
    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * Generate gift card code
     */
    public function generateGiftCardCode(): string
    {
        if ($this->gift_card_code) {
            return $this->gift_card_code;
        }
        
        $code = 'GC' . strtoupper(substr(md5(uniqid()), 0, 8));
        $this->gift_card_code = $code;
        $this->gift_card_balance = $this->reward->monetary_value ?? 0;
        $this->save();
        
        return $code;
    }

    /**
     * Use gift card amount
     */
    public function useGiftCardAmount(float $amount): float
    {
        if (!$this->gift_card_code || $this->gift_card_balance <= 0) {
            return 0;
        }
        
        $usedAmount = min($amount, $this->gift_card_balance);
        $this->gift_card_balance -= $usedAmount;
        
        if ($this->gift_card_balance <= 0) {
            $this->markAsUsed();
        }
        
        $this->save();
        
        return $usedAmount;
    }

    /**
     * Calculate discount for order
     */
    public function calculateDiscountForOrder(float $orderAmount): float
    {
        if (!$this->canBeUsed()) {
            return 0;
        }
        
        return $this->reward->calculateDiscountAmount($orderAmount);
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        return [
            'id' => $this->id,
            'reward_name' => $this->reward->name,
            'reward_type' => $this->reward->type,
            'points_used' => $this->points_used,
            'discount_amount' => $this->discount_amount,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),
            'redeemed_at' => $this->redeemed_at?->format('Y-m-d H:i:s'),
            'used_at' => $this->used_at?->format('Y-m-d H:i:s'),
            'expired_at' => $this->expired_at?->format('Y-m-d H:i:s'),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'expires_soon' => $this->expiresSoon(),
            'can_be_used' => $this->canBeUsed(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_be_refunded' => $this->canBeRefunded(),
            'gift_card_code' => $this->gift_card_code,
            'gift_card_balance' => $this->gift_card_balance,
            'order_id' => $this->order_id,
            'notes' => $this->notes
        ];
    }

    /**
     * Get redemption summary for period
     */
    public static function getSummaryForPeriod(int $loyaltyMemberId, $startDate, $endDate): array
    {
        $redemptions = self::where('loyalty_member_id', $loyaltyMemberId)
            ->whereBetween('redeemed_at', [$startDate, $endDate])
            ->get();
        
        return [
            'total_redemptions' => $redemptions->count(),
            'total_points_used' => $redemptions->sum('points_used'),
            'total_discount_amount' => $redemptions->sum('discount_amount'),
            'active_redemptions' => $redemptions->where('status', self::STATUS_ACTIVE)->count(),
            'used_redemptions' => $redemptions->where('status', self::STATUS_USED)->count(),
            'expired_redemptions' => $redemptions->where('status', self::STATUS_EXPIRED)->count(),
            'cancelled_redemptions' => $redemptions->where('status', self::STATUS_CANCELLED)->count(),
            'gift_cards_generated' => $redemptions->whereNotNull('gift_card_code')->count(),
            'gift_card_balance' => $redemptions->whereNotNull('gift_card_code')->sum('gift_card_balance')
        ];
    }
}