<?php
namespace InventoryForecasting;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Smart Inventory Forecasting Plugin
 * 
 * Predictive inventory management with demand forecasting and automated reordering
 */
class InventoryForecastingPlugin extends AbstractPlugin
{
    private $forecastEngine;
    private $demandAnalyzer;
    private $reorderManager;
    private $trendAnalyzer;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->scheduleForecastingJobs();
    }

    private function registerServices(): void
    {
        $this->forecastEngine = new Services\ForecastEngine($this->api);
        $this->demandAnalyzer = new Services\DemandAnalyzer($this->api);
        $this->reorderManager = new Services\ReorderManager($this->api);
        $this->trendAnalyzer = new Services\TrendAnalyzer($this->api);
    }

    private function registerHooks(): void
    {
        // Order tracking for demand analysis
        Hook::addAction('order.completed', [$this, 'trackOrderDemand'], 10, 1);
        Hook::addAction('order.cancelled', [$this, 'adjustDemandForecast'], 10, 1);
        
        // Inventory monitoring
        Hook::addAction('inventory.updated', [$this, 'checkReorderPoint'], 10, 2);
        Hook::addAction('product.low_stock', [$this, 'triggerReorderAlert'], 10, 2);
        Hook::addAction('inventory.received', [$this, 'updateLeadTimeMetrics'], 10, 2);
        
        // Forecasting triggers
        Hook::addFilter('inventory.reorder_quantity', [$this, 'calculateOptimalReorder'], 10, 2);
        Hook::addFilter('product.safety_stock', [$this, 'calculateSafetyStock'], 10, 2);
        
        // Admin integration
        Hook::addAction('admin.inventory.dashboard', [$this, 'addForecastingWidget'], 10, 1);
        Hook::addFilter('admin.product.inventory', [$this, 'addForecastingInfo'], 10, 2);
    }

    public function trackOrderDemand($order): void
    {
        foreach ($order->items as $item) {
            $this->demandAnalyzer->recordDemand([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'date' => date('Y-m-d'),
                'order_id' => $order->id,
                'customer_segment' => $this->getCustomerSegment($order->customer_id),
                'season' => $this->getCurrentSeason(),
                'day_of_week' => date('N'),
                'is_promotion' => $this->isPromotionActive($item->product_id)
            ]);
            
            // Update real-time demand metrics
            $this->updateDemandMetrics($item->product_id, $item->quantity);
        }
        
        // Trigger forecast update if significant demand change
        $this->checkForecastAccuracy();
    }

    public function adjustDemandForecast($order): void
    {
        foreach ($order->items as $item) {
            $this->demandAnalyzer->adjustDemand($item->product_id, -$item->quantity, [
                'reason' => 'order_cancelled',
                'order_id' => $order->id
            ]);
        }
    }

    public function checkReorderPoint($productId, $newQuantity): void
    {
        $forecast = $this->forecastEngine->getProductForecast($productId);
        $reorderPoint = $this->calculateReorderPoint($productId, $forecast);
        
        if ($newQuantity <= $reorderPoint) {
            if ($this->getConfig('auto_reorder', false)) {
                $this->createAutomaticReorder($productId, $forecast);
            } else {
                $this->createReorderAlert($productId, $forecast, $newQuantity);
            }
        }
        
        // Check for overstock situations
        $overstockThreshold = $forecast['average_demand'] * 90; // 90 days of inventory
        if ($newQuantity > $overstockThreshold) {
            $this->alertOverstock($productId, $newQuantity, $overstockThreshold);
        }
    }

    public function triggerReorderAlert($productId, $currentStock): void
    {
        $forecast = $this->forecastEngine->getProductForecast($productId);
        $daysUntilStockout = $this->calculateDaysUntilStockout($productId, $currentStock, $forecast);
        
        $alert = [
            'product_id' => $productId,
            'current_stock' => $currentStock,
            'days_until_stockout' => $daysUntilStockout,
            'recommended_order_quantity' => $this->calculateOptimalReorder($currentStock, $productId),
            'forecast_confidence' => $forecast['confidence_score'],
            'priority' => $this->calculateReorderPriority($productId, $daysUntilStockout)
        ];
        
        $this->reorderManager->createAlert($alert);
        
        // Send notifications based on priority
        if ($alert['priority'] === 'critical') {
            $this->sendCriticalStockNotification($productId, $alert);
        }
    }

    public function updateLeadTimeMetrics($productId, $receivedData): void
    {
        $orderDate = $receivedData['order_date'];
        $receiveDate = $receivedData['receive_date'];
        $leadTime = (strtotime($receiveDate) - strtotime($orderDate)) / 86400;
        
        $this->reorderManager->updateLeadTime($productId, [
            'supplier_id' => $receivedData['supplier_id'],
            'lead_time_days' => $leadTime,
            'quantity_ordered' => $receivedData['quantity_ordered'],
            'quantity_received' => $receivedData['quantity_received']
        ]);
        
        // Recalculate safety stock based on new lead time data
        $this->recalculateSafetyStock($productId);
    }

    public function calculateOptimalReorder($currentStock, $productId): int
    {
        $forecast = $this->forecastEngine->getProductForecast($productId);
        $leadTime = $this->reorderManager->getAverageLeadTime($productId);
        $safetyStock = $this->calculateSafetyStock($currentStock, $productId);
        
        // Economic Order Quantity (EOQ) calculation
        $annualDemand = $forecast['annual_demand'];
        $orderingCost = $this->getOrderingCost($productId);
        $holdingCost = $this->getHoldingCost($productId);
        
        $eoq = sqrt((2 * $annualDemand * $orderingCost) / $holdingCost);
        
        // Adjust for forecast period and constraints
        $forecastDays = $this->getConfig('forecast_window_days', 30);
        $forecastDemand = $forecast['demand_forecast'] * $forecastDays;
        $leadTimeDemand = $forecast['daily_average'] * $leadTime;
        
        $reorderQuantity = max(
            $eoq,
            $forecastDemand + $leadTimeDemand + $safetyStock - $currentStock
        );
        
        // Apply constraints
        $minOrder = $this->getMinimumOrderQuantity($productId);
        $maxOrder = $this->getMaximumOrderQuantity($productId);
        $orderMultiple = $this->getOrderMultiple($productId);
        
        $reorderQuantity = max($minOrder, min($maxOrder, $reorderQuantity));
        
        // Round to order multiple
        if ($orderMultiple > 1) {
            $reorderQuantity = ceil($reorderQuantity / $orderMultiple) * $orderMultiple;
        }
        
        return (int) $reorderQuantity;
    }

    public function calculateSafetyStock($currentStock, $productId): int
    {
        $leadTime = $this->reorderManager->getAverageLeadTime($productId);
        $leadTimeVariability = $this->reorderManager->getLeadTimeVariability($productId);
        $demandVariability = $this->demandAnalyzer->getDemandVariability($productId);
        
        $safetyDays = $this->getConfig('safety_stock_days', 7);
        $serviceLevel = 0.95; // 95% service level
        $zScore = 1.645; // Z-score for 95% service level
        
        // Safety stock formula considering both demand and lead time variability
        $avgDailyDemand = $this->demandAnalyzer->getAverageDailyDemand($productId);
        
        $safetyStock = $zScore * sqrt(
            pow($leadTime * $demandVariability, 2) + 
            pow($avgDailyDemand * $leadTimeVariability, 2)
        );
        
        // Add buffer for critical items
        if ($this->isProductCritical($productId)) {
            $safetyStock *= 1.2;
        }
        
        // Seasonal adjustment
        if ($this->getConfig('seasonal_adjustment', true)) {
            $seasonalFactor = $this->trendAnalyzer->getSeasonalFactor($productId);
            $safetyStock *= $seasonalFactor;
        }
        
        return (int) ceil($safetyStock);
    }

    public function addForecastingWidget($widgets): array
    {
        $widgets['inventory_forecast'] = [
            'title' => 'Inventory Forecast',
            'template' => 'forecasting/dashboard-widget',
            'data' => $this->getForecastingOverview()
        ];
        
        $widgets['reorder_alerts'] = [
            'title' => 'Reorder Alerts',
            'template' => 'forecasting/reorder-alerts',
            'data' => $this->reorderManager->getPendingAlerts()
        ];

        return $widgets;
    }

    public function addForecastingInfo($inventorySection, $product): string
    {
        $forecast = $this->forecastEngine->getProductForecast($product->id);
        $trends = $this->trendAnalyzer->getProductTrends($product->id);
        
        $forecastingInfo = $this->api->view('forecasting/product-forecast', [
            'product' => $product,
            'forecast' => $forecast,
            'trends' => $trends,
            'reorder_point' => $this->calculateReorderPoint($product->id, $forecast),
            'safety_stock' => $this->calculateSafetyStock($product->stock_quantity, $product->id),
            'lead_time' => $this->reorderManager->getAverageLeadTime($product->id),
            'stockout_risk' => $this->calculateStockoutRisk($product->id, $product->stock_quantity)
        ]);

        return $inventorySection . $forecastingInfo;
    }

    private function calculateReorderPoint($productId, $forecast): int
    {
        $leadTime = $this->reorderManager->getAverageLeadTime($productId);
        $avgDailyDemand = $forecast['daily_average'];
        $safetyStock = $this->calculateSafetyStock(0, $productId);
        
        return (int) ceil(($avgDailyDemand * $leadTime) + $safetyStock);
    }

    private function calculateDaysUntilStockout($productId, $currentStock, $forecast): int
    {
        if ($forecast['daily_average'] == 0) {
            return 999; // No demand
        }
        
        return (int) floor($currentStock / $forecast['daily_average']);
    }

    private function updateDemandMetrics($productId, $quantity): void
    {
        $this->api->cache()->increment("demand_today_{$productId}", $quantity);
        $this->api->cache()->increment("demand_week_{$productId}", $quantity);
        $this->api->cache()->increment("demand_month_{$productId}", $quantity);
        
        // Update moving averages
        $this->demandAnalyzer->updateMovingAverages($productId);
    }

    private function checkForecastAccuracy(): void
    {
        $accuracy = $this->forecastEngine->calculateAccuracy();
        
        if ($accuracy < 0.8) { // Less than 80% accurate
            $this->forecastEngine->retrainModels();
        }
    }

    private function createAutomaticReorder($productId, $forecast): void
    {
        $product = $this->api->service('ProductRepository')->find($productId);
        $quantity = $this->calculateOptimalReorder($product->stock_quantity, $productId);
        
        $reorder = $this->reorderManager->createReorder([
            'product_id' => $productId,
            'quantity' => $quantity,
            'supplier_id' => $product->primary_supplier_id,
            'expected_cost' => $product->cost * $quantity,
            'forecast_data' => $forecast,
            'auto_generated' => true
        ]);
        
        // Send notification
        $this->api->notification()->send('inventory_manager', [
            'type' => 'auto_reorder_created',
            'title' => 'Automatic Reorder Created',
            'message' => "Reorder for {$product->name} - Quantity: {$quantity}",
            'reorder_id' => $reorder->id
        ]);
    }

    private function createReorderAlert($productId, $forecast, $currentStock): void
    {
        $this->api->notification()->send('inventory_manager', [
            'type' => 'reorder_needed',
            'title' => 'Reorder Point Reached',
            'message' => "Product #{$productId} has reached reorder point. Current stock: {$currentStock}",
            'data' => [
                'product_id' => $productId,
                'current_stock' => $currentStock,
                'forecast' => $forecast
            ]
        ]);
    }

    private function alertOverstock($productId, $currentStock, $threshold): void
    {
        $this->api->notification()->send('inventory_manager', [
            'type' => 'overstock_alert',
            'title' => 'Overstock Situation',
            'message' => "Product #{$productId} is overstocked. Current: {$currentStock}, Threshold: {$threshold}",
            'priority' => 'low'
        ]);
    }

    private function getForecastingOverview(): array
    {
        return [
            'total_products' => $this->forecastEngine->getTotalProducts(),
            'products_below_reorder' => $this->reorderManager->getProductsBelowReorderPoint(),
            'pending_reorders' => $this->reorderManager->getPendingReorderCount(),
            'forecast_accuracy' => $this->forecastEngine->getOverallAccuracy(),
            'stockout_predictions' => $this->forecastEngine->getPredictedStockouts(7),
            'overstock_products' => $this->forecastEngine->getOverstockedProducts(),
            'seasonal_trends' => $this->trendAnalyzer->getCurrentSeasonalTrends()
        ];
    }

    private function getCurrentSeason(): string
    {
        $month = date('n');
        
        if ($month >= 3 && $month <= 5) return 'spring';
        if ($month >= 6 && $month <= 8) return 'summer';
        if ($month >= 9 && $month <= 11) return 'fall';
        return 'winter';
    }

    private function scheduleForecastingJobs(): void
    {
        // Daily forecast updates
        $this->api->scheduler()->addJob('update_forecasts', '0 3 * * *', function() {
            $this->forecastEngine->updateAllForecasts();
        });
        
        // Weekly trend analysis
        $this->api->scheduler()->addJob('analyze_trends', '0 4 * * 0', function() {
            $this->trendAnalyzer->analyzeWeeklyTrends();
        });
        
        // Monthly seasonal pattern detection
        $this->api->scheduler()->addJob('seasonal_analysis', '0 5 1 * *', function() {
            $this->trendAnalyzer->updateSeasonalPatterns();
        });
        
        // Real-time monitoring
        $this->api->scheduler()->addJob('monitor_stock_levels', '*/30 * * * *', function() {
            $this->monitorCriticalStockLevels();
        });
    }

    private function monitorCriticalStockLevels(): void
    {
        $criticalProducts = $this->forecastEngine->getCriticalStockProducts();
        
        foreach ($criticalProducts as $product) {
            $this->checkReorderPoint($product->id, $product->stock_quantity);
        }
    }

    private function registerRoutes(): void
    {
        $this->api->router()->get('/inventory/forecast/{product_id}', 'Controllers\ForecastController@getProductForecast');
        $this->api->router()->post('/inventory/reorder', 'Controllers\ForecastController@createReorder');
        $this->api->router()->get('/inventory/recommendations', 'Controllers\ForecastController@getRecommendations');
        $this->api->router()->get('/inventory/trends', 'Controllers\ForecastController@getTrends');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->initializeForecastingModels();
        $this->createDefaultReorderRules();
    }

    private function initializeForecastingModels(): void
    {
        $this->forecastEngine->initializeModels([
            'arima' => ['order' => [1, 1, 1]], // ARIMA model
            'exponential_smoothing' => ['alpha' => 0.3],
            'moving_average' => ['window' => 7],
            'seasonal_decomposition' => ['period' => 365]
        ]);
    }

    private function createDefaultReorderRules(): void
    {
        $rules = [
            ['name' => 'Critical Stock', 'condition' => 'stock_quantity <= safety_stock', 'priority' => 'critical'],
            ['name' => 'Low Stock', 'condition' => 'stock_quantity <= reorder_point', 'priority' => 'high'],
            ['name' => 'Optimal Reorder', 'condition' => 'stock_quantity <= (reorder_point * 1.2)', 'priority' => 'medium']
        ];

        foreach ($rules as $rule) {
            $this->api->database()->table('reorder_rules')->insert($rule);
        }
    }
}