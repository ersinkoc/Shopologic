<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin\Modules;

use Shopologic\Core\Admin\AdminPanel;
use Shopologic\Core\Admin\AdminModuleInterface;
use Shopologic\Core\Admin\DashboardWidgetInterface;
use Shopologic\Core\Analytics\AnalyticsReporter;
use Shopologic\Core\Database\DB;

/**
 * Dashboard module for admin panel
 */
class DashboardModule implements AdminModuleInterface
{
    private DB $db;
    private AnalyticsReporter $analytics;
    
    public function __construct()
    {
        $this->db = app('db');
        $this->analytics = app('analytics.reporter');
    }
    
    public function register(AdminPanel $admin): void
    {
        // Register menu items
        $admin->getMenu()->addItem([
            'title' => 'Dashboard',
            'url' => '/admin',
            'icon' => 'dashboard',
            'order' => 0,
            'group' => 'main'
        ]);
        
        // Register widgets
        $admin->registerWidget('left', new SalesWidget($this->db, $this->analytics));
        $admin->registerWidget('left', new OrdersWidget($this->db));
        $admin->registerWidget('right', new RecentActivityWidget($this->db));
        $admin->registerWidget('right', new TopProductsWidget($this->db));
        $admin->registerWidget('full', new PerformanceWidget($this->analytics));
    }
    
    public function getName(): string
    {
        return 'dashboard';
    }
    
    public function getRoutes(): array
    {
        return [
            ['GET', '/admin', 'DashboardController@index'],
            ['GET', '/admin/dashboard/stats', 'DashboardController@stats'],
            ['GET', '/admin/dashboard/export', 'DashboardController@export']
        ];
    }
    
    public function getMenuItems(): array
    {
        return [
            [
                'title' => 'Dashboard',
                'url' => '/admin',
                'icon' => 'dashboard',
                'order' => 0
            ]
        ];
    }
    
    public function getPermissions(): array
    {
        return [
            'admin.dashboard.view' => 'View dashboard',
            'admin.dashboard.export' => 'Export dashboard data'
        ];
    }
}

/**
 * Sales overview widget
 */
class SalesWidget implements DashboardWidgetInterface
{
    private DB $db;
    private AnalyticsReporter $analytics;
    
    public function __construct(DB $db, AnalyticsReporter $analytics)
    {
        $this->db = $db;
        $this->analytics = $analytics;
    }
    
    public function getName(): string
    {
        return 'sales_overview';
    }
    
    public function render(): string
    {
        $data = $this->getSalesData();
        
        return <<<HTML
        <div class="widget widget-sales">
            <h3>Sales Overview</h3>
            <div class="stats-grid">
                <div class="stat">
                    <span class="value">\${$data['today']}</span>
                    <span class="label">Today</span>
                    <span class="change {$data['today_change_class']}">{$data['today_change']}%</span>
                </div>
                <div class="stat">
                    <span class="value">\${$data['week']}</span>
                    <span class="label">This Week</span>
                    <span class="change {$data['week_change_class']}">{$data['week_change']}%</span>
                </div>
                <div class="stat">
                    <span class="value">\${$data['month']}</span>
                    <span class="label">This Month</span>
                    <span class="change {$data['month_change_class']}">{$data['month_change']}%</span>
                </div>
                <div class="stat">
                    <span class="value">\${$data['year']}</span>
                    <span class="label">This Year</span>
                    <span class="change {$data['year_change_class']}">{$data['year_change']}%</span>
                </div>
            </div>
            <canvas id="sales-chart" data-chart='{$data['chart_data']}'></canvas>
        </div>
        HTML;
    }
    
    public function canView($user): bool
    {
        return $user->hasPermission('admin.dashboard.view');
    }
    
    public function getPosition(): string
    {
        return 'left';
    }
    
    public function getOrder(): int
    {
        return 1;
    }
    
    private function getSalesData(): array
    {
        $today = $this->db->table('orders')
            ->whereDate('created_at', date('Y-m-d'))
            ->where('status', '!=', 'cancelled')
            ->sum('total');
            
        $yesterday = $this->db->table('orders')
            ->whereDate('created_at', date('Y-m-d', strtotime('-1 day')))
            ->where('status', '!=', 'cancelled')
            ->sum('total');
            
        $thisWeek = $this->db->table('orders')
            ->whereBetween('created_at', [
                date('Y-m-d', strtotime('monday this week')),
                date('Y-m-d 23:59:59')
            ])
            ->where('status', '!=', 'cancelled')
            ->sum('total');
            
        $lastWeek = $this->db->table('orders')
            ->whereBetween('created_at', [
                date('Y-m-d', strtotime('monday last week')),
                date('Y-m-d 23:59:59', strtotime('sunday last week'))
            ])
            ->where('status', '!=', 'cancelled')
            ->sum('total');
            
        $thisMonth = $this->db->table('orders')
            ->whereMonth('created_at', date('m'))
            ->whereYear('created_at', date('Y'))
            ->where('status', '!=', 'cancelled')
            ->sum('total');
            
        $lastMonth = $this->db->table('orders')
            ->whereMonth('created_at', date('m', strtotime('-1 month')))
            ->whereYear('created_at', date('Y', strtotime('-1 month')))
            ->where('status', '!=', 'cancelled')
            ->sum('total');
            
        $thisYear = $this->db->table('orders')
            ->whereYear('created_at', date('Y'))
            ->where('status', '!=', 'cancelled')
            ->sum('total');
            
        $lastYear = $this->db->table('orders')
            ->whereYear('created_at', date('Y') - 1)
            ->where('status', '!=', 'cancelled')
            ->sum('total');
        
        // Calculate changes
        $todayChange = $yesterday > 0 ? round((($today - $yesterday) / $yesterday) * 100, 1) : 0;
        $weekChange = $lastWeek > 0 ? round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1) : 0;
        $monthChange = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : 0;
        $yearChange = $lastYear > 0 ? round((($thisYear - $lastYear) / $lastYear) * 100, 1) : 0;
        
        // Get chart data for last 30 days
        $chartData = $this->db->table('orders')
            ->selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->where('created_at', '>=', date('Y-m-d', strtotime('-30 days')))
            ->where('status', '!=', 'cancelled')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
        
        return [
            'today' => number_format($today, 2),
            'today_change' => $todayChange,
            'today_change_class' => $todayChange >= 0 ? 'positive' : 'negative',
            'week' => number_format($thisWeek, 2),
            'week_change' => $weekChange,
            'week_change_class' => $weekChange >= 0 ? 'positive' : 'negative',
            'month' => number_format($thisMonth, 2),
            'month_change' => $monthChange,
            'month_change_class' => $monthChange >= 0 ? 'positive' : 'negative',
            'year' => number_format($thisYear, 2),
            'year_change' => $yearChange,
            'year_change_class' => $yearChange >= 0 ? 'positive' : 'negative',
            'chart_data' => json_encode($chartData)
        ];
    }
}

/**
 * Orders overview widget
 */
class OrdersWidget implements DashboardWidgetInterface
{
    private DB $db;
    
    public function __construct(DB $db)
    {
        $this->db = $db;
    }
    
    public function getName(): string
    {
        return 'orders_overview';
    }
    
    public function render(): string
    {
        $data = $this->getOrdersData();
        
        return <<<HTML
        <div class="widget widget-orders">
            <h3>Orders Overview</h3>
            <div class="order-stats">
                <div class="stat">
                    <span class="value">{$data['pending']}</span>
                    <span class="label">Pending</span>
                </div>
                <div class="stat">
                    <span class="value">{$data['processing']}</span>
                    <span class="label">Processing</span>
                </div>
                <div class="stat">
                    <span class="value">{$data['shipped']}</span>
                    <span class="label">Shipped</span>
                </div>
                <div class="stat">
                    <span class="value">{$data['delivered']}</span>
                    <span class="label">Delivered</span>
                </div>
            </div>
            <div class="recent-orders">
                <h4>Recent Orders</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$data['recent_orders_html']}
                    </tbody>
                </table>
                <a href="/admin/orders" class="view-all">View All Orders</a>
            </div>
        </div>
        HTML;
    }
    
    public function canView($user): bool
    {
        return $user->hasPermission('admin.orders.view');
    }
    
    public function getPosition(): string
    {
        return 'left';
    }
    
    public function getOrder(): int
    {
        return 2;
    }
    
    private function getOrdersData(): array
    {
        $pending = $this->db->table('orders')->where('status', 'pending')->count();
        $processing = $this->db->table('orders')->where('status', 'processing')->count();
        $shipped = $this->db->table('orders')->where('status', 'shipped')->count();
        $delivered = $this->db->table('orders')->where('status', 'delivered')->count();
        
        $recentOrders = $this->db->table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->select('orders.*', 'users.name as customer_name')
            ->orderBy('orders.created_at', 'desc')
            ->limit(5)
            ->get();
        
        $ordersHtml = '';
        foreach ($recentOrders as $order) {
            $statusClass = $this->getStatusClass($order->status);
            $ordersHtml .= <<<HTML
            <tr>
                <td>#{$order->id}</td>
                <td>{$order->customer_name}</td>
                <td>\${$order->total}</td>
                <td><span class="status {$statusClass}">{$order->status}</span></td>
            </tr>
            HTML;
        }
        
        return [
            'pending' => $pending,
            'processing' => $processing,
            'shipped' => $shipped,
            'delivered' => $delivered,
            'recent_orders_html' => $ordersHtml
        ];
    }
    
    private function getStatusClass(string $status): string
    {
        return match($status) {
            'pending' => 'status-pending',
            'processing' => 'status-processing',
            'shipped' => 'status-shipped',
            'delivered' => 'status-success',
            'cancelled' => 'status-danger',
            default => 'status-default'
        };
    }
}

/**
 * Recent activity widget
 */
class RecentActivityWidget implements DashboardWidgetInterface
{
    private DB $db;
    
    public function __construct(DB $db)
    {
        $this->db = $db;
    }
    
    public function getName(): string
    {
        return 'recent_activity';
    }
    
    public function render(): string
    {
        $activities = $this->getRecentActivities();
        
        $html = '<div class="widget widget-activity">
            <h3>Recent Activity</h3>
            <div class="activity-list">';
        
        foreach ($activities as $activity) {
            $html .= <<<HTML
            <div class="activity-item">
                <div class="activity-icon {$activity['icon_class']}">
                    <i class="{$activity['icon']}"></i>
                </div>
                <div class="activity-content">
                    <p>{$activity['description']}</p>
                    <span class="time">{$activity['time_ago']}</span>
                </div>
            </div>
            HTML;
        }
        
        $html .= '</div></div>';
        
        return $html;
    }
    
    public function canView($user): bool
    {
        return $user->hasPermission('admin.dashboard.view');
    }
    
    public function getPosition(): string
    {
        return 'right';
    }
    
    public function getOrder(): int
    {
        return 1;
    }
    
    private function getRecentActivities(): array
    {
        $activities = [];
        
        // Get recent orders
        $orders = $this->db->table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->select('orders.*', 'users.name as customer_name')
            ->orderBy('orders.created_at', 'desc')
            ->limit(3)
            ->get();
        
        foreach ($orders as $order) {
            $activities[] = [
                'icon' => 'shopping-cart',
                'icon_class' => 'icon-order',
                'description' => "New order #{$order->id} from {$order->customer_name}",
                'time_ago' => $this->timeAgo($order->created_at),
                'timestamp' => $order->created_at
            ];
        }
        
        // Get recent user registrations
        $users = $this->db->table('users')
            ->where('role', 'customer')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
        
        foreach ($users as $user) {
            $activities[] = [
                'icon' => 'user-plus',
                'icon_class' => 'icon-user',
                'description' => "New customer registered: {$user->name}",
                'time_ago' => $this->timeAgo($user->created_at),
                'timestamp' => $user->created_at
            ];
        }
        
        // Get recent product updates
        $products = $this->db->table('products')
            ->orderBy('updated_at', 'desc')
            ->limit(3)
            ->get();
        
        foreach ($products as $product) {
            $activities[] = [
                'icon' => 'edit',
                'icon_class' => 'icon-product',
                'description' => "Product updated: {$product->name}",
                'time_ago' => $this->timeAgo($product->updated_at),
                'timestamp' => $product->updated_at
            ];
        }
        
        // Sort by timestamp
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($activities, 0, 10);
    }
    
    private function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
    }
}

/**
 * Top products widget
 */
class TopProductsWidget implements DashboardWidgetInterface
{
    private DB $db;
    
    public function __construct(DB $db)
    {
        $this->db = $db;
    }
    
    public function getName(): string
    {
        return 'top_products';
    }
    
    public function render(): string
    {
        $products = $this->getTopProducts();
        
        $html = '<div class="widget widget-products">
            <h3>Top Products</h3>
            <div class="product-list">';
        
        foreach ($products as $product) {
            $html .= <<<HTML
            <div class="product-item">
                <div class="product-info">
                    <h5>{$product->name}</h5>
                    <span class="sales">{$product->sales_count} sales</span>
                </div>
                <div class="product-revenue">
                    \${$product->revenue}
                </div>
            </div>
            HTML;
        }
        
        $html .= '</div>
            <a href="/admin/products?sort=sales" class="view-all">View All Products</a>
        </div>';
        
        return $html;
    }
    
    public function canView($user): bool
    {
        return $user->hasPermission('admin.products.view');
    }
    
    public function getPosition(): string
    {
        return 'right';
    }
    
    public function getOrder(): int
    {
        return 2;
    }
    
    private function getTopProducts(): array
    {
        return $this->db->table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(
                'products.id',
                'products.name',
                'COUNT(order_items.id) as sales_count',
                'SUM(order_items.quantity * order_items.price) as revenue'
            )
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.created_at', '>=', date('Y-m-d', strtotime('-30 days')))
            ->groupBy('products.id', 'products.name')
            ->orderBy('revenue', 'desc')
            ->limit(5)
            ->get()
            ->map(function($product) {
                $product->revenue = number_format($product->revenue, 2);
                return $product;
            })
            ->toArray();
    }
}

/**
 * Performance widget
 */
class PerformanceWidget implements DashboardWidgetInterface
{
    private AnalyticsReporter $analytics;
    
    public function __construct(AnalyticsReporter $analytics)
    {
        $this->analytics = $analytics;
    }
    
    public function getName(): string
    {
        return 'performance_metrics';
    }
    
    public function render(): string
    {
        $metrics = $this->analytics->getPerformanceMetrics('hour');
        
        return <<<HTML
        <div class="widget widget-performance widget-full">
            <h3>Performance Metrics</h3>
            <div class="metrics-grid">
                <div class="metric">
                    <span class="label">Avg Page Load Time</span>
                    <span class="value">{$metrics['avg_page_load']}ms</span>
                </div>
                <div class="metric">
                    <span class="label">API Response Time</span>
                    <span class="value">{$metrics['avg_api_response']}ms</span>
                </div>
                <div class="metric">
                    <span class="label">Cache Hit Rate</span>
                    <span class="value">{$metrics['cache_hit_rate']}%</span>
                </div>
                <div class="metric">
                    <span class="label">Error Rate</span>
                    <span class="value">{$metrics['error_rate']}%</span>
                </div>
            </div>
            <canvas id="performance-chart" data-metrics='{$metrics['chart_data']}'></canvas>
        </div>
        HTML;
    }
    
    public function canView($user): bool
    {
        return $user->hasPermission('admin.dashboard.view');
    }
    
    public function getPosition(): string
    {
        return 'full';
    }
    
    public function getOrder(): int
    {
        return 1;
    }
}