<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;

class PointTransactionRepository extends Repository
{
    protected string $table = 'loyalty_point_transactions';
    protected string $primaryKey = 'id';

    /**
     * Record point transaction
     */
    public function recordTransaction(array $data): array
    {
        $data['transaction_id'] = $this->generateTransactionId();
        $data['created_at'] = $data['created_at'] ?? now();
        
        return $this->create($data);
    }

    /**
     * Get transactions by member
     */
    public function getByMember(int $memberId, array $filters = []): array
    {
        $query = DB::table($this->table)
            ->where('member_id', $memberId);

        $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get transactions by type
     */
    public function getByType(string $type, array $filters = []): array
    {
        $query = DB::table($this->table)
            ->where('type', $type);

        $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get transactions by source
     */
    public function getBySource(string $sourceType, int $sourceId): array
    {
        return DB::table($this->table)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get member point balance
     */
    public function getMemberBalance(int $memberId): int
    {
        $result = DB::table($this->table)
            ->where('member_id', $memberId)
            ->where('status', 'completed')
            ->sum('points');

        return (int)$result;
    }

    /**
     * Get member point balance by type
     */
    public function getMemberBalanceByType(int $memberId): array
    {
        return DB::table($this->table)
            ->where('member_id', $memberId)
            ->where('status', 'completed')
            ->select(
                'type',
                DB::raw('SUM(points) as total_points'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy('type')
            ->get();
    }

    /**
     * Get expiring points
     */
    public function getExpiringPoints(int $memberId, int $days = 30): int
    {
        $result = DB::table('loyalty_point_balances')
            ->where('member_id', $memberId)
            ->where('expires_at', '<=', date('Y-m-d', strtotime("+{$days} days")))
            ->where('expires_at', '>', date('Y-m-d'))
            ->where('balance', '>', 0)
            ->sum('balance');

        return (int)$result;
    }

    /**
     * Get point activity summary
     */
    public function getActivitySummary(array $filters = []): array
    {
        $query = DB::table($this->table);
        $this->applyFilters($query, $filters);

        return $query->select(
            DB::raw('DATE(created_at) as date'),
            'type',
            DB::raw('COUNT(*) as transaction_count'),
            DB::raw('SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as points_earned'),
            DB::raw('SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) as points_redeemed')
        )
        ->groupBy(DB::raw('DATE(created_at)'), 'type')
        ->orderBy('date', 'desc')
        ->get();
    }

    /**
     * Get transaction statistics
     */
    public function getTransactionStatistics(array $filters = []): array
    {
        $query = DB::table($this->table);
        $this->applyFilters($query, $filters);

        $stats = $query->select(
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('COUNT(DISTINCT member_id) as unique_members'),
            DB::raw('SUM(CASE WHEN type = "earned" THEN points ELSE 0 END) as total_earned'),
            DB::raw('SUM(CASE WHEN type = "redeemed" THEN ABS(points) ELSE 0 END) as total_redeemed'),
            DB::raw('AVG(CASE WHEN type = "earned" THEN points ELSE NULL END) as avg_earned'),
            DB::raw('AVG(CASE WHEN type = "redeemed" THEN ABS(points) ELSE NULL END) as avg_redeemed')
        )->first();

        return (array)$stats;
    }

    /**
     * Get top earning sources
     */
    public function getTopEarningSources(int $limit = 10): array
    {
        return DB::table($this->table)
            ->where('type', 'earned')
            ->select(
                'source_type',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(points) as total_points'),
                DB::raw('COUNT(DISTINCT member_id) as unique_members')
            )
            ->groupBy('source_type')
            ->orderBy('total_points', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Cancel transaction
     */
    public function cancelTransaction(int $transactionId, string $reason): bool
    {
        $transaction = $this->findById($transactionId);
        if (!$transaction || $transaction['status'] !== 'completed') {
            return false;
        }

        // Update transaction status
        $this->update($transactionId, [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $reason
        ]);

        // Create reversal transaction
        $reversalData = [
            'member_id' => $transaction['member_id'],
            'type' => 'adjustment',
            'points' => -$transaction['points'],
            'description' => "Reversal of transaction #{$transaction['transaction_id']}: {$reason}",
            'source_type' => 'transaction_reversal',
            'source_id' => $transactionId,
            'status' => 'completed'
        ];

        $this->recordTransaction($reversalData);

        return true;
    }

    /**
     * Get pending transactions
     */
    public function getPendingTransactions(): array
    {
        return DB::table($this->table)
            ->where('status', 'pending')
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->get();
    }

    /**
     * Process pending transaction
     */
    public function processPendingTransaction(int $transactionId): bool
    {
        return $this->update($transactionId, [
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    /**
     * Get member earning history
     */
    public function getMemberEarningHistory(int $memberId, int $days = 30): array
    {
        return DB::table($this->table)
            ->where('member_id', $memberId)
            ->where('type', 'earned')
            ->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(points) as points_earned'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Get redemption history
     */
    public function getRedemptionHistory(int $memberId = null): array
    {
        $query = DB::table($this->table . ' as t')
            ->join('loyalty_rewards_redeemed as r', function($join) {
                $join->on('t.source_type', '=', DB::raw('"reward_redemption"'))
                     ->on('t.source_id', '=', 'r.id');
            })
            ->join('loyalty_rewards as rw', 'r.reward_id', '=', 'rw.id')
            ->select(
                't.*',
                'rw.name as reward_name',
                'rw.type as reward_type',
                'r.redeemed_at'
            )
            ->where('t.type', 'redeemed');

        if ($memberId) {
            $query->where('t.member_id', $memberId);
        }

        return $query->orderBy('t.created_at', 'desc')->get();
    }

    /**
     * Get point trends
     */
    public function getPointTrends(int $days = 30): array
    {
        return DB::table($this->table)
            ->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN type = "earned" THEN points ELSE 0 END) as earned'),
                DB::raw('SUM(CASE WHEN type = "redeemed" THEN ABS(points) ELSE 0 END) as redeemed'),
                DB::raw('COUNT(DISTINCT member_id) as active_members')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
    }

    /**
     * Bulk create transactions
     */
    public function bulkCreate(array $transactions): array
    {
        $created = [];
        
        DB::transaction(function() use ($transactions, &$created) {
            foreach ($transactions as $transaction) {
                $created[] = $this->recordTransaction($transaction);
            }
        });

        return $created;
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        if (isset($filters['min_points'])) {
            $query->where('points', '>=', $filters['min_points']);
        }

        if (isset($filters['max_points'])) {
            $query->where('points', '<=', $filters['max_points']);
        }
    }

    /**
     * Generate unique transaction ID
     */
    private function generateTransactionId(): string
    {
        return 'TXN' . date('YmdHis') . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}