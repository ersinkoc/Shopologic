<?php

declare(strict_types=1);

namespace Shopologic\Core\Analytics;

use Shopologic\Core\Database\DB;
use Shopologic\Core\Database\Model;
use Shopologic\Core\Template\TemplateEngineInterface;
use Shopologic\Core\Export\ExportManager;

/**
 * Analytics report generation system
 */
class ReportGenerator
{
    private DB $db;
    private TemplateEngineInterface $templateEngine;
    private ExportManager $exportManager;
    private AnalyticsEngine $analyticsEngine;
    private array $config;

    public function __construct(
        DB $db,
        TemplateEngineInterface $templateEngine,
        ExportManager $exportManager,
        AnalyticsEngine $analyticsEngine,
        array $config = []
    ) {
        $this->db = $db;
        $this->templateEngine = $templateEngine;
        $this->exportManager = $exportManager;
        $this->analyticsEngine = $analyticsEngine;
        $this->config = array_merge([
            'output_path' => 'storage/reports',
            'templates_path' => 'templates/reports',
            'formats' => ['pdf', 'excel', 'csv', 'html']
        ], $config);
    }

    /**
     * Generate report
     */
    public function generate(Report $report): string
    {
        $config = $report->config;
        
        try {
            // Collect data based on report type
            $data = $this->collectReportData($report);
            
            // Generate report content
            $content = $this->renderReport($report, $data);
            
            // Export to requested format
            $outputPath = $this->exportReport($report, $content, $data);
            
            // Update report status
            $report->status = 'completed';
            $report->output_path = $outputPath;
            $report->completed_at = new \DateTime();
            $report->save();
            
            return $outputPath;
            
        } catch (\Exception $e) {
            $report->status = 'failed';
            $report->error = $e->getMessage();
            $report->save();
            
            throw $e;
        }
    }

    /**
     * Generate dashboard data
     */
    public function generateDashboard(array $config): array
    {
        $period = $config['period'] ?? 'last_30_days';
        $compareWith = $config['compare_with'] ?? 'previous_period';
        
        // Calculate date ranges
        list($startDate, $endDate) = $this->calculateDateRange($period);
        list($compareStartDate, $compareEndDate) = $this->calculateComparisonDateRange(
            $startDate,
            $endDate,
            $compareWith
        );
        
        // Current period metrics
        $currentMetrics = $this->getDashboardMetrics($startDate, $endDate);
        
        // Comparison period metrics
        $comparisonMetrics = $this->getDashboardMetrics($compareStartDate, $compareEndDate);
        
        // Calculate changes
        $changes = $this->calculateChanges($currentMetrics, $comparisonMetrics);
        
        // Get additional dashboard data
        $realtimeData = $this->analyticsEngine->getRealTimeMetrics();
        $topContent = $this->getTopContent($startDate, $endDate);
        $trafficSources = $this->getTrafficSources($startDate, $endDate);
        $userFlow = $this->getUserFlow($startDate, $endDate);
        
        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'metrics' => $currentMetrics,
            'changes' => $changes,
            'realtime' => $realtimeData,
            'top_content' => $topContent,
            'traffic_sources' => $trafficSources,
            'user_flow' => $userFlow,
            'charts' => $this->generateDashboardCharts($startDate, $endDate)
        ];
    }

    /**
     * Generate scheduled reports
     */
    public function processScheduledReports(): void
    {
        $scheduledReports = ScheduledReport::where('status', 'active')
            ->where('next_run_at', '<=', date('Y-m-d H:i:s'))
            ->get();
        
        foreach ($scheduledReports as $scheduledReport) {
            try {
                // Create report instance
                $report = new Report();
                $report->fill([
                    'name' => $scheduledReport->name,
                    'type' => $scheduledReport->type,
                    'config' => $scheduledReport->config,
                    'scheduled_report_id' => $scheduledReport->id,
                    'status' => 'processing'
                ]);
                $report->save();
                
                // Generate report
                $outputPath = $this->generate($report);
                
                // Send report to recipients
                $this->distributeReport($scheduledReport, $outputPath);
                
                // Update next run time
                $scheduledReport->last_run_at = new \DateTime();
                $scheduledReport->next_run_at = $this->calculateNextRunTime($scheduledReport);
                $scheduledReport->save();
                
            } catch (\Exception $e) {
                // Log error
                error_log("Failed to generate scheduled report {$scheduledReport->id}: " . $e->getMessage());
            }
        }
    }

    // Private methods

    private function collectReportData(Report $report): array
    {
        $config = $report->config;
        $data = [];
        
        // Date range
        $startDate = new \DateTime($config['start_date']);
        $endDate = new \DateTime($config['end_date']);
        
        switch ($report->type) {
            case 'overview':
                $data = $this->collectOverviewData($startDate, $endDate);
                break;
                
            case 'audience':
                $data = $this->collectAudienceData($startDate, $endDate);
                break;
                
            case 'acquisition':
                $data = $this->collectAcquisitionData($startDate, $endDate);
                break;
                
            case 'behavior':
                $data = $this->collectBehaviorData($startDate, $endDate);
                break;
                
            case 'conversions':
                $data = $this->collectConversionsData($startDate, $endDate);
                break;
                
            case 'ecommerce':
                $data = $this->collectEcommerceData($startDate, $endDate);
                break;
                
            case 'custom':
                $data = $this->collectCustomData($config);
                break;
        }
        
        // Add common data
        $data['report'] = $report;
        $data['period'] = [
            'start' => $startDate,
            'end' => $endDate
        ];
        
        return $data;
    }

    private function collectOverviewData(\DateTime $startDate, \DateTime $endDate): array
    {
        return [
            'summary' => $this->getSummaryMetrics($startDate, $endDate),
            'trends' => $this->getTrendData($startDate, $endDate),
            'top_content' => $this->getTopContent($startDate, $endDate),
            'traffic_sources' => $this->getTrafficSources($startDate, $endDate),
            'devices' => $this->getDeviceBreakdown($startDate, $endDate),
            'locations' => $this->getLocationData($startDate, $endDate)
        ];
    }

    private function collectAudienceData(\DateTime $startDate, \DateTime $endDate): array
    {
        return [
            'demographics' => $this->getDemographics($startDate, $endDate),
            'interests' => $this->getInterests($startDate, $endDate),
            'behavior' => $this->getUserBehavior($startDate, $endDate),
            'technology' => $this->getTechnologyData($startDate, $endDate),
            'cohorts' => $this->analyticsEngine->getCohortAnalysis(
                'monthly',
                'retention',
                $startDate,
                $endDate
            ),
            'segments' => $this->analyticsEngine->getUserSegments()
        ];
    }

    private function collectAcquisitionData(\DateTime $startDate, \DateTime $endDate): array
    {
        return [
            'channels' => $this->getAcquisitionChannels($startDate, $endDate),
            'campaigns' => $this->getCampaignPerformance($startDate, $endDate),
            'sources' => $this->getTrafficSources($startDate, $endDate),
            'landing_pages' => $this->getLandingPagePerformance($startDate, $endDate),
            'referrals' => $this->getReferralData($startDate, $endDate)
        ];
    }

    private function collectBehaviorData(\DateTime $startDate, \DateTime $endDate): array
    {
        return [
            'pages' => $this->getPageAnalytics($startDate, $endDate),
            'events' => $this->getEventAnalytics($startDate, $endDate),
            'site_search' => $this->getSiteSearchData($startDate, $endDate),
            'user_flow' => $this->getUserFlow($startDate, $endDate),
            'site_speed' => $this->getSiteSpeedData($startDate, $endDate)
        ];
    }

    private function collectConversionsData(\DateTime $startDate, \DateTime $endDate): array
    {
        $conversionGoals = $this->getConversionGoals();
        $data = [];
        
        foreach ($conversionGoals as $goal) {
            $data['goals'][$goal->name] = [
                'conversions' => $this->getGoalConversions($goal, $startDate, $endDate),
                'funnel' => $this->getGoalFunnel($goal, $startDate, $endDate),
                'attribution' => $this->getGoalAttribution($goal, $startDate, $endDate)
            ];
        }
        
        $data['summary'] = $this->getConversionSummary($startDate, $endDate);
        $data['paths'] = $this->getConversionPaths($startDate, $endDate);
        
        return $data;
    }

    private function collectEcommerceData(\DateTime $startDate, \DateTime $endDate): array
    {
        return [
            'overview' => $this->getEcommerceOverview($startDate, $endDate),
            'products' => $this->getProductPerformance($startDate, $endDate),
            'transactions' => $this->getTransactionData($startDate, $endDate),
            'shopping_behavior' => $this->getShoppingBehavior($startDate, $endDate),
            'checkout_behavior' => $this->getCheckoutBehavior($startDate, $endDate),
            'product_lists' => $this->getProductListPerformance($startDate, $endDate)
        ];
    }

    private function renderReport(Report $report, array $data): string
    {
        $template = $this->config['templates_path'] . '/' . $report->type . '.twig';
        
        return $this->templateEngine->render($template, $data);
    }

    private function exportReport(Report $report, string $content, array $data): string
    {
        $format = $report->config['format'] ?? 'pdf';
        $filename = $this->generateFilename($report);
        
        switch ($format) {
            case 'pdf':
                return $this->exportManager->exportToPdf($content, $filename);
                
            case 'excel':
                return $this->exportManager->exportToExcel($data, $filename);
                
            case 'csv':
                return $this->exportManager->exportToCsv($data, $filename);
                
            case 'html':
                $path = $this->config['output_path'] . '/' . $filename . '.html';
                file_put_contents($path, $content);
                return $path;
                
            default:
                throw new \Exception("Unsupported format: {$format}");
        }
    }

    private function getDashboardMetrics(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->analyticsEngine->getMetrics(
            $startDate,
            $endDate,
            ['users', 'sessions', 'pageviews', 'bounce_rate', 'avg_session_duration']
        );
    }

    private function calculateChanges(array $current, array $previous): array
    {
        $changes = [];
        
        foreach ($current as $metric => $value) {
            $previousValue = $previous[$metric] ?? 0;
            
            if ($previousValue > 0) {
                $change = (($value - $previousValue) / $previousValue) * 100;
            } else {
                $change = $value > 0 ? 100 : 0;
            }
            
            $changes[$metric] = [
                'value' => $change,
                'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat')
            ];
        }
        
        return $changes;
    }

    private function generateDashboardCharts(\DateTime $startDate, \DateTime $endDate): array
    {
        return [
            'traffic' => $this->generateTrafficChart($startDate, $endDate),
            'conversions' => $this->generateConversionsChart($startDate, $endDate),
            'revenue' => $this->generateRevenueChart($startDate, $endDate),
            'devices' => $this->generateDevicesChart($startDate, $endDate)
        ];
    }

    private function generateTrafficChart(\DateTime $startDate, \DateTime $endDate): array
    {
        $data = $this->db->table('analytics_aggregations')
            ->select('date', 'unique_users', 'sessions', 'pageviews')
            ->where('type', 'daily')
            ->whereBetween('date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])
            ->orderBy('date')
            ->get();
        
        return [
            'labels' => $data->pluck('date')->toArray(),
            'datasets' => [
                [
                    'label' => 'Users',
                    'data' => $data->pluck('unique_users')->toArray()
                ],
                [
                    'label' => 'Sessions',
                    'data' => $data->pluck('sessions')->toArray()
                ],
                [
                    'label' => 'Page Views',
                    'data' => $data->pluck('pageviews')->toArray()
                ]
            ]
        ];
    }

    private function calculateDateRange(string $period): array
    {
        $endDate = new \DateTime();
        $startDate = clone $endDate;
        
        switch ($period) {
            case 'today':
                $startDate->setTime(0, 0, 0);
                break;
            case 'yesterday':
                $startDate->modify('-1 day')->setTime(0, 0, 0);
                $endDate->modify('-1 day')->setTime(23, 59, 59);
                break;
            case 'last_7_days':
                $startDate->modify('-7 days');
                break;
            case 'last_30_days':
                $startDate->modify('-30 days');
                break;
            case 'last_90_days':
                $startDate->modify('-90 days');
                break;
            case 'this_month':
                $startDate->modify('first day of this month')->setTime(0, 0, 0);
                break;
            case 'last_month':
                $startDate->modify('first day of last month')->setTime(0, 0, 0);
                $endDate->modify('last day of last month')->setTime(23, 59, 59);
                break;
            case 'this_year':
                $startDate->modify('first day of January')->setTime(0, 0, 0);
                break;
        }
        
        return [$startDate, $endDate];
    }

    private function calculateComparisonDateRange(
        \DateTime $startDate,
        \DateTime $endDate,
        string $compareWith
    ): array {
        $interval = $startDate->diff($endDate);
        $days = $interval->days + 1;
        
        switch ($compareWith) {
            case 'previous_period':
                $compareEndDate = clone $startDate;
                $compareEndDate->modify('-1 day');
                $compareStartDate = clone $compareEndDate;
                $compareStartDate->modify("-{$days} days");
                break;
                
            case 'previous_year':
                $compareStartDate = clone $startDate;
                $compareStartDate->modify('-1 year');
                $compareEndDate = clone $endDate;
                $compareEndDate->modify('-1 year');
                break;
                
            default:
                $compareStartDate = clone $startDate;
                $compareEndDate = clone $endDate;
        }
        
        return [$compareStartDate, $compareEndDate];
    }

    private function distributeReport(ScheduledReport $scheduledReport, string $filePath): void
    {
        foreach ($scheduledReport->recipients as $recipient) {
            // Email report
            $this->emailReport($recipient, $scheduledReport, $filePath);
        }
    }

    private function calculateNextRunTime(ScheduledReport $report): \DateTime
    {
        $next = new \DateTime();
        
        switch ($report->frequency) {
            case 'daily':
                $next->modify('+1 day');
                break;
            case 'weekly':
                $next->modify('+1 week');
                break;
            case 'monthly':
                $next->modify('+1 month');
                break;
        }
        
        return $next;
    }

    private function generateFilename(Report $report): string
    {
        return sprintf(
            '%s_%s_%s',
            str_replace(' ', '_', strtolower($report->name)),
            $report->type,
            date('Y-m-d_His')
        );
    }

    // Additional helper methods for specific data collection...
    
    private function getSummaryMetrics(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->db->table('analytics_aggregations')
            ->selectRaw('
                SUM(unique_users) as total_users,
                SUM(sessions) as total_sessions,
                SUM(pageviews) as total_pageviews,
                AVG(bounce_rate) as avg_bounce_rate,
                AVG(avg_session_duration) as avg_session_duration
            ')
            ->where('type', 'daily')
            ->whereBetween('date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])
            ->first();
    }

    private function getTopContent(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->db->table('analytics_events')
            ->select('properties->>"$.page" as page', 'COUNT(*) as views')
            ->where('event', 'page_view')
            ->whereBetween('timestamp', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->groupBy('page')
            ->orderBy('views', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    private function getTrafficSources(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->db->table('analytics_sessions')
            ->select('source', 'medium', 'COUNT(*) as sessions', 'COUNT(DISTINCT user_id) as users')
            ->whereBetween('started_at', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->groupBy('source', 'medium')
            ->orderBy('sessions', 'desc')
            ->get()
            ->toArray();
    }

    private function getUserFlow(\DateTime $startDate, \DateTime $endDate): array
    {
        // Simplified user flow - track page sequences
        return $this->db->table('analytics_events')
            ->selectRaw("
                session_id,
                properties->>'$.page' as page,
                timestamp
            ")
            ->where('event', 'page_view')
            ->whereBetween('timestamp', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->orderBy('session_id')
            ->orderBy('timestamp')
            ->get()
            ->groupBy('session_id')
            ->map(function ($pages) {
                return $pages->pluck('page')->toArray();
            })
            ->take(100)
            ->toArray();
    }
}

/**
 * Report model
 */
class Report extends Model
{
    protected $table = 'analytics_reports';
    
    protected $fillable = [
        'name', 'type', 'config', 'status',
        'scheduled_report_id', 'output_path',
        'completed_at', 'error'
    ];
    
    protected $casts = [
        'config' => 'array',
        'completed_at' => 'datetime'
    ];
}

/**
 * Scheduled report model
 */
class ScheduledReport extends Model
{
    protected $table = 'scheduled_reports';
    
    protected $fillable = [
        'name', 'type', 'config', 'frequency',
        'recipients', 'status', 'last_run_at',
        'next_run_at'
    ];
    
    protected $casts = [
        'config' => 'array',
        'recipients' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime'
    ];
}