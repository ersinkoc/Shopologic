<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards\Models;

use Shopologic\Core\Database\Model;

class LoyaltyTier extends Model
{
    protected string $table = 'loyalty_tiers';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'name',
        'slug',
        'description',
        'level',
        'min_points',
        'min_spend',
        'min_orders',
        'point_multiplier',
        'benefits',
        'perks',
        'badge_color',
        'badge_icon',
        'is_active',
        'welcome_message',
        'upgrade_message',
        'retention_period_days'
    ];

    protected array $casts = [
        'level' => 'integer',
        'min_points' => 'integer',
        'min_spend' => 'decimal:2',
        'min_orders' => 'integer',
        'point_multiplier' => 'decimal:2',
        'benefits' => 'json',
        'perks' => 'json',
        'is_active' => 'boolean',
        'retention_period_days' => 'integer'
    ];

    /**
     * Get loyalty members in this tier
     */
    public function members()
    {
        return $this->hasMany(LoyaltyMember::class, 'current_tier_id');
    }

    /**
     * Get tier upgrades to this tier
     */
    public function upgrades()
    {
        return $this->hasMany(TierUpgrade::class, 'new_tier_id');
    }

    /**
     * Get tier downgrades from this tier
     */
    public function downgrades()
    {
        return $this->hasMany(TierUpgrade::class, 'previous_tier_id');
    }

    /**
     * Scope active tiers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by level
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Get next tier
     */
    public function getNextTier(): ?self
    {
        return self::where('level', '>', $this->level)
            ->where('is_active', true)
            ->orderBy('level')
            ->first();
    }

    /**
     * Get previous tier
     */
    public function getPreviousTier(): ?self
    {
        return self::where('level', '<', $this->level)
            ->where('is_active', true)
            ->orderBy('level', 'desc')
            ->first();
    }

    /**
     * Check if member qualifies for this tier
     */
    public function memberQualifies(LoyaltyMember $member): bool
    {
        // Check points requirement
        if ($this->min_points > 0 && $member->total_points < $this->min_points) {
            return false;
        }
        
        // Check spend requirement
        if ($this->min_spend > 0 && $member->total_spend < $this->min_spend) {
            return false;
        }
        
        // Check orders requirement
        if ($this->min_orders > 0 && $member->total_orders < $this->min_orders) {
            return false;
        }
        
        return true;
    }

    /**
     * Get qualification progress for member
     */
    public function getQualificationProgress(LoyaltyMember $member): array
    {
        $progress = [];
        
        if ($this->min_points > 0) {
            $progress['points'] = [
                'current' => $member->total_points,
                'required' => $this->min_points,
                'percentage' => min(100, ($member->total_points / $this->min_points) * 100),
                'remaining' => max(0, $this->min_points - $member->total_points)
            ];
        }
        
        if ($this->min_spend > 0) {
            $progress['spend'] = [
                'current' => $member->total_spend,
                'required' => $this->min_spend,
                'percentage' => min(100, ($member->total_spend / $this->min_spend) * 100),
                'remaining' => max(0, $this->min_spend - $member->total_spend)
            ];
        }
        
        if ($this->min_orders > 0) {
            $progress['orders'] = [
                'current' => $member->total_orders,
                'required' => $this->min_orders,
                'percentage' => min(100, ($member->total_orders / $this->min_orders) * 100),
                'remaining' => max(0, $this->min_orders - $member->total_orders)
            ];
        }
        
        return $progress;
    }

    /**
     * Get benefit by key
     */
    public function getBenefit(string $key): mixed
    {
        $benefits = $this->benefits ?? [];
        return $benefits[$key] ?? null;
    }

    /**
     * Has benefit
     */
    public function hasBenefit(string $key): bool
    {
        $benefits = $this->benefits ?? [];
        return isset($benefits[$key]) && $benefits[$key] !== false;
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentage(): float
    {
        return (float)$this->getBenefit('discount_percentage') ?? 0;
    }

    /**
     * Get free shipping threshold
     */
    public function getFreeShippingThreshold(): ?float
    {
        $threshold = $this->getBenefit('free_shipping_threshold');
        return $threshold !== null ? (float)$threshold : null;
    }

    /**
     * Has free shipping
     */
    public function hasFreeShipping(): bool
    {
        return $this->hasBenefit('free_shipping') || $this->getFreeShippingThreshold() !== null;
    }

    /**
     * Get early access hours
     */
    public function getEarlyAccessHours(): int
    {
        return (int)$this->getBenefit('early_access_hours') ?? 0;
    }

    /**
     * Has early access
     */
    public function hasEarlyAccess(): bool
    {
        return $this->getEarlyAccessHours() > 0;
    }

    /**
     * Get birthday bonus points
     */
    public function getBirthdayBonusPoints(): int
    {
        return (int)$this->getBenefit('birthday_bonus_points') ?? 0;
    }

    /**
     * Get priority support level
     */
    public function getPrioritySupportLevel(): ?string
    {
        return $this->getBenefit('priority_support');
    }

    /**
     * Has priority support
     */
    public function hasPrioritySupport(): bool
    {
        return $this->getPrioritySupportLevel() !== null;
    }

    /**
     * Get exclusive products access
     */
    public function hasExclusiveAccess(): bool
    {
        return $this->hasBenefit('exclusive_products');
    }

    /**
     * Get member count
     */
    public function getMemberCount(): int
    {
        return $this->members()->count();
    }

    /**
     * Get active member count
     */
    public function getActiveMemberCount(): int
    {
        return $this->members()->where('status', 'active')->count();
    }

    /**
     * Get tier statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_members' => $this->getMemberCount(),
            'active_members' => $this->getActiveMemberCount(),
            'avg_points' => $this->members()->avg('current_points') ?? 0,
            'avg_spend' => $this->members()->avg('total_spend') ?? 0,
            'retention_rate' => $this->calculateRetentionRate(),
            'upgrade_rate' => $this->calculateUpgradeRate(),
            'point_multiplier' => $this->point_multiplier
        ];
    }

    /**
     * Calculate retention rate
     */
    private function calculateRetentionRate(): float
    {
        $totalMembers = $this->getMemberCount();
        if ($totalMembers === 0) {
            return 0;
        }
        
        $activeMembers = $this->getActiveMemberCount();
        return ($activeMembers / $totalMembers) * 100;
    }

    /**
     * Calculate upgrade rate
     */
    private function calculateUpgradeRate(): float
    {
        $nextTier = $this->getNextTier();
        if (!$nextTier) {
            return 0; // Highest tier
        }
        
        $eligibleMembers = $this->members()
            ->where('status', 'active')
            ->get()
            ->filter(fn($member) => $nextTier->memberQualifies($member))
            ->count();
        
        $totalMembers = $this->getActiveMemberCount();
        return $totalMembers > 0 ? ($eligibleMembers / $totalMembers) * 100 : 0;
    }

    /**
     * Activate tier
     */
    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    /**
     * Deactivate tier
     */
    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Get badge HTML
     */
    public function getBadgeHtml(): string
    {
        $color = $this->badge_color ?? '#3B82F6';
        $icon = $this->badge_icon ?? '★';
        
        return sprintf(
            '<span class="tier-badge" style="background-color: %s; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                %s %s
            </span>',
            $color,
            $icon,
            $this->name
        );
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'description' => $this->description,
            'requirements' => [
                'points' => $this->min_points,
                'spend' => $this->min_spend,
                'orders' => $this->min_orders
            ],
            'benefits' => $this->benefits ?? [],
            'perks' => $this->perks ?? [],
            'point_multiplier' => $this->point_multiplier,
            'badge' => [
                'color' => $this->badge_color,
                'icon' => $this->badge_icon,
                'html' => $this->getBadgeHtml()
            ],
            'member_count' => $this->getMemberCount(),
            'is_active' => $this->is_active
        ];
    }
}