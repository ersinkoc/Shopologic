<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;

class SubscriberRepository extends Repository
{
    protected string $table = 'email_subscribers';
    protected string $primaryKey = 'id';

    /**
     * Find subscriber by email
     */
    public function findByEmail(string $email): ?array
    {
        return DB::table($this->table)
            ->where('email', $email)
            ->first();
    }

    /**
     * Find subscriber by customer ID
     */
    public function findByCustomerId(int $customerId): ?array
    {
        return DB::table($this->table)
            ->where('customer_id', $customerId)
            ->first();
    }

    /**
     * Find by confirmation token
     */
    public function findByConfirmationToken(string $token): ?array
    {
        return DB::table($this->table)
            ->where('confirmation_token', $token)
            ->first();
    }

    /**
     * Get active subscribers
     */
    public function getActiveSubscribers(): array
    {
        return DB::table($this->table)
            ->where('status', 'subscribed')
            ->get();
    }

    /**
     * Get subscribers for export
     */
    public function getSubscribersForExport(array $filters = []): array
    {
        $query = DB::table($this->table . ' as s')
            ->leftJoin('customers as c', 's.customer_id', '=', 'c.id')
            ->select(
                's.*',
                'c.first_name as customer_first_name',
                'c.last_name as customer_last_name',
                'c.phone as customer_phone'
            );

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * Get email statistics for subscriber
     */
    public function getEmailStats(int $subscriberId): array
    {
        $stats = DB::table('email_sends as s')
            ->leftJoin('email_opens as o', 's.id', '=', 'o.send_id')
            ->leftJoin('email_clicks as c', 's.id', '=', 'c.send_id')
            ->where('s.subscriber_id', $subscriberId)
            ->select(
                DB::raw('COUNT(DISTINCT s.id) as total_received'),
                DB::raw('COUNT(DISTINCT o.id) as opened'),
                DB::raw('COUNT(DISTINCT c.id) as clicked')
            )
            ->first();

        return (array)$stats;
    }

    /**
     * Update engagement score
     */
    public function updateEngagementScore(int $subscriberId, float $score): bool
    {
        return DB::table($this->table)
            ->where('id', $subscriberId)
            ->update([
                'engagement_score' => $score,
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * Increment bounce count
     */
    public function incrementBounceCount(int $subscriberId): int
    {
        DB::table($this->table)
            ->where('id', $subscriberId)
            ->increment('bounce_count');

        $subscriber = $this->findById($subscriberId);
        return $subscriber ? $subscriber['bounce_count'] : 0;
    }

    /**
     * Get subscribers by segment
     */
    public function getBySegment(int $segmentId): array
    {
        return DB::table('segment_members as sm')
            ->join($this->table . ' as s', 'sm.subscriber_id', '=', 's.id')
            ->where('sm.segment_id', $segmentId)
            ->where('s.status', 'subscribed')
            ->select('s.*')
            ->get();
    }

    /**
     * Get subscribers by tag
     */
    public function getByTag(string $tag): array
    {
        return DB::table($this->table)
            ->whereJsonContains('tags', $tag)
            ->where('status', 'subscribed')
            ->get();
    }

    /**
     * Get recent subscribers
     */
    public function getRecentSubscribers(int $days = 7, int $limit = 100): array
    {
        return DB::table($this->table)
            ->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get unengaged subscribers
     */
    public function getUnengagedSubscribers(int $days = 90): array
    {
        return DB::table($this->table . ' as s')
            ->leftJoin('email_opens as o', function($join) use ($days) {
                $join->on('s.id', '=', 'o.subscriber_id')
                     ->where('o.opened_at', '>=', date('Y-m-d', strtotime("-{$days} days")));
            })
            ->whereNull('o.id')
            ->where('s.status', 'subscribed')
            ->where('s.created_at', '<', date('Y-m-d', strtotime("-{$days} days")))
            ->select('s.*')
            ->get();
    }

    /**
     * Get subscriber growth
     */
    public function getSubscriberGrowth(int $days = 30): array
    {
        return DB::table($this->table)
            ->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as new_subscribers'),
                DB::raw('SUM(CASE WHEN status = "subscribed" THEN 1 ELSE 0 END) as active_subscribers')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
    }

    /**
     * Get subscriber demographics
     */
    public function getSubscriberDemographics(): array
    {
        return [
            'by_country' => $this->getByCountry(),
            'by_language' => $this->getByLanguage(),
            'by_timezone' => $this->getByTimezone(),
            'by_source' => $this->getBySource(),
            'by_engagement' => $this->getByEngagementLevel()
        ];
    }

    /**
     * Search subscribers
     */
    public function searchSubscribers(string $query, array $filters = []): array
    {
        $search = DB::table($this->table)
            ->where(function($q) use ($query) {
                $q->where('email', 'LIKE', "%{$query}%")
                  ->orWhere('first_name', 'LIKE', "%{$query}%")
                  ->orWhere('last_name', 'LIKE', "%{$query}%");
            });

        $this->applyFilters($search, $filters);

        return $search->orderBy('created_at', 'desc')->get();
    }

    /**
     * Bulk update subscribers
     */
    public function bulkUpdate(array $subscriberIds, array $data): int
    {
        return DB::table($this->table)
            ->whereIn('id', $subscriberIds)
            ->update(array_merge($data, ['updated_at' => now()]));
    }

    /**
     * Get total count
     */
    public function getTotalCount(): int
    {
        return DB::table($this->table)
            ->where('status', 'subscribed')
            ->count();
    }

    /**
     * Get subscribers by birthday
     */
    public function getByBirthday(\DateTime $date): array
    {
        return DB::table($this->table . ' as s')
            ->join('customers as c', 's.customer_id', '=', 'c.id')
            ->whereRaw('MONTH(c.birthday) = ?', [$date->format('m')])
            ->whereRaw('DAY(c.birthday) = ?', [$date->format('d')])
            ->where('s.status', 'subscribed')
            ->select('s.*', 'c.birthday')
            ->get();
    }

    /**
     * Get subscribers by lifecycle stage
     */
    public function getByLifecycleStage(string $stage): array
    {
        $query = DB::table($this->table)
            ->where('status', 'subscribed');

        switch ($stage) {
            case 'new':
                $query->where('created_at', '>=', date('Y-m-d', strtotime('-30 days')));
                break;
            case 'engaged':
                $query->where('engagement_score', '>=', 70);
                break;
            case 'at_risk':
                $query->whereBetween('engagement_score', [30, 69])
                      ->where('last_activity_at', '<', date('Y-m-d', strtotime('-30 days')));
                break;
            case 'inactive':
                $query->where('engagement_score', '<', 30)
                      ->where('last_activity_at', '<', date('Y-m-d', strtotime('-90 days')));
                break;
        }

        return $query->get();
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (isset($filters['min_engagement'])) {
            $query->where('engagement_score', '>=', $filters['min_engagement']);
        }

        if (isset($filters['max_engagement'])) {
            $query->where('engagement_score', '<=', $filters['max_engagement']);
        }

        if (isset($filters['tags'])) {
            foreach ((array)$filters['tags'] as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if (isset($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (isset($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }
    }

    /**
     * Get subscribers by country
     */
    private function getByCountry(): array
    {
        return DB::table($this->table)
            ->where('status', 'subscribed')
            ->whereNotNull('country')
            ->select('country', DB::raw('COUNT(*) as count'))
            ->groupBy('country')
            ->orderBy('count', 'desc')
            ->get();
    }

    /**
     * Get subscribers by language
     */
    private function getByLanguage(): array
    {
        return DB::table($this->table)
            ->where('status', 'subscribed')
            ->select('language', DB::raw('COUNT(*) as count'))
            ->groupBy('language')
            ->orderBy('count', 'desc')
            ->get();
    }

    /**
     * Get subscribers by timezone
     */
    private function getByTimezone(): array
    {
        return DB::table($this->table)
            ->where('status', 'subscribed')
            ->whereNotNull('timezone')
            ->select('timezone', DB::raw('COUNT(*) as count'))
            ->groupBy('timezone')
            ->orderBy('count', 'desc')
            ->get();
    }

    /**
     * Get subscribers by source
     */
    private function getBySource(): array
    {
        return DB::table($this->table)
            ->where('status', 'subscribed')
            ->select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->orderBy('count', 'desc')
            ->get();
    }

    /**
     * Get subscribers by engagement level
     */
    private function getByEngagementLevel(): array
    {
        return DB::table($this->table)
            ->where('status', 'subscribed')
            ->select(
                DB::raw('CASE 
                    WHEN engagement_score >= 80 THEN "high"
                    WHEN engagement_score >= 50 THEN "medium"
                    ELSE "low"
                END as engagement_level'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('engagement_level')
            ->get();
    }
}