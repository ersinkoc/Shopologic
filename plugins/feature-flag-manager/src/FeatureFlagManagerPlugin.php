<?php

declare(strict_types=1);

namespace Shopologic\Plugins\FeatureFlagManager;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use FeatureFlagManager\Core\{
    FlagManager,
    Evaluator,
    ExperimentManager,
    TargetingEngine,
    RolloutManager,
    AnalyticsCollector,
    CacheManager,;
    WebSocketServer;
};
use FeatureFlagManager\Services\{
    FlagService,
    ExperimentService,
    TargetingService,
    EvaluationService,
    AuditService,
    WebhookService,
    SDKService,;
    SafetyService;
};
use FeatureFlagManager\Strategies\{
    PercentageRollout,
    RingDeployment,
    CanaryRelease,
    BetaRollout,;
    GeographicRollout;
};

class FeatureFlagManagerPlugin extends AbstractPlugin
{
    private FlagManager $flagManager;
    private Evaluator $evaluator;
    private ExperimentManager $experimentManager;
    private TargetingEngine $targetingEngine;
    private RolloutManager $rolloutManager;
    private AnalyticsCollector $analyticsCollector;
    private CacheManager $cacheManager;
    private ?WebSocketServer $webSocketServer = null;
    
    private FlagService $flagService;
    private ExperimentService $experimentService;
    private TargetingService $targetingService;
    private EvaluationService $evaluationService;
    private AuditService $auditService;
    private WebhookService $webhookService;
    private SDKService $sdkService;
    private SafetyService $safetyService;
    
    private array $flags = [];
    private array $experiments = [];
    private array $evaluationCache = [];
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Initialize default flags
        $this->initializeDefaultFlags();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Create required directories
        $this->createDirectories();
        
        // Setup WebSocket server if enabled
        $this->setupWebSocketServer();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Complete running experiments
        $this->completeRunningExperiments();
        
        // Stop WebSocket server
        $this->stopWebSocketServer();
        
        // Flush evaluation cache
        $this->flushEvaluationCache();
        
        // Save current state
        $this->saveCurrentState();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize services early
        HookSystem::addAction('init', [$this, 'initializeServices'], 5);
        
        // Feature evaluation
        HookSystem::addFilter('feature_enabled', [$this, 'isFeatureEnabled'], 10, 3);
        HookSystem::addFilter('feature_value', [$this, 'getFeatureValue'], 10, 3);
        HookSystem::addFilter('experiment_variant', [$this, 'getExperimentVariant'], 10, 3);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // API endpoints
        $this->registerApiEndpoints();
        
        // Scheduled tasks
        HookSystem::addAction('features_update_rollout', [$this, 'updateRolloutPercentages']);
        HookSystem::addAction('features_check_experiments', [$this, 'checkExperimentCompletion']);
        HookSystem::addAction('features_calculate_results', [$this, 'calculateExperimentResults']);
        HookSystem::addAction('features_cleanup_evaluations', [$this, 'cleanupOldEvaluations']);
        HookSystem::addAction('features_sync_remote', [$this, 'syncRemoteFlags']);
        
        // Real-time updates
        if ($this->getOption('sdk_settings.enable_websocket', true)) {
            HookSystem::addAction('flag_updated', [$this, 'broadcastFlagUpdate']);
            HookSystem::addAction('experiment_updated', [$this, 'broadcastExperimentUpdate']);
        }
        
        // Safety features
        HookSystem::addAction('application_error_rate_high', [$this, 'handleHighErrorRate']);
        HookSystem::addAction('flag_evaluation_error', [$this, 'handleEvaluationError']);
        
        // Analytics tracking
        HookSystem::addAction('feature_evaluated', [$this, 'trackEvaluation']);
        HookSystem::addAction('experiment_exposure', [$this, 'trackExposure']);
        
        // Audit logging
        if ($this->getOption('audit_settings.enable_audit_log', true)) {
            HookSystem::addAction('flag_changed', [$this, 'auditFlagChange']);
            HookSystem::addAction('experiment_changed', [$this, 'auditExperimentChange']);
        }
        
        // Frontend integration
        HookSystem::addAction('wp_head', [$this, 'injectFeatureFlagScript']);
        HookSystem::addFilter('body_class', [$this, 'addFeatureFlagClasses']);
    }
    
    /**
     * Initialize services
     */
    public function initializeServices(): void
    {
        // Initialize cache manager
        $this->cacheManager = new CacheManager($this->container);
        
        // Initialize core managers
        $this->flagManager = new FlagManager($this->container);
        $this->targetingEngine = new TargetingEngine($this->container);
        $this->evaluator = new Evaluator($this->flagManager, $this->targetingEngine, $this->cacheManager);
        $this->experimentManager = new ExperimentManager($this->container);
        $this->rolloutManager = new RolloutManager($this->container);
        $this->analyticsCollector = new AnalyticsCollector($this->container);
        
        // Initialize services
        $this->flagService = new FlagService($this->flagManager);
        $this->experimentService = new ExperimentService($this->experimentManager);
        $this->targetingService = new TargetingService($this->targetingEngine);
        $this->evaluationService = new EvaluationService($this->evaluator);
        $this->auditService = new AuditService($this->container);
        $this->webhookService = new WebhookService($this->container);
        $this->sdkService = new SDKService($this->container);
        $this->safetyService = new SafetyService($this->container);
        
        // Register rollout strategies
        $this->registerRolloutStrategies();
        
        // Load flags and experiments
        $this->loadFlags();
        $this->loadActiveExperiments();
        
        // Initialize WebSocket if enabled
        if ($this->getOption('sdk_settings.enable_websocket', true)) {
            $this->initializeWebSocket();
        }
    }
    
    /**
     * Register rollout strategies
     */
    private function registerRolloutStrategies(): void
    {
        $this->rolloutManager->registerStrategy('percentage', new PercentageRollout());
        $this->rolloutManager->registerStrategy('ring', new RingDeployment());
        $this->rolloutManager->registerStrategy('canary', new CanaryRelease());
        $this->rolloutManager->registerStrategy('beta', new BetaRollout());
        $this->rolloutManager->registerStrategy('geographic', new GeographicRollout());
    }
    
    /**
     * Check if feature is enabled
     */
    public function isFeatureEnabled($default, string $flagKey, array $context = []): bool
    {
        try {
            // Check cache first
            $cacheKey = $this->getCacheKey($flagKey, $context);
            if (isset($this->evaluationCache[$cacheKey])) {
                return $this->evaluationCache[$cacheKey];
            }
            
            // Get flag
            $flag = $this->flagManager->getFlag($flagKey);
            if (!$flag) {
                return $default;
            }
            
            // Check if globally disabled
            if (!$flag->isEnabled()) {
                return false;
            }
            
            // Evaluate flag
            $result = $this->evaluator->evaluate($flag, $context);
            
            // Cache result
            if ($this->shouldCacheEvaluation()) {
                $this->evaluationCache[$cacheKey] = $result->getValue();
                $this->cacheManager->set($cacheKey, $result->getValue(), $this->getCacheTTL());
            }
            
            // Track evaluation
            $this->trackEvaluation($flag, $result, $context);
            
            return $result->getValue();
            
        } catch (\RuntimeException $e) {
            $this->handleEvaluationError($e, $flagKey);
            return $default;
        }
    }
    
    /**
     * Get feature value
     */
    public function getFeatureValue($default, string $flagKey, array $context = []): mixed
    {
        try {
            $flag = $this->flagManager->getFlag($flagKey);
            if (!$flag || !$flag->isEnabled()) {
                return $default;
            }
            
            $result = $this->evaluator->evaluate($flag, $context);
            
            // Handle experiments
            if ($flag->hasExperiment()) {
                $this->handleExperimentExposure($flag, $result, $context);
            }
            
            return $result->getValue() ?? $default;
            
        } catch (\RuntimeException $e) {
            $this->handleEvaluationError($e, $flagKey);
            return $default;
        }
    }
    
    /**
     * Get experiment variant
     */
    public function getExperimentVariant($default, string $experimentKey, array $context = []): string
    {
        try {
            $experiment = $this->experimentManager->getExperiment($experimentKey);
            if (!$experiment || !$experiment->isActive()) {
                return $default;
            }
            
            // Check if user is eligible
            if (!$this->targetingEngine->evaluate($experiment->getTargeting(), $context)) {
                return $experiment->getControlVariant();
            }
            
            // Get or assign variant
            $variant = $this->experimentService->getOrAssignVariant($experiment, $context);
            
            // Track exposure
            $this->analyticsCollector->trackExposure($experiment, $variant, $context);
            
            return $variant;
            
        } catch (\RuntimeException $e) {
            $this->log('Experiment evaluation error: ' . $e->getMessage(), 'error');
            return $default;
        }
    }
    
    /**
     * Update rollout percentages
     */
    public function updateRolloutPercentages(): void
    {
        $flags = $this->flagManager->getFlagsWithGradualRollout();
        
        foreach ($flags as $flag) {
            $rollout = $flag->getRollout();
            if (!$rollout || !$rollout->isGradual()) {
                continue;
            }
            
            // Calculate new percentage
            $newPercentage = $this->rolloutManager->calculateNextPercentage($rollout);
            
            if ($newPercentage !== $rollout->getPercentage()) {
                // Update rollout
                $rollout->setPercentage($newPercentage);
                $this->flagManager->updateFlag($flag);
                
                // Broadcast update
                $this->broadcastFlagUpdate($flag);
                
                // Trigger webhook
                $this->webhookService->trigger('rollout_changed', [
                    'flag' => $flag->getKey(),
                    'old_percentage' => $rollout->getPercentage(),
                    'new_percentage' => $newPercentage
                ]);
            }
        }
    }
    
    /**
     * Check experiment completion
     */
    public function checkExperimentCompletion(): void
    {
        $experiments = $this->experimentManager->getActiveExperiments();
        
        foreach ($experiments as $experiment) {
            if ($this->experimentService->shouldComplete($experiment)) {
                $this->completeExperiment($experiment);
            }
        }
    }
    
    /**
     * Complete experiment
     */
    private function completeExperiment($experiment): void
    {
        // Calculate final results
        $results = $this->experimentService->calculateResults($experiment);
        
        // Determine winner
        $winner = $this->experimentService->determineWinner($results);
        
        // Mark experiment as completed
        $experiment->complete($winner, $results);
        $this->experimentManager->updateExperiment($experiment);
        
        // Auto-rollout winner if configured
        if ($this->getOption('experiment_settings.auto_conclude', false) && $winner) {
            $this->rolloutWinner($experiment, $winner);
        }
        
        // Send notifications
        $this->notificationService->notifyExperimentCompleted($experiment, $results);
        
        // Trigger webhook
        $this->webhookService->trigger('experiment_completed', [
            'experiment' => $experiment->toArray(),
            'results' => $results,
            'winner' => $winner
        ]);
    }
    
    /**
     * Handle high error rate
     */
    public function handleHighErrorRate(array $metrics): void
    {
        if (!$this->getOption('safety_features.automatic_rollback', true)) {
            return;
        }
        
        $threshold = $this->getOption('safety_features.error_threshold', 5);
        
        if ($metrics['error_rate'] > $threshold) {
            // Find recently changed flags
            $recentFlags = $this->flagManager->getRecentlyChangedFlags(3600); // Last hour
            
            foreach ($recentFlags as $flag) {
                // Roll back to previous state
                $this->safetyService->rollbackFlag($flag);
                
                // Log incident
                $this->auditService->logSafetyRollback($flag, $metrics);
                
                // Send alert
                $this->notificationService->notifySafetyRollback($flag, $metrics);
            }
        }
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Feature Flags',
            'Feature Flags',
            'features.access',
            'feature-flag-manager',
            [$this, 'renderDashboard'],
            'dashicons-flag',
            57
        );
        
        add_submenu_page(
            'feature-flag-manager',
            'All Flags',
            'All Flags',
            'features.access',
            'feature-flags',
            [$this, 'renderFlags']
        );
        
        add_submenu_page(
            'feature-flag-manager',
            'Experiments',
            'Experiments',
            'features.access',
            'feature-experiments',
            [$this, 'renderExperiments']
        );
        
        add_submenu_page(
            'feature-flag-manager',
            'Targeting',
            'Targeting',
            'features.configure_targeting',
            'feature-targeting',
            [$this, 'renderTargeting']
        );
        
        add_submenu_page(
            'feature-flag-manager',
            'Analytics',
            'Analytics',
            'features.view_analytics',
            'feature-analytics',
            [$this, 'renderAnalytics']
        );
        
        add_submenu_page(
            'feature-flag-manager',
            'Settings',
            'Settings',
            'features.manage_flags',
            'feature-settings',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Inject feature flag script
     */
    public function injectFeatureFlagScript(): void
    {
        $context = $this->getCurrentUserContext();
        $flags = $this->evaluateAllFlags($context);
        
        ?>
        <script>
            window.FeatureFlags = <?php echo json_encode($flags); ?>;
            window.FeatureFlagContext = <?php echo json_encode($context); ?>;
        </script>
        <?php
        
        // Include SDK if configured
        if ($this->getOption('sdk_settings.enable_websocket', true)) {
            wp_enqueue_script('feature-flag-sdk', $this->getPluginUrl() . '/assets/js/feature-flag-sdk.js');
        }
    }
    
    /**
     * Create feature flag
     */
    public function createFlag(array $data): FeatureFlag
    {
        return $this->flagService->create($data);
    }
    
    /**
     * Update feature flag
     */
    public function updateFlag(string $key, array $data): FeatureFlag
    {
        return $this->flagService->update($key, $data);
    }
    
    /**
     * Create experiment
     */
    public function createExperiment(array $data): Experiment
    {
        return $this->experimentService->create($data);
    }
    
    /**
     * Get flag evaluation
     */
    public function evaluateFlag(string $key, array $context = []): mixed
    {
        return $this->isFeatureEnabled(false, $key, $context);
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/analytics',
            $this->getPluginPath() . '/exports',
            $this->getPluginPath() . '/logs',
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