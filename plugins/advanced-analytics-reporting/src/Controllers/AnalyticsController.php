<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AdvancedAnalyticsReporting\Services\{
    AnalyticsEngine,
    ReportGenerator,;
    EventTracker,;
    MetricsCalculator;
};

class AnalyticsController extends Controller
{
    private AnalyticsEngine $analyticsEngine;
    private ReportGenerator $reportGenerator;
    private EventTracker $eventTracker;
    private MetricsCalculator $metricsCalculator;

    public function __construct()
    {
        $this->analyticsEngine = app(AnalyticsEngine::class);
        $this->reportGenerator = app(ReportGenerator::class);
        $this->eventTracker = app(EventTracker::class);
        $this->metricsCalculator = app(MetricsCalculator::class);
    }

    /**
     * Get analytics overview
     */
    public function overview(Request $request): Response
    {
        $period = $request->query('period', '7days');
        $compareWith = $request->query('compare_with');

        $overview = $this->analyticsEngine->getOverview($period, $compareWith);

        return $this->json([
            'status' => 'success',
            'data' => $overview
        ]);
    }

    /**
     * Get real-time analytics
     */
    public function realtime(Request $request): Response
    {
        $data = $this->analyticsEngine->getRealtimeData();

        return $this->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Get traffic analytics
     */
    public function traffic(Request $request): Response
    {
        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'source' => $request->query('source'),
            'medium' => $request->query('medium'),
            'campaign' => $request->query('campaign')
        ];

        $traffic = $this->analyticsEngine->getTrafficAnalytics(array_filter($filters));

        return $this->json([
            'status' => 'success',
            'data' => $traffic
        ]);
    }

    /**
     * Get conversion analytics
     */
    public function conversions(Request $request): Response
    {
        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'goal' => $request->query('goal'),
            'source' => $request->query('source')
        ];

        $conversions = $this->analyticsEngine->getConversionAnalytics(array_filter($filters));

        return $this->json([
            'status' => 'success',
            'data' => $conversions
        ]);
    }

    /**
     * Get behavior analytics
     */
    public function behavior(Request $request): Response
    {
        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'page_type' => $request->query('page_type')
        ];

        $behavior = $this->analyticsEngine->getBehaviorAnalytics(array_filter($filters));

        return $this->json([
            'status' => 'success',
            'data' => $behavior
        ]);
    }

    /**
     * Get e-commerce analytics
     */
    public function ecommerce(Request $request): Response
    {
        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'product_id' => $request->query('product_id'),
            'category_id' => $request->query('category_id')
        ];

        $ecommerce = $this->analyticsEngine->getEcommerceAnalytics(array_filter($filters));

        return $this->json([
            'status' => 'success',
            'data' => $ecommerce
        ]);
    }

    /**
     * Get custom metrics
     */
    public function metrics(Request $request): Response
    {
        $metrics = $request->query('metrics', []);
        $dimensions = $request->query('dimensions', []);
        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date')
        ];

        $data = $this->metricsCalculator->calculateMetrics($metrics, $dimensions, array_filter($filters));

        return $this->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Track event
     */
    public function trackEvent(Request $request): Response
    {
        $this->validate($request, [
            'event_type' => 'required|string',
            'event_category' => 'required|string',
            'event_action' => 'string',
            'event_label' => 'string',
            'event_value' => 'numeric',
            'session_id' => 'string',
            'custom_data' => 'array'
        ]);

        try {
            $event = $this->eventTracker->trackEvent(
                $request->input('event_type'),
                $request->input('event_category'),
                $request->all()
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Event tracked successfully',
                'data' => ['event_id' => $event['id']]
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Track pageview
     */
    public function trackPageview(Request $request): Response
    {
        $this->validate($request, [
            'page_url' => 'required|string',
            'page_title' => 'string',
            'session_id' => 'string',
            'referrer' => 'string'
        ]);

        try {
            $event = $this->eventTracker->trackPageview(
                $request->input('page_url'),
                $request->input('page_title'),
                $request->all()
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Pageview tracked successfully',
                'data' => ['event_id' => $event['id']]
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get goals
     */
    public function goals(Request $request): Response
    {
        $goals = $this->analyticsEngine->getGoals();

        return $this->json([
            'status' => 'success',
            'data' => $goals
        ]);
    }

    /**
     * Create goal
     */
    public function createGoal(Request $request): Response
    {
        $this->validate($request, [
            'name' => 'required|string',
            'type' => 'required|in:destination,duration,pages_per_session,event',
            'conditions' => 'required|array',
            'value' => 'numeric'
        ]);

        try {
            $goal = $this->analyticsEngine->createGoal($request->all());

            return $this->json([
                'status' => 'success',
                'message' => 'Goal created successfully',
                'data' => $goal
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get funnel analysis
     */
    public function funnel(Request $request): Response
    {
        $this->validate($request, [
            'steps' => 'required|array',
            'start_date' => 'date',
            'end_date' => 'date'
        ]);

        $funnel = $this->analyticsEngine->analyzeFunnel(
            $request->input('steps'),
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->json([
            'status' => 'success',
            'data' => $funnel
        ]);
    }

    /**
     * Get cohort analysis
     */
    public function cohort(Request $request): Response
    {
        $metric = $request->query('metric', 'retention');
        $period = $request->query('period', 'weekly');
        $cohortSize = (int)$request->query('cohort_size', 4);

        $cohort = $this->analyticsEngine->getCohortAnalysis($metric, $period, $cohortSize);

        return $this->json([
            'status' => 'success',
            'data' => $cohort
        ]);
    }

    /**
     * Get user flow
     */
    public function userFlow(Request $request): Response
    {
        $startPage = $request->query('start_page');
        $maxSteps = (int)$request->query('max_steps', 5);
        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date')
        ];

        $flow = $this->analyticsEngine->getUserFlow($startPage, $maxSteps, array_filter($filters));

        return $this->json([
            'status' => 'success',
            'data' => $flow
        ]);
    }

    /**
     * Get attribution analysis
     */
    public function attribution(Request $request): Response
    {
        $model = $request->query('model', 'last_click');
        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'conversion_type' => $request->query('conversion_type')
        ];

        $attribution = $this->analyticsEngine->getAttributionAnalysis($model, array_filter($filters));

        return $this->json([
            'status' => 'success',
            'data' => $attribution
        ]);
    }

    /**
     * Generate report
     */
    public function generateReport(Request $request): Response
    {
        $this->validate($request, [
            'type' => 'required|string',
            'name' => 'required|string',
            'parameters' => 'required|array',
            'schedule' => 'array'
        ]);

        try {
            $report = $this->reportGenerator->createReport(
                $request->input('type'),
                $request->input('name'),
                $request->input('parameters'),
                $request->input('schedule')
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Report created successfully',
                'data' => $report
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get reports
     */
    public function reports(Request $request): Response
    {
        $type = $request->query('type');
        $status = $request->query('status');

        $reports = $this->reportGenerator->getReports($type, $status);

        return $this->json([
            'status' => 'success',
            'data' => $reports
        ]);
    }

    /**
     * Download report
     */
    public function downloadReport(Request $request, int $reportId): Response
    {
        try {
            $report = $this->reportGenerator->getReport($reportId);
            
            if (!$report) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Report not found'
                ], 404);
            }

            $file = $this->reportGenerator->exportReport($reportId, $request->query('format', 'pdf'));

            return $this->download($file, $report['name']);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get dashboard widgets
     */
    public function widgets(Request $request): Response
    {
        $widgets = $this->analyticsEngine->getDashboardWidgets();

        return $this->json([
            'status' => 'success',
            'data' => $widgets
        ]);
    }

    /**
     * Export analytics data
     */
    public function export(Request $request): Response
    {
        $this->validate($request, [
            'data_type' => 'required|string',
            'format' => 'required|in:csv,xlsx,json',
            'filters' => 'array'
        ]);

        try {
            $file = $this->analyticsEngine->exportData(
                $request->input('data_type'),
                $request->input('format'),
                $request->input('filters', [])
            );

            return $this->download($file, "analytics_export_{$request->input('data_type')}.{$request->input('format')}");
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}