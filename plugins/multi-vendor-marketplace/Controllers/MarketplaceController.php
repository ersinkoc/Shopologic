<?php

declare(strict_types=1);
namespace MultiVendorMarketplace\Controllers;

use Shopologic\Core\Controller\BaseController;
use Shopologic\Core\Http\JsonResponse;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Exceptions\UnauthorizedException;
use Shopologic\Core\Exceptions\ValidationException;
use MultiVendorMarketplace\Services\VendorManager;
use MultiVendorMarketplace\Services\VendorAnalytics;
use MultiVendorMarketplace\Services\PayoutManager;
use MultiVendorMarketplace\Services\CommissionEngine;

/**
 * Marketplace Controller
 * 
 * Handles marketplace admin functionality
 */
class MarketplaceController extends BaseController
{
    private VendorManager $vendorManager;
    private VendorAnalytics $vendorAnalytics;
    private PayoutManager $payoutManager;
    private CommissionEngine $commissionEngine;
    
    public function __construct()
    {
        parent::__construct();
        
        // Check admin permission
        if (!$this->api->auth()->hasRole(['admin', 'marketplace_admin'])) {
            throw new UnauthorizedException('Admin access required');
        }
        
        // Initialize services
        $this->vendorManager = new VendorManager($this->api);
        $this->vendorAnalytics = new VendorAnalytics($this->api);
        $this->payoutManager = new PayoutManager($this->api);
        $this->commissionEngine = new CommissionEngine($this->api);
    }
    
    /**
     * Get marketplace statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', 'month');
            
            $stats = [
                'overview' => $this->getMarketplaceOverview(),
                'vendor_stats' => $this->getVendorStats(),
                'revenue_stats' => $this->getRevenueStats($period),
                'product_stats' => $this->getProductStats(),
                'order_stats' => $this->getOrderStats($period),
                'commission_stats' => $this->getCommissionStats($period),
                'payout_stats' => $this->getPayoutStats($period),
                'growth_metrics' => $this->getGrowthMetrics($period)
            ];
            
            return $this->success($stats);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get vendor management data
     */
    public function vendors(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'search', 'sort_by', 'sort_order', 'page', 'per_page']);
            
            $vendors = $this->vendorManager->searchVendors($filters['search'] ?? '', $filters);
            
            // Add analytics to each vendor
            $vendors['data'] = array_map(function($vendor) {
                $vendor['analytics'] = [
                    'total_products' => $this->getVendorProductCount($vendor['id']),
                    'monthly_sales' => $this->getVendorMonthlySales($vendor['id']),
                    'pending_orders' => $this->getVendorPendingOrders($vendor['id']),
                    'performance_score' => $this->calculateVendorScore($vendor['id'])
                ];
                return $vendor;
            }, $vendors['data']);
            
            return $this->success([
                'vendors' => $vendors,
                'pending_approvals' => $this->vendorManager->getPendingApprovals(),
                'filters' => $this->getVendorFilters()
            ]);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Approve vendor
     */
    public function approveVendor(Request $request, $id): JsonResponse
    {
        try {
            $vendor = $this->vendorManager->getVendor($id);
            
            if (!$vendor) {
                return $this->error('Vendor not found', 404);
            }
            
            if ($vendor['status'] !== 'pending') {
                return $this->error('Vendor is not pending approval', 400);
            }
            
            // Update vendor status
            $this->vendorManager->updateStatus($id, 'active', 'Approved by admin');
            
            return $this->success([
                'message' => 'Vendor approved successfully',
                'vendor' => $this->vendorManager->getVendor($id)
            ]);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Suspend vendor
     */
    public function suspendVendor(Request $request, $id): JsonResponse
    {
        try {
            $validated = $this->validate($request, [
                'reason' => 'required|string'
            ]);
            
            $vendor = $this->vendorManager->getVendor($id);
            
            if (!$vendor) {
                return $this->error('Vendor not found', 404);
            }
            
            if ($vendor['status'] === 'suspended') {
                return $this->error('Vendor is already suspended', 400);
            }
            
            // Update vendor status
            $this->vendorManager->updateStatus($id, 'suspended', $validated['reason']);
            
            return $this->success([
                'message' => 'Vendor suspended successfully',
                'vendor' => $this->vendorManager->getVendor($id)
            ]);
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Reactivate vendor
     */
    public function reactivateVendor($id): JsonResponse
    {
        try {
            $vendor = $this->vendorManager->getVendor($id);
            
            if (!$vendor) {
                return $this->error('Vendor not found', 404);
            }
            
            if ($vendor['status'] === 'active') {
                return $this->error('Vendor is already active', 400);
            }
            
            // Update vendor status
            $this->vendorManager->updateStatus($id, 'active', 'Reactivated by admin');
            
            return $this->success([
                'message' => 'Vendor reactivated successfully',
                'vendor' => $this->vendorManager->getVendor($id)
            ]);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get product approvals
     */
    public function productApprovals(Request $request): JsonResponse
    {
        try {
            $products = $this->getPendingProducts($request->all());
            
            return $this->success([
                'products' => $products,
                'stats' => [
                    'pending_count' => $this->getPendingProductCount(),
                    'approved_today' => $this->getApprovedTodayCount(),
                    'rejected_today' => $this->getRejectedTodayCount()
                ]
            ]);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Approve product
     */
    public function approveProduct($id): JsonResponse
    {
        try {
            $product = $this->api->database()->table('products')->find($id);
            
            if (!$product) {
                return $this->error('Product not found', 404);
            }
            
            if ($product['status'] !== 'pending_review') {
                return $this->error('Product is not pending review', 400);
            }
            
            // Update product status
            $this->api->database()->table('products')
                ->where('id', $id)
                ->update([
                    'status' => 'active',
                    'reviewed_at' => date('Y-m-d H:i:s'),
                    'reviewed_by' => $this->api->auth()->id()
                ]);
                
            // Notify vendor
            $this->notifyVendorProductApproval($product);
            
            return $this->success([
                'message' => 'Product approved successfully'
            ]);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Reject product
     */
    public function rejectProduct(Request $request, $id): JsonResponse
    {
        try {
            $validated = $this->validate($request, [
                'reason' => 'required|string',
                'notes' => 'array'
            ]);
            
            $product = $this->api->database()->table('products')->find($id);
            
            if (!$product) {
                return $this->error('Product not found', 404);
            }
            
            if ($product['status'] !== 'pending_review') {
                return $this->error('Product is not pending review', 400);
            }
            
            // Update product status
            $this->api->database()->table('products')
                ->where('id', $id)
                ->update([
                    'status' => 'needs_revision',
                    'review_notes' => json_encode($validated['notes'] ?? []),
                    'reviewed_at' => date('Y-m-d H:i:s'),
                    'reviewed_by' => $this->api->auth()->id()
                ]);
                
            // Notify vendor
            $this->notifyVendorProductRejection($product, $validated['reason'], $validated['notes'] ?? []);
            
            return $this->success([
                'message' => 'Product rejected with feedback'
            ]);
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get commission management data
     */
    public function commissions(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', 'month');
            
            $data = [
                'rules' => $this->commissionEngine->getCommissionRules(),
                'analytics' => $this->commissionEngine->getAnalytics(null, $period),
                'pending_calculations' => $this->getPendingCommissionCalculations(),
                'recent_transactions' => $this->getRecentCommissionTransactions()
            ];
            
            return $this->success($data);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Create commission rule
     */
    public function createCommissionRule(Request $request): JsonResponse
    {
        try {
            $validated = $this->validate($request, [
                'name' => 'required|string',
                'type' => 'required|in:category,vendor_tier,volume',
                'rate' => 'required|numeric|min:0|max:100',
                'category' => 'required_if:type,category',
                'vendor_tier' => 'required_if:type,vendor_tier',
                'volume_threshold' => 'required_if:type,volume|numeric|min:0',
                'priority' => 'integer|min:0',
                'active' => 'boolean'
            ]);
            
            $ruleId = $this->commissionEngine->createRule($validated);
            
            return $this->success([
                'message' => 'Commission rule created successfully',
                'rule_id' => $ruleId
            ]);
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Update commission rule
     */
    public function updateCommissionRule(Request $request, $id): JsonResponse
    {
        try {
            $validated = $this->validate($request, [
                'name' => 'string',
                'rate' => 'numeric|min:0|max:100',
                'priority' => 'integer|min:0',
                'active' => 'boolean'
            ]);
            
            $updated = $this->commissionEngine->updateRule($id, $validated);
            
            if ($updated) {
                return $this->success([
                    'message' => 'Commission rule updated successfully'
                ]);
            }
            
            return $this->error('Commission rule not found', 404);
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Delete commission rule
     */
    public function deleteCommissionRule($id): JsonResponse
    {
        try {
            $deleted = $this->commissionEngine->deleteRule($id);
            
            if ($deleted) {
                return $this->success([
                    'message' => 'Commission rule deleted successfully'
                ]);
            }
            
            return $this->error('Commission rule not found', 404);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get payout management data
     */
    public function payouts(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', 'month');
            
            $data = [
                'pending_payouts' => $this->payoutManager->getPendingPayouts(),
                'payout_analytics' => $this->payoutManager->getPayoutAnalytics($period),
                'upcoming_schedule' => $this->getUpcomingPayoutSchedule(),
                'payout_methods' => $this->getPayoutMethodStats()
            ];
            
            return $this->success($data);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Process manual payout
     */
    public function processPayout(Request $request): JsonResponse
    {
        try {
            $validated = $this->validate($request, [
                'vendor_id' => 'required|integer',
                'amount' => 'numeric|min:0'
            ]);
            
            $result = $this->payoutManager->processVendorPayout(
                $validated['vendor_id'],
                $validated['amount'] ?? null
            );
            
            return $this->success([
                'message' => 'Payout processed successfully',
                'payout' => $result
            ]);
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
    
    /**
     * Process all scheduled payouts
     */
    public function processScheduledPayouts(): JsonResponse
    {
        try {
            $results = $this->payoutManager->processScheduledPayouts();
            
            return $this->success([
                'message' => 'Scheduled payouts processed',
                'results' => $results
            ]);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get analytics dashboard data
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', 'month');
            
            $analytics = [
                'revenue_trends' => $this->getRevenueTrends($period),
                'vendor_performance' => $this->vendorAnalytics->getTopVendors(10, $period),
                'category_distribution' => $this->vendorAnalytics->getCategoryDistribution(),
                'geographic_insights' => $this->getGeographicInsights($period),
                'customer_behavior' => $this->getCustomerBehaviorAnalytics($period),
                'conversion_metrics' => $this->getConversionMetrics($period)
            ];
            
            return $this->success($analytics);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get marketplace settings
     */
    public function settings(Request $request): JsonResponse
    {
        try {
            if ($request->isMethod('POST')) {
                return $this->updateSettings($request);
            }
            
            $settings = [
                'general' => $this->getGeneralSettings(),
                'commission' => $this->getCommissionSettings(),
                'payout' => $this->getPayoutSettings(),
                'vendor' => $this->getVendorSettings(),
                'approval' => $this->getApprovalSettings()
            ];
            
            return $this->success($settings);
            
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Update marketplace settings
     */
    private function updateSettings(Request $request): JsonResponse
    {
        $validated = $this->validate($request, [
            'settings' => 'required|array'
        ]);
        
        foreach ($validated['settings'] as $key => $value) {
            $this->api->setting()->set("marketplace.{$key}", $value);
        }
        
        return $this->success([
            'message' => 'Settings updated successfully'
        ]);
    }
    
    /**
     * Helper methods for statistics
     */
    private function getMarketplaceOverview(): array
    {
        return [
            'total_vendors' => $this->vendorManager->getTotalVendors(),
            'active_vendors' => $this->vendorManager->getActiveVendors(),
            'pending_vendors' => $this->vendorManager->getTotalVendors('pending'),
            'total_products' => $this->vendorManager->getTotalVendorProducts(),
            'total_revenue' => $this->vendorAnalytics->getTotalMarketplaceSales(),
            'total_commissions' => $this->commissionEngine->getTotalCommissions()
        ];
    }
    
    private function getVendorStats(): array
    {
        return [
            'new_vendors_today' => $this->getNewVendorsCount('today'),
            'new_vendors_week' => $this->getNewVendorsCount('week'),
            'new_vendors_month' => $this->getNewVendorsCount('month'),
            'vendor_growth_rate' => $this->calculateVendorGrowthRate(),
            'average_vendor_rating' => $this->getAverageVendorRating(),
            'vendor_retention_rate' => $this->calculateVendorRetentionRate()
        ];
    }
    
    private function getRevenueStats($period): array
    {
        $analytics = $this->vendorAnalytics->getRevenueAnalytics(null, $period);
        
        return [
            'total_revenue' => $analytics['summary']['total_revenue'],
            'total_commissions' => $analytics['summary']['total_commission'],
            'average_order_value' => $analytics['summary']['average_order_value'],
            'revenue_growth' => $this->calculateRevenueGrowth($period),
            'daily_revenue' => $analytics['daily_data']
        ];
    }
    
    private function getProductStats(): array
    {
        return [
            'total_products' => $this->getTotalProductCount(),
            'active_products' => $this->getActiveProductCount(),
            'pending_approval' => $this->getPendingProductCount(),
            'out_of_stock' => $this->getOutOfStockCount(),
            'categories' => $this->vendorAnalytics->getCategoryDistribution()
        ];
    }
    
    private function getOrderStats($period): array
    {
        return [
            'total_orders' => $this->getTotalOrderCount($period),
            'pending_orders' => $this->getPendingOrderCount(),
            'completed_orders' => $this->getCompletedOrderCount($period),
            'cancelled_orders' => $this->getCancelledOrderCount($period),
            'fulfillment_rate' => $this->calculateFulfillmentRate($period),
            'average_processing_time' => $this->calculateAverageProcessingTime()
        ];
    }
    
    private function getCommissionStats($period): array
    {
        return [
            'total_commissions' => $this->commissionEngine->getTotalCommissions(['date_from' => $this->getStartDate($period)]),
            'pending_commissions' => $this->commissionEngine->getTotalCommissions(['status' => 'pending']),
            'average_commission_rate' => $this->calculateAverageCommissionRate(),
            'commission_by_category' => $this->getCommissionByCategory($period)
        ];
    }
    
    private function getPayoutStats($period): array
    {
        $analytics = $this->payoutManager->getPayoutAnalytics($period);
        
        return [
            'total_payouts' => $analytics['summary']['total_amount'],
            'payout_count' => $analytics['summary']['total_payouts'],
            'average_payout' => $analytics['summary']['average_amount'],
            'payment_methods' => $analytics['payment_methods'],
            'upcoming_payouts' => $this->getUpcomingPayoutTotal()
        ];
    }
    
    private function getGrowthMetrics($period): array
    {
        return [
            'vendor_growth' => $this->vendorAnalytics->getVendorGrowthChart($period),
            'revenue_growth' => $this->calculateRevenueGrowth($period),
            'order_growth' => $this->calculateOrderGrowth($period),
            'product_growth' => $this->calculateProductGrowth($period)
        ];
    }
    
    /**
     * Additional helper methods would be implemented here
     */
    private function getNewVendorsCount($period): int
    {
        $date = match($period) {
            'today' => date('Y-m-d'),
            'week' => date('Y-m-d', strtotime('-7 days')),
            'month' => date('Y-m-d', strtotime('-30 days'))
        };
        
        return $this->api->database()->table('vendors')
            ->where('created_at', '>=', $date)
            ->count();
    }
    
    private function getPendingProducts($filters): array
    {
        $query = $this->api->database()->table('products as p')
            ->join('vendor_products as vp', 'p.id', '=', 'vp.product_id')
            ->join('vendors as v', 'vp.vendor_id', '=', 'v.id')
            ->where('p.status', 'pending_review')
            ->select('p.*', 'v.store_name', 'vp.vendor_id');
            
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('p.name', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('p.sku', 'LIKE', "%{$filters['search']}%");
            });
        }
        
        return $query->orderBy('p.created_at', 'ASC')
                    ->paginate($filters['per_page'] ?? 20);
    }
}