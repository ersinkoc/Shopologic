<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Services;

use AdvancedAnalytics\Repositories\EventRepository;
use AdvancedAnalytics\Repositories\MetricsRepository;
use Shopologic\Core\Database\QueryBuilder;

class MetricsCalculator\n{
    private EventRepository $eventRepository;
    private MetricsRepository $metricsRepository;

    public function __construct(
        EventRepository $eventRepository,
        MetricsRepository $metricsRepository
    ) {
        $this->eventRepository = $eventRepository;
        $this->metricsRepository = $metricsRepository;
    }

    /**
     * Calculate daily metrics
     */
    public function calculateDailyMetrics(string $date = null): void
    {
        $date = $date ?? date('Y-m-d');
        
        $metrics = [
            'revenue' => $this->calculateDailyRevenue($date),
            'orders' => $this->calculateDailyOrders($date),
            'sessions' => $this->calculateDailySessions($date),
            'page_views' => $this->calculateDailyPageViews($date),
            'unique_visitors' => $this->calculateDailyUniqueVisitors($date),
            'conversion_rate' => $this->calculateDailyConversionRate($date),
            'average_order_value' => $this->calculateDailyAverageOrderValue($date),
            'bounce_rate' => $this->calculateDailyBounceRate($date),
            'new_customers' => $this->calculateDailyNewCustomers($date),
            'returning_customers' => $this->calculateDailyReturningCustomers($date)
        ];

        foreach ($metrics as $metricName => $value) {
            $this->storeMetric($metricName, 'daily', $date, null, null, $value);
        }
    }

    /**
     * Calculate hourly metrics
     */
    public function calculateHourlyMetrics(string $datetime = null): void
    {
        $datetime = $datetime ?? date('Y-m-d H:00:00');
        $date = date('Y-m-d', strtotime($datetime));
        $hour = date('H', strtotime($datetime));
        
        $metrics = [
            'revenue' => $this->calculateHourlyRevenue($datetime),
            'orders' => $this->calculateHourlyOrders($datetime),
            'sessions' => $this->calculateHourlySessions($datetime),
            'page_views' => $this->calculateHourlyPageViews($datetime),
            'conversion_rate' => $this->calculateHourlyConversionRate($datetime)
        ];

        foreach ($metrics as $metricName => $value) {
            $this->storeMetric($metricName, 'hourly', $date, 'hour', $hour, $value);
        }
    }

    /**
     * Calculate product metrics
     */
    public function calculateProductMetrics(string $date = null): void
    {
        $date = $date ?? date('Y-m-d');
        
        $products = $this->getProductsWithActivity($date);
        
        foreach ($products as $productId) {
            $metrics = [
                'views' => $this->calculateProductViews($productId, $date),
                'sales' => $this->calculateProductSales($productId, $date),
                'revenue' => $this->calculateProductRevenue($productId, $date),
                'conversion_rate' => $this->calculateProductConversionRate($productId, $date),
                'cart_additions' => $this->calculateProductCartAdditions($productId, $date)
            ];

            foreach ($metrics as $metricName => $value) {
                $this->storeMetric($metricName, 'product', $date, 'product', $productId, $value);
            }
        }
    }

    /**
     * Calculate customer metrics
     */
    public function calculateCustomerMetrics(string $date = null): void
    {
        $date = $date ?? date('Y-m-d');
        
        $metrics = [
            'customer_acquisition_cost' => $this->calculateCustomerAcquisitionCost($date),
            'customer_lifetime_value' => $this->calculateAverageCustomerLifetimeValue($date),
            'churn_rate' => $this->calculateChurnRate($date),
            'repeat_customer_rate' => $this->calculateRepeatCustomerRate($date),
            'average_order_frequency' => $this->calculateAverageOrderFrequency($date)
        ];

        foreach ($metrics as $metricName => $value) {
            $this->storeMetric($metricName, 'customer', $date, null, null, $value);
        }
    }

    /**
     * Calculate real-time metrics
     */
    public function calculateRealtimeMetrics(): array
    {
        $now = date('Y-m-d H:i:s');
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        return [
            'active_sessions' => $this->getActiveSessionsCount(),
            'current_visitors' => $this->getCurrentVisitorsCount(),
            'hourly_revenue' => $this->calculateRevenueInPeriod($oneHourAgo, $now),
            'hourly_orders' => $this->calculateOrdersInPeriod($oneHourAgo, $now),
            'hourly_page_views' => $this->calculatePageViewsInPeriod($oneHourAgo, $now),
            'top_pages' => $this->getTopPagesLastHour(),
            'traffic_sources' => $this->getTrafficSourcesLastHour(),
            'device_breakdown' => $this->getDeviceBreakdownLastHour()
        ];
    }

    /**
     * Calculate daily revenue
     */
    private function calculateDailyRevenue(string $date): float
    {
        $query = "SELECT COALESCE(SUM(JSON_EXTRACT(event_data, '$.total_amount')), 0) as revenue
                  FROM analytics_events 
                  WHERE event_type = 'order_completed'
                    AND DATE(event_timestamp) = ?";
        
        $result = QueryBuilder::select($query, [$date])[0] ?? [];
        return (float)($result['revenue'] ?? 0);
    }

    /**
     * Calculate daily orders
     */
    private function calculateDailyOrders(string $date): int
    {
        $query = "SELECT COUNT(*) as orders
                  FROM analytics_events 
                  WHERE event_type = 'order_completed'
                    AND DATE(event_timestamp) = ?";
        
        $result = QueryBuilder::select($query, [$date])[0] ?? [];
        return (int)($result['orders'] ?? 0);
    }

    /**
     * Calculate daily sessions
     */
    private function calculateDailySessions(string $date): int
    {
        $query = "SELECT COUNT(*) as sessions
                  FROM analytics_sessions 
                  WHERE DATE(session_start) = ?";
        
        $result = QueryBuilder::select($query, [$date])[0] ?? [];
        return (int)($result['sessions'] ?? 0);
    }

    /**
     * Calculate daily page views
     */
    private function calculateDailyPageViews(string $date): int
    {
        $query = "SELECT COUNT(*) as page_views
                  FROM analytics_events 
                  WHERE event_type = 'page_viewed'
                    AND DATE(event_timestamp) = ?";
        
        $result = QueryBuilder::select($query, [$date])[0] ?? [];
        return (int)($result['page_views'] ?? 0);
    }

    /**
     * Calculate daily unique visitors
     */
    private function calculateDailyUniqueVisitors(string $date): int
    {
        $query = "SELECT COUNT(DISTINCT session_id) as unique_visitors
                  FROM analytics_events 
                  WHERE DATE(event_timestamp) = ?";
        
        $result = QueryBuilder::select($query, [$date])[0] ?? [];
        return (int)($result['unique_visitors'] ?? 0);
    }

    /**
     * Calculate daily conversion rate
     */
    private function calculateDailyConversionRate(string $date): float
    {
        $sessions = $this->calculateDailySessions($date);
        $orders = $this->calculateDailyOrders($date);
        
        return $sessions > 0 ? ($orders / $sessions) * 100 : 0;
    }

    /**
     * Calculate daily average order value
     */
    private function calculateDailyAverageOrderValue(string $date): float
    {
        $revenue = $this->calculateDailyRevenue($date);
        $orders = $this->calculateDailyOrders($date);
        
        return $orders > 0 ? $revenue / $orders : 0;
    }

    /**
     * Calculate daily bounce rate
     */
    private function calculateDailyBounceRate(string $date): float
    {
        $query = "SELECT 
                    COUNT(*) as total_sessions,
                    SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounce_sessions
                  FROM analytics_sessions 
                  WHERE DATE(session_start) = ?";
        
        $result = QueryBuilder::select($query, [$date])[0] ?? [];
        $totalSessions = (int)($result['total_sessions'] ?? 0);
        $bounceSessions = (int)($result['bounce_sessions'] ?? 0);
        
        return $totalSessions > 0 ? ($bounceSessions / $totalSessions) * 100 : 0;
    }

    /**
     * Calculate daily new customers
     */
    private function calculateDailyNewCustomers(string $date): int
    {
        $query = "SELECT COUNT(*) as new_customers
                  FROM analytics_events 
                  WHERE event_type = 'customer_registered'
                    AND DATE(event_timestamp) = ?";
        
        $result = QueryBuilder::select($query, [$date])[0] ?? [];
        return (int)($result['new_customers'] ?? 0);
    }

    /**
     * Calculate daily returning customers
     */
    private function calculateDailyReturningCustomers(string $date): int
    {
        $query = "SELECT COUNT(DISTINCT customer_id) as returning_customers
                  FROM analytics_events 
                  WHERE event_type = 'customer_login'
                    AND DATE(event_timestamp) = ?
                    AND customer_id IS NOT NULL";
        
        $result = QueryBuilder::select($query, [$date])[0] ?? [];
        return (int)($result['returning_customers'] ?? 0);
    }

    /**
     * Calculate product views
     */
    private function calculateProductViews(int $productId, string $date): int
    {
        $query = "SELECT COUNT(*) as views
                  FROM analytics_events 
                  WHERE event_type = 'product_viewed'
                    AND JSON_EXTRACT(event_data, '$.product_id') = ?
                    AND DATE(event_timestamp) = ?";
        
        $result = QueryBuilder::select($query, [$productId, $date])[0] ?? [];
        return (int)($result['views'] ?? 0);
    }

    /**
     * Calculate product sales
     */
    private function calculateProductSales(int $productId, string $date): int
    {
        $query = "SELECT COALESCE(SUM(JSON_EXTRACT(event_data, '$.quantity')), 0) as sales
                  FROM analytics_events 
                  WHERE event_type = 'order_item_purchased'
                    AND JSON_EXTRACT(event_data, '$.product_id') = ?
                    AND DATE(event_timestamp) = ?";
        
        $result = QueryBuilder::select($query, [$productId, $date])[0] ?? [];
        return (int)($result['sales'] ?? 0);
    }

    /**
     * Calculate product revenue
     */
    private function calculateProductRevenue(int $productId, string $date): float
    {
        $query = "SELECT COALESCE(SUM(JSON_EXTRACT(event_data, '$.total_price')), 0) as revenue
                  FROM analytics_events 
                  WHERE event_type = 'order_item_purchased'
                    AND JSON_EXTRACT(event_data, '$.product_id') = ?
                    AND DATE(event_timestamp) = ?";
        
        $result = QueryBuilder::select($query, [$productId, $date])[0] ?? [];
        return (float)($result['revenue'] ?? 0);
    }

    /**
     * Calculate product conversion rate
     */
    private function calculateProductConversionRate(int $productId, string $date): float
    {
        $views = $this->calculateProductViews($productId, $date);
        $sales = $this->calculateProductSales($productId, $date);
        
        return $views > 0 ? ($sales / $views) * 100 : 0;
    }

    /**
     * Get active sessions count
     */
    private function getActiveSessionsCount(): int
    {
        $timeoutMinutes = 30;
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));
        
        $query = "SELECT COUNT(*) as active_sessions
                  FROM analytics_sessions 
                  WHERE session_end IS NULL
                    AND updated_at >= ?";
        
        $result = QueryBuilder::select($query, [$cutoffTime])[0] ?? [];
        return (int)($result['active_sessions'] ?? 0);
    }

    /**
     * Get current visitors count
     */
    private function getCurrentVisitorsCount(): int
    {
        $last15Minutes = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        
        $query = "SELECT COUNT(DISTINCT session_id) as current_visitors
                  FROM analytics_events 
                  WHERE event_timestamp >= ?";
        
        $result = QueryBuilder::select($query, [$last15Minutes])[0] ?? [];
        return (int)($result['current_visitors'] ?? 0);
    }

    /**
     * Store metric value
     */
    private function storeMetric(
        string $metricName,
        string $metricType,
        string $date,
        ?string $dimensionType,
        $dimensionValue,
        float $value
    ): void {
        $this->metricsRepository->store([
            'metric_name' => $metricName,
            'metric_type' => $metricType,
            'metric_date' => $date,
            'dimension_type' => $dimensionType,
            'dimension_value' => (string)$dimensionValue,
            'metric_value' => $value
        ]);
    }

    /**
     * Get products with activity
     */
    private function getProductsWithActivity(string $date): array
    {
        $query = "SELECT DISTINCT JSON_EXTRACT(event_data, '$.product_id') as product_id
                  FROM analytics_events 
                  WHERE event_type IN ('product_viewed', 'order_item_purchased', 'product_added_to_cart')
                    AND DATE(event_timestamp) = ?
                    AND JSON_EXTRACT(event_data, '$.product_id') IS NOT NULL";
        
        $results = QueryBuilder::select($query, [$date]);
        return array_column($results, 'product_id');
    }

    /**
     * Calculate revenue in period
     */
    private function calculateRevenueInPeriod(string $startTime, string $endTime): float
    {
        $query = "SELECT COALESCE(SUM(JSON_EXTRACT(event_data, '$.total_amount')), 0) as revenue
                  FROM analytics_events 
                  WHERE event_type = 'order_completed'
                    AND event_timestamp BETWEEN ? AND ?";
        
        $result = QueryBuilder::select($query, [$startTime, $endTime])[0] ?? [];
        return (float)($result['revenue'] ?? 0);
    }

    /**
     * Calculate orders in period
     */
    private function calculateOrdersInPeriod(string $startTime, string $endTime): int
    {
        $query = "SELECT COUNT(*) as orders
                  FROM analytics_events 
                  WHERE event_type = 'order_completed'
                    AND event_timestamp BETWEEN ? AND ?";
        
        $result = QueryBuilder::select($query, [$startTime, $endTime])[0] ?? [];
        return (int)($result['orders'] ?? 0);
    }

    /**
     * Calculate page views in period
     */
    private function calculatePageViewsInPeriod(string $startTime, string $endTime): int
    {
        $query = "SELECT COUNT(*) as page_views
                  FROM analytics_events 
                  WHERE event_type = 'page_viewed'
                    AND event_timestamp BETWEEN ? AND ?";
        
        $result = QueryBuilder::select($query, [$startTime, $endTime])[0] ?? [];
        return (int)($result['page_views'] ?? 0);
    }

    /**
     * Get top pages last hour
     */
    private function getTopPagesLastHour(): array
    {
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $query = "SELECT page_url, COUNT(*) as views
                  FROM analytics_events 
                  WHERE event_type = 'page_viewed'
                    AND event_timestamp >= ?
                  GROUP BY page_url
                  ORDER BY views DESC
                  LIMIT 10";
        
        return QueryBuilder::select($query, [$oneHourAgo]);
    }

    /**
     * Get traffic sources last hour
     */
    private function getTrafficSourcesLastHour(): array
    {
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $query = "SELECT utm_source, COUNT(DISTINCT session_id) as sessions
                  FROM analytics_events 
                  WHERE event_timestamp >= ?
                  GROUP BY utm_source
                  ORDER BY sessions DESC";
        
        return QueryBuilder::select($query, [$oneHourAgo]);
    }

    /**
     * Get device breakdown last hour
     */
    private function getDeviceBreakdownLastHour(): array
    {
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $query = "SELECT device_type, COUNT(DISTINCT session_id) as sessions
                  FROM analytics_events 
                  WHERE event_timestamp >= ?
                  GROUP BY device_type
                  ORDER BY sessions DESC";
        
        return QueryBuilder::select($query, [$oneHourAgo]);
    }
}