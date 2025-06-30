<?php

declare(strict_types=1);

namespace Shopologic\Plugins\DatabaseQueryOptimizer;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Database\DB;
use DatabaseQueryOptimizer\Services\{
    QueryMonitor,
    QueryAnalyzer,
    IndexAdvisor,
    QueryRewriter,
    ExecutionPlanAnalyzer,
    StatisticsCollector,
    MaintenanceScheduler,
    PerformanceProfiler,;
    AlertManager;
};
use DatabaseQueryOptimizer\Analyzers\{
    SlowQueryAnalyzer,
    QueryPatternMatcher,;
    TableStatisticsAnalyzer;
};

class DatabaseQueryOptimizerPlugin extends AbstractPlugin
{
    private QueryMonitor $queryMonitor;
    private QueryAnalyzer $queryAnalyzer;
    private IndexAdvisor $indexAdvisor;
    private QueryRewriter $queryRewriter;
    private ExecutionPlanAnalyzer $planAnalyzer;
    private StatisticsCollector $statsCollector;
    private MaintenanceScheduler $maintenanceScheduler;
    private PerformanceProfiler $profiler;
    private AlertManager $alertManager;
    
    private array $capturedQueries = [];
    private float $queryStartTime = 0;
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Initialize database extensions if needed
        $this->initializeDatabaseExtensions();
        
        // Create initial indexes for optimization tables
        $this->createOptimizationIndexes();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Schedule initial analysis
        $this->scheduleInitialAnalysis();
        
        // Create reports directory
        $this->createDirectories();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Stop real-time monitoring
        $this->stopMonitoring();
        
        // Clear query cache
        $this->clearQueryCache();
        
        // Save current statistics
        $this->saveStatistics();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize services
        HookSystem::addAction('init', [$this, 'initializeServices'], 1);
        
        // Query monitoring hooks
        HookSystem::addFilter('database.query.before', [$this, 'beforeQuery'], 1);
        HookSystem::addAction('database.query.after', [$this, 'afterQuery'], 999);
        
        // Query optimization hooks
        HookSystem::addFilter('database.query', [$this, 'optimizeQuery'], 10);
        HookSystem::addFilter('database.query.prepare', [$this, 'rewriteQuery'], 5);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // API endpoints
        $this->registerApiEndpoints();
        
        // Real-time monitoring
        HookSystem::addAction('wp_ajax_db_optimizer_monitor', [$this, 'handleMonitorRequest']);
        
        // Scheduled tasks
        HookSystem::addAction('db_optimizer_collect_stats', [$this, 'collectQueryStatistics']);
        HookSystem::addAction('db_optimizer_analyze_slow', [$this, 'analyzeSlowQueries']);
        HookSystem::addAction('db_optimizer_suggest_indexes', [$this, 'generateIndexSuggestions']);
        HookSystem::addAction('db_optimizer_maintenance', [$this, 'performMaintenance']);
        
        // Alert hooks
        HookSystem::addAction('db_optimizer_alert', [$this, 'handleAlert'], 10, 2);
    }
    
    /**
     * Initialize services
     */
    public function initializeServices(): void
    {
        // Initialize core services
        $this->queryMonitor = new QueryMonitor($this->container);
        $this->queryAnalyzer = new QueryAnalyzer($this->container);
        $this->indexAdvisor = new IndexAdvisor($this->container);
        $this->queryRewriter = new QueryRewriter($this->getOption('rewrite_rules', []));
        $this->planAnalyzer = new ExecutionPlanAnalyzer($this->container);
        $this->statsCollector = new StatisticsCollector($this->container);
        $this->maintenanceScheduler = new MaintenanceScheduler($this->container);
        $this->profiler = new PerformanceProfiler($this->container);
        $this->alertManager = new AlertManager($this->container, $this->getOption('alert_thresholds', []));
        
        // Start monitoring if enabled
        if ($this->getOption('enable_real_time_monitoring', true)) {
            $this->startMonitoring();
        }
        
        // Initialize query cache
        $this->initializeQueryCache();
    }
    
    /**
     * Before query execution
     */
    public function beforeQuery(string $query): string
    {
        if (!$this->shouldMonitorQuery($query)) {
            return $query;
        }
        
        // Start timing
        $this->queryStartTime = microtime(true);
        
        // Check query cache
        $cachedResult = $this->checkQueryCache($query);
        if ($cachedResult !== null) {
            return $cachedResult;
        }
        
        // Apply query rewriting if enabled
        if ($this->getOption('enable_query_rewriting', true)) {
            $rewrittenQuery = $this->queryRewriter->rewrite($query);
            if ($rewrittenQuery !== $query) {
                $this->logQueryRewrite($query, $rewrittenQuery);
                return $rewrittenQuery;
            }
        }
        
        return $query;
    }
    
    /**
     * After query execution
     */
    public function afterQuery($result, string $query): void
    {
        if (!$this->shouldMonitorQuery($query)) {
            return;
        }
        
        $executionTime = (microtime(true) - $this->queryStartTime) * 1000; // Convert to milliseconds
        
        // Log query execution
        $this->logQueryExecution($query, $executionTime, $result);
        
        // Check for slow query
        if ($executionTime > $this->getOption('slow_query_threshold', 1000)) {
            $this->handleSlowQuery($query, $executionTime);
        }
        
        // Update statistics
        $this->updateQueryStatistics($query, $executionTime);
        
        // Check for optimization opportunities
        $this->checkOptimizationOpportunities($query, $executionTime);
        
        // Cache result if appropriate
        $this->cacheQueryResult($query, $result);
    }
    
    /**
     * Optimize query
     */
    public function optimizeQuery(string $query): string
    {
        if (!$this->getOption('enable_auto_optimization', false)) {
            return $query;
        }
        
        // Analyze query structure
        $analysis = $this->queryAnalyzer->analyze($query);
        
        // Apply optimizations based on analysis
        $optimizedQuery = $query;
        
        // Optimize JOIN order
        if ($analysis['has_joins']) {
            $optimizedQuery = $this->optimizeJoinOrder($optimizedQuery, $analysis);
        }
        
        // Optimize WHERE clauses
        if ($analysis['has_where']) {
            $optimizedQuery = $this->optimizeWhereClause($optimizedQuery, $analysis);
        }
        
        // Optimize subqueries
        if ($analysis['has_subqueries']) {
            $optimizedQuery = $this->optimizeSubqueries($optimizedQuery, $analysis);
        }
        
        // Add query hints if beneficial
        $optimizedQuery = $this->addQueryHints($optimizedQuery, $analysis);
        
        if ($optimizedQuery !== $query) {
            $this->logOptimization($query, $optimizedQuery, $analysis);
        }
        
        return $optimizedQuery;
    }
    
    /**
     * Handle slow query
     */
    private function handleSlowQuery(string $query, float $executionTime): void
    {
        // Get execution plan
        $executionPlan = $this->planAnalyzer->getExecutionPlan($query);
        
        // Analyze slow query
        $analysis = $this->queryAnalyzer->analyzeSlowQuery($query, $executionPlan);
        
        // Store slow query data
        DB::table('db_optimizer_slow_queries')->insert([
            'query' => $query,
            'query_hash' => md5($query),
            'execution_time' => $executionTime,
            'execution_plan' => json_encode($executionPlan),
            'analysis' => json_encode($analysis),
            'suggestions' => json_encode($analysis['suggestions'] ?? []),
            'occurred_at' => now()
        ]);
        
        // Generate index suggestions
        $indexSuggestions = $this->indexAdvisor->suggestIndexes($query, $executionPlan);
        if (!empty($indexSuggestions)) {
            $this->storeIndexSuggestions($query, $indexSuggestions);
        }
        
        // Send alert if threshold exceeded
        if ($executionTime > $this->getOption('slow_query_threshold', 1000) * 2) {
            $this->alertManager->sendAlert('critical_slow_query', [
                'query' => $query,
                'execution_time' => $executionTime,
                'suggestions' => $analysis['suggestions'] ?? []
            ]);
        }
    }
    
    /**
     * Generate index suggestions
     */
    public function generateIndexSuggestions(): void
    {
        // Get frequently executed queries
        $frequentQueries = DB::table('db_optimizer_query_stats')
            ->where('execution_count', '>=', $this->getOption('index_suggestion_threshold', 100))
            ->where('avg_execution_time', '>', 100)
            ->orderBy('total_execution_time', 'desc')
            ->limit(100)
            ->get();
        
        $suggestions = [];
        
        foreach ($frequentQueries as $queryStats) {
            // Analyze query for index opportunities
            $query = $queryStats->query;
            $analysis = $this->queryAnalyzer->analyze($query);
            
            // Get current execution plan
            $executionPlan = $this->planAnalyzer->getExecutionPlan($query);
            
            // Generate suggestions
            $indexSuggestions = $this->indexAdvisor->suggestIndexes($query, $executionPlan);
            
            if (!empty($indexSuggestions)) {
                foreach ($indexSuggestions as $suggestion) {
                    $suggestions[] = array_merge($suggestion, [
                        'query_hash' => $queryStats->query_hash,
                        'impact_score' => $this->calculateIndexImpact($suggestion, $queryStats),
                        'estimated_improvement' => $this->estimateImprovement($suggestion, $executionPlan)
                    ]);
                }
            }
        }
        
        // Rank suggestions by impact
        usort($suggestions, function($a, $b) {
            return $b['impact_score'] <=> $a['impact_score'];
        });
        
        // Store top suggestions
        foreach (array_slice($suggestions, 0, 20) as $suggestion) {
            $this->storeIndexSuggestion($suggestion);
        }
        
        // Send notification if high-impact suggestions found
        if (!empty($suggestions) && $suggestions[0]['impact_score'] > 80) {
            $this->alertManager->sendAlert('high_impact_index_suggestion', [
                'suggestion' => $suggestions[0],
                'potential_improvement' => $suggestions[0]['estimated_improvement']
            ]);
        }
    }
    
    /**
     * Perform database maintenance
     */
    public function performMaintenance(): void
    {
        $maintenanceLog = [];
        
        // Update table statistics
        if ($this->shouldRunMaintenance('analyze')) {
            $tables = $this->getTablesForMaintenance();
            foreach ($tables as $table) {
                $result = $this->maintenanceScheduler->analyzeTable($table);
                $maintenanceLog[] = [
                    'action' => 'analyze',
                    'table' => $table,
                    'result' => $result
                ];
            }
        }
        
        // Vacuum tables
        if ($this->shouldRunMaintenance('vacuum')) {
            $tables = $this->getTablesNeedingVacuum();
            foreach ($tables as $table) {
                $result = $this->maintenanceScheduler->vacuumTable($table);
                $maintenanceLog[] = [
                    'action' => 'vacuum',
                    'table' => $table,
                    'result' => $result
                ];
            }
        }
        
        // Reindex if needed
        if ($this->shouldRunMaintenance('reindex')) {
            $indexes = $this->getIndexesNeedingReindex();
            foreach ($indexes as $index) {
                $result = $this->maintenanceScheduler->reindex($index);
                $maintenanceLog[] = [
                    'action' => 'reindex',
                    'index' => $index,
                    'result' => $result
                ];
            }
        }
        
        // Log maintenance activities
        $this->logMaintenanceActivities($maintenanceLog);
        
        // Update statistics after maintenance
        $this->updateDatabaseStatistics();
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Database Optimizer',
            'DB Optimizer',
            'db_optimizer.access',
            'database-query-optimizer',
            [$this, 'renderDashboard'],
            'dashicons-database-view',
            70
        );
        
        add_submenu_page(
            'database-query-optimizer',
            'Query Monitor',
            'Query Monitor',
            'db_optimizer.view_queries',
            'db-optimizer-monitor',
            [$this, 'renderQueryMonitor']
        );
        
        add_submenu_page(
            'database-query-optimizer',
            'Slow Queries',
            'Slow Queries',
            'db_optimizer.view_queries',
            'db-optimizer-slow-queries',
            [$this, 'renderSlowQueries']
        );
        
        add_submenu_page(
            'database-query-optimizer',
            'Index Advisor',
            'Index Advisor',
            'db_optimizer.analyze_queries',
            'db-optimizer-indexes',
            [$this, 'renderIndexAdvisor']
        );
        
        add_submenu_page(
            'database-query-optimizer',
            'Execution Plans',
            'Execution Plans',
            'db_optimizer.analyze_queries',
            'db-optimizer-plans',
            [$this, 'renderExecutionPlans']
        );
        
        add_submenu_page(
            'database-query-optimizer',
            'Reports',
            'Reports',
            'db_optimizer.view_reports',
            'db-optimizer-reports',
            [$this, 'renderReports']
        );
        
        add_submenu_page(
            'database-query-optimizer',
            'Settings',
            'Settings',
            'db_optimizer.configure',
            'db-optimizer-settings',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Initialize database extensions
     */
    private function initializeDatabaseExtensions(): void
    {
        // Enable pg_stat_statements if available
        try {
            DB::statement("CREATE EXTENSION IF NOT EXISTS pg_stat_statements");
        } catch (\RuntimeException $e) {
            $this->log('Could not enable pg_stat_statements: ' . $e->getMessage(), 'warning');
        }
        
        // Enable other useful extensions
        $extensions = ['pg_trgm', 'btree_gin', 'btree_gist'];
        foreach ($extensions as $extension) {
            try {
                DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension}");
            } catch (\RuntimeException $e) {
                $this->log("Could not enable {$extension}: " . $e->getMessage(), 'info');
            }
        }
    }
    
    /**
     * Create optimization indexes
     */
    private function createOptimizationIndexes(): void
    {
        // Create indexes for optimization tables
        $indexes = [
            'CREATE INDEX IF NOT EXISTS idx_queries_hash ON db_optimizer_queries(query_hash)',
            'CREATE INDEX IF NOT EXISTS idx_queries_time ON db_optimizer_queries(execution_time)',
            'CREATE INDEX IF NOT EXISTS idx_slow_queries_time ON db_optimizer_slow_queries(occurred_at)',
            'CREATE INDEX IF NOT EXISTS idx_stats_query ON db_optimizer_query_stats(query_hash)',
            'CREATE INDEX IF NOT EXISTS idx_suggestions_score ON db_optimizer_index_suggestions(impact_score DESC)'
        ];
        
        foreach ($indexes as $index) {
            try {
                DB::statement($index);
            } catch (\RuntimeException $e) {
                $this->log('Failed to create index: ' . $e->getMessage(), 'error');
            }
        }
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/reports',
            $this->getPluginPath() . '/exports',
            $this->getPluginPath() . '/cache',
            $this->getPluginPath() . '/logs'
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