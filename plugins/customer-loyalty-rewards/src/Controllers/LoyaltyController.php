<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use CustomerLoyaltyRewards\Services\LoyaltyManager;
use CustomerLoyaltyRewards\Repositories\LoyaltyMemberRepository;
use CustomerLoyaltyRewards\Repositories\PointTransactionRepository;

class LoyaltyController extends Controller
{
    private LoyaltyManager $loyaltyManager;
    private LoyaltyMemberRepository $memberRepository;
    private PointTransactionRepository $transactionRepository;

    public function __construct()
    {
        $this->loyaltyManager = app(LoyaltyManager::class);
        $this->memberRepository = app(LoyaltyMemberRepository::class);
        $this->transactionRepository = app(PointTransactionRepository::class);
    }

    /**
     * Get loyalty program overview
     */
    public function overview(Request $request): Response
    {
        $stats = $this->loyaltyManager->getProgramStatistics();

        return $this->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }

    /**
     * Get member details
     */
    public function getMember(Request $request, int $memberId): Response
    {
        $member = $this->memberRepository->findById($memberId);
        
        if (!$member) {
            return $this->json([
                'status' => 'error',
                'message' => 'Member not found'
            ], 404);
        }

        $memberData = $this->loyaltyManager->getMemberDetails($memberId);

        return $this->json([
            'status' => 'success',
            'data' => $memberData
        ]);
    }

    /**
     * Get member by customer ID
     */
    public function getMemberByCustomer(Request $request, int $customerId): Response
    {
        $member = $this->memberRepository->findByCustomerId($customerId);
        
        if (!$member) {
            return $this->json([
                'status' => 'error',
                'message' => 'Member not found for this customer'
            ], 404);
        }

        $memberData = $this->loyaltyManager->getMemberDetails($member['id']);

        return $this->json([
            'status' => 'success',
            'data' => $memberData
        ]);
    }

    /**
     * Enroll new member
     */
    public function enrollMember(Request $request): Response
    {
        $this->validate($request, [
            'customer_id' => 'required|integer',
            'referred_by' => 'string',
            'enrollment_source' => 'string'
        ]);

        try {
            $member = $this->loyaltyManager->enrollCustomer(
                (int)$request->input('customer_id'),
                $request->input('enrollment_source', 'manual'),
                $request->input('referred_by')
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Customer enrolled successfully',
                'data' => $member
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Award points to member
     */
    public function awardPoints(Request $request): Response
    {
        $this->validate($request, [
            'member_id' => 'required|integer',
            'points' => 'required|integer|min:1',
            'reason' => 'required|string',
            'source_type' => 'string',
            'source_id' => 'integer'
        ]);

        try {
            $transaction = $this->loyaltyManager->awardPoints(
                (int)$request->input('member_id'),
                (int)$request->input('points'),
                $request->input('reason'),
                $request->input('source_type', 'manual'),
                $request->input('source_id')
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Points awarded successfully',
                'data' => $transaction
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Redeem points
     */
    public function redeemPoints(Request $request): Response
    {
        $this->validate($request, [
            'member_id' => 'required|integer',
            'points' => 'required|integer|min:1',
            'reason' => 'required|string',
            'redemption_type' => 'string',
            'redemption_id' => 'integer'
        ]);

        try {
            $transaction = $this->loyaltyManager->redeemPoints(
                (int)$request->input('member_id'),
                (int)$request->input('points'),
                $request->input('reason'),
                $request->input('redemption_type', 'manual'),
                $request->input('redemption_id')
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Points redeemed successfully',
                'data' => $transaction
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get member transactions
     */
    public function getMemberTransactions(Request $request, int $memberId): Response
    {
        $filters = [
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'from_date' => $request->query('from_date'),
            'to_date' => $request->query('to_date')
        ];

        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 20);

        $transactions = $this->transactionRepository->paginate($page, $perPage, array_merge($filters, ['member_id' => $memberId]));

        return $this->json([
            'status' => 'success',
            'data' => $transactions['data'],
            'meta' => [
                'current_page' => $transactions['current_page'],
                'per_page' => $transactions['per_page'],
                'total' => $transactions['total'],
                'last_page' => $transactions['last_page']
            ]
        ]);
    }

    /**
     * Get member balance
     */
    public function getMemberBalance(Request $request, int $memberId): Response
    {
        $balance = $this->loyaltyManager->getMemberBalance($memberId);

        return $this->json([
            'status' => 'success',
            'data' => $balance
        ]);
    }

    /**
     * Get tier information
     */
    public function getTiers(Request $request): Response
    {
        $tiers = $this->loyaltyManager->getAllTiers();

        return $this->json([
            'status' => 'success',
            'data' => $tiers
        ]);
    }

    /**
     * Get member's current tier
     */
    public function getMemberTier(Request $request, int $memberId): Response
    {
        $tier = $this->loyaltyManager->getMemberTier($memberId);

        return $this->json([
            'status' => 'success',
            'data' => $tier
        ]);
    }

    /**
     * Update member tier
     */
    public function updateMemberTier(Request $request, int $memberId): Response
    {
        $this->validate($request, [
            'tier_id' => 'required|integer'
        ]);

        try {
            $result = $this->loyaltyManager->updateMemberTier(
                $memberId,
                (int)$request->input('tier_id')
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Member tier updated successfully',
                'data' => $result
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get available rewards
     */
    public function getAvailableRewards(Request $request, int $memberId): Response
    {
        $rewards = $this->loyaltyManager->getAvailableRewards($memberId);

        return $this->json([
            'status' => 'success',
            'data' => $rewards
        ]);
    }

    /**
     * Redeem reward
     */
    public function redeemReward(Request $request): Response
    {
        $this->validate($request, [
            'member_id' => 'required|integer',
            'reward_id' => 'required|integer'
        ]);

        try {
            $redemption = $this->loyaltyManager->redeemReward(
                (int)$request->input('member_id'),
                (int)$request->input('reward_id')
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Reward redeemed successfully',
                'data' => $redemption
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get member activity
     */
    public function getMemberActivity(Request $request, int $memberId): Response
    {
        $limit = (int)$request->query('limit', 20);
        
        $activity = $this->memberRepository->getMemberActivityTimeline($memberId, $limit);

        return $this->json([
            'status' => 'success',
            'data' => $activity
        ]);
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard(Request $request): Response
    {
        $period = $request->query('period', 'all');
        $limit = (int)$request->query('limit', 10);

        $leaderboard = $this->memberRepository->getTopMembers($limit, $period);

        return $this->json([
            'status' => 'success',
            'data' => $leaderboard,
            'meta' => [
                'period' => $period,
                'limit' => $limit
            ]
        ]);
    }

    /**
     * Search members
     */
    public function searchMembers(Request $request): Response
    {
        $query = $request->query('q', '');
        $filters = [
            'status' => $request->query('status'),
            'tier_id' => $request->query('tier_id'),
            'min_points' => $request->query('min_points')
        ];

        $members = $this->memberRepository->searchMembers($query, array_filter($filters));

        return $this->json([
            'status' => 'success',
            'data' => $members,
            'meta' => [
                'total' => count($members)
            ]
        ]);
    }

    /**
     * Get expiring points summary
     */
    public function getExpiringPoints(Request $request): Response
    {
        $days = (int)$request->query('days', 30);
        
        $members = $this->memberRepository->getMembersWithExpiringPoints($days);

        return $this->json([
            'status' => 'success',
            'data' => $members,
            'meta' => [
                'total' => count($members),
                'days_ahead' => $days,
                'total_expiring_points' => array_sum(array_column($members, 'expiring_points'))
            ]
        ]);
    }

    /**
     * Process referral
     */
    public function processReferral(Request $request): Response
    {
        $this->validate($request, [
            'referrer_code' => 'required|string',
            'referred_customer_id' => 'required|integer'
        ]);

        try {
            $result = $this->loyaltyManager->processReferral(
                $request->input('referrer_code'),
                (int)$request->input('referred_customer_id')
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Referral processed successfully',
                'data' => $result
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get program statistics
     */
    public function getStatistics(Request $request): Response
    {
        $period = $request->query('period', '30days');
        
        $stats = $this->loyaltyManager->getProgramStatistics($period);

        return $this->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }

    /**
     * Export members data
     */
    public function exportMembers(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        $filters = [
            'status' => $request->query('status'),
            'tier_id' => $request->query('tier_id'),
            'min_points' => $request->query('min_points')
        ];

        try {
            $file = $this->loyaltyManager->exportMembers($format, array_filter($filters));

            return $this->download($file, "loyalty_members_export.{$format}");
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}