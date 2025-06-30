<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards\Models;

use Shopologic\Core\Database\Model;

class PointTransaction extends Model
{
    protected string $table = 'point_transactions';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'loyalty_member_id',
        'type',
        'points',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'reason',
        'description',
        'expires_at',
        'processed_at',
        'admin_user_id',
        'metadata'
    ];

    protected array $casts = [
        'loyalty_member_id' => 'integer',
        'points' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'reference_id' => 'integer',
        'expires_at' => 'datetime',
        'processed_at' => 'datetime',
        'admin_user_id' => 'integer',
        'metadata' => 'json'
    ];

    /**
     * Transaction types
     */
    const TYPE_EARNED = 'earned';
    const TYPE_REDEEMED = 'redeemed';
    const TYPE_EXPIRED = 'expired';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_BONUS = 'bonus';
    const TYPE_REFUND = 'refund';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_TRANSFER_OUT = 'transfer_out';

    /**
     * Reasons for earning points
     */
    const REASON_PURCHASE = 'purchase';
    const REASON_SIGNUP = 'signup';
    const REASON_BIRTHDAY = 'birthday';
    const REASON_REFERRAL = 'referral';
    const REASON_REVIEW = 'review';
    const REASON_SOCIAL_SHARE = 'social_share';
    const REASON_NEWSLETTER = 'newsletter';
    const REASON_SURVEY = 'survey';
    const REASON_BONUS = 'bonus';
    const REASON_ADMIN_ADJUSTMENT = 'admin_adjustment';

    /**
     * Reasons for redeeming points
     */
    const REASON_DISCOUNT = 'discount';
    const REASON_REWARD = 'reward';
    const REASON_GIFT_CARD = 'gift_card';
    const REASON_CHARITY = 'charity';
    const REASON_TRANSFER = 'transfer';

    /**
     * Get loyalty member
     */
    public function loyaltyMember()
    {
        return $this->belongsTo(LoyaltyMember::class, 'loyalty_member_id');
    }

    /**
     * Get admin user
     */
    public function adminUser()
    {
        return $this->belongsTo('Shopologic\Core\Models\User', 'admin_user_id');
    }

    /**
     * Get reference model (polymorphic)
     */
    public function reference()
    {
        return $this->morphTo('reference');
    }

    /**
     * Scope earned points
     */
    public function scopeEarned($query)
    {
        return $query->where('type', self::TYPE_EARNED);
    }

    /**
     * Scope redeemed points
     */
    public function scopeRedeemed($query)
    {
        return $query->where('type', self::TYPE_REDEEMED);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by reason
     */
    public function scopeByReason($query, string $reason)
    {
        return $query->where('reason', $reason);
    }

    /**
     * Scope active points (not expired)
     */
    public function scopeActive($query)
    {
        return $query->where(function($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope expired points
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Check if transaction is earning points
     */
    public function isEarning(): bool
    {
        return $this->type === self::TYPE_EARNED || $this->points > 0;
    }

    /**
     * Check if transaction is spending points
     */
    public function isSpending(): bool
    {
        return in_array($this->type, [self::TYPE_REDEEMED, self::TYPE_TRANSFER_OUT]) || $this->points < 0;
    }

    /**
     * Check if points are expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if points will expire soon
     */
    public function expiresSoon(int $days = 30): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return $this->expires_at->isBefore(now()->addDays($days));
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        
        return max(0, now()->diffInDays($this->expires_at, false));
    }

    /**
     * Get absolute points value
     */
    public function getAbsolutePoints(): int
    {
        return abs($this->points);
    }

    /**
     * Get transaction direction
     */
    public function getDirection(): string
    {
        return $this->points >= 0 ? 'credit' : 'debit';
    }

    /**
     * Get type label
     */
    public function getTypeLabel(): string
    {
        $labels = [
            self::TYPE_EARNED => 'Earned',
            self::TYPE_REDEEMED => 'Redeemed',
            self::TYPE_EXPIRED => 'Expired',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_BONUS => 'Bonus',
            self::TYPE_REFUND => 'Refund',
            self::TYPE_TRANSFER_IN => 'Transfer In',
            self::TYPE_TRANSFER_OUT => 'Transfer Out'
        ];
        
        return $labels[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get reason label
     */
    public function getReasonLabel(): string
    {
        $labels = [
            self::REASON_PURCHASE => 'Purchase',
            self::REASON_SIGNUP => 'Account Signup',
            self::REASON_BIRTHDAY => 'Birthday Bonus',
            self::REASON_REFERRAL => 'Referral',
            self::REASON_REVIEW => 'Product Review',
            self::REASON_SOCIAL_SHARE => 'Social Share',
            self::REASON_NEWSLETTER => 'Newsletter Signup',
            self::REASON_SURVEY => 'Survey Completion',
            self::REASON_BONUS => 'Special Bonus',
            self::REASON_ADMIN_ADJUSTMENT => 'Admin Adjustment',
            self::REASON_DISCOUNT => 'Discount Applied',
            self::REASON_REWARD => 'Reward Redeemed',
            self::REASON_GIFT_CARD => 'Gift Card',
            self::REASON_CHARITY => 'Charity Donation',
            self::REASON_TRANSFER => 'Point Transfer'
        ];
        
        return $labels[$this->reason] ?? ucfirst(str_replace('_', ' ', $this->reason));
    }

    /**
     * Get icon for transaction type
     */
    public function getIcon(): string
    {
        $icons = [
            self::TYPE_EARNED => '⬆️',
            self::TYPE_REDEEMED => '⬇️',
            self::TYPE_EXPIRED => '⏰',
            self::TYPE_ADJUSTMENT => '⚖️',
            self::TYPE_BONUS => '🎁',
            self::TYPE_REFUND => '↩️',
            self::TYPE_TRANSFER_IN => '📥',
            self::TYPE_TRANSFER_OUT => '📤'
        ];
        
        return $icons[$this->type] ?? '💰';
    }

    /**
     * Get color for transaction type
     */
    public function getColor(): string
    {
        $colors = [
            self::TYPE_EARNED => 'green',
            self::TYPE_REDEEMED => 'blue',
            self::TYPE_EXPIRED => 'red',
            self::TYPE_ADJUSTMENT => 'orange',
            self::TYPE_BONUS => 'purple',
            self::TYPE_REFUND => 'teal',
            self::TYPE_TRANSFER_IN => 'green',
            self::TYPE_TRANSFER_OUT => 'orange'
        ];
        
        return $colors[$this->type] ?? 'gray';
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
     * Mark as processed
     */
    public function markAsProcessed(): void
    {
        $this->processed_at = now();
        $this->save();
    }

    /**
     * Create reversal transaction
     */
    public function createReversal(string $reason = null): self
    {
        $reversal = new self([
            'loyalty_member_id' => $this->loyalty_member_id,
            'type' => $this->type === self::TYPE_EARNED ? self::TYPE_ADJUSTMENT : self::TYPE_REFUND,
            'points' => -$this->points,
            'reason' => $reason ?? 'Reversal of transaction #' . $this->id,
            'description' => 'Reversal of: ' . $this->description,
            'reference_type' => 'point_transaction',
            'reference_id' => $this->id,
            'metadata' => ['original_transaction_id' => $this->id]
        ]);
        
        $reversal->save();
        
        return $reversal;
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'reason' => $this->reason,
            'reason_label' => $this->getReasonLabel(),
            'points' => $this->points,
            'absolute_points' => $this->getAbsolutePoints(),
            'direction' => $this->getDirection(),
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'description' => $this->description,
            'reference' => $this->reference_type ? $this->reference_type . '#' . $this->reference_id : null,
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'expires_soon' => $this->expiresSoon(),
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'admin_user' => $this->adminUser?->name
        ];
    }

    /**
     * Get transaction summary for period
     */
    public static function getSummaryForPeriod(int $loyaltyMemberId, $startDate, $endDate): array
    {
        $transactions = self::where('loyalty_member_id', $loyaltyMemberId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
        
        return [
            'total_earned' => $transactions->where('type', self::TYPE_EARNED)->sum('points'),
            'total_redeemed' => abs($transactions->where('type', self::TYPE_REDEEMED)->sum('points')),
            'total_expired' => abs($transactions->where('type', self::TYPE_EXPIRED)->sum('points')),
            'total_bonus' => $transactions->where('type', self::TYPE_BONUS)->sum('points'),
            'transaction_count' => $transactions->count(),
            'net_change' => $transactions->sum('points')
        ];
    }
}