<?php

declare(strict_types=1);
namespace MultiVendorMarketplace\Controllers;

use Shopologic\Core\Controller\BaseController;
use Shopologic\Core\Http\JsonResponse;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Exceptions\UnauthorizedException;
use MultiVendorMarketplace\Services\VendorManager;
use MultiVendorMarketplace\Services\VendorAnalytics;
use MultiVendorMarketplace\Services\PayoutManager;
use MultiVendorMarketplace\Services\CommissionEngine;

/**
 * Vendor Dashboard Controller
 * 
 * Handles vendor dashboard functionality
 */
class VendorDashboardController extends BaseController
{
    private VendorManager $vendorManager;
    private VendorAnalytics $vendorAnalytics;
    private PayoutManager $payoutManager;
    private CommissionEngine $commissionEngine;
    private $vendor;
    
    public function __construct()
    {
        parent::__construct();
        
        // Initialize services
        $this->vendorManager = new VendorManager($this->api);
        $this->vendorAnalytics = new VendorAnalytics($this->api);
        $this->payoutManager = new PayoutManager($this->api);
        $this->commissionEngine = new CommissionEngine($this->api);
        
        // Get current vendor
        $this->vendor = $this->getCurrentVendor();
    }
    
    /**
     * Dashboard index
     */
    public function index(): JsonResponse
    {
        try {
            if (!$this->vendor) {
                throw new UnauthorizedException('Vendor access required');
            }
            
            // Get dashboard data
            $dashboardData = [
                'vendor' => $this->getVendorInfo(),
                'stats' => $this->getDashboardStats(),
                'sales_overview' => $this->vendorAnalytics->getSalesOverview($this->vendor['id']),
                'recent_orders' => $this->getRecentOrders(),
                'pending_actions' => $this->getPendingActions(),
                'notifications' => $this->getNotifications(),
                'quick_actions' => $this->getQuickActions()
            ];
            
            return $this->success($dashboardData);
            
        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Products management
     */
    public function products(Request $request): JsonResponse
    {
        try {
            if (!$this->vendor) {
                throw new UnauthorizedException('Vendor access required');
            }
            
            $filters = $request->only(['status', 'category_id', 'search', 'sort_by', 'sort_order', 'page', 'per_page']);
            
            $products = $this->vendorManager->getVendorProducts($this->vendor['id'], $filters);
            
            // Add analytics for each product
            $products['data'] = array_map(function($product) {
                $product['analytics'] = $this->getProductAnalytics($product['id']);
                return $product;
            }, $products['data']);
            
            return $this->success([
                'products' => $products,
                'categories' => $this->getProductCategories(),
                'stats' => $this->getProductStats()
            ]);
            
        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Orders management
     */
    public function orders(Request $request): JsonResponse
    {
        try {
            if (!$this->vendor) {
                throw new UnauthorizedException('Vendor access required');
            }
            
            $filters = $request->only(['status', 'date_from', 'date_to', 'search', 'page', 'per_page']);
            
            $orders = $this->getVendorOrders($filters);
            
            return $this->success([
                'orders' => $orders,
                'stats' => $this->getOrderStats(),
                'status_options' => $this->getOrderStatusOptions()
            ]);
            
        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Earnings overview
     */
    public function earnings(Request $request): JsonResponse
    {
        try {
            if (!$this->vendor) {
                throw new UnauthorizedException('Vendor access required');
            }
            
            $period = $request->input('period', 'month');
            
            $earnings = [
                'summary' => $this->payoutManager->getEarningsSummary($this->vendor['id']),
                'revenue_analytics' => $this->vendorAnalytics->getRevenueAnalytics($this->vendor['id'], $period),
                'commission_analytics' => $this->commissionEngine->getAnalytics($this->vendor['id'], $period),
                'payout_history' => $this->payoutManager->getPayoutHistory($this->vendor['id'], ['per_page' => 10]),
                'pending_commissions' => $this->commissionEngine->getPendingCommissions($this->vendor['id'])
            ];
            
            return $this->success($earnings);
            
        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Analytics dashboard
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            if (!$this->vendor) {
                throw new UnauthorizedException('Vendor access required');
            }
            
            $period = $request->input('period', 'month');
            
            $analytics = [
                'performance_metrics' => $this->vendorAnalytics->getPerformanceMetrics($this->vendor['id']),
                'conversion_funnel' => $this->vendorAnalytics->getConversionFunnel($this->vendor['id'], $period),
                'customer_analytics' => $this->vendorAnalytics->getCustomerAnalytics($this->vendor['id']),
                'product_performance' => $this->vendorAnalytics->getProductPerformance($this->vendor['id']),
                'traffic_sources' => $this->getTrafficSources($period),
                'geographic_distribution' => $this->getGeographicDistribution($period)
            ];
            
            // Add competitor analysis for premium vendors
            if ($this->vendor['settings']['tier'] === 'premium') {
                $analytics['competitor_analysis'] = $this->vendorAnalytics->getCompetitorAnalysis($this->vendor['id']);
            }
            
            return $this->success($analytics);
            
        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Settings management
     */
    public function settings(Request $request): JsonResponse
    {
        try {
            if (!$this->vendor) {
                throw new UnauthorizedException('Vendor access required');
            }
            
            if ($request->isMethod('POST')) {
                return $this->updateSettings($request);
            }
            
            // Get current settings
            $settings = [
                'profile' => $this->getProfileSettings(),
                'payment' => $this->getPaymentSettings(),
                'shipping' => $this->getShippingSettings(),
                'notifications' => $this->getNotificationSettings(),
                'policies' => $this->getPolicySettings()
            ];
            
            return $this->success($settings);
            
        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Support center
     */
    public function support(Request $request): JsonResponse
    {
        try {
            if (!$this->vendor) {
                throw new UnauthorizedException('Vendor access required');
            }
            
            $support = [
                'tickets' => $this->getSupportTickets(),
                'faqs' => $this->getFAQs(),
                'guides' => $this->getGuides(),
                'contact_info' => $this->getSupportContactInfo()
            ];
            
            return $this->success($support);
            
        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get current vendor
     */
    private function getCurrentVendor(): ?array
    {
        $user = $this->api->auth()->user();
        
        if (!$user) {
            return null;
        }
        
        return $this->vendorManager->getVendorByUserId($user->id);
    }
    
    /**
     * Get vendor info
     */
    private function getVendorInfo(): array
    {
        return [
            'id' => $this->vendor['id'],
            'store_name' => $this->vendor['store_name'],
            'status' => $this->vendor['status'],
            'rating' => $this->vendor['rating'],
            'total_sales' => $this->vendor['total_sales'],
            'verification_status' => $this->vendor['verified_at'] ? 'verified' : 'unverified',
            'capabilities' => $this->vendor['capabilities']
        ];
    }
    
    /**
     * Get dashboard stats
     */
    private function getDashboardStats(): array
    {
        return [
            'active_products' => $this->getActiveProductCount(),
            'pending_orders' => $this->getPendingOrderCount(),
            'unread_messages' => $this->getUnreadMessageCount(),
            'low_stock_items' => $this->getLowStockCount()
        ];
    }
    
    /**
     * Get recent orders
     */
    private function getRecentOrders(): array
    {
        return $this->api->database()->table('vendor_orders as vo')
            ->join('customers as c', 'vo.customer_id', '=', 'c.id')
            ->where('vo.vendor_id', $this->vendor['id'])
            ->orderBy('vo.created_at', 'DESC')
            ->limit(10)
            ->select('vo.*', 'c.first_name', 'c.last_name', 'c.email')
            ->get()
            ->map(function($order) {
                $order['items'] = json_decode($order['items'], true);
                return $order;
            })
            ->toArray();
    }
    
    /**
     * Get pending actions
     */
    private function getPendingActions(): array
    {
        $actions = [];
        
        // Pending orders
        $pendingOrders = $this->vendorManager->getPendingOrders($this->vendor['id']);
        if (count($pendingOrders) > 0) {
            $actions[] = [
                'type' => 'orders',
                'title' => 'Process pending orders',
                'count' => count($pendingOrders),
                'priority' => 'high',
                'action_url' => '/vendor/orders?status=pending'
            ];
        }
        
        // Low stock products
        $lowStockCount = $this->getLowStockCount();
        if ($lowStockCount > 0) {
            $actions[] = [
                'type' => 'inventory',
                'title' => 'Restock low inventory items',
                'count' => $lowStockCount,
                'priority' => 'medium',
                'action_url' => '/vendor/products?filter=low_stock'
            ];
        }
        
        // Unanswered questions
        $unansweredQuestions = $this->getUnansweredQuestionCount();
        if ($unansweredQuestions > 0) {
            $actions[] = [
                'type' => 'questions',
                'title' => 'Answer customer questions',
                'count' => $unansweredQuestions,
                'priority' => 'medium',
                'action_url' => '/vendor/questions'
            ];
        }
        
        // Document verification
        if (!$this->vendor['verified_at']) {
            $actions[] = [
                'type' => 'verification',
                'title' => 'Complete verification process',
                'priority' => 'high',
                'action_url' => '/vendor/settings/verification'
            ];
        }
        
        return $actions;
    }
    
    /**
     * Get notifications
     */
    private function getNotifications(): array
    {
        return $this->vendorManager->getNotifications($this->vendor['id'], true);
    }
    
    /**
     * Get quick actions
     */
    private function getQuickActions(): array
    {
        return [
            [
                'title' => 'Add Product',
                'icon' => 'plus',
                'url' => '/vendor/products/add'
            ],
            [
                'title' => 'View Orders',
                'icon' => 'shopping-cart',
                'url' => '/vendor/orders'
            ],
            [
                'title' => 'Request Payout',
                'icon' => 'dollar-sign',
                'url' => '/vendor/earnings/payout'
            ],
            [
                'title' => 'View Analytics',
                'icon' => 'chart-line',
                'url' => '/vendor/analytics'
            ]
        ];
    }
    
    /**
     * Get vendor orders
     */
    private function getVendorOrders($filters): array
    {
        $query = $this->api->database()->table('vendor_orders as vo')
            ->join('customers as c', 'vo.customer_id', '=', 'c.id')
            ->where('vo.vendor_id', $this->vendor['id']);
            
        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('vo.status', $filters['status']);
        }
        
        if (!empty($filters['date_from'])) {
            $query->where('vo.created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('vo.created_at', '<=', $filters['date_to']);
        }
        
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('vo.order_number', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('c.email', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('c.first_name', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('c.last_name', 'LIKE', "%{$filters['search']}%");
            });
        }
        
        $query->select('vo.*', 'c.first_name', 'c.last_name', 'c.email')
              ->orderBy('vo.created_at', 'DESC');
              
        $orders = $query->paginate($filters['per_page'] ?? 20);
        
        // Process items
        $orders['data'] = array_map(function($order) {
            $order['items'] = json_decode($order['items'], true);
            return $order;
        }, $orders['data']);
        
        return $orders;
    }
    
    /**
     * Update settings
     */
    private function updateSettings(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $settings = $request->input('settings');
        
        if (!$type || !$settings) {
            return $this->error('Invalid settings data', 422);
        }
        
        switch ($type) {
            case 'profile':
                return $this->updateProfileSettings($settings);
                
            case 'payment':
                return $this->updatePaymentSettings($settings);
                
            case 'shipping':
                return $this->updateShippingSettings($settings);
                
            case 'notifications':
                return $this->updateNotificationSettings($settings);
                
            case 'policies':
                return $this->updatePolicySettings($settings);
                
            default:
                return $this->error('Invalid settings type', 422);
        }
    }
    
    /**
     * Helper methods for counts and stats
     */
    private function getActiveProductCount(): int
    {
        return $this->api->database()->table('vendor_products as vp')
            ->join('products as p', 'vp.product_id', '=', 'p.id')
            ->where('vp.vendor_id', $this->vendor['id'])
            ->where('p.status', 'active')
            ->count();
    }
    
    private function getPendingOrderCount(): int
    {
        return $this->api->database()->table('vendor_orders')
            ->where('vendor_id', $this->vendor['id'])
            ->whereIn('status', ['pending', 'processing'])
            ->count();
    }
    
    private function getUnreadMessageCount(): int
    {
        return $this->api->database()->table('vendor_notifications')
            ->where('vendor_id', $this->vendor['id'])
            ->whereNull('read_at')
            ->count();
    }
    
    private function getLowStockCount(): int
    {
        return $this->api->database()->table('vendor_products')
            ->where('vendor_id', $this->vendor['id'])
            ->where('stock_quantity', '<=', 10)
            ->count();
    }
    
    private function getUnansweredQuestionCount(): int
    {
        // Placeholder - would query product questions
        return 0;
    }
    
    /**
     * Get product analytics
     */
    private function getProductAnalytics($productId): array
    {
        // Simplified analytics - would be more comprehensive in production
        return [
            'views_today' => rand(10, 100),
            'sales_this_week' => rand(0, 20),
            'conversion_rate' => rand(1, 10) / 100
        ];
    }
    
    /**
     * Get order stats
     */
    private function getOrderStats(): array
    {
        return [
            'pending' => $this->getOrderCountByStatus('pending'),
            'processing' => $this->getOrderCountByStatus('processing'),
            'shipped' => $this->getOrderCountByStatus('shipped'),
            'delivered' => $this->getOrderCountByStatus('delivered'),
            'cancelled' => $this->getOrderCountByStatus('cancelled')
        ];
    }
    
    private function getOrderCountByStatus($status): int
    {
        return $this->api->database()->table('vendor_orders')
            ->where('vendor_id', $this->vendor['id'])
            ->where('status', $status)
            ->count();
    }
}