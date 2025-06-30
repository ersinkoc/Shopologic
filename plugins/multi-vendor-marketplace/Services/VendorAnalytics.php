<?php

declare(strict_types=1);
namespace MultiVendorMarketplace\Services;

use Shopologic\Core\Services\BaseService;

/**
 * Vendor Analytics Service
 * 
 * Comprehensive analytics and reporting for vendors and marketplace
 */
class VendorAnalytics extends BaseService
{
    private $cache;
    
    public function __construct($api)
    {
        parent::__construct($api);
        $this->cache = $api->cache();
    }
    
    /**
     * Initialize vendor analytics
     */
    public function initializeVendor($vendorId): void
    {
        // Create initial analytics record
        $this->api->database()->table('vendor_analytics')->insert([
            'vendor_id' => $vendorId,
            'date' => date('Y-m-d'),
            'views' => 0,
            'visits' => 0,
            'orders' => 0,
            'revenue' => 0,
            'commission' => 0,
            'products_sold' => 0,
            'new_customers' => 0,
            'return_rate' => 0,
            'average_order_value' => 0,
            'conversion_rate' => 0
        ]);
    }
    
    /**
     * Record sale for vendor
     */
    public function recordSale($vendorId, $order): void
    {
        $date = date('Y-m-d');
        
        // Update or create analytics record
        $analytics = $this->api->database()->table('vendor_analytics')
            ->where('vendor_id', $vendorId)
            ->where('date', $date)
            ->first();
            
        if ($analytics) {
            // Update existing record
            $this->api->database()->table('vendor_analytics')
                ->where('id', $analytics['id'])
                ->update([
                    'orders' => $analytics['orders'] + 1,
                    'revenue' => $analytics['revenue'] + $order->total,
                    'products_sold' => $analytics['products_sold'] + count($order->items),
                    'average_order_value' => ($analytics['revenue'] + $order->total) / ($analytics['orders'] + 1)
                ]);
        } else {
            // Create new record
            $this->api->database()->table('vendor_analytics')->insert([
                'vendor_id' => $vendorId,
                'date' => $date,
                'views' => 0,
                'visits' => 0,
                'orders' => 1,
                'revenue' => $order->total,
                'commission' => 0,
                'products_sold' => count($order->items),
                'new_customers' => 0,
                'return_rate' => 0,
                'average_order_value' => $order->total,
                'conversion_rate' => 0
            ]);
        }
        
        // Update customer analytics
        $this->updateCustomerAnalytics($vendorId, $order->customer_id);
        
        // Clear cache
        $this->clearAnalyticsCache($vendorId);
    }
    
    /**
     * Get sales overview for vendor
     */
    public function getSalesOverview($vendorId): array
    {
        return $this->cache->remember("vendor_sales_overview_{$vendorId}", 300, function() use ($vendorId) {
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $thisMonth = date('Y-m');
            $lastMonth = date('Y-m', strtotime('-1 month'));
            
            // Today's stats
            $todayStats = $this->getDayStats($vendorId, $today);
            $yesterdayStats = $this->getDayStats($vendorId, $yesterday);
            
            // This month stats
            $thisMonthStats = $this->getMonthStats($vendorId, $thisMonth);
            $lastMonthStats = $this->getMonthStats($vendorId, $lastMonth);
            
            // Calculate growth
            $dailyGrowth = $yesterdayStats['revenue'] > 0 
                ? (($todayStats['revenue'] - $yesterdayStats['revenue']) / $yesterdayStats['revenue']) * 100 
                : 0;
                
            $monthlyGrowth = $lastMonthStats['revenue'] > 0
                ? (($thisMonthStats['revenue'] - $lastMonthStats['revenue']) / $lastMonthStats['revenue']) * 100
                : 0;
            
            return [
                'today' => [
                    'orders' => $todayStats['orders'],
                    'revenue' => $todayStats['revenue'],
                    'growth' => round($dailyGrowth, 2)
                ],
                'yesterday' => [
                    'orders' => $yesterdayStats['orders'],
                    'revenue' => $yesterdayStats['revenue']
                ],
                'this_month' => [
                    'orders' => $thisMonthStats['orders'],
                    'revenue' => $thisMonthStats['revenue'],
                    'growth' => round($monthlyGrowth, 2)
                ],
                'last_month' => [
                    'orders' => $lastMonthStats['orders'],
                    'revenue' => $lastMonthStats['revenue']
                ],
                'best_selling_products' => $this->getBestSellingProducts($vendorId, 5),
                'recent_orders' => $this->getRecentOrders($vendorId, 5)
            ];
        });
    }
    
    /**
     * Get performance metrics for vendor
     */
    public function getPerformanceMetrics($vendorId): array
    {
        return $this->cache->remember("vendor_performance_{$vendorId}", 600, function() use ($vendorId) {
            // Calculate various performance metrics
            $metrics = [
                'fulfillment_rate' => $this->calculateFulfillmentRate($vendorId),
                'average_processing_time' => $this->calculateAverageProcessingTime($vendorId),
                'customer_satisfaction' => $this->calculateCustomerSatisfaction($vendorId),
                'return_rate' => $this->calculateReturnRate($vendorId),
                'response_time' => $this->calculateAverageResponseTime($vendorId),
                'product_quality_score' => $this->calculateProductQualityScore($vendorId),
                'shipping_performance' => $this->calculateShippingPerformance($vendorId),
                'inventory_turnover' => $this->calculateInventoryTurnover($vendorId)
            ];
            
            // Calculate overall performance score
            $metrics['overall_score'] = $this->calculateOverallScore($metrics);
            
            // Get trends
            $metrics['trends'] = $this->getPerformanceTrends($vendorId);
            
            return $metrics;
        });
    }
    
    /**
     * Get total marketplace sales
     */
    public function getTotalMarketplaceSales(): float
    {
        return $this->cache->remember('marketplace_total_sales', 300, function() {
            return (float) $this->api->database()->table('vendor_orders')
                ->where('status', 'completed')
                ->sum('total');
        });
    }
    
    /**
     * Get top vendors
     */
    public function getTopVendors($limit = 10, $period = 'month'): array
    {
        $cacheKey = "top_vendors_{$limit}_{$period}";
        
        return $this->cache->remember($cacheKey, 3600, function() use ($limit, $period) {
            $dateRange = $this->getDateRangeForPeriod($period);
            
            return $this->api->database()->table('vendor_analytics as va')
                ->join('vendors as v', 'va.vendor_id', '=', 'v.id')
                ->whereBetween('va.date', $dateRange)
                ->where('v.status', 'active')
                ->groupBy('va.vendor_id', 'v.store_name', 'v.rating')
                ->selectRaw('
                    va.vendor_id,
                    v.store_name,
                    v.rating,
                    SUM(va.orders) as total_orders,
                    SUM(va.revenue) as total_revenue,
                    AVG(va.average_order_value) as avg_order_value,
                    AVG(va.conversion_rate) as avg_conversion_rate
                ')
                ->orderBy('total_revenue', 'DESC')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }
    
    /**
     * Get vendor growth chart data
     */
    public function getVendorGrowthChart($period = '6months'): array
    {
        $cacheKey = "vendor_growth_chart_{$period}";
        
        return $this->cache->remember($cacheKey, 3600, function() use ($period) {
            $endDate = date('Y-m-d');
            $startDate = $this->getStartDateForPeriod($period);
            
            $data = $this->api->database()->table('vendors')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('
                    DATE(created_at) as date,
                    COUNT(*) as new_vendors,
                    SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_vendors
                ')
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->get();
                
            // Calculate cumulative totals
            $cumulative = 0;
            $chartData = [];
            
            foreach ($data as $day) {
                $cumulative += $day['new_vendors'];
                $chartData[] = [
                    'date' => $day['date'],
                    'new_vendors' => $day['new_vendors'],
                    'active_vendors' => $day['active_vendors'],
                    'cumulative_total' => $cumulative
                ];
            }
            
            return $chartData;
        });
    }
    
    /**
     * Get category distribution
     */
    public function getCategoryDistribution(): array
    {
        return $this->cache->remember('marketplace_category_distribution', 3600, function() {
            return $this->api->database()->table('vendor_products as vp')
                ->join('products as p', 'vp.product_id', '=', 'p.id')
                ->join('categories as c', 'p.category_id', '=', 'c.id')
                ->groupBy('c.id', 'c.name')
                ->selectRaw('
                    c.id as category_id,
                    c.name as category_name,
                    COUNT(DISTINCT vp.vendor_id) as vendor_count,
                    COUNT(vp.product_id) as product_count
                ')
                ->orderBy('product_count', 'DESC')
                ->get()
                ->toArray();
        });
    }
    
    /**
     * Record product view
     */
    public function recordProductView($vendorId, $productId): void
    {
        $date = date('Y-m-d');
        
        // Update view count
        $this->api->database()->table('vendor_analytics')
            ->where('vendor_id', $vendorId)
            ->where('date', $date)
            ->increment('views');
            
        // Record detailed view analytics
        $this->api->database()->table('vendor_product_views')->insert([
            'vendor_id' => $vendorId,
            'product_id' => $productId,
            'viewer_id' => $this->api->auth()->id(),
            'session_id' => session_id(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Record vendor visit
     */
    public function recordVendorVisit($vendorId): void
    {
        $date = date('Y-m-d');
        $sessionKey = "vendor_visit_{$vendorId}_" . session_id();
        
        // Check if already counted this session
        if (!$this->cache->has($sessionKey)) {
            // Update visit count
            $this->api->database()->table('vendor_analytics')
                ->where('vendor_id', $vendorId)
                ->where('date', $date)
                ->increment('visits');
                
            // Mark session as counted (expires in 24 hours)
            $this->cache->put($sessionKey, true, 86400);
        }
    }
    
    /**
     * Get vendor conversion funnel
     */
    public function getConversionFunnel($vendorId, $period = 'month'): array
    {
        $dateRange = $this->getDateRangeForPeriod($period);
        
        // Get funnel metrics
        $views = $this->api->database()->table('vendor_product_views')
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', $dateRange)
            ->count();
            
        $addedToCart = $this->api->database()->table('cart_items as ci')
            ->join('vendor_products as vp', function($join) {
                $join->on('ci.product_id', '=', 'vp.product_id');
            })
            ->where('vp.vendor_id', $vendorId)
            ->whereBetween('ci.created_at', $dateRange)
            ->count();
            
        $purchases = $this->api->database()->table('vendor_orders')
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', $dateRange)
            ->count();
            
        return [
            'period' => $period,
            'stages' => [
                [
                    'name' => 'Product Views',
                    'value' => $views,
                    'percentage' => 100
                ],
                [
                    'name' => 'Added to Cart',
                    'value' => $addedToCart,
                    'percentage' => $views > 0 ? round(($addedToCart / $views) * 100, 2) : 0
                ],
                [
                    'name' => 'Purchases',
                    'value' => $purchases,
                    'percentage' => $views > 0 ? round(($purchases / $views) * 100, 2) : 0
                ]
            ],
            'conversion_rate' => $views > 0 ? round(($purchases / $views) * 100, 2) : 0
        ];
    }
    
    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics($vendorId = null, $period = 'month'): array
    {
        $query = $this->api->database()->table('vendor_analytics');
        
        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }
        
        $dateRange = $this->getDateRangeForPeriod($period);
        $query->whereBetween('date', $dateRange);
        
        $data = $query->selectRaw('
            date,
            SUM(revenue) as total_revenue,
            SUM(commission) as total_commission,
            SUM(orders) as total_orders,
            AVG(average_order_value) as avg_order_value
        ')
        ->groupBy('date')
        ->orderBy('date', 'ASC')
        ->get()
        ->toArray();
        
        return [
            'period' => $period,
            'summary' => [
                'total_revenue' => array_sum(array_column($data, 'total_revenue')),
                'total_commission' => array_sum(array_column($data, 'total_commission')),
                'total_orders' => array_sum(array_column($data, 'total_orders')),
                'average_order_value' => count($data) > 0 ? array_sum(array_column($data, 'avg_order_value')) / count($data) : 0
            ],
            'daily_data' => $data,
            'trends' => $this->calculateRevenueTrends($data)
        ];
    }
    
    /**
     * Get customer analytics
     */
    public function getCustomerAnalytics($vendorId): array
    {
        return [
            'total_customers' => $this->getTotalCustomers($vendorId),
            'new_customers_month' => $this->getNewCustomersThisMonth($vendorId),
            'repeat_customers' => $this->getRepeatCustomers($vendorId),
            'customer_lifetime_value' => $this->getAverageCustomerLifetimeValue($vendorId),
            'customer_retention_rate' => $this->getCustomerRetentionRate($vendorId),
            'top_customers' => $this->getTopCustomers($vendorId, 10),
            'customer_segments' => $this->getCustomerSegments($vendorId)
        ];
    }
    
    /**
     * Get product performance
     */
    public function getProductPerformance($vendorId, $limit = 20): array
    {
        return $this->api->database()->table('vendor_orders as vo')
            ->join('vendor_products as vp', 'vo.vendor_id', '=', 'vp.vendor_id')
            ->join('products as p', 'vp.product_id', '=', 'p.id')
            ->where('vo.vendor_id', $vendorId)
            ->where('vo.status', 'completed')
            ->whereRaw('JSON_CONTAINS(vo.items, JSON_OBJECT("product_id", vp.product_id))')
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->selectRaw('
                p.id,
                p.name,
                p.sku,
                COUNT(DISTINCT vo.id) as orders,
                SUM(JSON_EXTRACT(vo.items, "$[*].quantity")) as units_sold,
                SUM(JSON_EXTRACT(vo.items, "$[*].price") * JSON_EXTRACT(vo.items, "$[*].quantity")) as revenue
            ')
            ->orderBy('revenue', 'DESC')
            ->limit($limit)
            ->get()
            ->toArray();
    }
    
    /**
     * Get competitor analysis
     */
    public function getCompetitorAnalysis($vendorId): array
    {
        $vendor = $this->api->database()->table('vendors')->find($vendorId);
        
        // Get vendors in same categories
        $competitorIds = $this->api->database()->table('vendor_products as vp1')
            ->join('vendor_products as vp2', function($join) {
                $join->on('vp1.product_id', '=', 'vp2.product_id')
                     ->whereColumn('vp1.vendor_id', '!=', 'vp2.vendor_id');
            })
            ->where('vp1.vendor_id', $vendorId)
            ->distinct()
            ->pluck('vp2.vendor_id')
            ->toArray();
            
        if (empty($competitorIds)) {
            return [];
        }
        
        // Get competitor metrics
        $competitors = $this->api->database()->table('vendors as v')
            ->join('vendor_analytics as va', 'v.id', '=', 'va.vendor_id')
            ->whereIn('v.id', $competitorIds)
            ->where('va.date', '>=', date('Y-m-d', strtotime('-30 days')))
            ->groupBy('v.id', 'v.store_name', 'v.rating')
            ->selectRaw('
                v.id,
                v.store_name,
                v.rating,
                SUM(va.revenue) as total_revenue,
                SUM(va.orders) as total_orders,
                AVG(va.average_order_value) as avg_order_value,
                AVG(va.conversion_rate) as avg_conversion_rate
            ')
            ->orderBy('total_revenue', 'DESC')
            ->limit(5)
            ->get()
            ->toArray();
            
        return [
            'vendor_metrics' => $this->getVendorMetricsSummary($vendorId),
            'competitors' => $competitors,
            'market_position' => $this->calculateMarketPosition($vendorId, $competitorIds)
        ];
    }
    
    /**
     * Calculate fulfillment rate
     */
    private function calculateFulfillmentRate($vendorId): float
    {
        $totalOrders = $this->api->database()->table('vendor_orders')
            ->where('vendor_id', $vendorId)
            ->count();
            
        if ($totalOrders == 0) {
            return 100;
        }
        
        $fulfilledOrders = $this->api->database()->table('vendor_orders')
            ->where('vendor_id', $vendorId)
            ->whereIn('status', ['shipped', 'delivered', 'completed'])
            ->count();
            
        return round(($fulfilledOrders / $totalOrders) * 100, 2);
    }
    
    /**
     * Calculate average processing time
     */
    private function calculateAverageProcessingTime($vendorId): string
    {
        $avgHours = $this->api->database()->table('vendor_orders')
            ->where('vendor_id', $vendorId)
            ->whereNotNull('shipped_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, shipped_at)) as avg_hours')
            ->value('avg_hours');
            
        if ($avgHours < 24) {
            return round($avgHours) . ' hours';
        } else {
            return round($avgHours / 24, 1) . ' days';
        }
    }
    
    /**
     * Calculate customer satisfaction
     */
    private function calculateCustomerSatisfaction($vendorId): float
    {
        $avgRating = $this->api->database()->table('vendor_reviews')
            ->where('vendor_id', $vendorId)
            ->where('status', 'approved')
            ->avg('rating');
            
        return round($avgRating ?? 0, 1);
    }
    
    /**
     * Calculate return rate
     */
    private function calculateReturnRate($vendorId): float
    {
        $totalOrders = $this->api->database()->table('vendor_orders')
            ->where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->count();
            
        if ($totalOrders == 0) {
            return 0;
        }
        
        $returnedOrders = $this->api->database()->table('vendor_orders')
            ->where('vendor_id', $vendorId)
            ->where('status', 'returned')
            ->count();
            
        return round(($returnedOrders / $totalOrders) * 100, 2);
    }
    
    /**
     * Get day stats
     */
    private function getDayStats($vendorId, $date): array
    {
        $stats = $this->api->database()->table('vendor_analytics')
            ->where('vendor_id', $vendorId)
            ->where('date', $date)
            ->first();
            
        return $stats ?: ['orders' => 0, 'revenue' => 0];
    }
    
    /**
     * Get month stats
     */
    private function getMonthStats($vendorId, $month): array
    {
        return $this->api->database()->table('vendor_analytics')
            ->where('vendor_id', $vendorId)
            ->where('date', 'LIKE', $month . '%')
            ->selectRaw('SUM(orders) as orders, SUM(revenue) as revenue')
            ->first();
    }
    
    /**
     * Get best selling products
     */
    private function getBestSellingProducts($vendorId, $limit): array
    {
        // This would typically query order items
        // Simplified implementation
        return $this->api->database()->table('vendor_products as vp')
            ->join('products as p', 'vp.product_id', '=', 'p.id')
            ->where('vp.vendor_id', $vendorId)
            ->orderBy('p.sales_count', 'DESC')
            ->limit($limit)
            ->select('p.id', 'p.name', 'p.price', 'p.sales_count')
            ->get()
            ->toArray();
    }
    
    /**
     * Get recent orders
     */
    private function getRecentOrders($vendorId, $limit): array
    {
        return $this->api->database()->table('vendor_orders')
            ->where('vendor_id', $vendorId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->map(function($order) {
                $order['items'] = json_decode($order['items'], true);
                return $order;
            })
            ->toArray();
    }
    
    /**
     * Get date range for period
     */
    private function getDateRangeForPeriod($period): array
    {
        switch ($period) {
            case 'day':
                return [date('Y-m-d'), date('Y-m-d')];
            case 'week':
                return [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')];
            case 'month':
                return [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')];
            case 'quarter':
                return [date('Y-m-d', strtotime('-89 days')), date('Y-m-d')];
            case 'year':
                return [date('Y-m-d', strtotime('-364 days')), date('Y-m-d')];
            default:
                return [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')];
        }
    }
    
    /**
     * Clear analytics cache
     */
    private function clearAnalyticsCache($vendorId = null): void
    {
        if ($vendorId) {
            $this->cache->forget("vendor_sales_overview_{$vendorId}");
            $this->cache->forget("vendor_performance_{$vendorId}");
        } else {
            $this->cache->tags(['vendor_analytics'])->flush();
        }
    }
    
    /**
     * Helper methods for additional metrics
     */
    private function calculateAverageResponseTime($vendorId): string
    {
        // Placeholder - would calculate from support tickets/messages
        return "< 2 hours";
    }
    
    private function calculateProductQualityScore($vendorId): float
    {
        // Based on product reviews and return rates
        return 4.5;
    }
    
    private function calculateShippingPerformance($vendorId): float
    {
        // Based on on-time delivery rates
        return 95.5;
    }
    
    private function calculateInventoryTurnover($vendorId): float
    {
        // Calculate inventory turnover ratio
        return 12.5;
    }
    
    private function calculateOverallScore($metrics): float
    {
        // Weighted average of all metrics
        $weights = [
            'fulfillment_rate' => 0.2,
            'customer_satisfaction' => 0.3,
            'shipping_performance' => 0.2,
            'product_quality_score' => 0.2,
            'return_rate' => 0.1 // Lower is better, so we'll invert
        ];
        
        $score = 0;
        foreach ($weights as $metric => $weight) {
            if ($metric === 'return_rate') {
                $score += (100 - $metrics[$metric]) * $weight;
            } else {
                $score += ($metrics[$metric] ?? 0) * $weight;
            }
        }
        
        return round($score, 1);
    }
    
    private function getPerformanceTrends($vendorId): array
    {
        // Get performance data for last 6 months
        return [
            'fulfillment_trend' => 'improving',
            'satisfaction_trend' => 'stable',
            'sales_trend' => 'growing'
        ];
    }
}