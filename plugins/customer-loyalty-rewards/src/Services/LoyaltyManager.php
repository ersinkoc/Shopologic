<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards\Services;

use CustomerLoyaltyRewards\Repositories\MemberRepository;
use CustomerLoyaltyRewards\Repositories\PointsTransactionRepository;
use CustomerLoyaltyRewards\Models\LoyaltyMember;
use CustomerLoyaltyRewards\Models\PointsTransaction;
use CustomerLoyaltyRewards\Exceptions\InsufficientPointsException;
use CustomerLoyaltyRewards\Exceptions\MemberNotFoundException;

class LoyaltyManager\n{
    private MemberRepository $memberRepository;
    private PointsTransactionRepository $transactionRepository;
    private PointsCalculator $pointsCalculator;

    public function __construct(
        MemberRepository $memberRepository,
        PointsTransactionRepository $transactionRepository,
        PointsCalculator $pointsCalculator
    ) {
        $this->memberRepository = $memberRepository;
        $this->transactionRepository = $transactionRepository;
        $this->pointsCalculator = $pointsCalculator;
    }

    /**
     * Create a new loyalty member
     */
    public function createMember(int $customerId, array $data = []): LoyaltyMember
    {
        // Generate unique member number
        $memberNumber = $this->generateMemberNumber();
        
        $memberData = array_merge([
            'customer_id' => $customerId,
            'member_number' => $memberNumber,
            'status' => 'active',
            'points_balance' => 0,
            'lifetime_points_earned' => 0,
            'lifetime_points_redeemed' => 0,
            'total_spent' => 0,
            'annual_spent' => 0,
            'total_orders' => 0,
            'total_referrals' => 0,
            'anniversary_date' => date('Y-m-d'),
            'last_activity_at' => now()
        ], $data);

        return $this->memberRepository->create($memberData);
    }

    /**
     * Find member by customer ID
     */
    public function findMemberByCustomerId(int $customerId): ?LoyaltyMember
    {
        return $this->memberRepository->findByCustomerId($customerId);
    }

    /**
     * Find member by ID
     */
    public function findMemberById(int $memberId): ?LoyaltyMember
    {
        return $this->memberRepository->findById($memberId);
    }

    /**
     * Find member by member number
     */
    public function findMemberByNumber(string $memberNumber): ?LoyaltyMember
    {
        return $this->memberRepository->findByMemberNumber($memberNumber);
    }

    /**
     * Get all active members
     */
    public function getAllActiveMembers(): array
    {
        return $this->memberRepository->findByStatus('active');
    }

    /**
     * Award points to a member
     */
    public function awardPoints(
        int $memberId,
        int $points,
        string $reason,
        string $description = '',
        ?int $referenceId = null,
        ?int $campaignId = null,
        array $metadata = []
    ): PointsTransaction {
        $member = $this->findMemberById($memberId);
        if (!$member) {
            throw new MemberNotFoundException("Member not found: {$memberId}");
        }

        // Calculate new balance
        $newBalance = $member->getPointsBalance() + $points;

        // Create transaction record
        $transaction = $this->transactionRepository->create([
            'member_id' => $memberId,
            'type' => 'earned',
            'points' => $points,
            'balance_after' => $newBalance,
            'reason' => $reason,
            'description' => $description,
            'reference_type' => $metadata['reference_type'] ?? null,
            'reference_id' => $referenceId,
            'campaign_id' => $campaignId,
            'order_value' => $metadata['order_value'] ?? null,
            'expires_at' => $this->calculateExpiryDate(),
            'status' => 'completed'
        ]);

        // Update member balance and lifetime stats
        $this->memberRepository->update($memberId, [
            'points_balance' => $newBalance,
            'lifetime_points_earned' => $member->getLifetimePointsEarned() + $points,
            'last_activity_at' => now()
        ]);

        // Create activity record
        $this->createActivity($memberId, 'points_earned', "Earned {$points} points", [
            'points' => $points,
            'reason' => $reason,
            'transaction_id' => $transaction->getId()
        ]);

        return $transaction;
    }

    /**
     * Redeem points for a member
     */
    public function redeemPoints(
        int $memberId,
        int $points,
        string $reason,
        string $description = '',
        ?int $referenceId = null,
        array $metadata = []
    ): PointsTransaction {
        $member = $this->findMemberById($memberId);
        if (!$member) {
            throw new MemberNotFoundException("Member not found: {$memberId}");
        }

        if (!$this->canRedeemPoints($memberId, $points)) {
            throw new InsufficientPointsException(
                "Insufficient points. Available: {$member->getPointsBalance()}, Required: {$points}"
            );
        }

        // Calculate new balance
        $newBalance = $member->getPointsBalance() - $points;

        // Create transaction record
        $transaction = $this->transactionRepository->create([
            'member_id' => $memberId,
            'type' => 'redeemed',
            'points' => $points,
            'balance_after' => $newBalance,
            'reason' => $reason,
            'description' => $description,
            'reference_type' => $metadata['reference_type'] ?? null,
            'reference_id' => $referenceId,
            'status' => 'completed'
        ]);

        // Update member balance and lifetime stats
        $this->memberRepository->update($memberId, [
            'points_balance' => $newBalance,
            'lifetime_points_redeemed' => $member->getLifetimePointsRedeemed() + $points,
            'last_activity_at' => now()
        ]);

        // Create activity record
        $this->createActivity($memberId, 'points_redeemed', "Redeemed {$points} points", [
            'points' => $points,
            'reason' => $reason,
            'transaction_id' => $transaction->getId()
        ]);

        return $transaction;
    }

    /**
     * Check if member can redeem specified points
     */
    public function canRedeemPoints(int $memberId, int $points): bool
    {
        $member = $this->findMemberById($memberId);
        if (!$member) {
            return false;
        }

        return $member->getPointsBalance() >= $points;
    }

    /**
     * Adjust points (manual adjustment)
     */
    public function adjustPoints(
        int $memberId,
        int $pointsChange,
        string $reason,
        string $description = '',
        ?int $userId = null
    ): PointsTransaction {
        $member = $this->findMemberById($memberId);
        if (!$member) {
            throw new MemberNotFoundException("Member not found: {$memberId}");
        }

        $type = $pointsChange > 0 ? 'earned' : 'redeemed';
        $points = abs($pointsChange);
        $newBalance = $member->getPointsBalance() + $pointsChange;

        // Ensure balance doesn't go negative
        if ($newBalance < 0) {
            throw new InsufficientPointsException("Adjustment would result in negative balance");
        }

        // Create transaction record
        $transaction = $this->transactionRepository->create([
            'member_id' => $memberId,
            'type' => 'adjusted',
            'points' => $pointsChange,
            'balance_after' => $newBalance,
            'reason' => $reason,
            'description' => $description,
            'status' => 'completed',
            'created_by' => $userId
        ]);

        // Update member balance
        $this->memberRepository->update($memberId, [
            'points_balance' => $newBalance,
            'last_activity_at' => now()
        ]);

        // Update lifetime stats
        if ($pointsChange > 0) {
            $this->memberRepository->update($memberId, [
                'lifetime_points_earned' => $member->getLifetimePointsEarned() + $points
            ]);
        } else {
            $this->memberRepository->update($memberId, [
                'lifetime_points_redeemed' => $member->getLifetimePointsRedeemed() + $points
            ]);
        }

        return $transaction;
    }

    /**
     * Expire points
     */
    public function expirePoints(int $transactionId): void
    {
        $transaction = $this->transactionRepository->findById($transactionId);
        if (!$transaction || $transaction->getType() !== 'earned') {
            return;
        }

        $member = $this->findMemberById($transaction->getMemberId());
        if (!$member) {
            return;
        }

        $pointsToExpire = $transaction->getPoints();
        $newBalance = max(0, $member->getPointsBalance() - $pointsToExpire);

        // Create expiry transaction
        $this->transactionRepository->create([
            'member_id' => $transaction->getMemberId(),
            'type' => 'expired',
            'points' => $pointsToExpire,
            'balance_after' => $newBalance,
            'reason' => 'points_expired',
            'description' => "Points expired from transaction #{$transactionId}",
            'reference_type' => 'transaction',
            'reference_id' => $transactionId,
            'status' => 'completed'
        ]);

        // Update member balance
        $this->memberRepository->update($transaction->getMemberId(), [
            'points_balance' => $newBalance,
            'last_activity_at' => now()
        ]);

        // Mark original transaction as expired
        $this->transactionRepository->update($transactionId, [
            'status' => 'expired'
        ]);
    }

    /**
     * Find expiring points
     */
    public function findExpiringPoints(int $expiryMonths): array
    {
        $expiryDate = date('Y-m-d', strtotime("-{$expiryMonths} months"));
        
        return $this->transactionRepository->findExpiringPoints($expiryDate);
    }

    /**
     * Get member's points history
     */
    public function getMemberPointsHistory(int $memberId, int $limit = 50, int $offset = 0): array
    {
        return $this->transactionRepository->findByMember($memberId, $limit, $offset);
    }

    /**
     * Update member statistics
     */
    public function updateMemberStats(int $memberId, array $stats): void
    {
        $this->memberRepository->update($memberId, array_merge($stats, [
            'last_activity_at' => now()
        ]));
    }

    /**
     * Get today's birthday members
     */
    public function getTodaysBirthdayMembers(): array
    {
        $today = date('m-d');
        return $this->memberRepository->findBirthdayMembers($today);
    }

    /**
     * Get member statistics
     */
    public function getMemberStatistics(): array
    {
        return [
            'total_members' => $this->memberRepository->getTotalMembersCount(),
            'active_members' => $this->memberRepository->getActiveMembersCount(),
            'new_members_this_month' => $this->memberRepository->getNewMembersCount(30),
            'total_points_earned' => $this->transactionRepository->getTotalPointsEarned(),
            'total_points_redeemed' => $this->transactionRepository->getTotalPointsRedeemed(),
            'average_points_balance' => $this->memberRepository->getAveragePointsBalance()
        ];
    }

    /**
     * Calculate member engagement score
     */
    public function calculateEngagementScore(int $memberId): float
    {
        $member = $this->findMemberById($memberId);
        if (!$member) {
            return 0.0;
        }

        $score = 0.0;
        
        // Activity recency (30% weight)
        $daysSinceActivity = $member->getLastActivityAt() 
            ? (time() - strtotime($member->getLastActivityAt())) / (24 * 60 * 60)
            : 365;
        $activityScore = max(0, 1 - ($daysSinceActivity / 365)) * 30;
        
        // Purchase frequency (25% weight)
        $membershipDays = max(1, (time() - strtotime($member->getCreatedAt())) / (24 * 60 * 60));
        $purchaseFrequency = ($member->getTotalOrders() / $membershipDays) * 365; // Orders per year
        $frequencyScore = min(1, $purchaseFrequency / 12) * 25; // Cap at 12 orders/year = 100%
        
        // Points activity (20% weight)
        $pointsActivity = ($member->getLifetimePointsEarned() + $member->getLifetimePointsRedeemed()) / 1000;
        $pointsScore = min(1, $pointsActivity) * 20;
        
        // Referral activity (15% weight)
        $referralScore = min(1, $member->getTotalReferrals() / 5) * 15;
        
        // Tier progression (10% weight)
        $tierScore = $member->getTierId() ? 10 : 0;
        
        return $activityScore + $frequencyScore + $pointsScore + $referralScore + $tierScore;
    }

    /**
     * Generate unique member number
     */
    private function generateMemberNumber(): string
    {
        do {
            $number = 'LM' . str_pad((string) mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        } while ($this->memberRepository->findByMemberNumber($number));
        
        return $number;
    }

    /**
     * Calculate expiry date for points
     */
    private function calculateExpiryDate(): ?string
    {
        // This would be configurable via plugin settings
        // For now, return null (no expiry)
        return null;
    }

    /**
     * Create activity record
     */
    private function createActivity(int $memberId, string $type, string $title, array $metadata = []): void
    {
        // This would use an ActivityRepository
        // Implementation would store activity in loyalty_activities table
    }
}