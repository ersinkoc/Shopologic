<?php

declare(strict_types=1);
namespace Shopologic\Plugins\SmartPricing;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Smart Pricing Engine Plugin
 * 
 * Dynamic pricing based on demand, inventory, and market conditions
 */
class SmartPricingPlugin extends AbstractPlugin
{
    private $pricingEngine;
    private $competitorAnalyzer;
    private $demandAnalyzer;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->scheduleAnalysis();
    }

    private function registerServices(): void
    {
        $this->api->container()->bind('PricingEngineInterface', function() {
            return new Services\PricingEngine($this->api);
        });

        $this->api->container()->bind('CompetitorAnalysisInterface', function() {
            return new Services\CompetitorAnalyzer($this->api);
        });

        $this->pricingEngine = $this->api->container()->get('PricingEngineInterface');
        $this->competitorAnalyzer = $this->api->container()->get('CompetitorAnalysisInterface');
        $this->demandAnalyzer = new Services\DemandAnalyzer($this->api);
    }

    private function registerHooks(): void
    {
        // Intercept price calculations
        Hook::addFilter('product.price', [$this, 'calculateDynamicPrice'], 10, 2);
        
        // Track demand signals
        Hook::addAction('product.viewed', [$this, 'trackDemand'], 10, 1);
        Hook::addAction('cart.item_added', [$this, 'trackDemand'], 10, 1);
        Hook::addAction('order.completed', [$this, 'updateDemandMetrics'], 10, 1);
        
        // Monitor inventory changes
        Hook::addAction('inventory.updated', [$this, 'adjustPricingForInventory'], 10, 2);
        
        // Admin dashboard integration
        Hook::addAction('admin.dashboard.widgets', [$this, 'addPricingWidgets'], 10, 1);
        Hook::addFilter('admin.product.form', [$this, 'addPricingControls'], 10, 2);
    }

    public function calculateDynamicPrice($currentPrice, $product): float
    {
        if (!$this->getConfig('enable_dynamic_pricing', true)) {
            return $currentPrice;
        }

        $basePrice = $product->base_price ?? $currentPrice;
        $adjustmentLimit = $this->getConfig('price_adjustment_limit', 20) / 100;
        
        // Calculate pricing factors
        $demandFactor = $this->demandAnalyzer->getDemandFactor($product->id);
        $inventoryFactor = $this->calculateInventoryFactor($product);
        $competitorFactor = $this->competitorAnalyzer->getCompetitorFactor($product->id);
        
        // Apply weights
        $demandWeight = $this->getConfig('demand_weight', 0.4);
        $inventoryWeight = $this->getConfig('inventory_weight', 0.3);
        $competitorWeight = $this->getConfig('competitor_weight', 0.3);
        
        $totalAdjustment = (
            $demandFactor * $demandWeight +
            $inventoryFactor * $inventoryWeight +
            $competitorFactor * $competitorWeight
        );
        
        // Cap adjustment within limits
        $totalAdjustment = max(-$adjustmentLimit, min($adjustmentLimit, $totalAdjustment));
        
        $newPrice = $basePrice * (1 + $totalAdjustment);
        
        // Ensure minimum margin
        $minimumMargin = $this->getConfig('minimum_margin', 15) / 100;
        $cost = $product->cost ?? ($basePrice * 0.7);
        $minimumPrice = $cost * (1 + $minimumMargin);
        
        $finalPrice = max($minimumPrice, $newPrice);
        
        // Log pricing decision
        $this->logPricingDecision($product->id, $basePrice, $finalPrice, [
            'demand_factor' => $demandFactor,
            'inventory_factor' => $inventoryFactor,
            'competitor_factor' => $competitorFactor,
            'total_adjustment' => $totalAdjustment
        ]);
        
        return $finalPrice;
    }

    public function trackDemand($data): void
    {
        $productId = is_array($data) ? $data['product_id'] : $data->product_id;
        $this->demandAnalyzer->recordDemandSignal($productId, 'view');
    }

    public function updateDemandMetrics($order): void
    {
        foreach ($order->items as $item) {
            $this->demandAnalyzer->recordDemandSignal($item->product_id, 'purchase', $item->quantity);
        }
    }

    public function adjustPricingForInventory($productId, $newQuantity): void
    {
        if ($newQuantity <= 5) {
            // Low stock - increase price
            $this->api->cache()->forget("pricing_rules_{$productId}");
        } elseif ($newQuantity >= 100) {
            // High stock - decrease price
            $this->api->cache()->forget("pricing_rules_{$productId}");
        }
    }

    private function calculateInventoryFactor($product): float
    {
        $stock = $product->stock_quantity ?? 0;
        $averageStock = $this->getAverageStockLevel($product->id);
        
        if ($averageStock == 0) return 0;
        
        $stockRatio = $stock / $averageStock;
        
        if ($stockRatio < 0.2) {
            return 0.15; // Increase price by up to 15%
        } elseif ($stockRatio > 2.0) {
            return -0.10; // Decrease price by up to 10%
        }
        
        return 0;
    }

    private function getAverageStockLevel($productId): int
    {
        return $this->api->cache()->remember("avg_stock_{$productId}", 3600, function() use ($productId) {
            // Calculate 30-day average stock level
            return $this->api->database()->table('inventory_history')
                ->where('product_id', $productId)
                ->where('created_at', '>=', date('Y-m-d', strtotime('-30 days')))
                ->avg('quantity') ?? 0;
        });
    }

    private function logPricingDecision($productId, $oldPrice, $newPrice, $factors): void
    {
        $this->api->database()->table('pricing_history')->insert([
            'product_id' => $productId,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'factors' => json_encode($factors),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function addPricingWidgets($widgets): array
    {
        $widgets['pricing_performance'] = [
            'title' => 'Pricing Performance',
            'template' => 'pricing/dashboard-widget',
            'data' => $this->getPricingPerformanceData()
        ];
        
        return $widgets;
    }

    public function addPricingControls($form, $product): string
    {
        $pricingData = $this->pricingEngine->getProductPricingData($product->id);
        
        return $form . $this->api->view('pricing/product-controls', [
            'product' => $product,
            'pricing_data' => $pricingData,
            'suggested_price' => $this->calculateDynamicPrice($product->price, $product)
        ]);
    }

    private function scheduleAnalysis(): void
    {
        // Schedule competitor analysis every 6 hours
        $this->api->scheduler()->addJob('competitor_analysis', '0 */6 * * *', function() {
            $this->competitorAnalyzer->analyzeAllProducts();
        });
        
        // Update demand metrics every hour
        $this->api->scheduler()->addJob('demand_update', '0 * * * *', function() {
            $this->demandAnalyzer->updateDemandMetrics();
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->get('/pricing/rules', 'Controllers\PricingController@getRules');
        $this->api->router()->post('/pricing/rules', 'Controllers\PricingController@createRule');
        $this->api->router()->get('/pricing/analysis/{product_id}', 'Controllers\PricingController@getAnalysis');
        $this->api->router()->post('/pricing/optimize', 'Controllers\PricingController@optimizePricing');
    }

    private function getPricingPerformanceData(): array
    {
        return [
            'total_adjustments' => $this->api->database()->table('pricing_history')
                ->where('created_at', '>=', date('Y-m-d', strtotime('-7 days')))
                ->count(),
            'avg_price_increase' => $this->api->database()->table('pricing_history')
                ->where('created_at', '>=', date('Y-m-d', strtotime('-7 days')))
                ->where('new_price', '>', 'old_price')
                ->avg('new_price - old_price'),
            'revenue_impact' => $this->calculateRevenueImpact()
        ];
    }

    private function calculateRevenueImpact(): float
    {
        // Calculate revenue impact of pricing changes
        return $this->api->database()->table('pricing_history ph')
            ->join('order_items oi', 'ph.product_id', '=', 'oi.product_id')
            ->where('oi.created_at', '>=', 'ph.created_at')
            ->sum('(ph.new_price - ph.old_price) * oi.quantity') ?? 0;
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultRules();
    }

    private function createDefaultRules(): void
    {
        $defaultRules = [
            [
                'name' => 'Low Stock Premium',
                'condition' => 'stock_quantity <= 5',
                'adjustment' => 0.10,
                'active' => true
            ],
            [
                'name' => 'High Demand Surge',
                'condition' => 'demand_score >= 0.8',
                'adjustment' => 0.15,
                'active' => true
            ]
        ];

        foreach ($defaultRules as $rule) {
            $this->api->database()->table('pricing_rules')->insert($rule);
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