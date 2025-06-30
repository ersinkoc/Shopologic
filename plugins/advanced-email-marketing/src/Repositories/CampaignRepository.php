<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;
use AdvancedEmailMarketing\Models\Campaign;

class CampaignRepository extends Repository
{
    protected string $table = 'email_campaigns';
    protected string $primaryKey = 'id';
    protected string $modelClass = Campaign::class;

    /**
     * Get campaigns with pagination
     */
    public function getWithPagination(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = DB::table($this->table);
        
        $this->applyFilters($query, $filters);
        
        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        
        $campaigns = $query->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();
        
        return [
            'data' => $campaigns,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }

    /**
     * Find campaigns by segment
     */
    public function findBySegmentId(int $segmentId): array
    {
        return DB::table($this->table . ' as c')
            ->join('campaign_segments as cs', 'c.id', '=', 'cs.campaign_id')
            ->where('cs.segment_id', $segmentId)
            ->select('c.*')
            ->get();
    }

    /**
     * Get scheduled campaigns ready to send
     */
    public function getScheduledCampaignsToSend(): array
    {
        return DB::table($this->table)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();
    }

    /**
     * Get active campaigns
     */
    public function getActiveCampaigns(): array
    {
        return DB::table($this->table)
            ->whereIn('status', ['scheduled', 'sending'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Get campaign statistics
     */
    public function getCampaignStatistics(int $campaignId): array
    {
        $campaign = $this->findById($campaignId);
        if (!$campaign) {
            return [];
        }
        
        $stats = DB::table('email_sends as s')
            ->where('s.campaign_id', $campaignId)
            ->select(
                DB::raw('COUNT(*) as total_sent'),
                DB::raw('SUM(CASE WHEN s.status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                DB::raw('SUM(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened'),
                DB::raw('SUM(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked'),
                DB::raw('SUM(CASE WHEN s.status = "bounced" THEN 1 ELSE 0 END) as bounced'),
                DB::raw('SUM(CASE WHEN s.status = "complained" THEN 1 ELSE 0 END) as complained'),
                DB::raw('SUM(CASE WHEN s.unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as unsubscribed')
            )
            ->first();
        
        return (array)$stats;
    }

    /**
     * Get campaigns by template
     */
    public function findByTemplateId(int $templateId): array
    {
        return DB::table($this->table)
            ->where('template_id', $templateId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get recent campaigns
     */
    public function getRecentCampaigns(int $limit = 10): array
    {
        return DB::table($this->table)
            ->where('status', 'sent')
            ->orderBy('sent_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get campaign performance comparison
     */
    public function getCampaignPerformanceComparison(array $campaignIds): array
    {
        return DB::table('email_sends as s')
            ->whereIn('s.campaign_id', $campaignIds)
            ->groupBy('s.campaign_id')
            ->select(
                's.campaign_id',
                DB::raw('COUNT(*) as sends'),
                DB::raw('AVG(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as open_rate'),
                DB::raw('AVG(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as click_rate'),
                DB::raw('AVG(CASE WHEN s.status = "bounced" THEN 1 ELSE 0 END) * 100 as bounce_rate')
            )
            ->get();
    }

    /**
     * Get AB test results
     */
    public function getABTestResults(int $campaignId): array
    {
        $campaign = $this->findById($campaignId);
        if (!$campaign || $campaign['type'] !== 'ab_test') {
            return [];
        }
        
        return DB::table('campaign_variants as v')
            ->leftJoin('email_sends as s', 'v.id', '=', 's.variant_id')
            ->where('v.campaign_id', $campaignId)
            ->groupBy('v.id', 'v.name', 'v.subject', 'v.content_variation')
            ->select(
                'v.id',
                'v.name',
                'v.subject',
                'v.content_variation',
                DB::raw('COUNT(s.id) as sends'),
                DB::raw('SUM(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens'),
                DB::raw('SUM(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks'),
                DB::raw('AVG(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as open_rate'),
                DB::raw('AVG(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as click_rate')
            )
            ->get();
    }

    /**
     * Update campaign status
     */
    public function updateStatus(int $campaignId, string $status): bool
    {
        return DB::table($this->table)
            ->where('id', $campaignId)
            ->update([
                'status' => $status,
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * Update send progress
     */
    public function updateSendProgress(int $campaignId, int $sent, int $failed = 0): bool
    {
        return DB::table($this->table)
            ->where('id', $campaignId)
            ->update([
                'sent_count' => $sent,
                'failed_count' => $failed,
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * Mark campaign as sent
     */
    public function markAsSent(int $campaignId): bool
    {
        return DB::table($this->table)
            ->where('id', $campaignId)
            ->update([
                'status' => 'sent',
                'completed_at' => now(),
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * Get campaigns by date range
     */
    public function getCampaignsByDateRange(\DateTime $start, \DateTime $end): array
    {
        return DB::table($this->table)
            ->whereBetween('sent_at', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
            ->orderBy('sent_at')
            ->get();
    }

    /**
     * Search campaigns
     */
    public function searchCampaigns(string $query): array
    {
        return DB::table($this->table)
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('subject', 'LIKE', "%{$query}%");
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    /**
     * Get campaign recipients count
     */
    public function getRecipientsCount(int $campaignId): int
    {
        $campaign = $this->findById($campaignId);
        if (!$campaign) {
            return 0;
        }
        
        // If already calculated
        if (isset($campaign['recipients_count']) && $campaign['recipients_count'] > 0) {
            return $campaign['recipients_count'];
        }
        
        // Calculate from segments
        $segmentIds = DB::table('campaign_segments')
            ->where('campaign_id', $campaignId)
            ->pluck('segment_id');
        
        if (empty($segmentIds)) {
            // All subscribers
            return DB::table('email_subscribers')
                ->where('status', 'subscribed')
                ->count();
        }
        
        // Count unique subscribers in segments
        return DB::table('segment_members')
            ->whereIn('segment_id', $segmentIds)
            ->distinct('subscriber_id')
            ->count();
    }

    /**
     * Get top performing campaigns
     */
    public function getTopPerformingCampaigns(int $limit = 10, string $metric = 'engagement'): array
    {
        $query = DB::table($this->table . ' as c')
            ->join('email_sends as s', 'c.id', '=', 's.campaign_id')
            ->where('c.status', 'sent')
            ->groupBy('c.id', 'c.name', 'c.subject', 'c.sent_at');
        
        switch ($metric) {
            case 'open_rate':
                $query->orderBy(DB::raw('AVG(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END)'), 'desc');
                break;
            case 'click_rate':
                $query->orderBy(DB::raw('AVG(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END)'), 'desc');
                break;
            case 'revenue':
                // This would require revenue tracking implementation
                $query->orderBy('c.sent_at', 'desc');
                break;
            default: // engagement
                $query->orderBy(DB::raw('AVG(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) * 0.3 + AVG(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) * 0.7'), 'desc');
        }
        
        return $query->select(
            'c.id',
            'c.name',
            'c.subject',
            'c.sent_at',
            DB::raw('COUNT(s.id) as sends'),
            DB::raw('AVG(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as open_rate'),
            DB::raw('AVG(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as click_rate')
        )
        ->limit($limit)
        ->get();
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        
        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('subject', 'LIKE', '%' . $filters['search'] . '%');
            });
        }
    }
}