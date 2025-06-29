<?php
namespace JourneyAnalytics;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Customer Journey Analytics Plugin
 * 
 * Advanced customer behavior tracking, journey mapping, and conversion analysis
 */
class JourneyAnalyticsPlugin extends AbstractPlugin
{
    private $journeyTracker;
    private $touchpointAnalyzer;
    private $funnelEngine;
    private $heatmapGenerator;
    private $segmentBuilder;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->initializeTracking();
    }

    private function registerServices(): void
    {
        $this->journeyTracker = new Services\JourneyTracker($this->api);
        $this->touchpointAnalyzer = new Services\TouchpointAnalyzer($this->api);
        $this->funnelEngine = new Services\FunnelEngine($this->api);
        $this->heatmapGenerator = new Services\HeatmapGenerator($this->api);
        $this->segmentBuilder = new Services\BehaviorSegmentBuilder($this->api);
    }

    private function registerHooks(): void
    {
        // Page tracking
        Hook::addAction('page.viewed', [$this, 'trackPageView'], 5, 1);
        Hook::addAction('page.scrolled', [$this, 'trackScrollDepth'], 10, 2);
        Hook::addAction('page.time_spent', [$this, 'trackTimeOnPage'], 10, 2);
        
        // User interactions
        Hook::addAction('element.clicked', [$this, 'trackClick'], 10, 2);
        Hook::addAction('form.interacted', [$this, 'trackFormInteraction'], 10, 2);
        Hook::addAction('search.performed', [$this, 'trackSearch'], 10, 2);
        
        // E-commerce events
        Hook::addAction('product.viewed', [$this, 'trackProductView'], 10, 2);
        Hook::addAction('cart.item_added', [$this, 'trackAddToCart'], 10, 2);
        Hook::addAction('checkout.started', [$this, 'trackCheckoutStart'], 10, 1);
        Hook::addAction('checkout.step_completed', [$this, 'trackCheckoutStep'], 10, 2);
        Hook::addAction('order.completed', [$this, 'trackPurchase'], 10, 1);
        
        // Customer journey events
        Hook::addAction('customer.searched', [$this, 'trackSearchBehavior'], 10, 2);
        Hook::addAction('customer.filtered', [$this, 'trackFilterUsage'], 10, 2);
        Hook::addAction('customer.compared', [$this, 'trackProductComparison'], 10, 2);
        
        // Session management
        Hook::addAction('session.started', [$this, 'startJourneySession'], 5, 1);
        Hook::addAction('session.ended', [$this, 'endJourneySession'], 10, 1);
        
        // Analytics UI
        Hook::addAction('frontend.head', [$this, 'injectTrackingScript'], 5);
        Hook::addAction('admin.analytics.dashboard', [$this, 'journeyDashboard'], 10);
    }

    public function trackPageView($pageData): void
    {
        $sessionId = $this->getOrCreateSession();
        
        $touchpoint = [
            'session_id' => $sessionId,
            'customer_id' => $this->api->auth()->user()?->id,
            'touchpoint_type' => 'page_view',
            'page_url' => $pageData['url'],
            'page_title' => $pageData['title'],
            'page_type' => $pageData['type'],
            'referrer' => $pageData['referrer'] ?? null,
            'device_type' => $this->detectDeviceType(),
            'browser' => $this->detectBrowser(),
            'timestamp' => microtime(true),
            'metadata' => [
                'viewport_width' => $pageData['viewport_width'] ?? null,
                'viewport_height' => $pageData['viewport_height'] ?? null,
                'screen_resolution' => $pageData['screen_resolution'] ?? null
            ]
        ];
        
        $this->journeyTracker->recordTouchpoint($touchpoint);
        
        // Update journey stage
        $this->updateJourneyStage($sessionId, $pageData);
        
        // Generate heatmap data if enabled
        if ($this->getConfig('enable_heatmaps', true)) {
            $this->heatmapGenerator->initializePageTracking($pageData['url']);
        }
    }

    public function trackClick($element, $context): void
    {
        $clickData = [
            'session_id' => $this->getCurrentSession(),
            'element_type' => $element['type'],
            'element_id' => $element['id'] ?? null,
            'element_class' => $element['class'] ?? null,
            'element_text' => $element['text'] ?? null,
            'click_coordinates' => [
                'x' => $element['x'],
                'y' => $element['y'],
                'viewport_relative' => true
            ],
            'page_url' => $context['page_url'],
            'timestamp' => microtime(true)
        ];
        
        $this->journeyTracker->recordInteraction('click', $clickData);
        
        // Update heatmap data
        if ($this->getConfig('enable_heatmaps', true)) {
            $this->heatmapGenerator->recordClick($clickData);
        }
        
        // Detect rage clicks
        $this->detectRageClicks($clickData);
    }

    public function trackProductView($customer, $product): void
    {
        $touchpoint = [
            'session_id' => $this->getCurrentSession(),
            'customer_id' => $customer?->id,
            'touchpoint_type' => 'product_view',
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_category' => $product->category_name,
            'product_price' => $product->price,
            'view_source' => $this->determineViewSource(),
            'timestamp' => microtime(true)
        ];
        
        $this->journeyTracker->recordTouchpoint($touchpoint);
        
        // Update product interest profile
        $this->updateProductInterestProfile($customer?->id, $product);
        
        // Track in funnel
        $this->funnelEngine->recordStep('product_discovery', [
            'product_id' => $product->id,
            'session_id' => $this->getCurrentSession()
        ]);
    }

    public function trackAddToCart($customer, $cartItem): void
    {
        $touchpoint = [
            'session_id' => $this->getCurrentSession(),
            'customer_id' => $customer?->id,
            'touchpoint_type' => 'add_to_cart',
            'product_id' => $cartItem->product_id,
            'quantity' => $cartItem->quantity,
            'cart_value' => $this->getCartValue(),
            'time_to_add' => $this->calculateTimeToAction('product_view', 'add_to_cart'),
            'timestamp' => microtime(true)
        ];
        
        $this->journeyTracker->recordTouchpoint($touchpoint);
        
        // Update funnel
        $this->funnelEngine->recordStep('add_to_cart', [
            'product_id' => $cartItem->product_id,
            'session_id' => $this->getCurrentSession(),
            'cart_value' => $this->getCartValue()
        ]);
        
        // Analyze cart behavior
        $this->analyzeCartBehavior($customer?->id);
    }

    public function trackPurchase($order): void
    {
        $journey = $this->journeyTracker->getCurrentJourney($order->customer_id);
        
        $touchpoint = [
            'session_id' => $this->getCurrentSession(),
            'customer_id' => $order->customer_id,
            'touchpoint_type' => 'purchase',
            'order_id' => $order->id,
            'order_value' => $order->total,
            'products_count' => count($order->items),
            'journey_duration' => $this->calculateJourneyDuration($journey),
            'touchpoints_count' => count($journey['touchpoints']),
            'timestamp' => microtime(true)
        ];
        
        $this->journeyTracker->recordTouchpoint($touchpoint);
        
        // Complete funnel
        $this->funnelEngine->recordStep('purchase', [
            'order_id' => $order->id,
            'session_id' => $this->getCurrentSession(),
            'order_value' => $order->total,
            'conversion_time' => $this->calculateConversionTime()
        ]);
        
        // Analyze complete journey
        $this->analyzeCompletedJourney($journey, $order);
    }

    public function injectTrackingScript(): void
    {
        if (!$this->getConfig('track_anonymous_users', true) && !$this->api->auth()->check()) {
            return;
        }
        
        echo $this->api->view('journey-analytics/tracking-script', [
            'session_id' => $this->getCurrentSession(),
            'tracking_config' => [
                'track_clicks' => true,
                'track_scrolls' => true,
                'track_forms' => true,
                'track_time' => true,
                'heatmap_enabled' => $this->getConfig('enable_heatmaps', true),
                'session_timeout' => $this->getConfig('session_timeout', 30) * 60 * 1000
            ],
            'api_endpoint' => '/api/v1/analytics/track'
        ]);
    }

    public function journeyDashboard(): void
    {
        $metrics = [
            'overview' => $this->getJourneyOverview(),
            'funnel_analysis' => $this->funnelEngine->getConversionFunnel(),
            'journey_paths' => $this->journeyTracker->getTopJourneyPaths(10),
            'touchpoint_analysis' => $this->touchpointAnalyzer->analyze(),
            'behavior_segments' => $this->segmentBuilder->getSegments(),
            'drop_off_points' => $this->identifyDropOffPoints(),
            'conversion_paths' => $this->getSuccessfulPaths(),
            'heatmaps' => $this->heatmapGenerator->getTopPages(),
            'real_time_visitors' => $this->getRealTimeVisitors(),
            'journey_duration' => $this->getAverageJourneyMetrics()
        ];
        
        echo $this->api->view('journey-analytics/dashboard', $metrics);
    }

    private function updateJourneyStage($sessionId, $pageData): void
    {
        $stage = $this->determineJourneyStage($pageData);
        
        $this->journeyTracker->updateStage($sessionId, $stage, [
            'page_type' => $pageData['type'],
            'page_url' => $pageData['url'],
            'timestamp' => microtime(true)
        ]);
        
        // Check for stage transitions
        $previousStage = $this->journeyTracker->getPreviousStage($sessionId);
        if ($previousStage && $previousStage !== $stage) {
            $this->trackStageTransition($sessionId, $previousStage, $stage);
        }
    }

    private function determineJourneyStage($pageData): string
    {
        $pageType = $pageData['type'];
        $url = $pageData['url'];
        
        if ($pageType === 'home' || $url === '/') {
            return 'awareness';
        } elseif (in_array($pageType, ['category', 'search', 'product_list'])) {
            return 'discovery';
        } elseif ($pageType === 'product') {
            return 'consideration';
        } elseif (in_array($pageType, ['cart', 'checkout'])) {
            return 'intent';
        } elseif ($pageType === 'order_complete') {
            return 'purchase';
        } elseif (in_array($pageType, ['account', 'order_history'])) {
            return 'retention';
        }
        
        return 'exploration';
    }

    private function analyzeCompletedJourney($journey, $order): void
    {
        $analysis = [
            'journey_id' => $journey['id'],
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'journey_duration' => $this->calculateJourneyDuration($journey),
            'touchpoints_count' => count($journey['touchpoints']),
            'unique_pages_visited' => $this->countUniquePages($journey),
            'products_viewed' => $this->countProductsViewed($journey),
            'searches_performed' => $this->countSearches($journey),
            'cart_modifications' => $this->countCartModifications($journey),
            'device_switches' => $this->detectDeviceSwitches($journey),
            'channel_attribution' => $this->attributeChannels($journey),
            'key_influencers' => $this->identifyKeyInfluencers($journey),
            'journey_score' => $this->calculateJourneyScore($journey)
        ];
        
        $this->journeyTracker->saveJourneyAnalysis($analysis);
        
        // Update customer profile
        $this->updateCustomerJourneyProfile($order->customer_id, $analysis);
        
        // Identify optimization opportunities
        $this->identifyOptimizationOpportunities($analysis);
    }

    private function identifyDropOffPoints(): array
    {
        $dropOffs = [];
        
        // Analyze funnel drop-offs
        $funnelStages = ['discovery', 'consideration', 'add_to_cart', 'checkout_start', 'purchase'];
        
        for ($i = 0; $i < count($funnelStages) - 1; $i++) {
            $currentStage = $funnelStages[$i];
            $nextStage = $funnelStages[$i + 1];
            
            $dropOffRate = $this->funnelEngine->getDropOffRate($currentStage, $nextStage);
            
            if ($dropOffRate > 0.3) { // 30% threshold
                $dropOffs[] = [
                    'from_stage' => $currentStage,
                    'to_stage' => $nextStage,
                    'drop_off_rate' => $dropOffRate,
                    'lost_customers' => $this->funnelEngine->getLostCustomers($currentStage, $nextStage),
                    'common_exit_pages' => $this->getCommonExitPages($currentStage),
                    'recommendations' => $this->generateDropOffRecommendations($currentStage, $dropOffRate)
                ];
            }
        }
        
        return $dropOffs;
    }

    private function detectRageClicks($clickData): void
    {
        $recentClicks = $this->journeyTracker->getRecentClicks($clickData['session_id'], 3);
        
        if (count($recentClicks) >= 3) {
            $timeWindow = 2; // seconds
            $firstClick = reset($recentClicks);
            $lastClick = end($recentClicks);
            
            if (($lastClick['timestamp'] - $firstClick['timestamp']) <= $timeWindow) {
                // Check if clicks are in same area
                $maxDistance = 50; // pixels
                $sameArea = true;
                
                foreach ($recentClicks as $click) {
                    $distance = sqrt(
                        pow($click['x'] - $clickData['x'], 2) + 
                        pow($click['y'] - $clickData['y'], 2)
                    );
                    
                    if ($distance > $maxDistance) {
                        $sameArea = false;
                        break;
                    }
                }
                
                if ($sameArea) {
                    $this->journeyTracker->recordFrustrationSignal([
                        'type' => 'rage_click',
                        'session_id' => $clickData['session_id'],
                        'page_url' => $clickData['page_url'],
                        'element' => $clickData['element_type'],
                        'click_count' => count($recentClicks),
                        'area' => ['x' => $clickData['x'], 'y' => $clickData['y']]
                    ]);
                }
            }
        }
    }

    private function initializeTracking(): void
    {
        // Real-time visitor tracking
        $this->api->scheduler()->addJob('track_active_visitors', '* * * * *', function() {
            $this->updateActiveVisitors();
        });
        
        // Journey aggregation
        $this->api->scheduler()->addJob('aggregate_journeys', '0 * * * *', function() {
            $this->journeyTracker->aggregateHourlyData();
        });
        
        // Funnel calculation
        $this->api->scheduler()->addJob('calculate_funnels', '0 */4 * * *', function() {
            $this->funnelEngine->recalculateFunnels();
        });
        
        // Heatmap generation
        $this->api->scheduler()->addJob('generate_heatmaps', '0 2 * * *', function() {
            $this->heatmapGenerator->generateDailyHeatmaps();
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->get('/analytics/journey/{customer_id}', 'Controllers\JourneyController@getCustomerJourney');
        $this->api->router()->get('/analytics/funnel', 'Controllers\JourneyController@getFunnelAnalysis');
        $this->api->router()->get('/analytics/heatmap', 'Controllers\JourneyController@getHeatmapData');
        $this->api->router()->get('/analytics/segments', 'Controllers\JourneyController@getBehaviorSegments');
        $this->api->router()->post('/analytics/track', 'Controllers\JourneyController@trackEvent');
        $this->api->router()->get('/analytics/paths', 'Controllers\JourneyController@getJourneyPaths');
        $this->api->router()->get('/analytics/real-time', 'Controllers\JourneyController@getRealTimeData');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultFunnels();
        $this->initializeSegments();
    }

    private function createDefaultFunnels(): void
    {
        $funnels = [
            [
                'name' => 'Main Conversion Funnel',
                'steps' => ['home', 'product_view', 'add_to_cart', 'checkout', 'purchase'],
                'active' => true
            ],
            [
                'name' => 'Search to Purchase',
                'steps' => ['search', 'search_results', 'product_view', 'add_to_cart', 'purchase'],
                'active' => true
            ],
            [
                'name' => 'Category Browse Funnel',
                'steps' => ['category', 'product_list', 'product_view', 'add_to_cart', 'purchase'],
                'active' => true
            ]
        ];

        foreach ($funnels as $funnel) {
            $this->api->database()->table('conversion_funnels')->insert($funnel);
        }
    }

    private function initializeSegments(): void
    {
        $segments = [
            ['name' => 'Quick Buyers', 'criteria' => 'journey_duration < 900'], // 15 minutes
            ['name' => 'Researchers', 'criteria' => 'products_viewed > 10'],
            ['name' => 'Cart Abandoners', 'criteria' => 'has_cart_abandonment = true'],
            ['name' => 'Mobile Users', 'criteria' => 'device_type = mobile'],
            ['name' => 'High Intent', 'criteria' => 'checkout_starts > 0']
        ];

        foreach ($segments as $segment) {
            $this->api->database()->table('behavior_segments')->insert($segment);
        }
    }
}