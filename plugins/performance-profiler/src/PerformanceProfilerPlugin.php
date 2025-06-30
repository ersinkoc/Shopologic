<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PerformanceProfiler;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use PerformanceProfiler\Services\{
    ProfilerManager,
    MetricsCollector,
    BottleneckDetector,
    OptimizationEngine,
    BenchmarkRunner,
    AlertManager,
    ReportGenerator,;
    TimelineRecorder;
};
use PerformanceProfiler\Profilers\{
    ExecutionProfiler,
    MemoryProfiler,
    DatabaseProfiler,
    CacheProfiler,;
    NetworkProfiler;
};

class PerformanceProfilerPlugin extends AbstractPlugin
{
    private ProfilerManager $profilerManager;
    private MetricsCollector $metricsCollector;
    private BottleneckDetector $bottleneckDetector;
    private OptimizationEngine $optimizationEngine;
    private BenchmarkRunner $benchmarkRunner;
    private AlertManager $alertManager;
    private ReportGenerator $reportGenerator;
    private TimelineRecorder $timelineRecorder;
    
    private array $profilers = [];
    private ?string $currentProfileId = null;
    private float $requestStartTime;
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Initialize performance baseline
        $this->initializeBaseline();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Create required directories
        $this->createDirectories();
        
        // Schedule initial benchmark
        $this->scheduleInitialBenchmark();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Stop any active profiling
        $this->stopActiveProfiling();
        
        // Save current metrics
        $this->saveCurrentMetrics();
        
        // Clear profiling cache
        $this->clearProfilingCache();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize services very early
        HookSystem::addAction('plugins_loaded', [$this, 'initializeServices'], 1);
        
        // Start profiling
        HookSystem::addAction('init', [$this, 'startProfiling'], 1);
        HookSystem::addAction('shutdown', [$this, 'stopProfiling'], 999);
        
        // Hook into various WordPress events for profiling
        HookSystem::addAction('parse_request', [$this, 'profileRequestParsing']);
        HookSystem::addAction('wp', [$this, 'profileWordPressInit']);
        HookSystem::addAction('template_redirect', [$this, 'profileTemplateRedirect']);
        HookSystem::addAction('wp_head', [$this, 'profileHeadGeneration']);
        HookSystem::addAction('wp_footer', [$this, 'profileFooterGeneration']);
        
        // Database query profiling
        HookSystem::addFilter('query', [$this, 'profileDatabaseQuery'], 1);
        HookSystem::addAction('query_end', [$this, 'endDatabaseQuery']);
        
        // Cache profiling
        HookSystem::addAction('cache_get', [$this, 'profileCacheGet'], 10, 2);
        HookSystem::addAction('cache_set', [$this, 'profileCacheSet'], 10, 3);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        HookSystem::addAction('admin_bar_menu', [$this, 'addAdminBarMenu'], 100);
        
        // Frontend profiling toolbar
        if ($this->shouldShowProfilerToolbar()) {
            HookSystem::addAction('wp_footer', [$this, 'renderProfilerToolbar'], 999);
        }
        
        // API endpoints
        $this->registerApiEndpoints();
        
        // Real-time monitoring
        HookSystem::addAction('wp_ajax_get_performance_metrics', [$this, 'handleAjaxMetrics']);
        HookSystem::addAction('wp_ajax_nopriv_get_performance_metrics', [$this, 'handleAjaxMetrics']);
        
        // Scheduled tasks
        HookSystem::addAction('performance_collect_metrics', [$this, 'collectPerformanceMetrics']);
        HookSystem::addAction('performance_analyze_trends', [$this, 'analyzePerformanceTrends']);
        HookSystem::addAction('performance_run_benchmarks', [$this, 'runScheduledBenchmarks']);
        
        // Auto-optimization hooks
        if ($this->getOption('enable_auto_optimization', false)) {
            HookSystem::addAction('performance_bottleneck_detected', [$this, 'applyAutoOptimization']);
        }
    }
    
    /**
     * Initialize services
     */
    public function initializeServices(): void
    {
        // Record request start time
        $this->requestStartTime = microtime(true);
        
        // Initialize core services
        $this->profilerManager = new ProfilerManager($this->container);
        $this->metricsCollector = new MetricsCollector($this->container);
        $this->bottleneckDetector = new BottleneckDetector($this->container);
        $this->optimizationEngine = new OptimizationEngine($this->container);
        $this->benchmarkRunner = new BenchmarkRunner($this->container);
        $this->alertManager = new AlertManager($this->container);
        $this->reportGenerator = new ReportGenerator($this->container);
        $this->timelineRecorder = new TimelineRecorder($this->container);
        
        // Initialize profilers
        $this->initializeProfilers();
    }
    
    /**
     * Initialize profilers
     */
    private function initializeProfilers(): void
    {
        $enabledMetrics = $this->getOption('metrics_to_collect', []);
        
        // Execution profiler
        if (in_array('execution_time', $enabledMetrics)) {
            $this->profilers['execution'] = new ExecutionProfiler($this->container);
        }
        
        // Memory profiler
        if (in_array('memory_usage', $enabledMetrics) || in_array('peak_memory', $enabledMetrics)) {
            $this->profilers['memory'] = new MemoryProfiler($this->container);
        }
        
        // Database profiler
        if (in_array('database_queries', $enabledMetrics)) {
            $this->profilers['database'] = new DatabaseProfiler($this->container);
        }
        
        // Cache profiler
        if (in_array('cache_hits', $enabledMetrics)) {
            $this->profilers['cache'] = new CacheProfiler($this->container);
        }
        
        // Network profiler
        if (in_array('network_calls', $enabledMetrics)) {
            $this->profilers['network'] = new NetworkProfiler($this->container);
        }
        
        // Register profilers
        foreach ($this->profilers as $name => $profiler) {
            $this->profilerManager->registerProfiler($name, $profiler);
        }
    }
    
    /**
     * Start profiling
     */
    public function startProfiling(): void
    {
        if (!$this->shouldProfile()) {
            return;
        }
        
        // Create profile session
        $this->currentProfileId = $this->profilerManager->startProfile([
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'user_id' => get_current_user_id(),
            'timestamp' => microtime(true)
        ]);
        
        // Start timeline recording
        $this->timelineRecorder->start($this->currentProfileId);
        
        // Mark profiling start
        $this->timelineRecorder->mark('request_start', [
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ]);
        
        // Start individual profilers
        foreach ($this->profilers as $profiler) {
            $profiler->start();
        }
    }
    
    /**
     * Stop profiling
     */
    public function stopProfiling(): void
    {
        if (!$this->currentProfileId) {
            return;
        }
        
        // Mark request end
        $this->timelineRecorder->mark('request_end', [
            'total_time' => microtime(true) - $this->requestStartTime,
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ]);
        
        // Stop individual profilers
        $profileData = [];
        foreach ($this->profilers as $name => $profiler) {
            $profileData[$name] = $profiler->stop();
        }
        
        // Collect final metrics
        $metrics = $this->metricsCollector->collectFinalMetrics();
        
        // Detect bottlenecks
        $bottlenecks = $this->bottleneckDetector->analyze($profileData, $metrics);
        
        // Generate recommendations
        $recommendations = $this->optimizationEngine->generateRecommendations($bottlenecks);
        
        // Stop timeline recording
        $timeline = $this->timelineRecorder->stop();
        
        // Save profile data
        $this->profilerManager->saveProfile($this->currentProfileId, [
            'data' => $profileData,
            'metrics' => $metrics,
            'bottlenecks' => $bottlenecks,
            'recommendations' => $recommendations,
            'timeline' => $timeline
        ]);
        
        // Check thresholds and send alerts
        $this->checkPerformanceThresholds($metrics);
        
        // Clear current profile
        $this->currentProfileId = null;
    }
    
    /**
     * Profile database query
     */
    public function profileDatabaseQuery(string $query): string
    {
        if (!isset($this->profilers['database'])) {
            return $query;
        }
        
        $this->profilers['database']->startQuery($query);
        
        return $query;
    }
    
    /**
     * End database query profiling
     */
    public function endDatabaseQuery($result): void
    {
        if (!isset($this->profilers['database'])) {
            return;
        }
        
        $this->profilers['database']->endQuery($result);
    }
    
    /**
     * Check if profiling should be enabled
     */
    private function shouldProfile(): bool
    {
        if (!$this->getOption('enable_profiling', true)) {
            return false;
        }
        
        $mode = $this->getOption('profiling_mode', 'sampling');
        
        switch ($mode) {
            case 'sampling':
                $rate = $this->getOption('sampling_rate', 10);
                return mt_rand(1, 100) <= $rate;
                
            case 'continuous':
                return true;
                
            case 'triggered':
                return $this->isProfilingTriggered();
                
            default:
                return false;
        }
    }
    
    /**
     * Check performance thresholds
     */
    private function checkPerformanceThresholds(array $metrics): void
    {
        if (!$this->getOption('enable_alerts', true)) {
            return;
        }
        
        $thresholds = $this->getOption('performance_thresholds', []);
        $alerts = [];
        
        // Check page load time
        if (isset($thresholds['page_load_time'])) {
            $loadTime = ($metrics['total_time'] ?? 0) * 1000; // Convert to ms
            if ($loadTime > $thresholds['page_load_time']) {
                $alerts[] = [
                    'type' => 'slow_page_load',
                    'severity' => 'warning',
                    'message' => sprintf('Page load time %.2fms exceeds threshold of %dms', $loadTime, $thresholds['page_load_time']),
                    'value' => $loadTime
                ];
            }
        }
        
        // Check memory usage
        if (isset($thresholds['memory_limit_percent'])) {
            $memoryLimit = ini_get('memory_limit');
            $memoryLimitBytes = $this->convertToBytes($memoryLimit);
            $memoryUsage = $metrics['peak_memory'] ?? memory_get_peak_usage(true);
            $usagePercent = ($memoryUsage / $memoryLimitBytes) * 100;
            
            if ($usagePercent > $thresholds['memory_limit_percent']) {
                $alerts[] = [
                    'type' => 'high_memory_usage',
                    'severity' => 'critical',
                    'message' => sprintf('Memory usage %.1f%% exceeds threshold of %.1f%%', $usagePercent, $thresholds['memory_limit_percent']),
                    'value' => $usagePercent
                ];
            }
        }
        
        // Send alerts
        foreach ($alerts as $alert) {
            $this->alertManager->sendAlert($alert);
        }
    }
    
    /**
     * Render profiler toolbar
     */
    public function renderProfilerToolbar(): void
    {
        if (!$this->currentProfileId) {
            return;
        }
        
        $metrics = $this->metricsCollector->getCurrentMetrics();
        
        include $this->getPluginPath() . '/templates/toolbar.php';
    }
    
    /**
     * Add admin bar menu
     */
    public function addAdminBarMenu(\WP_Admin_Bar $adminBar): void
    {
        if (!current_user_can('performance.access')) {
            return;
        }
        
        $metrics = $this->metricsCollector->getCurrentMetrics();
        
        // Add main node
        $adminBar->add_node([
            'id' => 'performance-profiler',
            'title' => sprintf(
                '<span class="ab-icon dashicons dashicons-performance"></span>%.2fs | %.2fMB',
                $metrics['execution_time'] ?? 0,
                ($metrics['memory_usage'] ?? 0) / 1024 / 1024
            ),
            'href' => admin_url('admin.php?page=performance-profiler')
        ]);
        
        // Add sub-items
        $adminBar->add_node([
            'id' => 'performance-details',
            'parent' => 'performance-profiler',
            'title' => 'View Details',
            'href' => admin_url('admin.php?page=performance-profiler&profile=' . $this->currentProfileId)
        ]);
        
        $adminBar->add_node([
            'id' => 'performance-start-profile',
            'parent' => 'performance-profiler',
            'title' => 'Start Profiling',
            'href' => add_query_arg('start_profiling', '1', $_SERVER['REQUEST_URI'])
        ]);
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Performance Profiler',
            'Performance',
            'performance.access',
            'performance-profiler',
            [$this, 'renderDashboard'],
            'dashicons-performance',
            65
        );
        
        add_submenu_page(
            'performance-profiler',
            'Profiles',
            'Profiles',
            'performance.view_reports',
            'performance-profiles',
            [$this, 'renderProfiles']
        );
        
        add_submenu_page(
            'performance-profiler',
            'Real-time Monitor',
            'Real-time',
            'performance.access',
            'performance-monitor',
            [$this, 'renderMonitor']
        );
        
        add_submenu_page(
            'performance-profiler',
            'Benchmarks',
            'Benchmarks',
            'performance.run_benchmarks',
            'performance-benchmarks',
            [$this, 'renderBenchmarks']
        );
        
        add_submenu_page(
            'performance-profiler',
            'Optimization',
            'Optimization',
            'performance.profile',
            'performance-optimization',
            [$this, 'renderOptimization']
        );
        
        add_submenu_page(
            'performance-profiler',
            'Settings',
            'Settings',
            'performance.manage_settings',
            'performance-settings',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/profiles',
            $this->getPluginPath() . '/reports',
            $this->getPluginPath() . '/benchmarks',
            $this->getPluginPath() . '/cache'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }

    /**
     * Register Services
     */
    protected function registerServices(): void
    {
        // TODO: Implement registerServices
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Routes
     */
    protected function registerRoutes(): void
    {
        // TODO: Implement registerRoutes
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