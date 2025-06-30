<?php

declare(strict_types=1);
namespace Shopologic\Plugins\AbTestingFramework;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * A/B Testing Framework Plugin
 * 
 * Built-in experimentation platform for testing and optimization
 */
class ABTestingFrameworkPlugin extends AbstractPlugin
{
    private $experimentEngine;
    private $variantManager;
    private $statisticsEngine;
    private $conversionTracker;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->scheduleAnalytics();
    }

    private function registerServices(): void
    {
        $this->experimentEngine = new Services\ExperimentEngine($this->api);
        $this->variantManager = new Services\VariantManager($this->api);
        $this->statisticsEngine = new Services\StatisticsEngine($this->api);
        $this->conversionTracker = new Services\ConversionTracker($this->api);
    }

    private function registerHooks(): void
    {
        // Experiment rendering
        Hook::addAction('page.rendered', [$this, 'assignExperimentVariant'], 5);
        Hook::addFilter('content.render', [$this, 'applyContentVariants'], 10, 2);
        Hook::addFilter('pricing.display', [$this, 'applyPricingVariants'], 10, 2);
        Hook::addFilter('layout.render', [$this, 'applyLayoutVariants'], 10, 2);
        
        // Conversion tracking
        Hook::addAction('order.completed', [$this, 'trackConversion'], 10, 1);
        Hook::addAction('goal.completed', [$this, 'trackCustomGoal'], 10, 2);
        Hook::addAction('experiment.variant_shown', [$this, 'recordImpression'], 10, 2);
        
        // Analytics events
        Hook::addAction('user.action', [$this, 'trackUserAction'], 10, 2);
        Hook::addAction('page.exit', [$this, 'trackBounce'], 10, 1);
        
        // Admin interface
        Hook::addAction('admin.experiments.dashboard', [$this, 'experimentsDashboard'], 10);
        Hook::addFilter('admin.page.toolbar', [$this, 'addExperimentToolbar'], 10, 1);
    }

    public function assignExperimentVariant($page): void
    {
        $activeExperiments = $this->experimentEngine->getActiveExperiments($page['type']);
        
        foreach ($activeExperiments as $experiment) {
            // Check if user already assigned
            $assignment = $this->getExistingAssignment($experiment->id);
            
            if (!$assignment) {
                // Check traffic allocation
                if (!$this->shouldIncludeInExperiment($experiment)) {
                    continue;
                }
                
                // Assign variant
                $variant = $this->variantManager->assignVariant($experiment);
                $assignment = $this->recordAssignment($experiment->id, $variant->id);
            }
            
            // Apply variant
            $this->applyVariant($experiment, $assignment->variant_id);
            
            // Track impression
            Hook::doAction('experiment.variant_shown', $experiment, $assignment);
        }
    }

    public function applyContentVariants($content, $context): string
    {
        $experiments = $this->getContextExperiments($context, 'content');
        
        foreach ($experiments as $experiment) {
            $variantId = $this->getUserVariant($experiment->id);
            
            if ($variantId) {
                $variant = $this->variantManager->getVariant($variantId);
                $content = $this->applyContentChanges($content, $variant->changes);
            }
        }
        
        return $content;
    }

    public function applyPricingVariants($price, $product): float
    {
        $experiments = $this->experimentEngine->getProductExperiments($product->id, 'pricing');
        
        foreach ($experiments as $experiment) {
            $variantId = $this->getUserVariant($experiment->id);
            
            if ($variantId) {
                $variant = $this->variantManager->getVariant($variantId);
                
                if (isset($variant->changes['price_adjustment'])) {
                    $adjustment = $variant->changes['price_adjustment'];
                    
                    if ($adjustment['type'] === 'percentage') {
                        $price *= (1 + $adjustment['value'] / 100);
                    } else {
                        $price += $adjustment['value'];
                    }
                    
                    // Track price variant shown
                    $this->trackVariantEvent($experiment->id, $variantId, 'price_shown', [
                        'original_price' => $product->price,
                        'variant_price' => $price
                    ]);
                }
            }
        }
        
        return $price;
    }

    public function trackConversion($order): void
    {
        $userExperiments = $this->getUserExperiments();
        
        foreach ($userExperiments as $experiment) {
            if ($this->isConversionGoal($experiment, 'purchase')) {
                $this->conversionTracker->recordConversion([
                    'experiment_id' => $experiment->id,
                    'variant_id' => $experiment->variant_id,
                    'participant_id' => $this->getParticipantId(),
                    'goal_type' => 'purchase',
                    'goal_value' => $order->total,
                    'metadata' => [
                        'order_id' => $order->id,
                        'items_count' => count($order->items)
                    ]
                ]);
                
                // Update experiment statistics
                $this->updateExperimentStats($experiment->id);
            }
        }
    }

    public function experimentsDashboard(): void
    {
        $experiments = [
            'active' => $this->experimentEngine->getActiveExperiments(),
            'scheduled' => $this->experimentEngine->getScheduledExperiments(),
            'completed' => $this->experimentEngine->getCompletedExperiments(10),
            'draft' => $this->experimentEngine->getDraftExperiments()
        ];
        
        $metrics = [
            'total_experiments' => $this->getTotalExperiments(),
            'active_participants' => $this->getActiveParticipants(),
            'significant_results' => $this->getSignificantResults(),
            'revenue_impact' => $this->calculateRevenueImpact(),
            'conversion_improvements' => $this->getConversionImprovements()
        ];
        
        echo $this->api->view('ab-testing/dashboard', [
            'experiments' => $experiments,
            'metrics' => $metrics,
            'recommendations' => $this->getExperimentRecommendations()
        ]);
    }

    public function addExperimentToolbar($toolbar): string
    {
        if (!$this->userCanManageExperiments()) {
            return $toolbar;
        }
        
        $activeExperiments = $this->getPageExperiments();
        
        if (empty($activeExperiments)) {
            return $toolbar;
        }
        
        $experimentToolbar = $this->api->view('ab-testing/toolbar', [
            'experiments' => $activeExperiments,
            'current_variants' => $this->getCurrentVariants(),
            'can_force_variant' => true
        ]);
        
        return $toolbar . $experimentToolbar;
    }

    private function shouldIncludeInExperiment($experiment): bool
    {
        // Check maximum concurrent experiments
        $currentExperiments = $this->getUserExperimentCount();
        $maxConcurrent = $this->getConfig('max_concurrent_experiments', 3);
        
        if ($currentExperiments >= $maxConcurrent) {
            return false;
        }
        
        // Check traffic allocation
        $trafficPercentage = $experiment->traffic_percentage ?? 
                           $this->getConfig('default_traffic_split', 50);
        
        $hash = crc32($this->getParticipantId() . $experiment->id);
        $bucket = $hash % 100;
        
        return $bucket < $trafficPercentage;
    }

    private function applyVariant($experiment, $variantId): void
    {
        $variant = $this->variantManager->getVariant($variantId);
        
        // Store in session for consistency
        $this->api->session()->set("experiment_{$experiment->id}_variant", $variantId);
        
        // Apply variant changes based on type
        switch ($experiment->type) {
            case 'feature_flag':
                $this->applyFeatureFlag($variant);
                break;
                
            case 'ui_element':
                $this->applyUIChanges($variant);
                break;
                
            case 'algorithm':
                $this->applyAlgorithmVariant($variant);
                break;
                
            case 'multivariate':
                $this->applyMultivariateChanges($variant);
                break;
        }
    }

    private function updateExperimentStats($experimentId): void
    {
        $experiment = $this->experimentEngine->getExperiment($experimentId);
        $results = $this->calculateExperimentResults($experiment);
        
        // Check for statistical significance
        $significance = $this->statisticsEngine->calculateSignificance($results);
        
        if ($significance >= $this->getConfig('significance_threshold', 0.95)) {
            $this->markExperimentSignificant($experimentId, $results);
            
            // Auto-stop if configured
            if ($this->getConfig('auto_stop_on_significance', false)) {
                $this->experimentEngine->stopExperiment($experimentId);
                $this->notifyExperimentComplete($experiment, $results);
            }
        }
        
        // Update real-time metrics
        $this->updateRealtimeMetrics($experimentId, $results);
    }

    private function calculateExperimentResults($experiment): array
    {
        $variants = $this->variantManager->getExperimentVariants($experiment->id);
        $results = [];
        
        foreach ($variants as $variant) {
            $stats = $this->conversionTracker->getVariantStats($experiment->id, $variant->id);
            
            $results[$variant->id] = [
                'variant' => $variant,
                'participants' => $stats['participants'],
                'conversions' => $stats['conversions'],
                'conversion_rate' => $stats['conversions'] / max(1, $stats['participants']),
                'revenue' => $stats['total_revenue'],
                'avg_order_value' => $stats['total_revenue'] / max(1, $stats['conversions']),
                'confidence_interval' => $this->statisticsEngine->calculateConfidenceInterval($stats),
                'uplift' => null // Calculated below
            ];
        }
        
        // Calculate uplift vs control
        $controlId = $this->getControlVariantId($experiment);
        foreach ($results as $variantId => &$result) {
            if ($variantId !== $controlId) {
                $result['uplift'] = $this->calculateUplift(
                    $results[$controlId],
                    $result
                );
            }
        }
        
        return $results;
    }

    private function calculateUplift($control, $variant): array
    {
        $conversionUplift = (($variant['conversion_rate'] - $control['conversion_rate']) 
                           / max(0.0001, $control['conversion_rate'])) * 100;
                           
        $revenueUplift = (($variant['avg_order_value'] - $control['avg_order_value']) 
                        / max(0.01, $control['avg_order_value'])) * 100;
        
        return [
            'conversion' => round($conversionUplift, 2),
            'revenue' => round($revenueUplift, 2),
            'is_positive' => $conversionUplift > 0,
            'confidence' => $this->statisticsEngine->calculateUpliftConfidence($control, $variant)
        ];
    }

    private function getExperimentRecommendations(): array
    {
        $recommendations = [];
        
        // Low traffic experiments
        $lowTrafficExperiments = $this->experimentEngine->getLowTrafficExperiments();
        foreach ($lowTrafficExperiments as $experiment) {
            $recommendations[] = [
                'type' => 'increase_traffic',
                'experiment' => $experiment,
                'message' => 'Increase traffic allocation to reach significance faster'
            ];
        }
        
        // High-performing variants
        $winningVariants = $this->experimentEngine->getWinningVariants();
        foreach ($winningVariants as $variant) {
            $recommendations[] = [
                'type' => 'implement_winner',
                'variant' => $variant,
                'message' => 'This variant shows significant positive results'
            ];
        }
        
        // Experiment opportunities
        $opportunities = $this->identifyTestingOpportunities();
        foreach ($opportunities as $opportunity) {
            $recommendations[] = [
                'type' => 'new_experiment',
                'area' => $opportunity['area'],
                'message' => $opportunity['suggestion']
            ];
        }
        
        return $recommendations;
    }

    private function identifyTestingOpportunities(): array
    {
        $opportunities = [];
        
        // High-traffic pages without experiments
        $untestedPages = $this->experimentEngine->getUntestedHighTrafficPages();
        foreach ($untestedPages as $page) {
            $opportunities[] = [
                'area' => 'page_optimization',
                'page' => $page,
                'suggestion' => "High-traffic page '{$page['title']}' has no active experiments"
            ];
        }
        
        // Products with low conversion
        $lowConversionProducts = $this->experimentEngine->getLowConversionProducts();
        foreach ($lowConversionProducts as $product) {
            $opportunities[] = [
                'area' => 'product_optimization',
                'product' => $product,
                'suggestion' => "Product '{$product['name']}' has below-average conversion rate"
            ];
        }
        
        return $opportunities;
    }

    private function scheduleAnalytics(): void
    {
        // Real-time stats update
        $this->api->scheduler()->addJob('update_experiment_stats', '*/5 * * * *', function() {
            $this->updateAllExperimentStats();
        });
        
        // Daily experiment analysis
        $this->api->scheduler()->addJob('analyze_experiments', '0 2 * * *', function() {
            $this->performDailyAnalysis();
        });
        
        // Weekly experiment recommendations
        $this->api->scheduler()->addJob('generate_recommendations', '0 9 * * 1', function() {
            $this->generateWeeklyRecommendations();
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->post('/experiments/create', 'Controllers\ExperimentController@create');
        $this->api->router()->get('/experiments/{id}/results', 'Controllers\ExperimentController@getResults');
        $this->api->router()->post('/experiments/{id}/variants', 'Controllers\ExperimentController@createVariant');
        $this->api->router()->post('/experiments/{id}/start', 'Controllers\ExperimentController@start');
        $this->api->router()->post('/experiments/{id}/stop', 'Controllers\ExperimentController@stop');
        $this->api->router()->post('/experiments/{id}/implement', 'Controllers\ExperimentController@implementWinner');
        $this->api->router()->get('/experiments/recommendations', 'Controllers\ExperimentController@getRecommendations');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createSampleExperiments();
        $this->setupDefaultGoals();
    }

    private function createSampleExperiments(): void
    {
        $samples = [
            [
                'name' => 'CTA Button Color Test',
                'type' => 'ui_element',
                'status' => 'draft',
                'hypothesis' => 'Changing CTA button color to green will increase conversions'
            ],
            [
                'name' => 'Free Shipping Threshold',
                'type' => 'pricing',
                'status' => 'draft',
                'hypothesis' => 'Lowering free shipping threshold will increase AOV'
            ]
        ];

        foreach ($samples as $sample) {
            $this->api->database()->table('experiments')->insert($sample);
        }
    }

    private function setupDefaultGoals(): void
    {
        $goals = [
            ['name' => 'purchase', 'type' => 'conversion', 'description' => 'Order completion'],
            ['name' => 'add_to_cart', 'type' => 'micro_conversion', 'description' => 'Product added to cart'],
            ['name' => 'engagement', 'type' => 'behavior', 'description' => 'Page engagement time']
        ];

        foreach ($goals as $goal) {
            $this->api->database()->table('experiment_goals')->insert($goal);
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
     * Register Hooks
     */
    protected function registerHooks(): void
    {
        // TODO: Implement registerHooks
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