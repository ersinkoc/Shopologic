<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;

class AnalyticsRepository extends Repository
{
    protected string $table = 'email_analytics';
    protected string $primaryKey = 'id';

    /**
     * Track event
     */
    public function trackEvent(string $event, array $data): int
    {
        return DB::table($this->table)->insertGetId([
            'event_type' => $event,
            'event_data' => json_encode($data),
            'subscriber_id' => $data['subscriber_id'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'automation_id' => $data['automation_id'] ?? null,
            'email_send_id' => $data['email_send_id'] ?? null,
            'timestamp' => now(),
            'created_at' => now()
        ]);
    }

    /**
     * Get overview metrics
     */
    public function getOverviewMetrics(array $dateRange): array
    {
        $start = $dateRange['start'];
        $end = $dateRange['end'];
        
        // Email metrics
        $emailMetrics = DB::table('email_sends')
            ->whereBetween('sent_at', [$start, $end])
            ->select(
                DB::raw('COUNT(*) as total_sent'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                DB::raw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened'),
                DB::raw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked'),
                DB::raw('SUM(CASE WHEN status = "bounced" THEN 1 ELSE 0 END) as bounced'),
                DB::raw('SUM(CASE WHEN unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as unsubscribed')
            )
            ->first();
        
        // Subscriber metrics
        $subscriberMetrics = DB::table('email_subscribers')
            ->select(
                DB::raw('COUNT(*) as total_subscribers'),
                DB::raw('SUM(CASE WHEN status = "subscribed" THEN 1 ELSE 0 END) as active_subscribers'),
                DB::raw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_subscribers')
            )
            ->setBindings([$start, $end])
            ->first();
        
        // Campaign metrics
        $campaignMetrics = DB::table('email_campaigns')
            ->whereBetween('sent_at', [$start, $end])
            ->select(
                DB::raw('COUNT(*) as campaigns_sent'),
                DB::raw('AVG(recipients_count) as avg_recipients')
            )
            ->first();
        
        // Automation metrics
        $automationMetrics = DB::table('subscriber_automations')
            ->whereBetween('started_at', [$start, $end])
            ->select(
                DB::raw('COUNT(DISTINCT automation_id) as active_automations'),
                DB::raw('COUNT(*) as automation_enrollments'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as automation_completions')
            )
            ->first();
        
        return [
            'emails' => (array)$emailMetrics,
            'subscribers' => (array)$subscriberMetrics,
            'campaigns' => (array)$campaignMetrics,
            'automations' => (array)$automationMetrics
        ];
    }

    /**
     * Get trend data
     */
    public function getTrendData(array $dateRange, string $metric, string $groupBy = 'day'): array
    {
        $start = $dateRange['start'];
        $end = $dateRange['end'];
        
        $dateFormat = match($groupBy) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };
        
        $query = DB::table('email_sends')
            ->whereBetween('sent_at', [$start, $end])
            ->select(DB::raw("DATE_FORMAT(sent_at, '{$dateFormat}') as period"));
        
        switch ($metric) {
            case 'sends':
                $query->addSelect(DB::raw('COUNT(*) as value'));
                break;
            case 'opens':
                $query->addSelect(DB::raw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as value'));
                break;
            case 'clicks':
                $query->addSelect(DB::raw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as value'));
                break;
            case 'open_rate':
                $query->addSelect(DB::raw('AVG(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as value'));
                break;
            case 'click_rate':
                $query->addSelect(DB::raw('AVG(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as value'));
                break;
        }
        
        return $query->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    /**
     * Get engagement report data
     */
    public function getEngagementData(array $dateRange, int $segmentId = null): array
    {
        $query = DB::table('email_sends as s')
            ->join('email_subscribers as sub', 's.subscriber_id', '=', 'sub.id')
            ->whereBetween('s.sent_at', [$dateRange['start'], $dateRange['end']]);
        
        if ($segmentId) {
            $query->join('segment_members as sm', 'sub.id', '=', 'sm.subscriber_id')
                  ->where('sm.segment_id', $segmentId);
        }
        
        return $query->select(
                DB::raw('DATE(s.sent_at) as date'),
                DB::raw('COUNT(*) as sends'),
                DB::raw('SUM(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens'),
                DB::raw('SUM(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks'),
                DB::raw('AVG(sub.engagement_score) as avg_engagement_score')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get conversion data
     */
    public function getConversionData(array $dateRange, string $type = 'all'): array
    {
        $query = DB::table($this->table)
            ->where('event_type', 'conversion')
            ->whereBetween('timestamp', [$dateRange['start'], $dateRange['end']]);
        
        if ($type !== 'all') {
            $query->whereJsonContains('event_data->conversion_type', $type);
        }
        
        $conversions = $query->select(
                'campaign_id',
                'automation_id',
                DB::raw('COUNT(*) as conversions'),
                DB::raw('SUM(JSON_EXTRACT(event_data, "$.revenue")) as revenue')
            )
            ->groupBy('campaign_id', 'automation_id')
            ->get();
        
        return $conversions;
    }

    /**
     * Get subscriber lifecycle data
     */
    public function getSubscriberLifecycleData(array $dateRange): array
    {
        return DB::table('email_subscribers')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(CASE WHEN status = "subscribed" THEN 1 END) as new_subscribers'),
                DB::raw('COUNT(CASE WHEN status = "unsubscribed" AND DATE(unsubscribed_at) = DATE(created_at) THEN 1 END) as unsubscribed'),
                DB::raw('COUNT(CASE WHEN status = "bounced" THEN 1 END) as bounced'),
                DB::raw('COUNT(CASE WHEN status = "complained" THEN 1 END) as complained')
            )
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get top performing content
     */
    public function getTopPerformingContent(array $dateRange, int $limit = 10): array
    {
        // Top campaigns
        $topCampaigns = DB::table('email_campaigns as c')
            ->join('email_sends as s', 'c.id', '=', 's.campaign_id')
            ->whereBetween('c.sent_at', [$dateRange['start'], $dateRange['end']])
            ->groupBy('c.id', 'c.name', 'c.subject')
            ->select(
                'c.id',
                'c.name',
                'c.subject',
                DB::raw('COUNT(s.id) as sends'),
                DB::raw('AVG(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as open_rate'),
                DB::raw('AVG(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as click_rate'),
                DB::raw('(AVG(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) * 0.3 + 
                         AVG(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) * 0.7) * 100 as engagement_score')
            )
            ->orderBy('engagement_score', 'desc')
            ->limit($limit)
            ->get();
        
        // Top subject lines
        $topSubjects = DB::table('email_sends as s')
            ->whereBetween('s.sent_at', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('s.subject')
            ->groupBy('s.subject')
            ->select(
                's.subject',
                DB::raw('COUNT(*) as sends'),
                DB::raw('AVG(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as open_rate')
            )
            ->having('sends', '>', 100) // Minimum sends for statistical significance
            ->orderBy('open_rate', 'desc')
            ->limit($limit)
            ->get();
        
        return [
            'campaigns' => $topCampaigns,
            'subjects' => $topSubjects
        ];
    }

    /**
     * Get deliverability metrics
     */
    public function getDeliverabilityMetrics(array $dateRange, string $provider = null): array
    {
        $query = DB::table('email_sends')
            ->whereBetween('sent_at', [$dateRange['start'], $dateRange['end']]);
        
        if ($provider) {
            $query->where('provider', $provider);
        }
        
        return $query->select(
                DB::raw('DATE(sent_at) as date'),
                'provider',
                DB::raw('COUNT(*) as total_sent'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                DB::raw('SUM(CASE WHEN status = "bounced" THEN 1 ELSE 0 END) as bounced'),
                DB::raw('SUM(CASE WHEN bounce_type = "hard" THEN 1 ELSE 0 END) as hard_bounces'),
                DB::raw('SUM(CASE WHEN bounce_type = "soft" THEN 1 ELSE 0 END) as soft_bounces'),
                DB::raw('SUM(CASE WHEN status = "complained" THEN 1 ELSE 0 END) as complaints')
            )
            ->groupBy('date', 'provider')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get real-time statistics
     */
    public function getRealtimeStats(int $minutes = 60): array
    {
        $since = now()->subMinutes($minutes);
        
        return [
            'sends' => DB::table('email_sends')
                ->where('sent_at', '>=', $since)
                ->count(),
            'opens' => DB::table('email_opens')
                ->where('opened_at', '>=', $since)
                ->count(),
            'clicks' => DB::table('email_clicks')
                ->where('clicked_at', '>=', $since)
                ->count(),
            'unsubscribes' => DB::table('email_unsubscribes')
                ->where('unsubscribed_at', '>=', $since)
                ->count(),
            'active_campaigns' => DB::table('email_campaigns')
                ->where('status', 'sending')
                ->count(),
            'queue_size' => DB::table('email_queue')
                ->where('status', 'pending')
                ->count()
        ];
    }

    /**
     * Get benchmark comparison
     */
    public function getBenchmarkData(string $industry = null, string $companySize = null): array
    {
        // This would typically pull from a benchmarks table or external service
        // For now, return sample benchmark data
        return [
            'open_rate' => [
                'industry_avg' => 22.5,
                'top_performers' => 35.2
            ],
            'click_rate' => [
                'industry_avg' => 3.2,
                'top_performers' => 7.8
            ],
            'bounce_rate' => [
                'industry_avg' => 1.2,
                'top_performers' => 0.5
            ],
            'unsubscribe_rate' => [
                'industry_avg' => 0.25,
                'top_performers' => 0.1
            ]
        ];
    }

    /**
     * Clean up old analytics data
     */
    public function cleanupOldData(int $days = 365): int
    {
        return DB::table($this->table)
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Get revenue attribution
     */
    public function getRevenueAttribution(array $dateRange, string $attributionModel = 'last_click'): array
    {
        // This would implement various attribution models
        // For now, simple last-click attribution
        return DB::table($this->table)
            ->where('event_type', 'purchase')
            ->whereBetween('timestamp', [$dateRange['start'], $dateRange['end']])
            ->select(
                'campaign_id',
                'automation_id',
                DB::raw('COUNT(*) as conversions'),
                DB::raw('SUM(JSON_EXTRACT(event_data, "$.order_value")) as revenue'),
                DB::raw('AVG(JSON_EXTRACT(event_data, "$.order_value")) as avg_order_value')
            )
            ->groupBy('campaign_id', 'automation_id')
            ->orderBy('revenue', 'desc')
            ->get();
    }
}