<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Plugin\Hook;
use Shopologic\Core\Container\ContainerInterface;
use AdvancedAnalytics\Services\{
    AnalyticsEngine,
    ReportGenerator,
    DashboardManager,
    DataVisualization,
    PredictiveAnalytics,
    CohortAnalyzer,
    FunnelAnalyzer,
    RealtimeProcessor,
    EventTracker,
    MetricsCalculator,;
    AlertManager,;
    ExportService;
};
use AdvancedAnalytics\Repositories\{
    EventRepository,
    SessionRepository,
    ReportRepository,
    DashboardRepository,
    MetricsRepository,
    CohortRepository,;
    SegmentRepository,;
    AlertRepository;
};
use AdvancedAnalytics\Controllers\{
    DashboardController,
    SalesController,
    CustomerController,
    ProductController,
    CohortController,
    FunnelController,
    PredictiveController,
    ReportController,
    ExportController,;
    RealtimeController,;
    SegmentController;
};

class AdvancedAnalyticsPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'advanced-analytics-reporting';
    protected string $version = '1.0.0';
    protected string $description = 'Advanced analytics and reporting platform';
    protected string $author = 'Shopologic Team';
    protected array $dependencies = ['shopologic/commerce', 'shopologic/customers', 'shopologic/data'];

    private AnalyticsEngine $analyticsEngine;
    private ReportGenerator $reportGenerator;
    private DashboardManager $dashboardManager;
    private DataVisualization $dataVisualization;
    private PredictiveAnalytics $predictiveAnalytics;
    private CohortAnalyzer $cohortAnalyzer;
    private FunnelAnalyzer $funnelAnalyzer;
    private RealtimeProcessor $realtimeProcessor;
    private EventTracker $eventTracker;
    private MetricsCalculator $metricsCalculator;
    private AlertManager $alertManager;
    private ExportService $exportService;

    /**
     * Plugin installation
     */
    public function install(): void
    {
        // Run database migrations
        $this->runMigrations();
        
        // Create default dashboards
        $this->createDefaultDashboards();
        
        // Create default reports
        $this->createDefaultReports();
        
        // Setup default KPIs and metrics
        $this->setupDefaultKPIs();
        
        // Set default configuration
        $this->setDefaultConfiguration();
        
        // Create necessary directories
        $this->createDirectories();
        
        // Initialize data warehouse
        $this->initializeDataWarehouse();
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Register services
        $this->registerServices();
        
        // Register hooks and filters
        $this->registerHooks();
        
        // Register API routes
        $this->registerRoutes();
        
        // Schedule background tasks
        $this->scheduleBackgroundTasks();
        
        // Initialize real-time processing
        $this->initializeRealtimeProcessing();
        
        // Setup event tracking
        $this->setupEventTracking();
        
        // Initialize predictive models
        $this->initializePredictiveModels();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Stop real-time processing
        $this->stopRealtimeProcessing();
        
        // Unschedule background tasks
        $this->unscheduleBackgroundTasks();
        
        // Save analytics state
        $this->saveAnalyticsState();
        
        // Clear caches
        $this->clearCaches();
    }

    /**
     * Plugin uninstallation
     */
    public function uninstall(): void
    {
        // Note: Database cleanup is optional and user-configurable
        if ($this->getConfig('cleanup_on_uninstall', false)) {
            $this->cleanupDatabase();
        }
        
        // Remove configuration
        $this->removeConfiguration();
        
        // Clean up files
        $this->cleanupFiles();
        
        // Remove exported reports
        $this->cleanupExports();
    }

    /**
     * Plugin update
     */
    public function update(string $previousVersion): void
    {
        // Run version-specific updates
        if (version_compare($previousVersion, '1.0.0', '<')) {
            $this->updateTo100();
        }
        
        // Update database schema if needed
        $this->runMigrations();
        
        // Update configuration schema
        $this->updateConfiguration();
        
        // Migrate existing analytics data
        $this->migrateAnalyticsData($previousVersion);
        
        // Update predictive models
        $this->updatePredictiveModels();
    }

    /**
     * Plugin boot - called when plugin is loaded
     */
    public function boot(): void
    {
        // Initialize core services
        $this->initializeServices();
        
        // Register event listeners
        $this->registerEventListeners();
        
        // Load plugin configuration
        $this->loadConfiguration();
        
        // Initialize tracking scripts
        $this->initializeTrackingScripts();
        
        // Setup data aggregation
        $this->setupDataAggregation();
    }

    /**
     * Register services with the container
     */
    protected function registerServices(): void
    {
        $container = $this->getContainer();
        
        // Register repositories
        $container->singleton(EventRepository::class);
        $container->singleton(SessionRepository::class);
        $container->singleton(ReportRepository::class);
        $container->singleton(DashboardRepository::class);
        $container->singleton(MetricsRepository::class);
        $container->singleton(CohortRepository::class);
        $container->singleton(SegmentRepository::class);
        $container->singleton(AlertRepository::class);
        
        // Register core services
        $container->singleton(EventTracker::class, function ($container) {
            return new EventTracker(
                $container->get(EventRepository::class),
                $container->get(SessionRepository::class),
                $this->getConfig('tracking_settings', [])
            );
        });
        
        $container->singleton(MetricsCalculator::class, function ($container) {
            return new MetricsCalculator(
                $container->get(EventRepository::class),
                $container->get(MetricsRepository::class)
            );
        });
        
        $container->singleton(AnalyticsEngine::class, function ($container) {
            return new AnalyticsEngine(
                $container->get(EventRepository::class),
                $container->get(MetricsCalculator::class),
                $this->getConfig('data_processing', [])
            );
        });
        
        $container->singleton(ReportGenerator::class, function ($container) {
            return new ReportGenerator(
                $container->get(ReportRepository::class),
                $container->get(AnalyticsEngine::class),
                $this->getConfig('reporting_settings', [])
            );
        });
        
        $container->singleton(DashboardManager::class, function ($container) {
            return new DashboardManager(
                $container->get(DashboardRepository::class),
                $container->get(AnalyticsEngine::class),
                $this->getConfig('dashboard_settings', [])
            );
        });
        
        $container->singleton(DataVisualization::class, function ($container) {
            return new DataVisualization(
                $container->get(AnalyticsEngine::class)
            );
        });
        
        $container->singleton(PredictiveAnalytics::class, function ($container) {
            return new PredictiveAnalytics(
                $container->get(AnalyticsEngine::class),
                $this->getConfig('predictive_analytics', [])
            );
        });
        
        $container->singleton(CohortAnalyzer::class, function ($container) {
            return new CohortAnalyzer(
                $container->get(CohortRepository::class),
                $container->get(AnalyticsEngine::class),
                $this->getConfig('cohort_analysis', [])
            );
        });
        
        $container->singleton(FunnelAnalyzer::class, function ($container) {
            return new FunnelAnalyzer(
                $container->get(EventRepository::class),
                $container->get(AnalyticsEngine::class)
            );
        });
        
        $container->singleton(RealtimeProcessor::class, function ($container) {
            return new RealtimeProcessor(
                $container->get(EventRepository::class),
                $container->get(MetricsCalculator::class),
                $this->getConfig('dashboard_settings.realtime_enabled', true)
            );
        });
        
        $container->singleton(AlertManager::class, function ($container) {
            return new AlertManager(
                $container->get(AlertRepository::class),
                $container->get(MetricsCalculator::class),
                $this->getConfig('alerts_settings', [])
            );
        });
        
        $container->singleton(ExportService::class, function ($container) {
            return new ExportService(
                $container->get(ReportGenerator::class),
                $this->getConfig('reporting_settings.export_formats', ['csv', 'excel', 'pdf'])
            );
        });
        
        // Register controllers
        $container->singleton(DashboardController::class);
        $container->singleton(SalesController::class);
        $container->singleton(CustomerController::class);
        $container->singleton(ProductController::class);
        $container->singleton(CohortController::class);
        $container->singleton(FunnelController::class);
        $container->singleton(PredictiveController::class);
        $container->singleton(ReportController::class);
        $container->singleton(ExportController::class);
        $container->singleton(RealtimeController::class);
        $container->singleton(SegmentController::class);
    }

    /**
     * Initialize services
     */
    protected function initializeServices(): void
    {
        $container = $this->getContainer();
        
        $this->analyticsEngine = $container->get(AnalyticsEngine::class);
        $this->reportGenerator = $container->get(ReportGenerator::class);
        $this->dashboardManager = $container->get(DashboardManager::class);
        $this->dataVisualization = $container->get(DataVisualization::class);
        $this->predictiveAnalytics = $container->get(PredictiveAnalytics::class);
        $this->cohortAnalyzer = $container->get(CohortAnalyzer::class);
        $this->funnelAnalyzer = $container->get(FunnelAnalyzer::class);
        $this->realtimeProcessor = $container->get(RealtimeProcessor::class);
        $this->eventTracker = $container->get(EventTracker::class);
        $this->metricsCalculator = $container->get(MetricsCalculator::class);
        $this->alertManager = $container->get(AlertManager::class);
        $this->exportService = $container->get(ExportService::class);
    }

    /**
     * Register hooks and filters
     */
    protected function registerHooks(): void
    {
        // E-commerce event tracking
        Hook::addAction('order.created', [$this, 'trackOrderCreated'], 10);
        Hook::addAction('order.completed', [$this, 'trackOrderCompletion'], 10);
        Hook::addAction('order.cancelled', [$this, 'trackOrderCancellation'], 10);
        Hook::addAction('order.refunded', [$this, 'trackOrderRefund'], 10);
        
        // Customer event tracking
        Hook::addAction('customer.registered', [$this, 'trackCustomerRegistration'], 10);
        Hook::addAction('customer.login', [$this, 'trackCustomerLogin'], 10);
        Hook::addAction('customer.logout', [$this, 'trackCustomerLogout'], 10);
        Hook::addAction('customer.profile_updated', [$this, 'trackProfileUpdate'], 10);
        
        // Product interaction tracking
        Hook::addAction('product.viewed', [$this, 'trackProductView'], 10);
        Hook::addAction('product.added_to_cart', [$this, 'trackAddToCart'], 10);
        Hook::addAction('product.removed_from_cart', [$this, 'trackRemoveFromCart'], 10);
        Hook::addAction('product.added_to_wishlist', [$this, 'trackAddToWishlist'], 10);
        
        // Cart and checkout tracking
        Hook::addAction('cart.updated', [$this, 'trackCartUpdate'], 10);
        Hook::addAction('checkout.started', [$this, 'trackCheckoutStart'], 10);
        Hook::addAction('checkout.completed', [$this, 'trackCheckoutComplete'], 10);
        Hook::addAction('checkout.abandoned', [$this, 'trackCheckoutAbandonment'], 10);
        
        // Payment tracking
        Hook::addAction('payment.started', [$this, 'trackPaymentStart'], 10);
        Hook::addAction('payment.processed', [$this, 'trackPaymentEvent'], 10);
        Hook::addAction('payment.failed', [$this, 'trackPaymentFailure'], 10);
        
        // Search and navigation tracking
        Hook::addAction('search.performed', [$this, 'trackSearchEvent'], 10);
        Hook::addAction('category.viewed', [$this, 'trackCategoryView'], 10);
        Hook::addAction('page.viewed', [$this, 'trackPageView'], 10);
        
        // Dashboard and widget filters
        Hook::addFilter('dashboard.widgets', [$this, 'addAnalyticsWidgets'], 10);
        Hook::addFilter('admin.dashboard_data', [$this, 'addDashboardData'], 10);
        
        // Report filters
        Hook::addFilter('report.data', [$this, 'enrichReportData'], 15);
        Hook::addFilter('export.format', [$this, 'addExportFormats'], 10);
        
        // Admin hooks
        Hook::addAction('admin_menu', [$this, 'registerAdminMenu']);
        Hook::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        Hook::addAction('admin_footer', [$this, 'addTrackingScripts']);
        
        // Frontend hooks
        Hook::addAction('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        Hook::addAction('wp_footer', [$this, 'addFrontendTrackingScripts']);
        Hook::addAction('wp_head', [$this, 'addTrackingHeaders']);
        
        // AJAX hooks
        Hook::addAction('wp_ajax_generate_report', [$this, 'handleReportGeneration']);
        Hook::addAction('wp_ajax_export_data', [$this, 'handleDataExport']);
        Hook::addAction('wp_ajax_get_realtime_metrics', [$this, 'handleRealtimeMetrics']);
        Hook::addAction('wp_ajax_save_dashboard_layout', [$this, 'handleDashboardSave']);
    }

    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        // Dashboard routes
        $this->registerRoute('GET', '/api/v1/analytics/dashboard', 'DashboardController@overview');
        $this->registerRoute('GET', '/api/v1/dashboards', 'DashboardController@index');
        $this->registerRoute('POST', '/api/v1/dashboards', 'DashboardController@create');
        $this->registerRoute('PUT', '/api/v1/dashboards/{id}', 'DashboardController@update');
        $this->registerRoute('DELETE', '/api/v1/dashboards/{id}', 'DashboardController@delete');
        
        // Analytics routes
        $this->registerRoute('GET', '/api/v1/analytics/sales', 'SalesController@analytics');
        $this->registerRoute('GET', '/api/v1/analytics/customers', 'CustomerController@analytics');
        $this->registerRoute('GET', '/api/v1/analytics/products', 'ProductController@analytics');
        $this->registerRoute('GET', '/api/v1/analytics/realtime', 'RealtimeController@metrics');
        
        // Advanced analytics
        $this->registerRoute('GET', '/api/v1/analytics/cohort', 'CohortController@analysis');
        $this->registerRoute('GET', '/api/v1/analytics/funnel', 'FunnelController@analysis');
        $this->registerRoute('GET', '/api/v1/analytics/predictive', 'PredictiveController@forecast');
        $this->registerRoute('GET', '/api/v1/analytics/segments', 'SegmentController@analysis');
        
        // Reporting routes
        $this->registerRoute('GET', '/api/v1/reports', 'ReportController@index');
        $this->registerRoute('POST', '/api/v1/reports', 'ReportController@create');
        $this->registerRoute('GET', '/api/v1/reports/{id}', 'ReportController@show');
        $this->registerRoute('PUT', '/api/v1/reports/{id}', 'ReportController@update');
        $this->registerRoute('DELETE', '/api/v1/reports/{id}', 'ReportController@delete');
        $this->registerRoute('POST', '/api/v1/reports/{id}/generate', 'ReportController@generate');
        $this->registerRoute('POST', '/api/v1/reports/{id}/schedule', 'ReportController@schedule');
        
        // Export routes
        $this->registerRoute('POST', '/api/v1/reports/{id}/export', 'ExportController@export');
        $this->registerRoute('GET', '/api/v1/exports/{id}/download', 'ExportController@download');
        $this->registerRoute('GET', '/api/v1/exports', 'ExportController@index');
    }

    /**
     * Track order completion
     */
    public function trackOrderCompletion($order): void
    {
        $this->eventTracker->track('order_completed', [
            'order_id' => $order->getId(),
            'customer_id' => $order->getCustomerId(),
            'total_amount' => $order->getTotal(),
            'items_count' => $order->getItemsCount(),
            'payment_method' => $order->getPaymentMethod(),
            'shipping_method' => $order->getShippingMethod(),
            'currency' => $order->getCurrency(),
            'tax_amount' => $order->getTaxAmount(),
            'shipping_amount' => $order->getShippingAmount(),
            'discount_amount' => $order->getDiscountAmount(),
            'order_source' => $order->getSource(),
            'created_at' => $order->getCreatedAt()
        ]);
        
        // Track individual items
        foreach ($order->getItems() as $item) {
            $this->eventTracker->track('order_item_purchased', [
                'order_id' => $order->getId(),
                'product_id' => $item->getProductId(),
                'quantity' => $item->getQuantity(),
                'unit_price' => $item->getUnitPrice(),
                'total_price' => $item->getTotalPrice(),
                'product_category' => $item->getProductCategory(),
                'product_sku' => $item->getProductSku()
            ]);
        }
    }

    /**
     * Track customer registration
     */
    public function trackCustomerRegistration($customer): void
    {
        $this->eventTracker->track('customer_registered', [
            'customer_id' => $customer->getId(),
            'email' => $customer->getEmail(),
            'registration_source' => $customer->getRegistrationSource(),
            'referral_code' => $customer->getReferralCode(),
            'utm_source' => $customer->getUtmSource(),
            'utm_medium' => $customer->getUtmMedium(),
            'utm_campaign' => $customer->getUtmCampaign(),
            'country' => $customer->getCountry(),
            'state' => $customer->getState(),
            'city' => $customer->getCity(),
            'created_at' => $customer->getCreatedAt()
        ]);
    }

    /**
     * Track product view
     */
    public function trackProductView($product, $context = []): void
    {
        $this->eventTracker->track('product_viewed', [
            'product_id' => $product->getId(),
            'product_name' => $product->getName(),
            'product_sku' => $product->getSku(),
            'product_category' => $product->getCategoryName(),
            'product_price' => $product->getPrice(),
            'product_brand' => $product->getBrand(),
            'product_tags' => $product->getTags(),
            'view_source' => $context['source'] ?? 'direct',
            'referrer_url' => $context['referrer'] ?? null,
            'search_query' => $context['search_query'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_address' => $this->getClientIpAddress()
        ]);
    }

    /**
     * Track cart update
     */
    public function trackCartUpdate($cart): void
    {
        $this->eventTracker->track('cart_updated', [
            'customer_id' => $cart->getCustomerId(),
            'session_id' => $cart->getSessionId(),
            'items_count' => $cart->getItemsCount(),
            'total_amount' => $cart->getTotal(),
            'cart_items' => array_map(function ($item) {
                return [
                    'product_id' => $item->getProductId(),
                    'quantity' => $item->getQuantity(),
                    'unit_price' => $item->getUnitPrice(),
                    'total_price' => $item->getTotalPrice()
                ];
            }, $cart->getItems())
        ]);
    }

    /**
     * Track payment event
     */
    public function trackPaymentEvent($payment): void
    {
        $this->eventTracker->track('payment_processed', [
            'payment_id' => $payment->getId(),
            'order_id' => $payment->getOrderId(),
            'customer_id' => $payment->getCustomerId(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'payment_method' => $payment->getMethod(),
            'payment_status' => $payment->getStatus(),
            'gateway' => $payment->getGateway(),
            'transaction_id' => $payment->getTransactionId(),
            'processing_time' => $payment->getProcessingTime(),
            'fees' => $payment->getFees()
        ]);
    }

    /**
     * Add analytics widgets to dashboard
     */
    public function addAnalyticsWidgets($widgets): array
    {
        $analyticsWidgets = [
            'sales_overview' => [
                'title' => 'Sales Overview',
                'callback' => [$this, 'renderSalesOverviewWidget'],
                'permissions' => ['analytics.view']
            ],
            'customer_analytics' => [
                'title' => 'Customer Analytics',
                'callback' => [$this, 'renderCustomerAnalyticsWidget'],
                'permissions' => ['analytics.view']
            ],
            'product_performance' => [
                'title' => 'Product Performance',
                'callback' => [$this, 'renderProductPerformanceWidget'],
                'permissions' => ['analytics.view']
            ],
            'conversion_funnel' => [
                'title' => 'Conversion Funnel',
                'callback' => [$this, 'renderConversionFunnelWidget'],
                'permissions' => ['analytics.view']
            ],
            'realtime_metrics' => [
                'title' => 'Real-time Metrics',
                'callback' => [$this, 'renderRealtimeMetricsWidget'],
                'permissions' => ['analytics.view']
            ]
        ];
        
        return array_merge($widgets, $analyticsWidgets);
    }

    /**
     * Scheduled task: Process real-time metrics
     */
    public function processRealtimeMetrics(): void
    {
        if (!$this->getConfig('dashboard_settings.realtime_enabled', true)) {
            return;
        }
        
        $this->realtimeProcessor->processRealtimeData();
    }

    /**
     * Scheduled task: Aggregate session data
     */
    public function aggregateSessionData(): void
    {
        $this->analyticsEngine->aggregateSessionData();
    }

    /**
     * Scheduled task: Generate hourly reports
     */
    public function generateHourlyReports(): void
    {
        $this->reportGenerator->generateScheduledReports('hourly');
    }

    /**
     * Scheduled task: Process daily analytics
     */
    public function processDailyAnalytics(): void
    {
        // Process cohort data
        $this->cohortAnalyzer->processDailyCohorts();
        
        // Calculate daily metrics
        $this->metricsCalculator->calculateDailyMetrics();
        
        // Generate daily reports
        $this->reportGenerator->generateScheduledReports('daily');
        
        // Update customer segments
        $this->analyticsEngine->updateCustomerSegments();
    }

    /**
     * Scheduled task: Run predictive models
     */
    public function runPredictiveModels(): void
    {
        if (!$this->getConfig('predictive_analytics.enabled', true)) {
            return;
        }
        
        // Run sales forecasting
        $this->predictiveAnalytics->generateSalesForecast();
        
        // Predict customer churn
        $this->predictiveAnalytics->predictCustomerChurn();
        
        // Forecast inventory demand
        $this->predictiveAnalytics->forecastDemand();
        
        // Calculate customer lifetime value
        $this->predictiveAnalytics->calculateCustomerLifetimeValue();
    }

    /**
     * Scheduled task: Clean up old data
     */
    public function cleanupOldData(): void
    {
        $retentionDays = $this->getConfig('tracking_settings.data_retention_days', 365);
        
        // Clean up old events
        $this->analyticsEngine->cleanupOldEvents($retentionDays);
        
        // Clean up old sessions
        $this->analyticsEngine->cleanupOldSessions($retentionDays);
        
        // Clean up old exports
        $this->exportService->cleanupOldExports($retentionDays);
        
        // Optimize database tables
        $this->analyticsEngine->optimizeTables();
    }

    /**
     * Scheduled task: Check analytics alerts
     */
    public function checkAnalyticsAlerts(): void
    {
        if (!$this->getConfig('alerts_settings.enabled', true)) {
            return;
        }
        
        $this->alertManager->checkAllAlerts();
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Analytics',
            'Analytics',
            'analytics.view',
            'analytics',
            [$this, 'renderAnalyticsDashboard'],
            'dashicons-chart-bar',
            27
        );
        
        add_submenu_page(
            'analytics',
            'Dashboard',
            'Dashboard',
            'analytics.view',
            'analytics-dashboard',
            [$this, 'renderAnalyticsDashboard']
        );
        
        add_submenu_page(
            'analytics',
            'Sales Analytics',
            'Sales',
            'analytics.view',
            'sales-analytics',
            [$this, 'renderSalesAnalytics']
        );
        
        add_submenu_page(
            'analytics',
            'Customer Analytics',
            'Customers',
            'analytics.view',
            'customer-analytics',
            [$this, 'renderCustomerAnalytics']
        );
        
        add_submenu_page(
            'analytics',
            'Product Analytics',
            'Products',
            'analytics.view',
            'product-analytics',
            [$this, 'renderProductAnalytics']
        );
        
        add_submenu_page(
            'analytics',
            'Reports',
            'Reports',
            'reports.view',
            'analytics-reports',
            [$this, 'renderReports']
        );
        
        add_submenu_page(
            'analytics',
            'Cohort Analysis',
            'Cohort Analysis',
            'analytics.view',
            'cohort-analysis',
            [$this, 'renderCohortAnalysis']
        );
        
        add_submenu_page(
            'analytics',
            'Funnel Analysis',
            'Funnels',
            'analytics.view',
            'funnel-analysis',
            [$this, 'renderFunnelAnalysis']
        );
        
        add_submenu_page(
            'analytics',
            'Predictive Analytics',
            'Predictive',
            'predictive.view',
            'predictive-analytics',
            [$this, 'renderPredictiveAnalytics']
        );
        
        add_submenu_page(
            'analytics',
            'Real-time',
            'Real-time',
            'analytics.view',
            'realtime-analytics',
            [$this, 'renderRealtimeAnalytics']
        );
        
        add_submenu_page(
            'analytics',
            'Settings',
            'Settings',
            'analytics.manage',
            'analytics-settings',
            [$this, 'renderSettings']
        );
    }

    /**
     * Get client IP address
     */
    private function getClientIpAddress(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Set default configuration
     */
    private function setDefaultConfiguration(): void
    {
        $defaults = [
            'tracking_settings' => [
                'track_page_views' => true,
                'track_user_interactions' => true,
                'track_ecommerce_events' => true,
                'session_timeout' => 30,
                'data_retention_days' => 365
            ],
            'dashboard_settings' => [
                'default_date_range' => 'last_30_days',
                'auto_refresh_interval' => 300,
                'realtime_enabled' => true,
                'max_widgets_per_dashboard' => 20
            ],
            'reporting_settings' => [
                'scheduled_reports_enabled' => true,
                'max_report_rows' => 10000,
                'export_formats' => ['csv', 'excel', 'pdf'],
                'report_cache_duration' => 60
            ],
            'predictive_analytics' => [
                'enabled' => true,
                'forecasting_models' => ['linear_regression', 'seasonal_decomposition'],
                'prediction_horizon_days' => 90,
                'confidence_interval' => 95
            ],
            'cohort_analysis' => [
                'enabled' => true,
                'cohort_types' => ['registration', 'first_purchase'],
                'retention_periods' => ['daily', 'weekly', 'monthly']
            ],
            'alerts_settings' => [
                'enabled' => true,
                'notification_channels' => ['email', 'dashboard'],
                'check_frequency' => 'hourly'
            ]
        ];
        
        foreach ($defaults as $key => $value) {
            if (!$this->hasConfig($key)) {
                $this->setConfig($key, $value);
            }
        }
    }

    /**
     * Create necessary directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/exports',
            $this->getPluginPath() . '/reports',
            $this->getPluginPath() . '/cache',
            $this->getPluginPath() . '/models',
            $this->getPluginPath() . '/logs'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Permissions
     */
    protected function registerPermissions(): void
    {
        // TODO: Implement registerPermissions
    }

    /**
     * Register ScheduledJobs
     */
    protected function registerScheduledJobs(): void
    {
        // TODO: Implement registerScheduledJobs
    }
}