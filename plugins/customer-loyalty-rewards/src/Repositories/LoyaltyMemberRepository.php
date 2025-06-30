<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;

class LoyaltyMemberRepository extends Repository
{
    protected string $table = 'loyalty_members';
    protected string $primaryKey = 'id';

    /**
     * Find member by customer ID
     */
    public function findByCustomerId(int $customerId): ?array
    {
        return DB::table($this->table)
            ->where('customer_id', $customerId)
            ->first();
    }

    /**
     * Find member by membership number
     */
    public function findByMembershipNumber(string $membershipNumber): ?array
    {
        return DB::table($this->table)
            ->where('membership_number', $membershipNumber)
            ->first();
    }

    /**
     * Get members by tier
     */
    public function getByTier(int $tierId): array
    {
        return DB::table($this->table)
            ->where('current_tier_id', $tierId)
            ->where('status', 'active')
            ->orderBy('total_points', 'desc')
            ->get();
    }

    /**
     * Get top members
     */
    public function getTopMembers(int $limit = 10, string $period = 'all'): array
    {
        $query = DB::table($this->table . ' as m')
            ->join('customers as c', 'm.customer_id', '=', 'c.id')
            ->select(
                'm.*',
                'c.first_name',
                'c.last_name',
                'c.email',
                DB::raw('(SELECT SUM(points) FROM loyalty_point_transactions WHERE member_id = m.id AND type = "earned" AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_points')
            )
            ->where('m.status', 'active');

        switch ($period) {
            case 'month':
                $query->orderBy('recent_points', 'desc');
                break;
            case 'year':
                $query->orderBy('lifetime_points', 'desc');
                break;
            default:
                $query->orderBy('total_points', 'desc');
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get members with expiring points
     */
    public function getMembersWithExpiringPoints(int $days = 30): array
    {
        return DB::table($this->table . ' as m')
            ->join('loyalty_point_balances as b', 'm.id', '=', 'b.member_id')
            ->join('customers as c', 'm.customer_id', '=', 'c.id')
            ->select('m.*', 'c.email', 'c.first_name', 'c.last_name', DB::raw('SUM(b.balance) as expiring_points'))
            ->where('b.expires_at', '<=', date('Y-m-d', strtotime("+{$days} days")))
            ->where('b.expires_at', '>', date('Y-m-d'))
            ->where('b.balance', '>', 0)
            ->groupBy('m.id', 'c.email', 'c.first_name', 'c.last_name')
            ->having('expiring_points', '>', 0)
            ->get();
    }

    /**
     * Get members eligible for tier upgrade
     */
    public function getMembersEligibleForTierUpgrade(): array
    {
        return DB::table($this->table . ' as m')
            ->join('loyalty_tiers as ct', 'm.current_tier_id', '=', 'ct.id')
            ->join('loyalty_tiers as nt', function($join) {
                $join->on('nt.level', '=', DB::raw('ct.level + 1'));
            })
            ->select('m.*', 'ct.name as current_tier', 'nt.name as next_tier', 'nt.required_points')
            ->where('m.status', 'active')
            ->where('m.total_points', '>=', DB::raw('nt.required_points'))
            ->get();
    }

    /**
     * Get inactive members
     */
    public function getInactiveMembers(int $days = 90): array
    {
        return DB::table($this->table . ' as m')
            ->join('customers as c', 'm.customer_id', '=', 'c.id')
            ->select('m.*', 'c.email', 'c.first_name', 'c.last_name')
            ->where('m.status', 'active')
            ->where('m.last_activity_at', '<', date('Y-m-d H:i:s', strtotime("-{$days} days")))
            ->orderBy('m.last_activity_at')
            ->get();
    }

    /**
     * Update member points
     */
    public function updatePoints(int $memberId, int $points, string $operation = 'add'): bool
    {
        $update = match($operation) {
            'add' => [
                'total_points' => DB::raw("total_points + {$points}"),
                'lifetime_points' => DB::raw("lifetime_points + {$points}")
            ],
            'subtract' => [
                'total_points' => DB::raw("total_points - {$points}")
            ],
            'set' => [
                'total_points' => $points
            ]
        };

        $update['last_activity_at'] = now();

        return DB::table($this->table)
            ->where('id', $memberId)
            ->update($update) > 0;
    }

    /**
     * Update member tier
     */
    public function updateTier(int $memberId, int $tierId): bool
    {
        return DB::table($this->table)
            ->where('id', $memberId)
            ->update([
                'current_tier_id' => $tierId,
                'tier_updated_at' => now()
            ]) > 0;
    }

    /**
     * Get member statistics
     */
    public function getMemberStatistics(int $memberId): array
    {
        $member = $this->findById($memberId);
        if (!$member) {
            return [];
        }

        $stats = DB::table('loyalty_point_transactions')
            ->where('member_id', $memberId)
            ->select(
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(CASE WHEN type = "earned" THEN points ELSE 0 END) as total_earned'),
                DB::raw('SUM(CASE WHEN type = "redeemed" THEN ABS(points) ELSE 0 END) as total_redeemed'),
                DB::raw('COUNT(DISTINCT DATE(created_at)) as active_days'),
                DB::raw('MAX(created_at) as last_transaction')
            )
            ->first();

        $referrals = DB::table('loyalty_referrals')
            ->where('referrer_id', $memberId)
            ->count();

        $rewards = DB::table('loyalty_rewards_redeemed')
            ->where('member_id', $memberId)
            ->count();

        return [
            'member_since' => $member['created_at'],
            'current_points' => $member['total_points'],
            'lifetime_points' => $member['lifetime_points'],
            'current_tier' => $member['current_tier_id'],
            'total_transactions' => $stats['total_transactions'] ?? 0,
            'total_earned' => $stats['total_earned'] ?? 0,
            'total_redeemed' => $stats['total_redeemed'] ?? 0,
            'active_days' => $stats['active_days'] ?? 0,
            'last_transaction' => $stats['last_transaction'] ?? null,
            'referrals_made' => $referrals,
            'rewards_redeemed' => $rewards
        ];
    }

    /**
     * Search members
     */
    public function searchMembers(string $query, array $filters = []): array
    {
        $search = DB::table($this->table . ' as m')
            ->join('customers as c', 'm.customer_id', '=', 'c.id')
            ->select('m.*', 'c.first_name', 'c.last_name', 'c.email')
            ->where(function($q) use ($query) {
                $q->where('m.membership_number', 'LIKE', "%{$query}%")
                  ->orWhere('c.email', 'LIKE', "%{$query}%")
                  ->orWhere('c.first_name', 'LIKE', "%{$query}%")
                  ->orWhere('c.last_name', 'LIKE', "%{$query}%");
            });

        if (isset($filters['status'])) {
            $search->where('m.status', $filters['status']);
        }

        if (isset($filters['tier_id'])) {
            $search->where('m.current_tier_id', $filters['tier_id']);
        }

        if (isset($filters['min_points'])) {
            $search->where('m.total_points', '>=', $filters['min_points']);
        }

        return $search->orderBy('m.created_at', 'desc')->get();
    }

    /**
     * Get member activity timeline
     */
    public function getMemberActivityTimeline(int $memberId, int $limit = 20): array
    {
        $transactions = DB::table('loyalty_point_transactions')
            ->where('member_id', $memberId)
            ->select(
                'id',
                'type',
                'points',
                'description',
                'created_at',
                DB::raw('"transaction" as activity_type')
            );

        $rewards = DB::table('loyalty_rewards_redeemed as r')
            ->join('loyalty_rewards as rw', 'r.reward_id', '=', 'rw.id')
            ->where('r.member_id', $memberId)
            ->select(
                'r.id',
                DB::raw('"redeemed" as type'),
                DB::raw('rw.points_required as points'),
                DB::raw('CONCAT("Redeemed: ", rw.name) as description'),
                'r.created_at',
                DB::raw('"reward" as activity_type')
            );

        $referrals = DB::table('loyalty_referrals')
            ->where('referrer_id', $memberId)
            ->select(
                'id',
                DB::raw('"referral" as type'),
                'points_earned as points',
                DB::raw('"Referral bonus" as description'),
                'created_at',
                DB::raw('"referral" as activity_type')
            );

        return $transactions->union($rewards)->union($referrals)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get members by birthday month
     */
    public function getMembersByBirthdayMonth(int $month): array
    {
        return DB::table($this->table . ' as m')
            ->join('customers as c', 'm.customer_id', '=', 'c.id')
            ->select('m.*', 'c.email', 'c.first_name', 'c.last_name', 'c.birthday')
            ->whereNotNull('c.birthday')
            ->whereRaw('MONTH(c.birthday) = ?', [$month])
            ->where('m.status', 'active')
            ->get();
    }

    /**
     * Get tier distribution
     */
    public function getTierDistribution(): array
    {
        return DB::table($this->table . ' as m')
            ->join('loyalty_tiers as t', 'm.current_tier_id', '=', 't.id')
            ->select(
                't.id',
                't.name',
                't.level',
                DB::raw('COUNT(m.id) as member_count'),
                DB::raw('AVG(m.total_points) as avg_points'),
                DB::raw('SUM(m.total_points) as total_points')
            )
            ->where('m.status', 'active')
            ->groupBy('t.id', 't.name', 't.level')
            ->orderBy('t.level')
            ->get();
    }

    /**
     * Bulk update member status
     */
    public function bulkUpdateStatus(array $memberIds, string $status): int
    {
        return DB::table($this->table)
            ->whereIn('id', $memberIds)
            ->update([
                'status' => $status,
                'updated_at' => now()
            ]);
    }

    /**
     * Generate unique membership number
     */
    public function generateMembershipNumber(): string
    {
        do {
            $number = 'MEM' . date('Y') . str_pad((string)rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while ($this->findByMembershipNumber($number));

        return $number;
    }
}