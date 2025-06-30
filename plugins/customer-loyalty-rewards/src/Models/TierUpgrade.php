<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards\Models;

use Shopologic\Core\Database\Model;

class TierUpgrade extends Model
{
    protected string $table = 'tier_upgrades';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'loyalty_member_id',
        'previous_tier_id',
        'new_tier_id',
        'upgrade_type',
        'trigger_event',
        'points_at_upgrade',
        'spend_at_upgrade',
        'orders_at_upgrade',
        'upgrade_date',
        'qualification_data',
        'bonus_points_awarded',
        'welcome_message_sent',
        'is_automatic',
        'notes'
    ];

    protected array $casts = [
        'loyalty_member_id' => 'integer',
        'previous_tier_id' => 'integer',
        'new_tier_id' => 'integer',
        'points_at_upgrade' => 'integer',
        'spend_at_upgrade' => 'decimal:2',
        'orders_at_upgrade' => 'integer',
        'upgrade_date' => 'datetime',
        'qualification_data' => 'json',
        'bonus_points_awarded' => 'integer',
        'welcome_message_sent' => 'boolean',
        'is_automatic' => 'boolean'
    ];

    /**
     * Upgrade types
     */
    const TYPE_UPGRADE = 'upgrade';
    const TYPE_DOWNGRADE = 'downgrade';
    const TYPE_MANUAL = 'manual';
    const TYPE_AUTOMATIC = 'automatic';

    /**
     * Trigger events
     */
    const TRIGGER_POINTS_THRESHOLD = 'points_threshold';
    const TRIGGER_SPEND_THRESHOLD = 'spend_threshold';
    const TRIGGER_ORDERS_THRESHOLD = 'orders_threshold';
    const TRIGGER_MANUAL_ADJUSTMENT = 'manual_adjustment';
    const TRIGGER_ADMIN_OVERRIDE = 'admin_override';
    const TRIGGER_PROMOTION = 'promotion';
    const TRIGGER_ANNIVERSARY = 'anniversary';
    const TRIGGER_SPECIAL_EVENT = 'special_event';

    /**
     * Get loyalty member
     */
    public function loyaltyMember()
    {
        return $this->belongsTo(LoyaltyMember::class, 'loyalty_member_id');
    }

    /**
     * Get previous tier
     */
    public function previousTier()
    {
        return $this->belongsTo(LoyaltyTier::class, 'previous_tier_id');
    }

    /**
     * Get new tier
     */
    public function newTier()
    {
        return $this->belongsTo(LoyaltyTier::class, 'new_tier_id');
    }

    /**
     * Scope upgrades
     */
    public function scopeUpgrades($query)
    {
        return $query->where('upgrade_type', self::TYPE_UPGRADE);
    }

    /**
     * Scope downgrades
     */
    public function scopeDowngrades($query)
    {
        return $query->where('upgrade_type', self::TYPE_DOWNGRADE);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('upgrade_type', $type);
    }

    /**
     * Scope by trigger
     */
    public function scopeByTrigger($query, string $trigger)
    {
        return $query->where('trigger_event', $trigger);
    }

    /**
     * Scope automatic upgrades
     */
    public function scopeAutomatic($query)
    {
        return $query->where('is_automatic', true);
    }

    /**
     * Scope manual upgrades
     */
    public function scopeManual($query)
    {
        return $query->where('is_automatic', false);
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('upgrade_date', [$startDate, $endDate]);
    }

    /**
     * Check if this is an upgrade
     */
    public function isUpgrade(): bool
    {
        if (!$this->previousTier || !$this->newTier) {
            return false;
        }
        
        return $this->newTier->level > $this->previousTier->level;
    }

    /**
     * Check if this is a downgrade
     */
    public function isDowngrade(): bool
    {
        if (!$this->previousTier || !$this->newTier) {
            return false;
        }
        
        return $this->newTier->level < $this->previousTier->level;
    }

    /**
     * Get tier level change
     */
    public function getTierLevelChange(): int
    {
        if (!$this->previousTier || !$this->newTier) {
            return 0;
        }
        
        return $this->newTier->level - $this->previousTier->level;
    }

    /**
     * Get upgrade direction
     */
    public function getDirection(): string
    {
        $change = $this->getTierLevelChange();
        
        if ($change > 0) {
            return 'upgrade';
        } elseif ($change < 0) {
            return 'downgrade';
        } else {
            return 'lateral';
        }
    }

    /**
     * Get qualification data value
     */
    public function getQualificationData(string $key, $default = null)
    {
        $data = $this->qualification_data ?? [];
        return $data[$key] ?? $default;
    }

    /**
     * Set qualification data value
     */
    public function setQualificationData(string $key, $value): void
    {
        $data = $this->qualification_data ?? [];
        $data[$key] = $value;
        $this->qualification_data = $data;
        $this->save();
    }

    /**
     * Mark welcome message as sent
     */
    public function markWelcomeMessageSent(): void
    {
        $this->welcome_message_sent = true;
        $this->save();
    }

    /**
     * Get trigger event label
     */
    public function getTriggerEventLabel(): string
    {
        $labels = [
            self::TRIGGER_POINTS_THRESHOLD => 'Points Threshold Met',
            self::TRIGGER_SPEND_THRESHOLD => 'Spend Threshold Met',
            self::TRIGGER_ORDERS_THRESHOLD => 'Orders Threshold Met',
            self::TRIGGER_MANUAL_ADJUSTMENT => 'Manual Adjustment',
            self::TRIGGER_ADMIN_OVERRIDE => 'Admin Override',
            self::TRIGGER_PROMOTION => 'Promotional Upgrade',
            self::TRIGGER_ANNIVERSARY => 'Anniversary Upgrade',
            self::TRIGGER_SPECIAL_EVENT => 'Special Event'
        ];
        
        return $labels[$this->trigger_event] ?? ucfirst(str_replace('_', ' ', $this->trigger_event));
    }

    /**
     * Get upgrade type label
     */
    public function getUpgradeTypeLabel(): string
    {
        $labels = [
            self::TYPE_UPGRADE => 'Upgrade',
            self::TYPE_DOWNGRADE => 'Downgrade',
            self::TYPE_MANUAL => 'Manual',
            self::TYPE_AUTOMATIC => 'Automatic'
        ];
        
        return $labels[$this->upgrade_type] ?? ucfirst($this->upgrade_type);
    }

    /**
     * Get upgrade icon
     */
    public function getUpgradeIcon(): string
    {
        $direction = $this->getDirection();
        
        $icons = [
            'upgrade' => '⬆️',
            'downgrade' => '⬇️',
            'lateral' => '↔️'
        ];
        
        return $icons[$direction] ?? '🔄';
    }

    /**
     * Get upgrade color
     */
    public function getUpgradeColor(): string
    {
        $direction = $this->getDirection();
        
        $colors = [
            'upgrade' => 'green',
            'downgrade' => 'red',
            'lateral' => 'blue'
        ];
        
        return $colors[$direction] ?? 'gray';
    }

    /**
     * Calculate tier retention period
     */
    public function calculateRetentionPeriod(): ?int
    {
        if (!$this->newTier || !$this->newTier->retention_period_days) {
            return null;
        }
        
        return $this->newTier->retention_period_days;
    }

    /**
     * Get tier benefits gained
     */
    public function getBenefitsGained(): array
    {
        if (!$this->previousTier || !$this->newTier) {
            return [];
        }
        
        $previousBenefits = $this->previousTier->benefits ?? [];
        $newBenefits = $this->newTier->benefits ?? [];
        
        $gained = [];
        foreach ($newBenefits as $key => $value) {
            if (!isset($previousBenefits[$key]) || $previousBenefits[$key] !== $value) {
                $gained[$key] = $value;
            }
        }
        
        return $gained;
    }

    /**
     * Get tier benefits lost
     */
    public function getBenefitsLost(): array
    {
        if (!$this->previousTier || !$this->newTier) {
            return [];
        }
        
        $previousBenefits = $this->previousTier->benefits ?? [];
        $newBenefits = $this->newTier->benefits ?? [];
        
        $lost = [];
        foreach ($previousBenefits as $key => $value) {
            if (!isset($newBenefits[$key])) {
                $lost[$key] = $value;
            }
        }
        
        return $lost;
    }

    /**
     * Generate upgrade summary
     */
    public function generateSummary(): array
    {
        return [
            'member_name' => $this->loyaltyMember->name ?? 'Unknown',
            'previous_tier' => $this->previousTier->name ?? 'None',
            'new_tier' => $this->newTier->name ?? 'None',
            'direction' => $this->getDirection(),
            'level_change' => $this->getTierLevelChange(),
            'trigger' => $this->getTriggerEventLabel(),
            'qualification_met' => $this->getQualificationSummary(),
            'benefits_gained' => $this->getBenefitsGained(),
            'benefits_lost' => $this->getBenefitsLost(),
            'bonus_points' => $this->bonus_points_awarded,
            'is_automatic' => $this->is_automatic,
            'upgrade_date' => $this->upgrade_date->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Get qualification summary
     */
    private function getQualificationSummary(): array
    {
        $summary = [];
        
        if ($this->points_at_upgrade > 0) {
            $summary['points'] = $this->points_at_upgrade . ' points';
        }
        
        if ($this->spend_at_upgrade > 0) {
            $summary['spend'] = '$' . number_format($this->spend_at_upgrade, 2) . ' total spend';
        }
        
        if ($this->orders_at_upgrade > 0) {
            $summary['orders'] = $this->orders_at_upgrade . ' orders';
        }
        
        return $summary;
    }

    /**
     * Award bonus points
     */
    public function awardBonusPoints(int $points, string $reason = null): void
    {
        if ($points <= 0) {
            return;
        }
        
        $this->bonus_points_awarded = $points;
        $this->save();
        
        $this->loyaltyMember->addPoints(
            $points,
            'bonus',
            $reason ?? 'Tier upgrade bonus for reaching ' . $this->newTier->name
        );
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        return [
            'id' => $this->id,
            'member_name' => $this->loyaltyMember->name ?? 'Unknown',
            'previous_tier' => $this->previousTier->name ?? 'None',
            'new_tier' => $this->newTier->name ?? 'None',
            'upgrade_type' => $this->upgrade_type,
            'upgrade_type_label' => $this->getUpgradeTypeLabel(),
            'direction' => $this->getDirection(),
            'level_change' => $this->getTierLevelChange(),
            'trigger_event' => $this->trigger_event,
            'trigger_label' => $this->getTriggerEventLabel(),
            'upgrade_date' => $this->upgrade_date->format('Y-m-d H:i:s'),
            'points_at_upgrade' => $this->points_at_upgrade,
            'spend_at_upgrade' => $this->spend_at_upgrade,
            'orders_at_upgrade' => $this->orders_at_upgrade,
            'bonus_points_awarded' => $this->bonus_points_awarded,
            'welcome_message_sent' => $this->welcome_message_sent,
            'is_automatic' => $this->is_automatic,
            'icon' => $this->getUpgradeIcon(),
            'color' => $this->getUpgradeColor(),
            'notes' => $this->notes
        ];
    }

    /**
     * Get upgrade statistics for period
     */
    public static function getStatisticsForPeriod($startDate, $endDate): array
    {
        $upgrades = self::whereBetween('upgrade_date', [$startDate, $endDate])->get();
        
        return [
            'total_upgrades' => $upgrades->count(),
            'automatic_upgrades' => $upgrades->where('is_automatic', true)->count(),
            'manual_upgrades' => $upgrades->where('is_automatic', false)->count(),
            'tier_upgrades' => $upgrades->where('upgrade_type', self::TYPE_UPGRADE)->count(),
            'tier_downgrades' => $upgrades->where('upgrade_type', self::TYPE_DOWNGRADE)->count(),
            'total_bonus_points' => $upgrades->sum('bonus_points_awarded'),
            'unique_members' => $upgrades->unique('loyalty_member_id')->count(),
            'most_common_trigger' => $upgrades->groupBy('trigger_event')
                ->map->count()
                ->sortDesc()
                ->keys()
                ->first(),
            'average_upgrade_level' => $upgrades->where('upgrade_type', self::TYPE_UPGRADE)
                ->map(function($upgrade) {
                    return $upgrade->getTierLevelChange();
                })
                ->avg()
        ];
    }
}