<?php

namespace BehavioralPsychologyEngine;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Hook\HookSystem;
use Shopologic\Core\Container\ContainerInterface;
use Shopologic\Dashboard\WidgetInterface;
use Shopologic\Cron\CronInterface;
use BehavioralPsychologyEngine\Services\TriggerServiceInterface;
use BehavioralPsychologyEngine\Services\TriggerService;
use BehavioralPsychologyEngine\Services\PersonalizationServiceInterface;
use BehavioralPsychologyEngine\Services\PersonalizationService;
use BehavioralPsychologyEngine\Services\CognitiveBiasServiceInterface;
use BehavioralPsychologyEngine\Services\CognitiveBiasService;
use BehavioralPsychologyEngine\Repositories\CampaignRepositoryInterface;
use BehavioralPsychologyEngine\Repositories\CampaignRepository;
use BehavioralPsychologyEngine\Controllers\PsychologyApiController;
use BehavioralPsychologyEngine\Jobs\OptimizeCampaignsJob;

/**
 * Behavioral Psychology Engine Plugin
 * 
 * Applies psychological principles to optimize conversions through urgency triggers,
 * social proof, cognitive biases, and personalized messaging
 */
class BehavioralPsychologyEnginePlugin extends AbstractPlugin implements WidgetInterface, CronInterface
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
        $this->container->bind(TriggerServiceInterface::class, TriggerService::class);
        $this->container->bind(PersonalizationServiceInterface::class, PersonalizationService::class);
        $this->container->bind(CognitiveBiasServiceInterface::class, CognitiveBiasService::class);
        $this->container->bind(CampaignRepositoryInterface::class, CampaignRepository::class);

        $this->container->singleton(TriggerService::class, function(ContainerInterface $container) {
            return new TriggerService(
                $container->get(CognitiveBiasServiceInterface::class),
                $container->get(CampaignRepositoryInterface::class),
                $container->get('cache'),
                $this->getConfig()
            );
        });

        $this->container->singleton(PersonalizationService::class, function(ContainerInterface $container) {
            return new PersonalizationService(
                $container->get('database'),
                $container->get('events'),
                $this->getConfig('personalization', [])
            );
        });

        $this->container->singleton(CognitiveBiasService::class, function(ContainerInterface $container) {
            return new CognitiveBiasService(
                $container->get('database'),
                $container->get('cache'),
                $this->getConfig('biases', [])
            );
        });
    }

    protected function registerHooks(): void
    {
        // Urgency and scarcity triggers
        HookSystem::addFilter('product.display', [$this, 'applyUrgencyTriggers'], 10);
        HookSystem::addFilter('cart.messaging', [$this, 'applyCartPsychology'], 10);
        HookSystem::addAction('product.low_stock', [$this, 'triggerScarcityMessaging'], 5);
        
        // Social proof automation
        HookSystem::addFilter('product.social_proof', [$this, 'displaySocialProof'], 10);
        HookSystem::addAction('order.completed', [$this, 'updateSocialProofData'], 15);
        HookSystem::addFilter('checkout.trust_signals', [$this, 'addTrustSignals'], 10);
        
        // Cognitive bias application
        HookSystem::addFilter('pricing.display', [$this, 'applyAnchoringBias'], 10);
        HookSystem::addFilter('product.recommendations', [$this, 'applyConfirmationBias'], 10);
        HookSystem::addFilter('checkout.options', [$this, 'applyDefaultBias'], 10);
        
        // Behavioral triggers
        HookSystem::addAction('cart.abandoned', [$this, 'triggerLossAversion'], 5);
        HookSystem::addAction('page.exit_intent', [$this, 'triggerExitIntentPopup'], 5);
        HookSystem::addFilter('email.content', [$this, 'personalizeEmailPsychology'], 10);
        
        // A/B testing and optimization
        HookSystem::addFilter('page.variant', [$this, 'selectPsychologyVariant'], 5);
        HookSystem::addAction('conversion.tracked', [$this, 'trackTriggerEffectiveness'], 10);
        
        // Real-time personalization
        HookSystem::addAction('user.behavior_tracked', [$this, 'updatePersonalizationProfile'], 10);
        HookSystem::addFilter('content.personalize', [$this, 'applyPersonalization'], 10);
    }

    protected function registerApiEndpoints(): void
    {
        $this->router->group(['prefix' => 'api/v1/psychology'], function($router) {
            // Trigger management
            $router->get('/triggers/active', [PsychologyApiController::class, 'getActiveTriggers']);
            $router->post('/triggers/create', [PsychologyApiController::class, 'createTrigger']);
            $router->put('/triggers/{trigger_id}', [PsychologyApiController::class, 'updateTrigger']);
            $router->delete('/triggers/{trigger_id}', [PsychologyApiController::class, 'deleteTrigger']);
            
            // Campaign management
            $router->get('/campaigns', [PsychologyApiController::class, 'getCampaigns']);
            $router->get('/campaigns/{campaign_id}', [PsychologyApiController::class, 'getCampaignDetails']);
            $router->post('/campaigns/create', [PsychologyApiController::class, 'createCampaign']);
            $router->get('/campaigns/{campaign_id}/results', [PsychologyApiController::class, 'getCampaignResults']);
            
            // Personalization
            $router->post('/personalize', [PsychologyApiController::class, 'getPersonalizedContent']);
            $router->get('/profiles/{user_id}', [PsychologyApiController::class, 'getUserPsychologyProfile']);
            
            // Analytics
            $router->get('/analytics/effectiveness', [PsychologyApiController::class, 'getTriggerEffectiveness']);
            $router->get('/analytics/conversion-impact', [PsychologyApiController::class, 'getConversionImpact']);
            $router->get('/analytics/bias-performance', [PsychologyApiController::class, 'getBiasPerformance']);
        });

        // GraphQL schema extension
        $this->graphql->extendSchema([
            'Query' => [
                'psychologyTriggers' => [
                    'type' => '[PsychologyTrigger]',
                    'args' => ['active' => 'Boolean', 'type' => 'String'],
                    'resolve' => [$this, 'resolvePsychologyTriggers']
                ],
                'userPsychologyProfile' => [
                    'type' => 'UserPsychologyProfile',
                    'args' => ['userId' => 'ID!'],
                    'resolve' => [$this, 'resolveUserPsychologyProfile']
                ]
            ]
        ]);
    }

    protected function registerCronJobs(): void
    {
        // Update social proof data every 30 minutes
        $this->cron->schedule('*/30 * * * *', [$this, 'updateSocialProofData']);
        
        // Analyze trigger effectiveness every 4 hours
        $this->cron->schedule('0 */4 * * *', [$this, 'analyzeTriggerEffectiveness']);
        
        // Optimize campaigns daily
        $this->cron->schedule('0 2 * * *', [$this, 'optimizeCampaigns']);
        
        // Generate psychology reports weekly
        $this->cron->schedule('0 1 * * MON', [$this, 'generatePsychologyReports']);
        
        // Clean up expired triggers
        $this->cron->schedule('0 */6 * * *', [$this, 'cleanupExpiredTriggers']);
    }

    public function getDashboardWidget(): array
    {
        return [
            'id' => 'behavioral-psychology-widget',
            'title' => 'Behavioral Psychology Performance',
            'position' => 'main',
            'priority' => 20,
            'render' => [$this, 'renderPsychologyDashboard']
        ];
    }

    protected function registerPermissions(): void
    {
        $this->permissions->register([
            'psychology.triggers.view' => 'View psychology triggers',
            'psychology.triggers.manage' => 'Manage psychology triggers',
            'psychology.campaigns.create' => 'Create psychology campaigns',
            'psychology.campaigns.manage' => 'Manage psychology campaigns',
            'psychology.analytics.view' => 'View psychology analytics'
        ]);
    }

    // Hook Implementations

    public function applyUrgencyTriggers(array $display, array $data): array
    {
        $product = $data['product'];
        $triggerService = $this->container->get(TriggerServiceInterface::class);
        
        // Check for applicable urgency triggers
        $triggers = $triggerService->getApplicableTriggers($product, 'urgency');
        
        foreach ($triggers as $trigger) {
            switch ($trigger->type) {
                case 'limited_time':
                    $display['urgency_message'] = $this->generateLimitedTimeMessage($trigger, $product);
                    break;
                    
                case 'low_stock':
                    if ($product->stock <= $trigger->threshold) {
                        $display['scarcity_message'] = "Only {$product->stock} left in stock!";
                        $display['scarcity_class'] = 'urgent-red';
                    }
                    break;
                    
                case 'flash_sale':
                    $display['flash_sale'] = $this->generateFlashSaleDisplay($trigger, $product);
                    break;
                    
                case 'social_proof_urgency':
                    $display['viewing_message'] = $this->generateViewingMessage($product);
                    break;
            }
        }
        
        return $display;
    }

    public function displaySocialProof(array $proofData, array $data): array
    {
        $product = $data['product'];
        $cognitiveBiasService = $this->container->get(CognitiveBiasServiceInterface::class);
        
        // Recent purchases
        $recentPurchases = $this->getRecentPurchases($product->id, 5);
        if (!empty($recentPurchases)) {
            $proofData['recent_purchases'] = $this->formatRecentPurchases($recentPurchases);
        }
        
        // Viewing count
        $viewingCount = $this->getCurrentViewingCount($product->id);
        if ($viewingCount > 1) {
            $proofData['viewing_count'] = "{$viewingCount} people are viewing this product";
        }
        
        // Popular badge
        if ($this->isPopularProduct($product->id)) {
            $proofData['badges'][] = [
                'type' => 'popular',
                'text' => 'Best Seller',
                'class' => 'badge-popular'
            ];
        }
        
        // Expert endorsements
        $endorsements = $cognitiveBiasService->getAuthorityEndorsements($product->id);
        if (!empty($endorsements)) {
            $proofData['endorsements'] = $endorsements;
        }
        
        // Statistical proof
        $stats = $this->getProductStatistics($product->id);
        if ($stats['satisfaction_rate'] > 0.9) {
            $proofData['statistics'][] = sprintf(
                "%d%% of customers love this product",
                round($stats['satisfaction_rate'] * 100)
            );
        }
        
        return $proofData;
    }

    public function applyAnchoringBias(array $pricing, array $data): array
    {
        $product = $data['product'];
        $cognitiveBiasService = $this->container->get(CognitiveBiasServiceInterface::class);
        
        // Apply anchoring with original price
        if ($product->compare_price > $product->price) {
            $pricing['display_mode'] = 'anchored';
            $pricing['original_price'] = $product->compare_price;
            $pricing['savings'] = $product->compare_price - $product->price;
            $pricing['savings_percentage'] = round((($pricing['savings'] / $product->compare_price) * 100));
            $pricing['emphasis'] = 'savings';
        }
        
        // Decoy effect for product variants
        if (!empty($data['variants'])) {
            $pricing['decoy_pricing'] = $cognitiveBiasService->applyDecoyEffect($data['variants']);
        }
        
        // Charm pricing
        $pricing['display_price'] = $cognitiveBiasService->applyCharmPricing($product->price);
        
        return $pricing;
    }

    public function triggerLossAversion(array $data): void
    {
        $cart = $data['cart'];
        $user = $data['user'];
        
        $triggerService = $this->container->get(TriggerServiceInterface::class);
        
        // Create loss aversion campaign
        $campaign = $triggerService->createLossAversionCampaign([
            'user_id' => $user->id,
            'cart_value' => $cart->total,
            'items' => $cart->items,
            'abandonment_time' => now()
        ]);
        
        // Schedule recovery emails with increasing urgency
        $this->scheduleRecoverySequence($campaign, $user, $cart);
        
        // Log behavioral data
        $this->trackBehavioralEvent('cart_abandonment', [
            'user_id' => $user->id,
            'cart_value' => $cart->total,
            'session_duration' => $data['session_duration']
        ]);
    }

    public function personalizeEmailPsychology(string $content, array $data): string
    {
        $user = $data['user'];
        $type = $data['email_type'];
        
        $personalizationService = $this->container->get(PersonalizationServiceInterface::class);
        
        // Get user's psychology profile
        $profile = $personalizationService->getUserPsychologyProfile($user->id);
        
        // Apply personalized triggers based on profile
        $personalizedContent = $personalizationService->personalizeContent($content, [
            'profile' => $profile,
            'email_type' => $type,
            'user_preferences' => $this->getUserPreferences($user->id),
            'behavioral_history' => $this->getBehavioralHistory($user->id)
        ]);
        
        // Add personalized subject line variations
        if ($type === 'promotional') {
            $personalizedContent = $this->addPersonalizedSubjectLine($personalizedContent, $profile);
        }
        
        return $personalizedContent;
    }

    public function trackTriggerEffectiveness(array $data): void
    {
        $conversion = $data['conversion'];
        $triggers = $data['applied_triggers'] ?? [];
        
        $triggerService = $this->container->get(TriggerServiceInterface::class);
        
        foreach ($triggers as $trigger) {
            $triggerService->recordTriggerConversion([
                'trigger_id' => $trigger['id'],
                'conversion_value' => $conversion['value'],
                'user_id' => $conversion['user_id'],
                'product_id' => $conversion['product_id'],
                'timestamp' => now()
            ]);
        }
        
        // Update trigger effectiveness scores
        $triggerService->updateEffectivenessScores($triggers);
    }

    // Cron Job Implementations

    public function updateSocialProofData(): void
    {
        $triggerService = $this->container->get(TriggerServiceInterface::class);
        
        // Update recent activity data
        $recentActivity = $this->aggregateRecentActivity();
        $triggerService->updateSocialProofData($recentActivity);
        
        // Update product popularity scores
        $this->updatePopularityScores();
        
        // Generate fresh social proof messages
        $this->refreshSocialProofMessages();
        
        $this->logger->info('Social proof data updated');
    }

    public function analyzeTriggerEffectiveness(): void
    {
        $triggerService = $this->container->get(TriggerServiceInterface::class);
        $analytics = $triggerService->analyzeEffectiveness([
            'period' => '7d',
            'metrics' => ['conversion_rate', 'revenue_impact', 'engagement_rate']
        ]);
        
        // Identify underperforming triggers
        foreach ($analytics['triggers'] as $trigger) {
            if ($trigger['effectiveness_score'] < 0.5) {
                $this->optimizeTrigger($trigger);
            }
        }
        
        // Store analytics results
        $this->storeAnalyticsResults($analytics);
        
        $this->logger->info('Trigger effectiveness analyzed', [
            'total_triggers' => count($analytics['triggers']),
            'average_effectiveness' => $analytics['average_effectiveness']
        ]);
    }

    public function optimizeCampaigns(): void
    {
        $this->logger->info('Starting campaign optimization');
        
        $job = new OptimizeCampaignsJob([
            'optimization_goals' => ['conversion_rate', 'engagement', 'revenue'],
            'methods' => ['a/b_testing', 'multivariate', 'bandit_algorithm']
        ]);
        
        $this->jobs->dispatch($job);
        
        $this->logger->info('Campaign optimization job dispatched');
    }

    public function generatePsychologyReports(): void
    {
        $triggerService = $this->container->get(TriggerServiceInterface::class);
        $cognitiveService = $this->container->get(CognitiveBiasServiceInterface::class);
        
        $report = [
            'trigger_performance' => $triggerService->getPerformanceReport(),
            'bias_effectiveness' => $cognitiveService->getBiasEffectivenessReport(),
            'conversion_impact' => $this->calculateConversionImpact(),
            'recommendations' => $this->generateOptimizationRecommendations(),
            'ab_test_results' => $this->getABTestResults()
        ];
        
        // Save report
        $this->storage->put('psychology/weekly-report-' . date('Y-m-d') . '.json', json_encode($report));
        
        // Send to stakeholders
        $this->notifications->send('marketing', [
            'type' => 'psychology_report',
            'title' => 'Weekly Behavioral Psychology Report',
            'data' => $report
        ]);
        
        $this->logger->info('Generated psychology report');
    }

    // Widget and Dashboard

    public function renderPsychologyDashboard(): string
    {
        $triggerService = $this->container->get(TriggerServiceInterface::class);
        
        $data = [
            'active_triggers' => $triggerService->getActiveTriggerCount(),
            'conversion_lift' => $this->calculateConversionLift(),
            'top_performers' => $triggerService->getTopPerformingTriggers(5),
            'active_campaigns' => $this->getActiveCampaignCount(),
            'recent_conversions' => $this->getRecentConversions(10)
        ];
        
        return view('behavioral-psychology-engine::widgets.dashboard', $data);
    }

    // Helper Methods

    private function generateLimitedTimeMessage(object $trigger, object $product): string
    {
        $endTime = strtotime($trigger->end_time);
        $remaining = $endTime - time();
        
        if ($remaining < 3600) { // Less than 1 hour
            return sprintf("Offer ends in %d minutes!", round($remaining / 60));
        } elseif ($remaining < 86400) { // Less than 1 day
            return sprintf("Only %d hours left!", round($remaining / 3600));
        } else {
            return sprintf("Sale ends in %d days", round($remaining / 86400));
        }
    }

    private function generateViewingMessage(object $product): string
    {
        $viewingCount = $this->getCurrentViewingCount($product->id);
        
        if ($viewingCount > 20) {
            return "🔥 Hot item! {$viewingCount} people viewing now";
        } elseif ($viewingCount > 10) {
            return "👀 {$viewingCount} people are looking at this";
        } elseif ($viewingCount > 5) {
            return "{$viewingCount} others are viewing this product";
        }
        
        return "";
    }

    private function scheduleRecoverySequence(object $campaign, object $user, object $cart): void
    {
        $sequences = [
            ['delay' => '+1 hour', 'type' => 'gentle_reminder'],
            ['delay' => '+24 hours', 'type' => 'loss_aversion'],
            ['delay' => '+72 hours', 'type' => 'final_offer']
        ];
        
        foreach ($sequences as $sequence) {
            $this->jobs->dispatch(new SendRecoveryEmail([
                'campaign_id' => $campaign->id,
                'user_id' => $user->id,
                'cart_id' => $cart->id,
                'type' => $sequence['type']
            ]), $sequence['delay']);
        }
    }

    private function calculateConversionLift(): float
    {
        $baseline = $this->getBaselineConversionRate();
        $current = $this->getCurrentConversionRate();
        
        return (($current - $baseline) / $baseline) * 100;
    }

    private function getConfig(string $key = null, $default = null)
    {
        $config = [
            'urgency_triggers' => [
                'enabled' => true,
                'types' => ['limited_time', 'low_stock', 'flash_sale', 'social_proof']
            ],
            'personalization' => [
                'enabled' => true,
                'profile_factors' => ['purchase_history', 'browsing_behavior', 'demographics']
            ],
            'biases' => [
                'anchoring' => ['enabled' => true, 'strength' => 0.8],
                'social_proof' => ['enabled' => true, 'strength' => 0.9],
                'loss_aversion' => ['enabled' => true, 'strength' => 0.85],
                'authority' => ['enabled' => true, 'strength' => 0.7]
            ],
            'testing' => [
                'ab_testing_enabled' => true,
                'multivariate_testing' => true,
                'significance_threshold' => 0.95
            ]
        ];
        
        return $key ? ($config[$key] ?? $default) : $config;
    }
}