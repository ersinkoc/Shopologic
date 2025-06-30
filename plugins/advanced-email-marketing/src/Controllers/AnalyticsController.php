<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AdvancedEmailMarketing\Services\AnalyticsService;
use AdvancedEmailMarketing\Repositories\{
    AnalyticsRepository,
    CampaignRepository,;
    AutomationRepository,;
    SubscriberRepository;
};

class AnalyticsController extends Controller
{
    private AnalyticsService $analyticsService;
    private AnalyticsRepository $analyticsRepository;
    private CampaignRepository $campaignRepository;
    private AutomationRepository $automationRepository;
    private SubscriberRepository $subscriberRepository;

    public function __construct()
    {
        $this->analyticsService = app(AnalyticsService::class);
        $this->analyticsRepository = app(AnalyticsRepository::class);
        $this->campaignRepository = app(CampaignRepository::class);
        $this->automationRepository = app(AutomationRepository::class);
        $this->subscriberRepository = app(SubscriberRepository::class);
    }

    /**
     * Get analytics overview
     */
    public function overview(Request $request): Response
    {
        $period = $request->query('period', 'last_30_days');
        $compareWith = $request->query('compare_with');
        
        $dateRange = $this->getDateRange($period);
        $compareDateRange = $compareWith ? $this->getDateRange($compareWith) : null;
        
        $overview = $this->analyticsService->getOverview($dateRange, $compareDateRange);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'metrics' => $overview['metrics'],
                'trends' => $overview['trends'],
                'comparison' => $overview['comparison'] ?? null,
                'period' => $period,
                'date_range' => $dateRange
            ]
        ]);
    }

    /**
     * Get campaign metrics
     */
    public function campaignMetrics(Request $request, int $id): Response
    {
        $campaign = $this->campaignRepository->findById($id);
        
        if (!$campaign) {
            return $this->json([
                'status' => 'error',
                'message' => 'Campaign not found'
            ], 404);
        }
        
        $includeDetails = (bool)$request->query('include_details', false);
        $metrics = $this->analyticsService->getCampaignMetrics($id, $includeDetails);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'campaign' => [
                    'id' => $campaign['id'],
                    'name' => $campaign['name'],
                    'sent_at' => $campaign['sent_at']
                ],
                'metrics' => $metrics['summary'],
                'engagement' => $metrics['engagement'],
                'performance' => $metrics['performance'],
                'details' => $includeDetails ? $metrics['details'] : null
            ]
        ]);
    }

    /**
     * Get automation metrics
     */
    public function automationMetrics(Request $request, int $id): Response
    {
        $automation = $this->automationRepository->findById($id);
        
        if (!$automation) {
            return $this->json([
                'status' => 'error',
                'message' => 'Automation not found'
            ], 404);
        }
        
        $period = $request->query('period', 'last_30_days');
        $dateRange = $this->getDateRange($period);
        
        $metrics = $this->analyticsService->getAutomationMetrics($id, $dateRange);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'automation' => [
                    'id' => $automation['id'],
                    'name' => $automation['name'],
                    'status' => $automation['status']
                ],
                'metrics' => $metrics['summary'],
                'performance' => $metrics['performance'],
                'funnel' => $metrics['funnel'],
                'trends' => $metrics['trends']
            ]
        ]);
    }

    /**
     * Get subscriber analytics
     */
    public function subscriberAnalytics(Request $request): Response
    {
        $period = $request->query('period', 'last_30_days');
        $dateRange = $this->getDateRange($period);
        
        $analytics = $this->analyticsService->getSubscriberAnalytics($dateRange);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'growth' => $analytics['growth'],
                'engagement' => $analytics['engagement'],
                'demographics' => $analytics['demographics'],
                'lifecycle' => $analytics['lifecycle'],
                'trends' => $analytics['trends']
            ]
        ]);
    }

    /**
     * Get engagement report
     */
    public function engagementReport(Request $request): Response
    {
        $period = $request->query('period', 'last_30_days');
        $groupBy = $request->query('group_by', 'day');
        $segmentId = $request->query('segment_id');
        
        $dateRange = $this->getDateRange($period);
        $report = $this->analyticsService->getEngagementReport($dateRange, $groupBy, $segmentId);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'summary' => $report['summary'],
                'timeline' => $report['timeline'],
                'top_performers' => $report['top_performers'],
                'engagement_distribution' => $report['distribution']
            ]
        ]);
    }

    /**
     * Get conversion report
     */
    public function conversionReport(Request $request): Response
    {
        $period = $request->query('period', 'last_30_days');
        $conversionType = $request->query('type', 'all');
        
        $dateRange = $this->getDateRange($period);
        $report = $this->analyticsService->getConversionReport($dateRange, $conversionType);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'summary' => $report['summary'],
                'campaigns' => $report['campaigns'],
                'automations' => $report['automations'],
                'attribution' => $report['attribution'],
                'revenue' => $report['revenue']
            ]
        ]);
    }

    /**
     * Get deliverability report
     */
    public function deliverabilityReport(Request $request): Response
    {
        $period = $request->query('period', 'last_30_days');
        $provider = $request->query('provider');
        
        $dateRange = $this->getDateRange($period);
        $report = $this->analyticsService->getDeliverabilityReport($dateRange, $provider);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'summary' => $report['summary'],
                'providers' => $report['providers'],
                'bounces' => $report['bounces'],
                'complaints' => $report['complaints'],
                'reputation' => $report['reputation']
            ]
        ]);
    }

    /**
     * Get click map data
     */
    public function clickMap(Request $request, int $campaignId): Response
    {
        $campaign = $this->campaignRepository->findById($campaignId);
        
        if (!$campaign) {
            return $this->json([
                'status' => 'error',
                'message' => 'Campaign not found'
            ], 404);
        }
        
        $clickMap = $this->analyticsService->getClickMap($campaignId);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'campaign_id' => $campaignId,
                'total_clicks' => $clickMap['total_clicks'],
                'unique_clicks' => $clickMap['unique_clicks'],
                'links' => $clickMap['links'],
                'heatmap' => $clickMap['heatmap']
            ]
        ]);
    }

    /**
     * Get A/B test results
     */
    public function abTestResults(Request $request, int $campaignId): Response
    {
        $campaign = $this->campaignRepository->findById($campaignId);
        
        if (!$campaign) {
            return $this->json([
                'status' => 'error',
                'message' => 'Campaign not found'
            ], 404);
        }
        
        if ($campaign['type'] !== 'ab_test') {
            return $this->json([
                'status' => 'error',
                'message' => 'Campaign is not an A/B test'
            ], 400);
        }
        
        $results = $this->analyticsService->getABTestResults($campaignId);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'variants' => $results['variants'],
                'winner' => $results['winner'],
                'confidence' => $results['confidence'],
                'metrics' => $results['metrics']
            ]
        ]);
    }

    /**
     * Get revenue attribution
     */
    public function revenueAttribution(Request $request): Response
    {
        $period = $request->query('period', 'last_30_days');
        $attributionModel = $request->query('model', 'last_click');
        
        $dateRange = $this->getDateRange($period);
        $attribution = $this->analyticsService->getRevenueAttribution($dateRange, $attributionModel);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'total_revenue' => $attribution['total_revenue'],
                'campaigns' => $attribution['campaigns'],
                'automations' => $attribution['automations'],
                'channels' => $attribution['channels'],
                'top_products' => $attribution['top_products']
            ]
        ]);
    }

    /**
     * Get custom report
     */
    public function customReport(Request $request): Response
    {
        $this->validate($request, [
            'metrics' => 'required|array',
            'dimensions' => 'array',
            'filters' => 'array',
            'period' => 'string',
            'group_by' => 'string'
        ]);
        
        $period = $request->input('period', 'last_30_days');
        $dateRange = $this->getDateRange($period);
        
        $reportConfig = [
            'metrics' => $request->input('metrics'),
            'dimensions' => $request->input('dimensions', []),
            'filters' => $request->input('filters', []),
            'group_by' => $request->input('group_by', 'day')
        ];
        
        try {
            $report = $this->analyticsService->generateCustomReport($reportConfig, $dateRange);
            
            return $this->json([
                'status' => 'success',
                'data' => $report
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Export analytics data
     */
    public function export(Request $request): Response
    {
        $this->validate($request, [
            'report_type' => 'required|string',
            'format' => 'in:csv,excel,pdf',
            'period' => 'string',
            'filters' => 'array'
        ]);
        
        $reportType = $request->input('report_type');
        $format = $request->input('format', 'csv');
        $period = $request->input('period', 'last_30_days');
        $filters = $request->input('filters', []);
        
        $dateRange = $this->getDateRange($period);
        
        try {
            $export = $this->analyticsService->exportReport($reportType, $dateRange, $filters, $format);
            
            return $this->download($export['content'], $export['filename'], $export['mime_type']);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get real-time analytics
     */
    public function realtime(Request $request): Response
    {
        $timeframe = (int)$request->query('timeframe', 60); // Minutes
        
        $realtime = $this->analyticsService->getRealtimeAnalytics($timeframe);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'active_campaigns' => $realtime['active_campaigns'],
                'sending_queue' => $realtime['sending_queue'],
                'recent_activity' => $realtime['recent_activity'],
                'live_metrics' => $realtime['live_metrics']
            ]
        ]);
    }

    /**
     * Get benchmark data
     */
    public function benchmarks(Request $request): Response
    {
        $industry = $request->query('industry');
        $companySize = $request->query('company_size');
        
        $benchmarks = $this->analyticsService->getBenchmarks($industry, $companySize);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'industry_averages' => $benchmarks['industry'],
                'your_performance' => $benchmarks['performance'],
                'comparison' => $benchmarks['comparison'],
                'recommendations' => $benchmarks['recommendations']
            ]
        ]);
    }

    /**
     * Get date range from period string
     */
    private function getDateRange(string $period): array
    {
        $now = new \DateTime();
        $start = clone $now;
        
        switch ($period) {
            case 'today':
                $start->setTime(0, 0, 0);
                break;
            case 'yesterday':
                $start->modify('-1 day')->setTime(0, 0, 0);
                $now->modify('-1 day')->setTime(23, 59, 59);
                break;
            case 'last_7_days':
                $start->modify('-7 days');
                break;
            case 'last_30_days':
                $start->modify('-30 days');
                break;
            case 'last_90_days':
                $start->modify('-90 days');
                break;
            case 'this_month':
                $start->modify('first day of this month')->setTime(0, 0, 0);
                break;
            case 'last_month':
                $start->modify('first day of last month')->setTime(0, 0, 0);
                $now->modify('last day of last month')->setTime(23, 59, 59);
                break;
            case 'this_year':
                $start->modify('first day of January')->setTime(0, 0, 0);
                break;
            default:
                // Custom date range
                if (strpos($period, '|') !== false) {
                    list($startDate, $endDate) = explode('|', $period);
                    $start = new \DateTime($startDate);
                    $now = new \DateTime($endDate);
                }
        }
        
        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $now->format('Y-m-d H:i:s')
        ];
    }
}