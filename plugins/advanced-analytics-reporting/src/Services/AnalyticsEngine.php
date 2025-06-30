<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Services;

use AdvancedAnalytics\Repositories\EventRepository;
use Shopologic\Core\Database\QueryBuilder;
use Shopologic\Core\Cache\CacheInterface;

class AnalyticsEngine\n{
    private EventRepository $eventRepository;
    private MetricsCalculator $metricsCalculator;
    private CacheInterface $cache;
    private array $config;

    public function __construct(
        EventRepository $eventRepository,
        MetricsCalculator $metricsCalculator,
        array $config = []
    ) {
        $this->eventRepository = $eventRepository;
        $this->metricsCalculator = $metricsCalculator;
        $this->config = $config;
        $this->cache = app(CacheInterface::class);
    }

    /**
     * Process analytics data for a given period
     */
    public function processAnalyticsData(string $startDate, string $endDate): array
    {
        $cacheKey = "analytics_data_{$startDate}_{$endDate}";
        
        return $this->cache->remember($cacheKey, 900, function() use ($startDate, $endDate) {
            return [
                'sales' => $this->processSalesData($startDate, $endDate),
                'customers' => $this->processCustomerData($startDate, $endDate),
                'products' => $this->processProductData($startDate, $endDate),
                'sessions' => $this->processSessionData($startDate, $endDate),
                'conversions' => $this->processConversionData($startDate, $endDate)
            ];
        });
    }

    /**
     * Process sales analytics data
     */
    public function processSalesData(string $startDate, string $endDate): array
    {
        $events = $this->eventRepository->getEventsByType('order_completed', $startDate, $endDate);
        
        $totalRevenue = 0;
        $totalOrders = count($events);
        $dailyRevenue = [];
        $paymentMethods = [];
        $shippingMethods = [];
        
        foreach ($events as $event) {
            $data = $event['event_data'];
            $amount = $data['total_amount'] ?? 0;
            $totalRevenue += $amount;
            
            // Daily revenue
            $date = date('Y-m-d', strtotime($event['event_timestamp']));
            $dailyRevenue[$date] = ($dailyRevenue[$date] ?? 0) + $amount;
            
            // Payment methods
            $paymentMethod = $data['payment_method'] ?? 'unknown';
            $paymentMethods[$paymentMethod] = ($paymentMethods[$paymentMethod] ?? 0) + 1;
            
            // Shipping methods
            $shippingMethod = $data['shipping_method'] ?? 'unknown';
            $shippingMethods[$shippingMethod] = ($shippingMethods[$shippingMethod] ?? 0) + 1;
        }
        
        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'average_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
            'daily_revenue' => $dailyRevenue,
            'payment_methods' => $paymentMethods,
            'shipping_methods' => $shippingMethods
        ];
    }

    /**
     * Process customer analytics data
     */
    public function processCustomerData(string $startDate, string $endDate): array
    {
        $registrationEvents = $this->eventRepository->getEventsByType('customer_registered', $startDate, $endDate);
        $loginEvents = $this->eventRepository->getEventsByType('customer_login', $startDate, $endDate);
        
        $newCustomers = count($registrationEvents);
        $returningCustomers = count(array_unique(array_column($loginEvents, 'customer_id')));
        
        $acquisitionSources = [];
        foreach ($registrationEvents as $event) {
            $source = $event['utm_source'] ?? 'direct';
            $acquisitionSources[$source] = ($acquisitionSources[$source] ?? 0) + 1;
        }
        
        return [
            'new_customers' => $newCustomers,
            'returning_customers' => $returningCustomers,
            'acquisition_sources' => $acquisitionSources,
            'customer_lifetime_value' => $this->calculateCustomerLifetimeValue($startDate, $endDate),
            'retention_rate' => $this->calculateRetentionRate($startDate, $endDate)
        ];
    }

    /**
     * Process product analytics data
     */
    public function processProductData(string $startDate, string $endDate): array
    {
        $viewEvents = $this->eventRepository->getEventsByType('product_viewed', $startDate, $endDate);
        $purchaseEvents = $this->eventRepository->getEventsByType('order_item_purchased', $startDate, $endDate);
        
        $productViews = [];
        $productSales = [];
        $categoryPerformance = [];
        
        foreach ($viewEvents as $event) {
            $productId = $event['event_data']['product_id'] ?? null;
            if ($productId) {
                $productViews[$productId] = ($productViews[$productId] ?? 0) + 1;
            }
        }
        
        foreach ($purchaseEvents as $event) {
            $data = $event['event_data'];
            $productId = $data['product_id'] ?? null;
            $category = $data['product_category'] ?? 'uncategorized';
            $revenue = $data['total_price'] ?? 0;
            $quantity = $data['quantity'] ?? 1;
            
            if ($productId) {
                if (!isset($productSales[$productId])) {
                    $productSales[$productId] = ['quantity' => 0, 'revenue' => 0];
                }
                $productSales[$productId]['quantity'] += $quantity;
                $productSales[$productId]['revenue'] += $revenue;
            }
            
            if (!isset($categoryPerformance[$category])) {
                $categoryPerformance[$category] = ['quantity' => 0, 'revenue' => 0];
            }
            $categoryPerformance[$category]['quantity'] += $quantity;
            $categoryPerformance[$category]['revenue'] += $revenue;
        }
        
        // Calculate conversion rates
        $conversionRates = [];
        foreach ($productViews as $productId => $views) {
            $sales = $productSales[$productId]['quantity'] ?? 0;
            $conversionRates[$productId] = $views > 0 ? ($sales / $views) * 100 : 0;
        }
        
        return [
            'top_viewed_products' => array_slice(arsort($productViews) ? $productViews : [], 0, 10, true),
            'top_selling_products' => $this->getTopSellingProducts($productSales, 10),
            'category_performance' => $categoryPerformance,
            'conversion_rates' => $conversionRates
        ];
    }

    /**
     * Process session analytics data
     */
    public function processSessionData(string $startDate, string $endDate): array
    {
        $query = "SELECT 
                    COUNT(*) as total_sessions,
                    AVG(duration_seconds) as avg_session_duration,
                    AVG(page_views) as avg_page_views,
                    SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounce_sessions,
                    COUNT(CASE WHEN customer_id IS NOT NULL THEN 1 END) as authenticated_sessions
                  FROM analytics_sessions 
                  WHERE session_start BETWEEN ? AND ?";
        
        $result = QueryBuilder::select($query, [$startDate, $endDate])[0] ?? [];
        
        $totalSessions = $result['total_sessions'] ?? 0;
        $bounceRate = $totalSessions > 0 ? (($result['bounce_sessions'] ?? 0) / $totalSessions) * 100 : 0;
        
        return [
            'total_sessions' => $totalSessions,
            'average_session_duration' => $result['avg_session_duration'] ?? 0,
            'average_page_views' => $result['avg_page_views'] ?? 0,
            'bounce_rate' => $bounceRate,
            'authenticated_sessions' => $result['authenticated_sessions'] ?? 0
        ];
    }

    /**
     * Process conversion analytics data
     */
    public function processConversionData(string $startDate, string $endDate): array
    {
        $checkoutStarted = $this->eventRepository->getEventsByType('checkout_started', $startDate, $endDate);
        $checkoutCompleted = $this->eventRepository->getEventsByType('checkout_completed', $startDate, $endDate);
        $checkoutAbandoned = $this->eventRepository->getEventsByType('checkout_abandoned', $startDate, $endDate);
        
        $conversionRate = count($checkoutStarted) > 0 
            ? (count($checkoutCompleted) / count($checkoutStarted)) * 100 
            : 0;
        
        $abandonmentRate = count($checkoutStarted) > 0 
            ? (count($checkoutAbandoned) / count($checkoutStarted)) * 100 
            : 0;
        
        return [
            'checkout_started' => count($checkoutStarted),
            'checkout_completed' => count($checkoutCompleted),
            'checkout_abandoned' => count($checkoutAbandoned),
            'conversion_rate' => $conversionRate,
            'abandonment_rate' => $abandonmentRate
        ];
    }

    /**
     * Aggregate session data
     */
    public function aggregateSessionData(): void
    {
        // Find sessions that need aggregation (older than 1 hour)
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $query = "UPDATE analytics_sessions 
                  SET duration_seconds = TIMESTAMPDIFF(SECOND, session_start, session_end),
                      is_bounce = CASE WHEN page_views <= 1 THEN 1 ELSE 0 END
                  WHERE session_end IS NOT NULL 
                    AND session_end < ? 
                    AND duration_seconds IS NULL";
        
        QueryBuilder::execute($query, [$cutoffTime]);
    }

    /**
     * Update customer segments
     */
    public function updateCustomerSegments(): void
    {
        // Recalculate customer segments based on behavior
        $segments = [
            'high_value' => $this->identifyHighValueCustomers(),
            'at_risk' => $this->identifyAtRiskCustomers(),
            'new_customers' => $this->identifyNewCustomers(),
            'loyal_customers' => $this->identifyLoyalCustomers()
        ];
        
        foreach ($segments as $segmentType => $customerIds) {
            $this->updateSegmentMembership($segmentType, $customerIds);
        }
    }

    /**
     * Clean up old events
     */
    public function cleanupOldEvents(int $retentionDays): void
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$retentionDays} days"));
        
        QueryBuilder::execute(
            "DELETE FROM analytics_events WHERE event_timestamp < ?",
            [$cutoffDate]
        );
    }

    /**
     * Clean up old sessions
     */
    public function cleanupOldSessions(int $retentionDays): void
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$retentionDays} days"));
        
        QueryBuilder::execute(
            "DELETE FROM analytics_sessions WHERE session_start < ?",
            [$cutoffDate]
        );
    }

    /**
     * Optimize database tables
     */
    public function optimizeTables(): void
    {
        $tables = [
            'analytics_events',
            'analytics_sessions',
            'analytics_metrics',
            'analytics_cohorts'
        ];
        
        foreach ($tables as $table) {
            QueryBuilder::execute("OPTIMIZE TABLE {$table}");
        }
    }

    /**
     * Calculate customer lifetime value
     */
    private function calculateCustomerLifetimeValue(string $startDate, string $endDate): float
    {
        $query = "SELECT AVG(total_amount) as avg_order_value,
                         COUNT(*) / COUNT(DISTINCT customer_id) as avg_orders_per_customer
                  FROM analytics_events 
                  WHERE event_type = 'order_completed' 
                    AND event_timestamp BETWEEN ? AND ?
                    AND customer_id IS NOT NULL";
        
        $result = QueryBuilder::select($query, [$startDate, $endDate])[0] ?? [];
        
        $avgOrderValue = $result['avg_order_value'] ?? 0;
        $avgOrdersPerCustomer = $result['avg_orders_per_customer'] ?? 0;
        
        return $avgOrderValue * $avgOrdersPerCustomer;
    }

    /**
     * Calculate retention rate
     */
    private function calculateRetentionRate(string $startDate, string $endDate): float
    {
        $query = "SELECT COUNT(DISTINCT e1.customer_id) as returning_customers,
                         COUNT(DISTINCT e2.customer_id) as total_customers
                  FROM analytics_events e1
                  LEFT JOIN analytics_events e2 ON e1.customer_id = e2.customer_id
                  WHERE e1.event_type = 'customer_login'
                    AND e1.event_timestamp BETWEEN ? AND ?
                    AND e2.event_type = 'customer_registered'
                    AND e2.event_timestamp < ?";
        
        $result = QueryBuilder::select($query, [$startDate, $endDate, $startDate])[0] ?? [];
        
        $returningCustomers = $result['returning_customers'] ?? 0;
        $totalCustomers = $result['total_customers'] ?? 0;
        
        return $totalCustomers > 0 ? ($returningCustomers / $totalCustomers) * 100 : 0;
    }

    /**
     * Get top selling products
     */
    private function getTopSellingProducts(array $productSales, int $limit): array
    {
        uasort($productSales, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        
        return array_slice($productSales, 0, $limit, true);
    }

    /**
     * Identify high value customers
     */
    private function identifyHighValueCustomers(): array
    {
        $query = "SELECT customer_id
                  FROM analytics_events
                  WHERE event_type = 'order_completed'
                    AND event_timestamp >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                  GROUP BY customer_id
                  HAVING SUM(JSON_EXTRACT(event_data, '$.total_amount')) > 1000";
        
        return array_column(QueryBuilder::select($query), 'customer_id');
    }

    /**
     * Identify at-risk customers
     */
    private function identifyAtRiskCustomers(): array
    {
        $query = "SELECT customer_id
                  FROM analytics_events
                  WHERE event_type = 'customer_login'
                    AND customer_id IS NOT NULL
                  GROUP BY customer_id
                  HAVING MAX(event_timestamp) < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        
        return array_column(QueryBuilder::select($query), 'customer_id');
    }

    /**
     * Identify new customers
     */
    private function identifyNewCustomers(): array
    {
        $query = "SELECT customer_id
                  FROM analytics_events
                  WHERE event_type = 'customer_registered'
                    AND event_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        return array_column(QueryBuilder::select($query), 'customer_id');
    }

    /**
     * Identify loyal customers
     */
    private function identifyLoyalCustomers(): array
    {
        $query = "SELECT customer_id
                  FROM analytics_events
                  WHERE event_type = 'order_completed'
                    AND event_timestamp >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                  GROUP BY customer_id
                  HAVING COUNT(*) >= 5";
        
        return array_column(QueryBuilder::select($query), 'customer_id');
    }

    /**
     * Update segment membership
     */
    private function updateSegmentMembership(string $segmentType, array $customerIds): void
    {
        // This would update the customer segments in the analytics_segments table
        // Implementation would depend on how segments are stored and managed
    }
}