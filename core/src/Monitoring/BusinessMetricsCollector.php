<?php

declare(strict_types=1);

namespace Shopologic\Core\Monitoring;

use Shopologic\Core\Configuration\ConfigurationManager;

/**
 * Business Metrics Collector
 * 
 * Collects business-specific metrics like sales, orders, customers, inventory
 */
class BusinessMetricsCollector implements MetricsCollectorInterface
{
    private ConfigurationManager $config;
    private ?\PDO $connection = null;
    
    public function __construct()
    {
        $this->config = new ConfigurationManager();
    }
    
    /**
     * Collect business metrics
     */
    public function collect(): array
    {
        return [
            'sales' => $this->getSalesMetrics(),
            'orders' => $this->getOrderMetrics(),
            'customers' => $this->getCustomerMetrics(),
            'products' => $this->getProductMetrics(),
            'inventory' => $this->getInventoryMetrics(),
            'conversion' => $this->getConversionMetrics(),
            'revenue' => $this->getRevenueMetrics()
        ];
    }
    
    /**
     * Get sales metrics
     */
    private function getSalesMetrics(): array
    {
        $metrics = [
            'today' => [
                'total_sales' => 0,
                'order_count' => 0,
                'average_order_value' => 0
            ],
            'yesterday' => [
                'total_sales' => 0,
                'order_count' => 0,
                'average_order_value' => 0
            ],
            'this_week' => [
                'total_sales' => 0,
                'order_count' => 0,
                'average_order_value' => 0
            ],
            'this_month' => [
                'total_sales' => 0,
                'order_count' => 0,
                'average_order_value' => 0
            ],
            'growth' => [
                'daily_growth' => 0,
                'weekly_growth' => 0,
                'monthly_growth' => 0
            ]
        ];
        
        try {
            $connection = $this->getConnection();
            
            // Today's sales
            $todayResult = $connection->query("
                SELECT 
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as total_sales,
                    COALESCE(AVG(total_amount), 0) as avg_order_value
                FROM orders 
                WHERE DATE(created_at) = CURRENT_DATE 
                AND status != 'cancelled'
            ");
            
            if ($todayResult) {
                $todayData = $todayResult->fetch(\PDO::FETCH_ASSOC);
                $metrics['today'] = [
                    'total_sales' => (float)$todayData['total_sales'],
                    'order_count' => (int)$todayData['order_count'],
                    'average_order_value' => (float)$todayData['avg_order_value']
                ];
            }
            
            // Yesterday's sales
            $yesterdayResult = $connection->query("
                SELECT 
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as total_sales,
                    COALESCE(AVG(total_amount), 0) as avg_order_value
                FROM orders 
                WHERE DATE(created_at) = CURRENT_DATE - INTERVAL '1 day'
                AND status != 'cancelled'
            ");
            
            if ($yesterdayResult) {
                $yesterdayData = $yesterdayResult->fetch(\PDO::FETCH_ASSOC);
                $metrics['yesterday'] = [
                    'total_sales' => (float)$yesterdayData['total_sales'],
                    'order_count' => (int)$yesterdayData['order_count'],
                    'average_order_value' => (float)$yesterdayData['avg_order_value']
                ];
            }
            
            // This week's sales
            $weekResult = $connection->query("
                SELECT 
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as total_sales,
                    COALESCE(AVG(total_amount), 0) as avg_order_value
                FROM orders 
                WHERE created_at >= DATE_TRUNC('week', CURRENT_DATE)
                AND status != 'cancelled'
            ");
            
            if ($weekResult) {
                $weekData = $weekResult->fetch(\PDO::FETCH_ASSOC);
                $metrics['this_week'] = [
                    'total_sales' => (float)$weekData['total_sales'],
                    'order_count' => (int)$weekData['order_count'],
                    'average_order_value' => (float)$weekData['avg_order_value']
                ];
            }
            
            // This month's sales
            $monthResult = $connection->query("
                SELECT 
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as total_sales,
                    COALESCE(AVG(total_amount), 0) as avg_order_value
                FROM orders 
                WHERE created_at >= DATE_TRUNC('month', CURRENT_DATE)
                AND status != 'cancelled'
            ");
            
            if ($monthResult) {
                $monthData = $monthResult->fetch(\PDO::FETCH_ASSOC);
                $metrics['this_month'] = [
                    'total_sales' => (float)$monthData['total_sales'],
                    'order_count' => (int)$monthData['order_count'],
                    'average_order_value' => (float)$monthData['avg_order_value']
                ];
            }
            
            // Calculate growth rates
            if ($metrics['yesterday']['total_sales'] > 0) {
                $metrics['growth']['daily_growth'] = 
                    (($metrics['today']['total_sales'] - $metrics['yesterday']['total_sales']) / 
                     $metrics['yesterday']['total_sales']) * 100;
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get order metrics
     */
    private function getOrderMetrics(): array
    {
        $metrics = [
            'total_orders' => 0,
            'pending_orders' => 0,
            'processing_orders' => 0,
            'shipped_orders' => 0,
            'delivered_orders' => 0,
            'cancelled_orders' => 0,
            'refunded_orders' => 0,
            'status_breakdown' => [],
            'payment_methods' => [],
            'shipping_methods' => []
        ];
        
        try {
            $connection = $this->getConnection();
            
            // Order counts by status
            $statusResult = $connection->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM orders 
                WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
                GROUP BY status
                ORDER BY count DESC
            ");
            
            if ($statusResult) {
                while ($row = $statusResult->fetch(\PDO::FETCH_ASSOC)) {
                    $status = $row['status'];
                    $count = (int)$row['count'];
                    
                    $metrics['status_breakdown'][$status] = $count;
                    $metrics['total_orders'] += $count;
                    
                    // Map to specific metrics
                    switch ($status) {
                        case 'pending':
                            $metrics['pending_orders'] = $count;
                            break;
                        case 'processing':
                            $metrics['processing_orders'] = $count;
                            break;
                        case 'shipped':
                            $metrics['shipped_orders'] = $count;
                            break;
                        case 'delivered':
                            $metrics['delivered_orders'] = $count;
                            break;
                        case 'cancelled':
                            $metrics['cancelled_orders'] = $count;
                            break;
                        case 'refunded':
                            $metrics['refunded_orders'] = $count;
                            break;
                    }
                }
            }
            
            // Payment methods
            $paymentResult = $connection->query("
                SELECT 
                    payment_method,
                    COUNT(*) as count,
                    SUM(total_amount) as total_amount
                FROM orders 
                WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
                AND payment_method IS NOT NULL
                GROUP BY payment_method
                ORDER BY count DESC
            ");
            
            if ($paymentResult) {
                while ($row = $paymentResult->fetch(\PDO::FETCH_ASSOC)) {
                    $metrics['payment_methods'][] = [
                        'method' => $row['payment_method'],
                        'count' => (int)$row['count'],
                        'total_amount' => (float)$row['total_amount']
                    ];
                }
            }
            
            // Shipping methods
            $shippingResult = $connection->query("
                SELECT 
                    shipping_method,
                    COUNT(*) as count,
                    AVG(shipping_amount) as avg_cost
                FROM orders 
                WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
                AND shipping_method IS NOT NULL
                GROUP BY shipping_method
                ORDER BY count DESC
            ");
            
            if ($shippingResult) {
                while ($row = $shippingResult->fetch(\PDO::FETCH_ASSOC)) {
                    $metrics['shipping_methods'][] = [
                        'method' => $row['shipping_method'],
                        'count' => (int)$row['count'],
                        'average_cost' => (float)$row['avg_cost']
                    ];
                }
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get customer metrics
     */
    private function getCustomerMetrics(): array
    {
        $metrics = [
            'total_customers' => 0,
            'new_customers_today' => 0,
            'new_customers_this_week' => 0,
            'new_customers_this_month' => 0,
            'returning_customers' => 0,
            'customer_lifetime_value' => 0,
            'average_orders_per_customer' => 0,
            'top_customers' => []
        ];
        
        try {
            $connection = $this->getConnection();
            
            // Total customers
            $totalResult = $connection->query("SELECT COUNT(*) FROM customers");
            if ($totalResult) {
                $metrics['total_customers'] = (int)$totalResult->fetchColumn();
            }
            
            // New customers by period
            $newCustomersResult = $connection->query("
                SELECT 
                    COUNT(CASE WHEN DATE(created_at) = CURRENT_DATE THEN 1 END) as today,
                    COUNT(CASE WHEN created_at >= DATE_TRUNC('week', CURRENT_DATE) THEN 1 END) as this_week,
                    COUNT(CASE WHEN created_at >= DATE_TRUNC('month', CURRENT_DATE) THEN 1 END) as this_month
                FROM customers
            ");
            
            if ($newCustomersResult) {
                $newData = $newCustomersResult->fetch(\PDO::FETCH_ASSOC);
                $metrics['new_customers_today'] = (int)$newData['today'];
                $metrics['new_customers_this_week'] = (int)$newData['this_week'];
                $metrics['new_customers_this_month'] = (int)$newData['this_month'];
            }
            
            // Returning customers (customers with more than 1 order)
            $returningResult = $connection->query("
                SELECT COUNT(DISTINCT customer_id) 
                FROM orders 
                WHERE customer_id IN (
                    SELECT customer_id 
                    FROM orders 
                    GROUP BY customer_id 
                    HAVING COUNT(*) > 1
                )
            ");
            
            if ($returningResult) {
                $metrics['returning_customers'] = (int)$returningResult->fetchColumn();
            }
            
            // Customer lifetime value and average orders
            $clvResult = $connection->query("
                SELECT 
                    AVG(customer_total) as avg_lifetime_value,
                    AVG(order_count) as avg_orders_per_customer
                FROM (
                    SELECT 
                        customer_id,
                        SUM(total_amount) as customer_total,
                        COUNT(*) as order_count
                    FROM orders 
                    WHERE status != 'cancelled'
                    GROUP BY customer_id
                ) customer_stats
            ");
            
            if ($clvResult) {
                $clvData = $clvResult->fetch(\PDO::FETCH_ASSOC);
                $metrics['customer_lifetime_value'] = (float)$clvData['avg_lifetime_value'];
                $metrics['average_orders_per_customer'] = (float)$clvData['avg_orders_per_customer'];
            }
            
            // Top customers by total spent
            $topCustomersResult = $connection->query("
                SELECT 
                    c.id,
                    c.email,
                    c.first_name,
                    c.last_name,
                    COUNT(o.id) as order_count,
                    SUM(o.total_amount) as total_spent
                FROM customers c
                JOIN orders o ON c.id = o.customer_id
                WHERE o.status != 'cancelled'
                GROUP BY c.id, c.email, c.first_name, c.last_name
                ORDER BY total_spent DESC
                LIMIT 10
            ");
            
            if ($topCustomersResult) {
                while ($row = $topCustomersResult->fetch(\PDO::FETCH_ASSOC)) {
                    $metrics['top_customers'][] = [
                        'id' => (int)$row['id'],
                        'email' => $row['email'],
                        'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                        'order_count' => (int)$row['order_count'],
                        'total_spent' => (float)$row['total_spent']
                    ];
                }
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get product metrics
     */
    private function getProductMetrics(): array
    {
        $metrics = [
            'total_products' => 0,
            'active_products' => 0,
            'out_of_stock_products' => 0,
            'low_stock_products' => 0,
            'bestsellers' => [],
            'categories' => [],
            'new_products_this_month' => 0
        ];
        
        try {
            $connection = $this->getConnection();
            
            // Product counts
            $productCountsResult = $connection->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                    COUNT(CASE WHEN quantity = 0 AND track_quantity = true THEN 1 END) as out_of_stock,
                    COUNT(CASE WHEN quantity <= 10 AND track_quantity = true THEN 1 END) as low_stock,
                    COUNT(CASE WHEN created_at >= DATE_TRUNC('month', CURRENT_DATE) THEN 1 END) as new_this_month
                FROM products
            ");
            
            if ($productCountsResult) {
                $counts = $productCountsResult->fetch(\PDO::FETCH_ASSOC);
                $metrics['total_products'] = (int)$counts['total'];
                $metrics['active_products'] = (int)$counts['active'];
                $metrics['out_of_stock_products'] = (int)$counts['out_of_stock'];
                $metrics['low_stock_products'] = (int)$counts['low_stock'];
                $metrics['new_products_this_month'] = (int)$counts['new_this_month'];
            }
            
            // Bestsellers (by quantity sold in last 30 days)
            $bestsellersResult = $connection->query("
                SELECT 
                    p.id,
                    p.name,
                    p.sku,
                    p.price,
                    SUM(oi.quantity) as quantity_sold,
                    SUM(oi.quantity * oi.price) as revenue
                FROM products p
                JOIN order_items oi ON p.id = oi.product_id
                JOIN orders o ON oi.order_id = o.id
                WHERE o.created_at >= CURRENT_DATE - INTERVAL '30 days'
                AND o.status != 'cancelled'
                GROUP BY p.id, p.name, p.sku, p.price
                ORDER BY quantity_sold DESC
                LIMIT 10
            ");
            
            if ($bestsellersResult) {
                while ($row = $bestsellersResult->fetch(\PDO::FETCH_ASSOC)) {
                    $metrics['bestsellers'][] = [
                        'id' => (int)$row['id'],
                        'name' => $row['name'],
                        'sku' => $row['sku'],
                        'price' => (float)$row['price'],
                        'quantity_sold' => (int)$row['quantity_sold'],
                        'revenue' => (float)$row['revenue']
                    ];
                }
            }
            
            // Category performance
            $categoriesResult = $connection->query("
                SELECT 
                    c.id,
                    c.name,
                    c.slug,
                    COUNT(p.id) as product_count,
                    COALESCE(SUM(oi.quantity), 0) as total_sold
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id 
                    AND o.created_at >= CURRENT_DATE - INTERVAL '30 days'
                    AND o.status != 'cancelled'
                GROUP BY c.id, c.name, c.slug
                ORDER BY total_sold DESC
                LIMIT 10
            ");
            
            if ($categoriesResult) {
                while ($row = $categoriesResult->fetch(\PDO::FETCH_ASSOC)) {
                    $metrics['categories'][] = [
                        'id' => (int)$row['id'],
                        'name' => $row['name'],
                        'slug' => $row['slug'],
                        'product_count' => (int)$row['product_count'],
                        'total_sold' => (int)$row['total_sold']
                    ];
                }
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get inventory metrics
     */
    private function getInventoryMetrics(): array
    {
        $metrics = [
            'total_inventory_value' => 0,
            'low_stock_alerts' => 0,
            'out_of_stock_alerts' => 0,
            'negative_stock' => 0,
            'top_moving_products' => [],
            'slow_moving_products' => []
        ];
        
        try {
            $connection = $this->getConnection();
            
            // Inventory value and stock alerts
            $inventoryResult = $connection->query("
                SELECT 
                    SUM(CASE WHEN track_quantity = true THEN quantity * cost_price ELSE 0 END) as total_value,
                    COUNT(CASE WHEN quantity <= 10 AND track_quantity = true THEN 1 END) as low_stock,
                    COUNT(CASE WHEN quantity = 0 AND track_quantity = true THEN 1 END) as out_of_stock,
                    COUNT(CASE WHEN quantity < 0 AND track_quantity = true THEN 1 END) as negative_stock
                FROM products
                WHERE status = 'active'
            ");
            
            if ($inventoryResult) {
                $inventory = $inventoryResult->fetch(\PDO::FETCH_ASSOC);
                $metrics['total_inventory_value'] = (float)$inventory['total_value'];
                $metrics['low_stock_alerts'] = (int)$inventory['low_stock'];
                $metrics['out_of_stock_alerts'] = (int)$inventory['out_of_stock'];
                $metrics['negative_stock'] = (int)$inventory['negative_stock'];
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get conversion metrics
     */
    private function getConversionMetrics(): array
    {
        $metrics = [
            'cart_abandonment_rate' => 0,
            'checkout_conversion_rate' => 0,
            'email_conversion_rate' => 0,
            'search_conversion_rate' => 0,
            'mobile_conversion_rate' => 0,
            'desktop_conversion_rate' => 0
        ];
        
        // These would typically come from analytics data
        // For now, we'll provide placeholder values
        
        try {
            $connection = $this->getConnection();
            
            // Basic conversion rate (orders / sessions)
            // This would need session tracking to be accurate
            $ordersToday = $connection->query("
                SELECT COUNT(*) FROM orders 
                WHERE DATE(created_at) = CURRENT_DATE
            ")->fetchColumn();
            
            // Estimate sessions (this is a rough approximation)
            $estimatedSessions = $ordersToday * 50; // Assume 2% conversion rate baseline
            
            if ($estimatedSessions > 0) {
                $metrics['checkout_conversion_rate'] = ($ordersToday / $estimatedSessions) * 100;
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get revenue metrics
     */
    private function getRevenueMetrics(): array
    {
        $metrics = [
            'gross_revenue' => 0,
            'net_revenue' => 0,
            'refunded_amount' => 0,
            'tax_collected' => 0,
            'shipping_collected' => 0,
            'discount_given' => 0,
            'revenue_by_channel' => []
        ];
        
        try {
            $connection = $this->getConnection();
            
            // Revenue metrics for this month
            $revenueResult = $connection->query("
                SELECT 
                    SUM(total_amount) as gross_revenue,
                    SUM(subtotal) as net_revenue,
                    SUM(tax_amount) as tax_collected,
                    SUM(shipping_amount) as shipping_collected,
                    SUM(discount_amount) as discount_given
                FROM orders
                WHERE created_at >= DATE_TRUNC('month', CURRENT_DATE)
                AND status NOT IN ('cancelled', 'refunded')
            ");
            
            if ($revenueResult) {
                $revenue = $revenueResult->fetch(\PDO::FETCH_ASSOC);
                $metrics['gross_revenue'] = (float)$revenue['gross_revenue'];
                $metrics['net_revenue'] = (float)$revenue['net_revenue'];
                $metrics['tax_collected'] = (float)$revenue['tax_collected'];
                $metrics['shipping_collected'] = (float)$revenue['shipping_collected'];
                $metrics['discount_given'] = (float)$revenue['discount_given'];
            }
            
            // Refunded amount
            $refundResult = $connection->query("
                SELECT SUM(total_amount) as refunded
                FROM orders
                WHERE created_at >= DATE_TRUNC('month', CURRENT_DATE)
                AND status = 'refunded'
            ");
            
            if ($refundResult) {
                $metrics['refunded_amount'] = (float)$refundResult->fetchColumn();
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get database connection
     */
    private function getConnection(): \PDO
    {
        if ($this->connection === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $this->config->get('database.host'),
                $this->config->get('database.port', 5432),
                $this->config->get('database.database')
            );
            
            $this->connection = new \PDO(
                $dsn,
                $this->config->get('database.username'),
                $this->config->get('database.password'),
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                ]
            );
        }
        
        return $this->connection;
    }
}