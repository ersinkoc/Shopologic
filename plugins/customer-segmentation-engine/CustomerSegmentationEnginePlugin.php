<?php

namespace CustomerSegmentationEngine;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Hook\HookSystem;
use Shopologic\Core\Container\ContainerInterface;
use Shopologic\Dashboard\WidgetInterface;
use Shopologic\Cron\CronInterface;
use CustomerSegmentationEngine\Services\SegmentationServiceInterface;
use CustomerSegmentationEngine\Services\SegmentationService;
use CustomerSegmentationEngine\Services\BehaviorAnalysisServiceInterface;
use CustomerSegmentationEngine\Services\BehaviorAnalysisService;
use CustomerSegmentationEngine\Repositories\SegmentRepositoryInterface;
use CustomerSegmentationEngine\Repositories\SegmentRepository;
use CustomerSegmentationEngine\Controllers\SegmentationApiController;
use CustomerSegmentationEngine\Jobs\RecalculateSegmentsJob;

/**
 * Advanced Customer Segmentation Engine Plugin
 * 
 * Intelligent customer segmentation using RFM analysis, behavioral patterns,
 * machine learning clustering, and predictive lifetime value modeling
 */
class CustomerSegmentationEnginePlugin extends AbstractPlugin implements WidgetInterface, CronInterface
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
        $this->container->bind(SegmentationServiceInterface::class, SegmentationService::class);
        $this->container->bind(BehaviorAnalysisServiceInterface::class, BehaviorAnalysisService::class);
        $this->container->bind(SegmentRepositoryInterface::class, SegmentRepository::class);

        $this->container->singleton(SegmentationService::class, function(ContainerInterface $container) {
            return new SegmentationService(
                $container->get(BehaviorAnalysisServiceInterface::class),
                $container->get(SegmentRepositoryInterface::class),
                $container->get('cache'),
                $this->getConfig('segmentation_rules', [])
            );
        });

        $this->container->singleton(BehaviorAnalysisService::class, function(ContainerInterface $container) {
            return new BehaviorAnalysisService(
                $container->get('database'),
                $container->get('cache'),
                $this->getConfig('behavior_weights', [])
            );
        });
    }

    protected function registerHooks(): void
    {
        // Customer lifecycle events
        HookSystem::addAction('customer.registered', [$this, 'assignInitialSegment'], 10);
        HookSystem::addAction('order.completed', [$this, 'updateCustomerBehavior'], 10);
        HookSystem::addAction('customer.login', [$this, 'trackEngagement'], 10);
        HookSystem::addAction('product.reviewed', [$this, 'trackAdvocacyBehavior'], 10);

        // Segmentation-based personalization
        HookSystem::addFilter('product.recommendations', [$this, 'personalizeBySegment'], 15);
        HookSystem::addFilter('email.template', [$this, 'customizeEmailBySegment'], 10);
        HookSystem::addFilter('promotion.eligibility', [$this, 'filterPromotionsBySegment'], 10);
        HookSystem::addFilter('pricing.discount', [$this, 'applySegmentPricing'], 10);

        // Marketing automation
        HookSystem::addAction('segment.customer_moved', [$this, 'triggerSegmentAction'], 10);
        HookSystem::addAction('segment.at_risk_detected', [$this, 'triggerRetentionCampaign'], 5);
        HookSystem::addAction('segment.high_value_identified', [$this, 'triggerVipTreatment'], 5);

        // Analytics and reporting
        HookSystem::addFilter('analytics.customer_metrics', [$this, 'addSegmentMetrics'], 20);
        HookSystem::addAction('admin.customer.profile', [$this, 'displaySegmentInfo'], 15);
    }

    protected function registerApiEndpoints(): void
    {
        $this->router->group(['prefix' => 'api/v1/segmentation'], function($router) {
            $router->get('/customer/{customer_id}/segment', [SegmentationApiController::class, 'getCustomerSegment']);
            $router->get('/segments', [SegmentationApiController::class, 'getAllSegments']);
            $router->post('/segments', [SegmentationApiController::class, 'createSegment']);
            $router->put('/segments/{segment_id}', [SegmentationApiController::class, 'updateSegment']);
            $router->get('/segments/{segment_id}/customers', [SegmentationApiController::class, 'getSegmentCustomers']);
            $router->post('/recalculate', [SegmentationApiController::class, 'triggerRecalculation']);
            $router->get('/analytics', [SegmentationApiController::class, 'getSegmentAnalytics']);
            $router->post('/predict-ltv', [SegmentationApiController::class, 'predictCustomerLTV']);
            $router->get('/churn-risk', [SegmentationApiController::class, 'getChurnRiskAnalysis']);
        });

        // GraphQL schema extension
        $this->graphql->extendSchema([
            'Query' => [
                'customerSegment' => [
                    'type' => 'CustomerSegment',
                    'args' => ['customerId' => 'ID!'],
                    'resolve' => [$this, 'resolveCustomerSegment']
                ],
                'segmentPerformance' => [
                    'type' => '[SegmentMetrics]',
                    'args' => ['period' => 'String'],
                    'resolve' => [$this, 'resolveSegmentPerformance']
                ]
            ],
            'Mutation' => [
                'assignCustomerToSegment' => [
                    'type' => 'Boolean',
                    'args' => [
                        'customerId' => 'ID!',
                        'segmentId' => 'ID!',
                        'reason' => 'String'
                    ],
                    'resolve' => [$this, 'resolveAssignCustomerToSegment']
                ]
            ]
        ]);
    }

    protected function registerCronJobs(): void
    {
        // Recalculate segments daily at 2 AM
        $this->cron->schedule('0 2 * * *', [$this, 'recalculateAllSegments']);
        
        // Update RFM scores every 6 hours
        $this->cron->schedule('0 */6 * * *', [$this, 'updateRfmScores']);
        
        // Detect churn risk weekly
        $this->cron->schedule('0 9 * * SUN', [$this, 'detectChurnRisk']);
        
        // Update lifetime value predictions monthly
        $this->cron->schedule('0 3 1 * *', [$this, 'updateLifetimeValuePredictions']);
        
        // Generate segment performance reports weekly
        $this->cron->schedule('0 10 * * MON', [$this, 'generateSegmentReports']);
    }

    public function getDashboardWidget(): array
    {
        return [
            'id' => 'customer-segmentation-widget',
            'title' => 'Customer Segmentation Dashboard',
            'position' => 'main',
            'priority' => 20,
            'render' => [$this, 'renderSegmentationDashboard']
        ];
    }

    protected function registerPermissions(): void
    {
        $this->permissions->register([
            'segmentation.view_segments' => 'View customer segments',
            'segmentation.manage_segments' => 'Create and manage segments',
            'segmentation.view_analytics' => 'View segmentation analytics',
            'segmentation.assign_customers' => 'Manually assign customers to segments',
            'segmentation.configure_rules' => 'Configure segmentation rules'
        ]);
    }

    // Hook Implementations

    public function assignInitialSegment(array $data): void
    {
        $customer = $data['customer'];
        $segmentationService = $this->container->get(SegmentationServiceInterface::class);
        
        // New customers start in 'New Customer' segment
        $initialSegment = $segmentationService->getSegmentByKey('new_customer');
        
        if ($initialSegment) {
            $segmentationService->assignCustomerToSegment($customer->id, $initialSegment->id, [
                'reason' => 'initial_assignment',
                'source' => 'registration',
                'timestamp' => now()
            ]);
        }
        
        // Track registration channel for future segmentation
        $this->trackRegistrationSource($customer, $data);
    }

    public function updateCustomerBehavior(array $data): void
    {
        $order = $data['order'];
        $behaviorService = $this->container->get(BehaviorAnalysisServiceInterface::class);
        $segmentationService = $this->container->get(SegmentationServiceInterface::class);
        
        // Update RFM metrics
        $rfmMetrics = $behaviorService->calculateRfmMetrics($order->customer_id);
        
        // Calculate behavioral scores
        $behaviorScores = $behaviorService->calculateBehaviorScores($order->customer_id, [
            'purchase_frequency' => true,
            'average_order_value' => true,
            'category_affinity' => true,
            'brand_loyalty' => true,
            'price_sensitivity' => true
        ]);
        
        // Check if segment reassignment is needed
        $currentSegment = $segmentationService->getCustomerSegment($order->customer_id);
        $suggestedSegment = $segmentationService->suggestSegment($order->customer_id, $rfmMetrics, $behaviorScores);
        
        if ($currentSegment->id !== $suggestedSegment->id) {
            $segmentationService->moveCustomerToSegment(
                $order->customer_id,
                $suggestedSegment->id,
                [
                    'reason' => 'behavior_change',
                    'trigger' => 'order_completed',
                    'previous_segment' => $currentSegment->id,
                    'metrics' => $rfmMetrics
                ]
            );
            
            // Trigger segment movement hook
            HookSystem::doAction('segment.customer_moved', [
                'customer_id' => $order->customer_id,
                'from_segment' => $currentSegment,
                'to_segment' => $suggestedSegment,
                'trigger_order' => $order
            ]);
        }
    }

    public function personalizeBySegment(array $recommendations, array $data): array
    {
        $customerId = $data['customer_id'];
        if (!$customerId) return $recommendations;
        
        $segmentationService = $this->container->get(SegmentationServiceInterface::class);
        $segment = $segmentationService->getCustomerSegment($customerId);
        
        if (!$segment) return $recommendations;
        
        // Apply segment-specific recommendation rules
        $segmentRules = $segment->recommendation_rules ?? [];
        
        // Filter recommendations based on segment preferences
        $recommendations = $this->filterRecommendationsBySegment($recommendations, $segmentRules);
        
        // Adjust recommendation scores based on segment behavior
        $recommendations = $this->adjustRecommendationScores($recommendations, $segment);
        
        // Add segment-specific products
        $segmentProducts = $this->getSegmentSpecificProducts($segment);
        $recommendations = array_merge($recommendations, $segmentProducts);
        
        return $recommendations;
    }

    public function applySegmentPricing(float $discount, array $data): float
    {
        $customerId = $data['customer_id'] ?? null;
        if (!$customerId) return $discount;
        
        $segmentationService = $this->container->get(SegmentationServiceInterface::class);
        $segment = $segmentationService->getCustomerSegment($customerId);
        
        if (!$segment) return $discount;
        
        // Apply segment-specific pricing rules
        $segmentPricing = $segment->pricing_rules ?? [];
        
        if (isset($segmentPricing['discount_multiplier'])) {
            $discount *= $segmentPricing['discount_multiplier'];
        }
        
        if (isset($segmentPricing['max_discount'])) {
            $discount = min($discount, $segmentPricing['max_discount']);
        }
        
        if (isset($segmentPricing['min_discount'])) {
            $discount = max($discount, $segmentPricing['min_discount']);
        }
        
        return $discount;
    }

    public function triggerSegmentAction(array $data): void
    {
        $customerId = $data['customer_id'];
        $fromSegment = $data['from_segment'];
        $toSegment = $data['to_segment'];
        
        // Log segment movement
        $this->logger->info('Customer segment changed', [
            'customer_id' => $customerId,
            'from' => $fromSegment->name,
            'to' => $toSegment->name
        ]);
        
        // Trigger segment-specific actions
        $this->executeSegmentActions($customerId, $toSegment);
        
        // Update customer profile with segment history
        $this->updateSegmentHistory($customerId, $fromSegment, $toSegment);
    }

    public function triggerRetentionCampaign(array $data): void
    {
        $customerId = $data['customer_id'];
        $churnRisk = $data['churn_risk'];
        
        // Create retention campaign based on risk level
        $campaignType = $this->selectRetentionCampaign($churnRisk);
        
        $this->notifications->send('marketing', [
            'type' => 'retention_campaign',
            'customer_id' => $customerId,
            'campaign_type' => $campaignType,
            'churn_risk' => $churnRisk,
            'priority' => $churnRisk > 0.8 ? 'high' : 'medium'
        ]);
    }

    public function triggerVipTreatment(array $data): void
    {
        $customerId = $data['customer_id'];
        $lifetimeValue = $data['lifetime_value'];
        
        // Activate VIP benefits
        $this->activateVipBenefits($customerId);
        
        // Notify customer service team
        $this->notifications->send('customer_service', [
            'type' => 'vip_customer_alert',
            'customer_id' => $customerId,
            'lifetime_value' => $lifetimeValue,
            'special_treatment' => true
        ]);
    }

    // Cron Job Implementations

    public function recalculateAllSegments(): void
    {
        $this->logger->info('Starting segment recalculation for all customers');
        
        $job = new RecalculateSegmentsJob([
            'scope' => 'all',
            'force_recalculation' => false,
            'update_predictions' => true
        ]);
        
        $this->jobs->dispatch($job);
        
        $this->logger->info('Segment recalculation job dispatched');
    }

    public function updateRfmScores(): void
    {
        $behaviorService = $this->container->get(BehaviorAnalysisServiceInterface::class);
        $updated = $behaviorService->updateAllRfmScores();
        
        $this->logger->info("Updated RFM scores for {$updated} customers");
    }

    public function detectChurnRisk(): void
    {
        $behaviorService = $this->container->get(BehaviorAnalysisServiceInterface::class);
        $atRiskCustomers = $behaviorService->detectChurnRisk();
        
        foreach ($atRiskCustomers as $customer) {
            HookSystem::doAction('segment.at_risk_detected', [
                'customer_id' => $customer['customer_id'],
                'churn_risk' => $customer['churn_probability'],
                'risk_factors' => $customer['risk_factors']
            ]);
        }
        
        $this->logger->info("Detected {$count($atRiskCustomers)} at-risk customers");
    }

    public function updateLifetimeValuePredictions(): void
    {
        $segmentationService = $this->container->get(SegmentationServiceInterface::class);
        $updated = $segmentationService->updateLifetimeValuePredictions();
        
        $this->logger->info("Updated LTV predictions for {$updated} customers");
    }

    public function generateSegmentReports(): void
    {
        $segmentationService = $this->container->get(SegmentationServiceInterface::class);
        $report = $segmentationService->generatePerformanceReport();
        
        // Save report
        $this->storage->put(
            'segmentation/weekly-report-' . date('Y-m-d') . '.json',
            json_encode($report)
        );
        
        // Send to stakeholders
        $this->notifications->send('management', [
            'type' => 'segmentation_report',
            'title' => 'Weekly Customer Segmentation Report',
            'data' => $report
        ]);
        
        $this->logger->info('Generated weekly segmentation report');
    }

    // Widget and Dashboard

    public function renderSegmentationDashboard(): string
    {
        $segmentationService = $this->container->get(SegmentationServiceInterface::class);
        $behaviorService = $this->container->get(BehaviorAnalysisServiceInterface::class);
        
        $stats = [
            'total_segments' => $segmentationService->getActiveSegmentCount(),
            'segment_distribution' => $segmentationService->getSegmentDistribution(),
            'high_value_customers' => $segmentationService->getHighValueCustomerCount(),
            'at_risk_customers' => $behaviorService->getAtRiskCustomerCount(),
            'segment_performance' => $segmentationService->getSegmentPerformanceMetrics()
        ];
        
        return view('customer-segmentation-engine::widgets.dashboard', $stats);
    }

    public function displaySegmentInfo(array $data): void
    {
        $customer = $data['customer'];
        $segmentationService = $this->container->get(SegmentationServiceInterface::class);
        
        $segmentInfo = [
            'current_segment' => $segmentationService->getCustomerSegment($customer->id),
            'segment_history' => $segmentationService->getSegmentHistory($customer->id),
            'rfm_scores' => $segmentationService->getRfmScores($customer->id),
            'predicted_ltv' => $segmentationService->getPredictedLifetimeValue($customer->id),
            'churn_risk' => $segmentationService->getChurnRisk($customer->id)
        ];
        
        echo view('customer-segmentation-engine::admin.customer-segment-info', $segmentInfo);
    }

    // Helper Methods

    private function trackRegistrationSource($customer, array $data): void
    {
        $source = $data['source'] ?? 'direct';
        $campaign = $data['campaign'] ?? null;
        
        $this->database->table('customer_acquisition_data')->insert([
            'customer_id' => $customer->id,
            'source' => $source,
            'campaign' => $campaign,
            'registered_at' => $customer->created_at
        ]);
    }

    private function filterRecommendationsBySegment(array $recommendations, array $rules): array
    {
        if (empty($rules['category_preferences'])) {
            return $recommendations;
        }
        
        $preferredCategories = $rules['category_preferences'];
        
        return array_filter($recommendations, function($product) use ($preferredCategories) {
            return in_array($product->category_id, $preferredCategories);
        });
    }

    private function adjustRecommendationScores(array $recommendations, object $segment): array
    {
        $boostFactor = $segment->recommendation_boost ?? 1.0;
        
        foreach ($recommendations as &$product) {
            if (isset($product->recommendation_score)) {
                $product->recommendation_score *= $boostFactor;
            }
        }
        
        return $recommendations;
    }

    private function getSegmentSpecificProducts(object $segment): array
    {
        if (empty($segment->featured_products)) {
            return [];
        }
        
        return $this->database->table('products')
            ->whereIn('id', $segment->featured_products)
            ->where('is_active', true)
            ->limit(5)
            ->get()
            ->toArray();
    }

    private function executeSegmentActions(int $customerId, object $segment): void
    {
        $actions = $segment->automated_actions ?? [];
        
        foreach ($actions as $action) {
            switch ($action['type']) {
                case 'email_campaign':
                    $this->triggerEmailCampaign($customerId, $action['campaign_id']);
                    break;
                    
                case 'discount_code':
                    $this->generatePersonalizedDiscount($customerId, $action['discount_params']);
                    break;
                    
                case 'loyalty_points':
                    $this->awardLoyaltyBonus($customerId, $action['points']);
                    break;
            }
        }
    }

    private function updateSegmentHistory(int $customerId, object $fromSegment, object $toSegment): void
    {
        $this->database->table('customer_segment_history')->insert([
            'customer_id' => $customerId,
            'from_segment_id' => $fromSegment->id,
            'to_segment_id' => $toSegment->id,
            'moved_at' => now(),
            'trigger' => 'automatic'
        ]);
    }

    private function selectRetentionCampaign(float $churnRisk): string
    {
        if ($churnRisk > 0.8) {
            return 'urgent_retention';
        } elseif ($churnRisk > 0.6) {
            return 'high_risk_retention';
        } elseif ($churnRisk > 0.4) {
            return 'moderate_risk_retention';
        } else {
            return 'engagement_campaign';
        }
    }

    private function activateVipBenefits(int $customerId): void
    {
        // Implement VIP benefit activation
        $this->database->table('customer_vip_status')->updateOrInsert(
            ['customer_id' => $customerId],
            [
                'is_vip' => true,
                'activated_at' => now(),
                'benefits' => json_encode([
                    'free_shipping',
                    'priority_support',
                    'exclusive_offers',
                    'early_access'
                ])
            ]
        );
    }

    private function getConfig(string $key = null, $default = null)
    {
        $config = [
            'segmentation_rules' => [
                'rfm_weights' => ['recency' => 0.3, 'frequency' => 0.3, 'monetary' => 0.4],
                'behavior_weights' => ['engagement' => 0.25, 'loyalty' => 0.25, 'value' => 0.5],
                'update_frequency' => 'daily',
                'min_data_points' => 3
            ],
            'behavior_weights' => [
                'purchase_frequency' => 0.3,
                'average_order_value' => 0.25,
                'engagement_score' => 0.2,
                'brand_loyalty' => 0.15,
                'recommendation_acceptance' => 0.1
            ]
        ];
        
        return $key ? ($config[$key] ?? $default) : $config;
    }
}