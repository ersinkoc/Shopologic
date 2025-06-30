<?php

declare(strict_types=1);

namespace Shopologic\Plugins\Shared\Interfaces;

/**
 * Interface for plugins that provide loyalty program data
 * Enables integration with customer loyalty systems
 */
interface LoyaltyProviderInterface
{
    /**
     * Get customer's current point balance
     */
    public function getPointBalance(int $customerId): int;
    
    /**
     * Award points to customer
     */
    public function awardPoints(int $customerId, int $points, string $reason, array $metadata = []): bool;
    
    /**
     * Redeem points for customer
     */
    public function redeemPoints(int $customerId, int $points, string $reason, array $metadata = []): bool;
    
    /**
     * Get customer's loyalty tier
     */
    public function getCustomerTier(int $customerId): ?array;
    
    /**
     * Get available rewards for customer
     */
    public function getAvailableRewards(int $customerId): array;
    
    /**
     * Redeem reward for customer
     */
    public function redeemReward(int $customerId, int $rewardId): array;
    
    /**
     * Get loyalty member data for analytics
     */
    public function getLoyaltyMemberData(int $customerId): array;
    
    /**
     * Get tier upgrade candidates
     */
    public function getTierUpgradeCandidates(): array;
    
    /**
     * Subscribe to loyalty events (points earned, tier upgraded, etc.)
     */
    public function subscribeToLoyaltyEvents(string $eventType, callable $callback): void;
}