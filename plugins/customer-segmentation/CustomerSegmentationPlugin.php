<?php
namespace CustomerSegmentation;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Customer Segmentation Engine Plugin
 * 
 * Automatic customer segmentation with behavioral targeting and lifecycle marketing
 */
class CustomerSegmentationPlugin extends AbstractPlugin
{
    private $segmentEngine;
    private $behaviorAnalyzer;
    private $campaignManager;
    private $predictiveModeler;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->scheduleSegmentUpdates();
    }

    private function registerServices(): void
    {
        $this->segmentEngine = new Services\SegmentEngine($this->api);
        $this->behaviorAnalyzer = new Services\BehaviorAnalyzer($this->api);
        $this->campaignManager = new Services\CampaignManager($this->api);
        $this->predictiveModeler = new Services\PredictiveModeler($this->api);
    }

    private function registerHooks(): void
    {
        // Customer lifecycle events
        Hook::addAction('customer.registered', [$this, 'assignInitialSegments'], 10, 1);
        Hook::addAction('customer.login', [$this, 'updateLastActivity'], 10, 1);
        Hook::addAction('order.completed', [$this, 'analyzeOrderBehavior'], 10, 1);
        Hook::addAction('product.viewed', [$this, 'trackProductInterest'], 10, 2);
        Hook::addAction('cart.abandoned', [$this, 'trackCartAbandonment'], 10, 2);
        
        // Behavioral tracking
        Hook::addAction('email.opened', [$this, 'trackEmailEngagement'], 10, 2);
        Hook::addAction('email.clicked', [$this, 'trackEmailClick'], 10, 3);
        Hook::addAction('customer.unsubscribed', [$this, 'handleUnsubscribe'], 10, 1);
        
        // Marketing integration
        Hook::addFilter('email.recipients', [$this, 'filterBySegment'], 10, 2);
        Hook::addFilter('product.recommendations', [$this, 'personalizeBySegment'], 10, 2);
        Hook::addFilter('pricing.discount', [$this, 'applySegmentDiscounts'], 10, 2);
        
        // Admin interface
        Hook::addAction('admin.customers.dashboard', [$this, 'addSegmentationWidget'], 10, 1);
        Hook::addFilter('admin.customer.details', [$this, 'showCustomerSegments'], 10, 2);
    }

    public function assignInitialSegments($customer): void
    {
        if (!$this->getConfig('auto_segment', true)) {
            return;
        }

        // New customer segment
        $this->segmentEngine->addToSegment($customer->id, 'new_customers', [
            'registration_date' => date('Y-m-d H:i:s'),
            'source' => $customer->registration_source ?? 'organic'
        ]);

        // Geographic segment
        if ($customer->country) {
            $geoSegment = $this->segmentEngine->getGeographicSegment($customer->country, $customer->state);
            $this->segmentEngine->addToSegment($customer->id, $geoSegment);
        }

        // Acquisition channel segment
        $channel = $this->detectAcquisitionChannel($customer);
        if ($channel) {
            $this->segmentEngine->addToSegment($customer->id, "channel_{$channel}");
        }

        // Predictive segments
        if ($this->getConfig('enable_predictive_segments', true)) {
            $this->assignPredictiveSegments($customer);
        }
    }

    public function updateLastActivity($customer): void
    {
        $this->behaviorAnalyzer->updateActivity($customer->id, 'login', [
            'timestamp' => time(),
            'ip' => $this->api->request()->ip(),
            'device' => $this->api->request()->device()
        ]);

        // Check for dormant customer reactivation
        $lastActivity = $this->behaviorAnalyzer->getLastActivity($customer->id);
        if ($lastActivity && (time() - $lastActivity) > 2592000) { // 30 days
            $this->segmentEngine->moveToSegment($customer->id, 'dormant_customers', 'reactivated_customers');
            $this->triggerReactivationCampaign($customer);
        }
    }

    public function analyzeOrderBehavior($order): void
    {
        $customerId = $order->customer_id;
        
        // Update purchase metrics
        $metrics = $this->behaviorAnalyzer->updatePurchaseMetrics($customerId, $order);
        
        // RFM Analysis (Recency, Frequency, Monetary)
        $rfmScore = $this->calculateRFMScore($customerId, $metrics);
        $this->updateRFMSegments($customerId, $rfmScore);
        
        // Lifecycle stage progression
        $this->updateLifecycleStage($customerId, $metrics);
        
        // Product category preferences
        $this->updateCategoryPreferences($customerId, $order->items);
        
        // Spending behavior segments
        $this->updateSpendingSegments($customerId, $metrics);
        
        // Predictive churn analysis
        if ($this->getConfig('enable_predictive_segments', true)) {
            $churnRisk = $this->predictiveModeler->calculateChurnRisk($customerId, $metrics);
            if ($churnRisk > 0.7) {
                $this->segmentEngine->addToSegment($customerId, 'high_churn_risk');
                $this->triggerRetentionCampaign($customerId);
            }
        }
    }

    public function trackProductInterest($customer, $product): void
    {
        if (!$customer) return;

        $this->behaviorAnalyzer->trackInterest($customer->id, 'product_view', [
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'price' => $product->price,
            'brand' => $product->brand
        ]);

        // Update interest-based segments
        $interests = $this->behaviorAnalyzer->getCustomerInterests($customer->id);
        $this->updateInterestSegments($customer->id, $interests);
    }

    public function trackCartAbandonment($customerId, $cart): void
    {
        $this->segmentEngine->addToSegment($customerId, 'cart_abandoners', [
            'cart_value' => $cart->total,
            'items_count' => count($cart->items),
            'abandoned_at' => date('Y-m-d H:i:s')
        ]);

        // Analyze abandonment patterns
        $abandonmentCount = $this->behaviorAnalyzer->getAbandonmentCount($customerId);
        if ($abandonmentCount >= 3) {
            $this->segmentEngine->addToSegment($customerId, 'frequent_abandoners');
        }
    }

    public function trackEmailEngagement($customerId, $emailId): void
    {
        $this->behaviorAnalyzer->trackEngagement($customerId, 'email_open', [
            'email_id' => $emailId,
            'opened_at' => date('Y-m-d H:i:s')
        ]);

        $engagementScore = $this->behaviorAnalyzer->getEngagementScore($customerId);
        $this->updateEngagementSegments($customerId, $engagementScore);
    }

    public function filterBySegment($recipients, $campaign): array
    {
        if (!isset($campaign['target_segments'])) {
            return $recipients;
        }

        $segmentMembers = $this->segmentEngine->getSegmentMembers($campaign['target_segments']);
        
        return array_filter($recipients, function($recipient) use ($segmentMembers) {
            return in_array($recipient['customer_id'], $segmentMembers);
        });
    }

    public function personalizeBySegment($recommendations, $customer): array
    {
        if (!$customer) return $recommendations;

        $segments = $this->segmentEngine->getCustomerSegments($customer->id);
        
        // Apply segment-specific boosting
        foreach ($segments as $segment) {
            $boostRules = $this->getSegmentBoostRules($segment->name);
            $recommendations = $this->applyBoostRules($recommendations, $boostRules);
        }

        return $recommendations;
    }

    public function applySegmentDiscounts($discount, $customer): float
    {
        if (!$customer) return $discount;

        $segments = $this->segmentEngine->getCustomerSegments($customer->id);
        $maxDiscount = $discount;

        foreach ($segments as $segment) {
            $segmentDiscount = $this->getSegmentDiscount($segment->name);
            if ($segmentDiscount > $maxDiscount) {
                $maxDiscount = $segmentDiscount;
            }
        }

        return $maxDiscount;
    }

    public function addSegmentationWidget($widgets): array
    {
        $widgets['customer_segments'] = [
            'title' => 'Customer Segments',
            'template' => 'segmentation/dashboard-widget',
            'data' => $this->getSegmentationOverview()
        ];

        return $widgets;
    }

    public function showCustomerSegments($details, $customer): string
    {
        $segments = $this->segmentEngine->getCustomerSegments($customer->id);
        $behaviorScore = $this->behaviorAnalyzer->getBehaviorScore($customer->id);
        $predictions = $this->predictiveModeler->getCustomerPredictions($customer->id);

        $segmentWidget = $this->api->view('segmentation/customer-segments', [
            'segments' => $segments,
            'behavior_score' => $behaviorScore,
            'predictions' => $predictions,
            'segment_history' => $this->segmentEngine->getSegmentHistory($customer->id)
        ]);

        return $details . $segmentWidget;
    }

    private function calculateRFMScore($customerId, $metrics): array
    {
        // Recency score (days since last purchase)
        $recency = (time() - strtotime($metrics['last_purchase_date'])) / 86400;
        $recencyScore = $this->scoreRecency($recency);
        
        // Frequency score (number of purchases)
        $frequencyScore = $this->scoreFrequency($metrics['purchase_count']);
        
        // Monetary score (total spent)
        $monetaryScore = $this->scoreMonetary($metrics['total_spent']);

        return [
            'recency' => $recencyScore,
            'frequency' => $frequencyScore,
            'monetary' => $monetaryScore,
            'composite' => ($recencyScore + $frequencyScore + $monetaryScore) / 3
        ];
    }

    private function updateRFMSegments($customerId, $rfmScore): void
    {
        $segment = $this->determineRFMSegment($rfmScore);
        $this->segmentEngine->updateSegment($customerId, 'rfm_segment', $segment);

        // Special handling for VIP customers
        if ($segment === 'champions' || $segment === 'loyal_customers') {
            $this->segmentEngine->addToSegment($customerId, 'vip_customers');
        }
    }

    private function updateLifecycleStage($customerId, $metrics): void
    {
        $currentStage = $this->segmentEngine->getCurrentLifecycleStage($customerId);
        $newStage = $this->determineLifecycleStage($metrics);

        if ($currentStage !== $newStage) {
            $this->segmentEngine->updateLifecycleStage($customerId, $newStage);
            $this->triggerLifecycleCampaign($customerId, $currentStage, $newStage);
        }
    }

    private function updateSpendingSegments($customerId, $metrics): void
    {
        $avgOrderValue = $metrics['total_spent'] / $metrics['purchase_count'];
        
        if ($avgOrderValue > 500) {
            $this->segmentEngine->addToSegment($customerId, 'high_value_customers');
        } elseif ($avgOrderValue > 100) {
            $this->segmentEngine->addToSegment($customerId, 'medium_value_customers');
        } else {
            $this->segmentEngine->addToSegment($customerId, 'budget_conscious_customers');
        }
    }

    private function assignPredictiveSegments($customer): void
    {
        // Predict customer lifetime value
        $predictedCLV = $this->predictiveModeler->predictCLV($customer);
        if ($predictedCLV > 1000) {
            $this->segmentEngine->addToSegment($customer->id, 'high_potential_customers');
        }

        // Predict product preferences
        $preferences = $this->predictiveModeler->predictPreferences($customer);
        foreach ($preferences as $category => $score) {
            if ($score > 0.7) {
                $this->segmentEngine->addToSegment($customer->id, "likely_buyer_{$category}");
            }
        }
    }

    private function getSegmentationOverview(): array
    {
        return [
            'total_segments' => $this->segmentEngine->getTotalSegments(),
            'largest_segments' => $this->segmentEngine->getLargestSegments(5),
            'growth_rate' => $this->segmentEngine->getSegmentGrowthRate(),
            'active_campaigns' => $this->campaignManager->getActiveCampaignCount(),
            'segment_performance' => $this->getSegmentPerformance()
        ];
    }

    private function scheduleSegmentUpdates(): void
    {
        $frequency = $this->getConfig('segment_update_frequency', 'daily');
        
        switch ($frequency) {
            case 'hourly':
                $schedule = '0 * * * *';
                break;
            case 'weekly':
                $schedule = '0 2 * * 0';
                break;
            default: // daily
                $schedule = '0 2 * * *';
        }

        $this->api->scheduler()->addJob('update_segments', $schedule, function() {
            $this->segmentEngine->updateAllSegments();
            $this->predictiveModeler->retrainModels();
        });

        // Real-time segment updates for critical events
        $this->api->scheduler()->addJob('realtime_segments', '*/15 * * * *', function() {
            $this->segmentEngine->processRealtimeUpdates();
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->get('/segments', 'Controllers\SegmentController@index');
        $this->api->router()->post('/segments/create', 'Controllers\SegmentController@create');
        $this->api->router()->get('/segments/{id}/customers', 'Controllers\SegmentController@getCustomers');
        $this->api->router()->post('/segments/analyze', 'Controllers\SegmentController@analyze');
        $this->api->router()->post('/segments/campaign', 'Controllers\SegmentController@createCampaign');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultSegments();
        $this->initializePredictiveModels();
    }

    private function createDefaultSegments(): void
    {
        $defaultSegments = [
            ['name' => 'new_customers', 'description' => 'Customers registered in last 30 days'],
            ['name' => 'vip_customers', 'description' => 'High-value loyal customers'],
            ['name' => 'at_risk', 'description' => 'Customers showing signs of churn'],
            ['name' => 'champions', 'description' => 'Best customers - recent, frequent, high spenders'],
            ['name' => 'dormant_customers', 'description' => 'Haven\'t purchased in 90+ days'],
            ['name' => 'cart_abandoners', 'description' => 'Abandoned cart without purchase'],
            ['name' => 'window_shoppers', 'description' => 'Browse but rarely purchase'],
            ['name' => 'loyal_customers', 'description' => 'Consistent repeat purchasers']
        ];

        foreach ($defaultSegments as $segment) {
            $this->api->database()->table('customer_segments')->insert($segment);
        }
    }

    private function initializePredictiveModels(): void
    {
        $this->predictiveModeler->initializeModels([
            'clv_prediction',
            'churn_prediction',
            'next_purchase_prediction',
            'category_affinity'
        ]);
    }
}