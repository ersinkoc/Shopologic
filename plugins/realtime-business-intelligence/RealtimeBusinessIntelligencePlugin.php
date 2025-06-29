<?php

namespace RealtimeBusinessIntelligence;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Hook\HookSystem;
use Shopologic\Core\Container\ContainerInterface;
use Shopologic\Dashboard\WidgetInterface;
use Shopologic\Cron\CronInterface;
use RealtimeBusinessIntelligence\Services\MetricsServiceInterface;
use RealtimeBusinessIntelligence\Services\MetricsService;
use RealtimeBusinessIntelligence\Services\KpiServiceInterface;
use RealtimeBusinessIntelligence\Services\KpiService;
use RealtimeBusinessIntelligence\Services\AlertServiceInterface;
use RealtimeBusinessIntelligence\Services\AlertService;
use RealtimeBusinessIntelligence\Services\ReportServiceInterface;
use RealtimeBusinessIntelligence\Services\ReportService;
use RealtimeBusinessIntelligence\Services\PredictiveServiceInterface;
use RealtimeBusinessIntelligence\Services\PredictiveService;
use RealtimeBusinessIntelligence\Repositories\MetricsRepositoryInterface;
use RealtimeBusinessIntelligence\Repositories\MetricsRepository;
use RealtimeBusinessIntelligence\Controllers\BiApiController;
use RealtimeBusinessIntelligence\Jobs\UpdateMetricsJob;

/**
 * Real-time Business Intelligence Dashboard Plugin
 * 
 * Provides comprehensive real-time analytics, KPI tracking, predictive insights,
 * automated alerting, and executive reporting capabilities
 */
class RealtimeBusinessIntelligencePlugin extends AbstractPlugin implements WidgetInterface, CronInterface
{
    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerApiEndpoints();
        $this->registerCronJobs();
        $this->registerPermissions();
        $this->registerWidgets();
    }

    protected function registerServices(): void
    {
        $this->container->bind(MetricsServiceInterface::class, MetricsService::class);
        $this->container->bind(KpiServiceInterface::class, KpiService::class);
        $this->container->bind(AlertServiceInterface::class, AlertService::class);
        $this->container->bind(ReportServiceInterface::class, ReportService::class);
        $this->container->bind(PredictiveServiceInterface::class, PredictiveService::class);
        $this->container->bind(MetricsRepositoryInterface::class, MetricsRepository::class);

        $this->container->singleton(MetricsService::class, function(ContainerInterface $container) {
            return new MetricsService(
                $container->get(MetricsRepositoryInterface::class),
                $container->get('cache'),
                $container->get('events'),
                $this->getConfig()
            );
        });

        $this->container->singleton(KpiService::class, function(ContainerInterface $container) {
            return new KpiService(
                $container->get('database'),
                $container->get(MetricsServiceInterface::class),
                $this->getConfig('kpis', [])
            );
        });

        $this->container->singleton(AlertService::class, function(ContainerInterface $container) {
            return new AlertService(
                $container->get('database'),
                $container->get('events'),
                $this->getConfig('alerts', [])
            );
        });

        $this->container->singleton(ReportService::class, function(ContainerInterface $container) {
            return new ReportService(
                $container->get('database'),
                $container->get('storage'),
                $container->get(MetricsServiceInterface::class),
                $this->getConfig('reports', [])
            );
        });

        $this->container->singleton(PredictiveService::class, function(ContainerInterface $container) {
            return new PredictiveService(
                $container->get('database'),
                $container->get(MetricsServiceInterface::class),
                $this->getConfig('predictive', [])
            );
        });
    }

    protected function registerHooks(): void
    {
        // Real-time metric updates
        HookSystem::addAction('order.created', [$this, 'updateSalesMetrics'], 5);
        HookSystem::addAction('user.registered', [$this, 'updateCustomerMetrics'], 5);
        HookSystem::addAction('product.viewed', [$this, 'updateTrafficMetrics'], 5);
        HookSystem::addAction('cart.abandoned', [$this, 'updateConversionMetrics'], 5);
        
        // KPI calculations
        HookSystem::addAction('metrics.updated', [$this, 'recalculateKpis'], 10);
        HookSystem::addFilter('kpi.calculate', [$this, 'calculateCustomKpi'], 10);
        HookSystem::addAction('kpi.threshold_exceeded', [$this, 'triggerKpiAlert'], 5);
        
        // Dashboard data
        HookSystem::addFilter('dashboard.widgets', [$this, 'addBiWidgets'], 10);
        HookSystem::addFilter('dashboard.realtime_data', [$this, 'provideDashboardData'], 10);
        HookSystem::addAction('dashboard.refresh', [$this, 'refreshDashboardMetrics'], 5);
        
        // Alert system
        HookSystem::addAction('alert.condition_met', [$this, 'processAlert'], 5);
        HookSystem::addFilter('alert.recipients', [$this, 'determineAlertRecipients'], 10);
        HookSystem::addAction('alert.escalation', [$this, 'escalateAlert'], 10);
        
        // Predictive analytics
        HookSystem::addFilter('analytics.forecast', [$this, 'generateForecast'], 10);
        HookSystem::addAction('prediction.model_updated', [$this, 'updatePredictions'], 10);
        HookSystem::addFilter('trend.analysis', [$this, 'analyzeTrends'], 10);
        
        // Executive reporting
        HookSystem::addAction('report.schedule', [$this, 'scheduleReport'], 10);
        HookSystem::addFilter('report.executive_summary', [$this, 'generateExecutiveSummary'], 10);
        HookSystem::addAction('report.distribute', [$this, 'distributeReport'], 10);
    }

    protected function registerApiEndpoints(): void
    {
        $this->router->group(['prefix' => 'api/v1/bi'], function($router) {
            // Dashboard endpoints
            $router->get('/dashboard', [BiApiController::class, 'getDashboard']);
            $router->get('/dashboard/realtime', [BiApiController::class, 'getRealtimeData']);
            $router->post('/dashboard/customize', [BiApiController::class, 'customizeDashboard']);
            
            // KPI endpoints
            $router->get('/kpis', [BiApiController::class, 'getKpis']);
            $router->get('/kpis/{kpi_id}', [BiApiController::class, 'getKpiDetails']);
            $router->post('/kpis/custom', [BiApiController::class, 'createCustomKpi']);
            $router->get('/kpis/{kpi_id}/history', [BiApiController::class, 'getKpiHistory']);
            
            // Metrics endpoints
            $router->get('/metrics', [BiApiController::class, 'getMetrics']);
            $router->get('/metrics/realtime', [BiApiController::class, 'getRealtimeMetrics']);
            $router->post('/metrics/track', [BiApiController::class, 'trackCustomMetric']);
            
            // Alerts endpoints
            $router->get('/alerts', [BiApiController::class, 'getAlerts']);
            $router->post('/alerts/create', [BiApiController::class, 'createAlert']);
            $router->put('/alerts/{alert_id}', [BiApiController::class, 'updateAlert']);
            $router->post('/alerts/{alert_id}/acknowledge', [BiApiController::class, 'acknowledgeAlert']);
            
            // Reports endpoints
            $router->get('/reports', [BiApiController::class, 'getReports']);
            $router->post('/reports/generate', [BiApiController::class, 'generateReport']);
            $router->get('/reports/{report_id}', [BiApiController::class, 'getReport']);
            $router->get('/reports/executive-summary', [BiApiController::class, 'getExecutiveSummary']);
            
            // Trends and forecasting
            $router->get('/trends', [BiApiController::class, 'getTrends']);
            $router->get('/forecast', [BiApiController::class, 'getForecast']);
            $router->post('/analyze', [BiApiController::class, 'performAnalysis']);
        });

        // GraphQL schema extension
        $this->graphql->extendSchema([
            'Query' => [
                'businessIntelligence' => [
                    'type' => 'BusinessIntelligence',
                    'args' => ['timeframe' => 'String', 'metrics' => '[String]'],
                    'resolve' => [$this, 'resolveBusinessIntelligence']
                ],
                'kpiDashboard' => [
                    'type' => 'KpiDashboard',
                    'args' => ['period' => 'String'],
                    'resolve' => [$this, 'resolveKpiDashboard']
                ],
                'predictiveAnalytics' => [
                    'type' => 'PredictiveAnalytics',
                    'args' => ['metric' => 'String!', 'horizon' => 'String'],
                    'resolve' => [$this, 'resolvePredictiveAnalytics']
                ]
            ]
        ]);
    }

    protected function registerCronJobs(): void
    {
        // Update real-time metrics every minute
        $this->cron->schedule('* * * * *', [$this, 'updateRealtimeMetrics']);
        
        // Calculate KPIs every 5 minutes
        $this->cron->schedule('*/5 * * * *', [$this, 'calculateKpis']);
        
        // Check alert conditions hourly
        $this->cron->schedule('0 * * * *', [$this, 'checkAlertConditions']);
        
        // Generate daily executive report
        $this->cron->schedule('0 6 * * *', [$this, 'generateDailyReport']);
        
        // Update predictive models daily
        $this->cron->schedule('0 2 * * *', [$this, 'updatePredictiveModels']);
    }

    public function getDashboardWidget(): array
    {
        return [
            'id' => 'realtime-bi-widget',
            'title' => 'Business Intelligence',
            'position' => 'main',
            'priority' => 1,
            'render' => [$this, 'renderBiDashboard']
        ];
    }

    protected function registerPermissions(): void
    {
        $this->permissions->register([
            'bi.dashboard.view' => 'View BI dashboard',
            'bi.kpis.manage' => 'Manage KPIs',
            'bi.alerts.configure' => 'Configure alerts',
            'bi.reports.generate' => 'Generate reports',
            'bi.reports.executive' => 'View executive reports',
            'bi.analytics.advanced' => 'Access advanced analytics'
        ]);
    }

    // Hook Implementations

    public function updateSalesMetrics(array $data): void
    {
        $order = $data['order'];
        $metricsService = $this->container->get(MetricsServiceInterface::class);
        
        // Update real-time sales metrics
        $metricsService->incrementMetric('orders_total', 1);
        $metricsService->incrementMetric('revenue_total', $order->total);
        $metricsService->updateMetric('avg_order_value', $this->calculateAverageOrderValue());
        
        // Update hourly buckets
        $currentHour = now()->format('Y-m-d H:00:00');
        $metricsService->incrementMetric("sales_hourly.{$currentHour}", $order->total);
        
        // Update product metrics
        foreach ($order->items as $item) {
            $metricsService->incrementMetric("product_sales.{$item->product_id}", $item->quantity);
        }
        
        // Trigger KPI recalculation
        HookSystem::doAction('metrics.updated', ['metric_type' => 'sales', 'order' => $order]);
    }

    public function updateCustomerMetrics(array $data): void
    {
        $user = $data['user'];
        $metricsService = $this->container->get(MetricsServiceInterface::class);
        
        // Update customer metrics
        $metricsService->incrementMetric('customers_total', 1);
        $metricsService->incrementMetric('customers_new_today', 1);
        
        // Update acquisition metrics by source
        $source = $data['acquisition_source'] ?? 'direct';
        $metricsService->incrementMetric("acquisition.{$source}", 1);
        
        // Update geographic metrics
        if (isset($data['country'])) {
            $metricsService->incrementMetric("customers_by_country.{$data['country']}", 1);
        }
        
        // Calculate customer acquisition cost
        $this->updateCustomerAcquisitionCost($source);
    }

    public function recalculateKpis(array $data): void
    {
        $kpiService = $this->container->get(KpiServiceInterface::class);
        $metricType = $data['metric_type'];
        
        // Determine which KPIs need recalculation
        $affectedKpis = $this->getAffectedKpis($metricType);
        
        foreach ($affectedKpis as $kpiName) {
            $newValue = $kpiService->calculateKpi($kpiName);
            $previousValue = $kpiService->getKpiValue($kpiName);
            
            // Store new value
            $kpiService->updateKpi($kpiName, $newValue);
            
            // Check thresholds
            $this->checkKpiThresholds($kpiName, $newValue, $previousValue);
        }
    }

    public function provideDashboardData(array $dashboardData, array $data): array
    {
        $timeframe = $data['timeframe'] ?? '24h';
        $metricsService = $this->container->get(MetricsServiceInterface::class);
        $kpiService = $this->container->get(KpiServiceInterface::class);
        
        // Core business metrics
        $dashboardData['realtime'] = [
            'sales_today' => $metricsService->getMetric('sales_today'),
            'orders_today' => $metricsService->getMetric('orders_today'),
            'visitors_online' => $metricsService->getMetric('visitors_online'),
            'conversion_rate' => $kpiService->getKpiValue('conversion_rate'),
            'revenue_per_visitor' => $kpiService->getKpiValue('revenue_per_visitor')
        ];
        
        // KPI summary
        $dashboardData['kpis'] = $kpiService->getKpiSummary($timeframe);
        
        // Trending data
        $dashboardData['trends'] = [
            'sales_trend' => $this->getSalesTrend($timeframe),
            'traffic_trend' => $this->getTrafficTrend($timeframe),
            'conversion_trend' => $this->getConversionTrend($timeframe)
        ];
        
        // Alerts summary
        $dashboardData['alerts'] = $this->getActiveAlertsSummary();
        
        // Predictions
        $predictiveService = $this->container->get(PredictiveServiceInterface::class);
        $dashboardData['predictions'] = [
            'revenue_forecast' => $predictiveService->getRevenueForecast('7d'),
            'growth_projection' => $predictiveService->getGrowthProjection('30d')
        ];
        
        return $dashboardData;
    }

    public function processAlert(array $data): void
    {
        $condition = $data['condition'];
        $currentValue = $data['current_value'];
        $threshold = $data['threshold'];
        
        $alertService = $this->container->get(AlertServiceInterface::class);
        
        // Create alert record
        $alert = $alertService->createAlert([
            'type' => $condition['type'],
            'metric' => $condition['metric'],
            'current_value' => $currentValue,
            'threshold_value' => $threshold,
            'severity' => $this->determineSeverity($condition, $currentValue, $threshold),
            'triggered_at' => now()
        ]);
        
        // Send notifications
        $recipients = $this->determineAlertRecipients($condition);
        $this->sendAlertNotifications($alert, $recipients);
        
        // Auto-escalate if critical
        if ($alert->severity === 'critical') {
            $this->scheduleEscalation($alert);
        }
        
        // Log alert
        $this->logger->warning('Business alert triggered', [
            'alert_id' => $alert->id,
            'metric' => $condition['metric'],
            'current_value' => $currentValue,
            'threshold' => $threshold
        ]);
    }

    public function generateForecast(array $forecast, array $data): array
    {
        $metric = $data['metric'];
        $horizon = $data['horizon'] ?? '30d';
        
        $predictiveService = $this->container->get(PredictiveServiceInterface::class);
        
        $forecast[$metric] = $predictiveService->generateForecast($metric, [
            'horizon' => $horizon,
            'confidence_interval' => 0.95,
            'include_seasonality' => true,
            'include_trends' => true
        ]);
        
        return $forecast;
    }

    public function generateExecutiveSummary(array $summary, array $data): array
    {
        $period = $data['period'] ?? 'weekly';
        $metricsService = $this->container->get(MetricsServiceInterface::class);
        $kpiService = $this->container->get(KpiServiceInterface::class);
        
        // Key performance indicators
        $summary['kpi_overview'] = [
            'revenue_growth' => $kpiService->getGrowthRate('revenue', $period),
            'customer_growth' => $kpiService->getGrowthRate('customers', $period),
            'conversion_rate' => $kpiService->getKpiValue('conversion_rate'),
            'customer_satisfaction' => $kpiService->getKpiValue('customer_satisfaction')
        ];
        
        // Business highlights
        $summary['highlights'] = [
            'top_performing_products' => $this->getTopPerformingProducts($period),
            'fastest_growing_segments' => $this->getFastestGrowingSegments($period),
            'key_achievements' => $this->getKeyAchievements($period)
        ];
        
        // Areas of concern
        $summary['concerns'] = [
            'declining_metrics' => $this->getDecliningMetrics($period),
            'missed_targets' => $this->getMissedTargets($period),
            'risk_indicators' => $this->getRiskIndicators()
        ];
        
        // Recommendations
        $summary['recommendations'] = $this->generateActionableRecommendations($summary);
        
        return $summary;
    }

    // Cron Job Implementations

    public function updateRealtimeMetrics(): void
    {
        $metricsService = $this->container->get(MetricsServiceInterface::class);
        
        // Update visitor count
        $onlineVisitors = $this->getOnlineVisitorCount();
        $metricsService->updateMetric('visitors_online', $onlineVisitors);
        
        // Update cart metrics
        $activeCartsCount = $this->getActiveCartsCount();
        $metricsService->updateMetric('active_carts', $activeCartsCount);
        
        // Update inventory alerts
        $lowStockProducts = $this->getLowStockProductCount();
        $metricsService->updateMetric('low_stock_alerts', $lowStockProducts);
        
        // Update system performance
        $responseTime = $this->getAverageResponseTime();
        $metricsService->updateMetric('avg_response_time', $responseTime);
        
        // Cache for real-time dashboard
        $this->cacheRealtimeData();
    }

    public function calculateKpis(): void
    {
        $kpiService = $this->container->get(KpiServiceInterface::class);
        
        // Calculate all configured KPIs
        $kpiDefinitions = $this->getKpiDefinitions();
        
        foreach ($kpiDefinitions as $kpi) {
            try {
                $value = $kpiService->calculateKpi($kpi['name']);
                $kpiService->updateKpi($kpi['name'], $value);
                
                // Check for significant changes
                if ($this->isSignificantChange($kpi['name'], $value)) {
                    $this->notifyKpiChange($kpi['name'], $value);
                }
            } catch (\Exception $e) {
                $this->logger->error('KPI calculation failed', [
                    'kpi' => $kpi['name'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->info('KPI calculation completed', [
            'kpis_calculated' => count($kpiDefinitions)
        ]);
    }

    public function checkAlertConditions(): void
    {
        $alertService = $this->container->get(AlertServiceInterface::class);
        $alertConditions = $alertService->getActiveAlertConditions();
        
        foreach ($alertConditions as $condition) {
            $currentValue = $this->getCurrentMetricValue($condition['metric']);
            
            if ($this->isThresholdExceeded($condition, $currentValue)) {
                HookSystem::doAction('alert.condition_met', [
                    'condition' => $condition,
                    'current_value' => $currentValue,
                    'threshold' => $condition['threshold']
                ]);
            }
        }
        
        $this->logger->info('Alert conditions checked', [
            'conditions_checked' => count($alertConditions)
        ]);
    }

    public function generateDailyReport(): void
    {
        $reportService = $this->container->get(ReportServiceInterface::class);
        
        $report = $reportService->generateDailyExecutiveReport([
            'date' => now()->subDay()->toDateString(),
            'include_predictions' => true,
            'include_recommendations' => true
        ]);
        
        // Distribute to executives
        $this->distributeExecutiveReport($report);
        
        $this->logger->info('Daily executive report generated');
    }

    // Widget and Dashboard

    public function renderBiDashboard(): string
    {
        $metricsService = $this->container->get(MetricsServiceInterface::class);
        $kpiService = $this->container->get(KpiServiceInterface::class);
        
        $data = [
            'revenue_today' => $metricsService->getMetric('revenue_today'),
            'orders_today' => $metricsService->getMetric('orders_today'),
            'conversion_rate' => $kpiService->getKpiValue('conversion_rate'),
            'visitors_online' => $metricsService->getMetric('visitors_online'),
            'active_alerts' => $this->getActiveAlertsCount(),
            'performance_score' => $this->calculateOverallPerformanceScore()
        ];
        
        return view('realtime-business-intelligence::widgets.dashboard', $data);
    }

    // Helper Methods

    private function checkKpiThresholds(string $kpiName, float $newValue, ?float $previousValue): void
    {
        $thresholds = $this->getKpiThresholds($kpiName);
        
        foreach ($thresholds as $threshold) {
            if ($this->isThresholdExceeded(['threshold' => $threshold], $newValue)) {
                HookSystem::doAction('kpi.threshold_exceeded', [
                    'kpi' => $kpiName,
                    'value' => $newValue,
                    'threshold' => $threshold,
                    'previous_value' => $previousValue
                ]);
            }
        }
    }

    private function getAffectedKpis(string $metricType): array
    {
        $mappings = [
            'sales' => ['conversion_rate', 'avg_order_value', 'revenue_per_visitor'],
            'customers' => ['customer_lifetime_value', 'customer_acquisition_cost'],
            'traffic' => ['bounce_rate', 'conversion_rate', 'pages_per_session'],
            'inventory' => ['inventory_turnover', 'stockout_rate']
        ];
        
        return $mappings[$metricType] ?? [];
    }

    private function determineSeverity(array $condition, float $currentValue, float $threshold): string
    {
        $deviation = abs(($currentValue - $threshold) / $threshold);
        
        if ($deviation > 0.5) return 'critical';
        if ($deviation > 0.25) return 'high';
        if ($deviation > 0.1) return 'medium';
        return 'low';
    }

    private function getConfig(string $key = null, $default = null)
    {
        $config = [
            'update_intervals' => [
                'realtime' => 60, // seconds
                'kpis' => 300, // 5 minutes
                'alerts' => 3600 // 1 hour
            ],
            'kpis' => [
                'conversion_rate' => ['weight' => 0.3, 'target' => 2.5],
                'avg_order_value' => ['weight' => 0.25, 'target' => 75],
                'customer_lifetime_value' => ['weight' => 0.2, 'target' => 200],
                'revenue_per_visitor' => ['weight' => 0.25, 'target' => 2.0]
            ],
            'alerts' => [
                'escalation_delay' => 1800, // 30 minutes
                'max_escalations' => 3,
                'notification_channels' => ['email', 'slack', 'sms']
            ],
            'reports' => [
                'executive_recipients' => ['ceo@company.com', 'cfo@company.com'],
                'auto_generate' => true,
                'retention_days' => 90
            ],
            'predictive' => [
                'models' => ['arima', 'prophet', 'linear_regression'],
                'confidence_threshold' => 0.8,
                'forecast_horizon' => 30 // days
            ]
        ];
        
        return $key ? ($config[$key] ?? $default) : $config;
    }
}