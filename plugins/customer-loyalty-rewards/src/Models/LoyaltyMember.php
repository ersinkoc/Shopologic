<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards\Models;

use JsonSerializable;

class LoyaltyMember implements JsonSerializable
{
    private int $id;
    private int $customerId;
    private ?int $tierId;
    private string $memberNumber;
    private string $status;
    private int $pointsBalance;
    private int $lifetimePointsEarned;
    private int $lifetimePointsRedeemed;
    private float $totalSpent;
    private float $annualSpent;
    private int $totalOrders;
    private int $totalReferrals;
    private ?string $tierExpiryDate;
    private ?string $lastActivityAt;
    private ?string $anniversaryDate;
    private ?array $preferences;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(
        int $id,
        int $customerId,
        ?int $tierId,
        string $memberNumber,
        string $status,
        int $pointsBalance,
        int $lifetimePointsEarned,
        int $lifetimePointsRedeemed,
        float $totalSpent,
        float $annualSpent,
        int $totalOrders,
        int $totalReferrals,
        ?string $tierExpiryDate,
        ?string $lastActivityAt,
        ?string $anniversaryDate,
        ?array $preferences,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->tierId = $tierId;
        $this->memberNumber = $memberNumber;
        $this->status = $status;
        $this->pointsBalance = $pointsBalance;
        $this->lifetimePointsEarned = $lifetimePointsEarned;
        $this->lifetimePointsRedeemed = $lifetimePointsRedeemed;
        $this->totalSpent = $totalSpent;
        $this->annualSpent = $annualSpent;
        $this->totalOrders = $totalOrders;
        $this->totalReferrals = $totalReferrals;
        $this->tierExpiryDate = $tierExpiryDate;
        $this->lastActivityAt = $lastActivityAt;
        $this->anniversaryDate = $anniversaryDate;
        $this->preferences = $preferences;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function getTierId(): ?int
    {
        return $this->tierId;
    }

    public function setTierId(?int $tierId): void
    {
        $this->tierId = $tierId;
    }

    public function getMemberNumber(): string
    {
        return $this->memberNumber;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getPointsBalance(): int
    {
        return $this->pointsBalance;
    }

    public function setPointsBalance(int $pointsBalance): void
    {
        $this->pointsBalance = $pointsBalance;
    }

    public function getLifetimePointsEarned(): int
    {
        return $this->lifetimePointsEarned;
    }

    public function setLifetimePointsEarned(int $lifetimePointsEarned): void
    {
        $this->lifetimePointsEarned = $lifetimePointsEarned;
    }

    public function getLifetimePointsRedeemed(): int
    {
        return $this->lifetimePointsRedeemed;
    }

    public function setLifetimePointsRedeemed(int $lifetimePointsRedeemed): void
    {
        $this->lifetimePointsRedeemed = $lifetimePointsRedeemed;
    }

    public function getTotalSpent(): float
    {
        return $this->totalSpent;
    }

    public function setTotalSpent(float $totalSpent): void
    {
        $this->totalSpent = $totalSpent;
    }

    public function getAnnualSpent(): float
    {
        return $this->annualSpent;
    }

    public function setAnnualSpent(float $annualSpent): void
    {
        $this->annualSpent = $annualSpent;
    }

    public function getTotalOrders(): int
    {
        return $this->totalOrders;
    }

    public function setTotalOrders(int $totalOrders): void
    {
        $this->totalOrders = $totalOrders;
    }

    public function getTotalReferrals(): int
    {
        return $this->totalReferrals;
    }

    public function setTotalReferrals(int $totalReferrals): void
    {
        $this->totalReferrals = $totalReferrals;
    }

    public function getTierExpiryDate(): ?string
    {
        return $this->tierExpiryDate;
    }

    public function setTierExpiryDate(?string $tierExpiryDate): void
    {
        $this->tierExpiryDate = $tierExpiryDate;
    }

    public function getLastActivityAt(): ?string
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?string $lastActivityAt): void
    {
        $this->lastActivityAt = $lastActivityAt;
    }

    public function getAnniversaryDate(): ?string
    {
        return $this->anniversaryDate;
    }

    public function setAnniversaryDate(?string $anniversaryDate): void
    {
        $this->anniversaryDate = $anniversaryDate;
    }

    public function getPreferences(): ?array
    {
        return $this->preferences;
    }

    public function setPreferences(?array $preferences): void
    {
        $this->preferences = $preferences;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    // Helper methods

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function hasTier(): bool
    {
        return $this->tierId !== null;
    }

    public function hasPoints(): bool
    {
        return $this->pointsBalance > 0;
    }

    public function canRedeem(int $points): bool
    {
        return $this->pointsBalance >= $points && $this->isActive();
    }

    public function getLifetimePointsNet(): int
    {
        return $this->lifetimePointsEarned - $this->lifetimePointsRedeemed;
    }

    public function getAverageOrderValue(): float
    {
        return $this->totalOrders > 0 ? $this->totalSpent / $this->totalOrders : 0.0;
    }

    public function getDaysSinceLastActivity(): int
    {
        if (!$this->lastActivityAt) {
            return PHP_INT_MAX;
        }
        
        return (int) ((time() - strtotime($this->lastActivityAt)) / (24 * 60 * 60));
    }

    public function getDaysSinceJoining(): int
    {
        return (int) ((time() - strtotime($this->createdAt)) / (24 * 60 * 60));
    }

    public function isAnniversary(): bool
    {
        if (!$this->anniversaryDate) {
            return false;
        }
        
        return date('m-d') === date('m-d', strtotime($this->anniversaryDate));
    }

    public function getPreference(string $key, $default = null)
    {
        return $this->preferences[$key] ?? $default;
    }

    public function setPreference(string $key, $value): void
    {
        if ($this->preferences === null) {
            $this->preferences = [];
        }
        
        $this->preferences[$key] = $value;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customerId,
            'tier_id' => $this->tierId,
            'member_number' => $this->memberNumber,
            'status' => $this->status,
            'points_balance' => $this->pointsBalance,
            'lifetime_points_earned' => $this->lifetimePointsEarned,
            'lifetime_points_redeemed' => $this->lifetimePointsRedeemed,
            'lifetime_points_net' => $this->getLifetimePointsNet(),
            'total_spent' => $this->totalSpent,
            'annual_spent' => $this->annualSpent,
            'total_orders' => $this->totalOrders,
            'total_referrals' => $this->totalReferrals,
            'average_order_value' => $this->getAverageOrderValue(),
            'tier_expiry_date' => $this->tierExpiryDate,
            'last_activity_at' => $this->lastActivityAt,
            'anniversary_date' => $this->anniversaryDate,
            'days_since_last_activity' => $this->getDaysSinceLastActivity(),
            'days_since_joining' => $this->getDaysSinceJoining(),
            'is_anniversary' => $this->isAnniversary(),
            'preferences' => $this->preferences,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'flags' => [
                'is_active' => $this->isActive(),
                'is_inactive' => $this->isInactive(),
                'is_suspended' => $this->isSuspended(),
                'has_tier' => $this->hasTier(),
                'has_points' => $this->hasPoints()
            ]
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}