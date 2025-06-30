<?php

declare(strict_types=1);
namespace MultiVendorMarketplace\Services;

use Shopologic\Core\Services\BaseService;
use Shopologic\Core\Exceptions\BusinessException;

/**
 * Commission Engine Service
 * 
 * Handles commission calculations, rules, and tracking
 */
class CommissionEngine extends BaseService
{
    private $cache;
    private $vendorManager;
    
    public function __construct($api)
    {
        parent::__construct($api);
        $this->cache = $api->cache();
    }
    
    /**
     * Calculate commission for vendor order
     */
    public function calculate($vendorOrder): array
    {
        // Get vendor details
        $vendor = $this->getVendorManager()->getVendor($vendorOrder->vendor_id);
        
        if (!$vendor) {
            throw new BusinessException('Vendor not found');
        }
        
        // Get applicable commission rate
        $commissionRate = $this->getApplicableRate($vendor, $vendorOrder);
        
        // Calculate commission amount
        $commissionAmount = round($vendorOrder->subtotal * ($commissionRate / 100), 2);
        
        // Calculate vendor earnings
        $vendorEarnings = $vendorOrder->subtotal - $commissionAmount;
        
        // Apply any additional fees
        $additionalFees = $this->calculateAdditionalFees($vendor, $vendorOrder);
        $vendorEarnings -= $additionalFees['total'];
        
        return [
            'rate' => $commissionRate,
            'amount' => $commissionAmount,
            'vendor_earnings' => $vendorEarnings,
            'additional_fees' => $additionalFees,
            'calculation_details' => [
                'subtotal' => $vendorOrder->subtotal,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'fees' => $additionalFees,
                'net_earnings' => $vendorEarnings
            ]
        ];
    }
    
    /**
     * Record commission transaction
     */
    public function record($commissionData): int
    {
        // Validate commission data
        $this->validateCommissionData($commissionData);
        
        // Insert commission record
        $commissionId = $this->api->database()->table('vendor_commissions')->insertGetId([
            'vendor_id' => $commissionData['vendor_id'],
            'order_id' => $commissionData['order_id'],
            'order_total' => $commissionData['order_total'],
            'commission_rate' => $commissionData['commission_rate'],
            'commission_amount' => $commissionData['commission_amount'],
            'vendor_earnings' => $commissionData['vendor_earnings'],
            'status' => $commissionData['status'] ?? 'pending',
            'created_at' => $commissionData['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        
        // Update vendor total sales
        $this->updateVendorSales($commissionData['vendor_id'], $commissionData['order_total']);
        
        // Clear cache
        $this->clearCommissionCache($commissionData['vendor_id']);
        
        return $commissionId;
    }
    
    /**
     * Get applicable commission rate
     */
    public function getApplicableRate($vendor, $order): float
    {
        $cacheKey = "commission_rate_{$vendor['id']}_{$order->id}";
        
        return $this->cache->remember($cacheKey, 300, function() use ($vendor, $order) {
            // Priority order for commission rates:
            // 1. Product-specific override
            // 2. Category-specific rate
            // 3. Volume-based rate
            // 4. Vendor tier rate
            // 5. Vendor-specific rate
            // 6. Default marketplace rate
            
            // Check for product-specific overrides
            if ($productRate = $this->getProductOverrideRate($order)) {
                return $productRate;
            }
            
            // Check category-specific rates
            if ($categoryRate = $this->getCategorySpecificRate($order)) {
                return $categoryRate;
            }
            
            // Check volume-based rates
            if ($volumeRate = $this->getVolumeBasedRate($vendor)) {
                return $volumeRate;
            }
            
            // Check vendor tier rates
            if ($tierRate = $this->getVendorTierRate($vendor)) {
                return $tierRate;
            }
            
            // Use vendor-specific rate
            if ($vendor['commission_rate'] !== null) {
                return (float) $vendor['commission_rate'];
            }
            
            // Default marketplace rate
            return (float) $this->api->setting('marketplace.commission_rate', 15);
        });
    }
    
    /**
     * Get category-specific commission rate
     */
    public function getCategoryRate($categoryId): ?float
    {
        $rule = $this->api->database()->table('commission_rules')
            ->where('type', 'category')
            ->where('category', $categoryId)
            ->where('active', true)
            ->orderBy('priority', 'DESC')
            ->first();
            
        return $rule ? (float) $rule['rate'] : null;
    }
    
    /**
     * Get total commissions
     */
    public function getTotalCommissions($filters = []): float
    {
        $query = $this->api->database()->table('vendor_commissions');
        
        // Apply filters
        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        return (float) $query->sum('commission_amount');
    }
    
    /**
     * Get pending commissions for vendor
     */
    public function getPendingCommissions($vendorId): array
    {
        return $this->api->database()->table('vendor_commissions')
            ->where('vendor_id', $vendorId)
            ->where('status', 'pending')
            ->whereNull('payout_id')
            ->orderBy('created_at', 'ASC')
            ->get()
            ->toArray();
    }
    
    /**
     * Mark commissions as processed
     */
    public function markAsProcessed($commissionIds, $payoutId): int
    {
        return $this->api->database()->table('vendor_commissions')
            ->whereIn('id', $commissionIds)
            ->update([
                'status' => 'processed',
                'processed_at' => date('Y-m-d H:i:s'),
                'payout_id' => $payoutId
            ]);
    }
    
    /**
     * Calculate daily commissions
     */
    public function calculateDailyCommissions($date = null): array
    {
        $date = $date ?? date('Y-m-d', strtotime('-1 day'));
        
        // Get all completed orders for the date
        $orders = $this->api->database()->table('vendor_orders')
            ->whereDate('created_at', $date)
            ->where('status', 'completed')
            ->get();
            
        $results = [
            'date' => $date,
            'total_orders' => count($orders),
            'total_commissions' => 0,
            'vendor_commissions' => []
        ];
        
        foreach ($orders as $order) {
            // Skip if commission already calculated
            if ($this->commissionExists($order->id)) {
                continue;
            }
            
            // Calculate commission
            $commission = $this->calculate($order);
            
            // Record commission
            $this->record([
                'vendor_id' => $order->vendor_id,
                'order_id' => $order->id,
                'order_total' => $order->total,
                'commission_rate' => $commission['rate'],
                'commission_amount' => $commission['amount'],
                'vendor_earnings' => $commission['vendor_earnings']
            ]);
            
            // Update results
            $results['total_commissions'] += $commission['amount'];
            
            if (!isset($results['vendor_commissions'][$order->vendor_id])) {
                $results['vendor_commissions'][$order->vendor_id] = 0;
            }
            $results['vendor_commissions'][$order->vendor_id] += $commission['amount'];
        }
        
        return $results;
    }
    
    /**
     * Get commission rules
     */
    public function getCommissionRules($active = true): array
    {
        $query = $this->api->database()->table('commission_rules');
        
        if ($active) {
            $query->where('active', true);
        }
        
        return $query->orderBy('priority', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->toArray();
    }
    
    /**
     * Create commission rule
     */
    public function createRule($ruleData): int
    {
        // Validate rule data
        $this->validateRuleData($ruleData);
        
        // Set default priority if not specified
        if (!isset($ruleData['priority'])) {
            $ruleData['priority'] = $this->getNextPriority();
        }
        
        // Insert rule
        $ruleId = $this->api->database()->table('commission_rules')->insertGetId($ruleData);
        
        // Clear cache
        $this->cache->tags(['commission_rules'])->flush();
        
        return $ruleId;
    }
    
    /**
     * Update commission rule
     */
    public function updateRule($ruleId, $ruleData): bool
    {
        // Validate rule data
        $this->validateRuleData($ruleData);
        
        // Update rule
        $updated = $this->api->database()->table('commission_rules')
            ->where('id', $ruleId)
            ->update($ruleData);
            
        // Clear cache
        $this->cache->tags(['commission_rules'])->flush();
        
        return $updated > 0;
    }
    
    /**
     * Delete commission rule
     */
    public function deleteRule($ruleId): bool
    {
        $deleted = $this->api->database()->table('commission_rules')
            ->where('id', $ruleId)
            ->delete();
            
        // Clear cache
        $this->cache->tags(['commission_rules'])->flush();
        
        return $deleted > 0;
    }
    
    /**
     * Get commission analytics
     */
    public function getAnalytics($vendorId = null, $period = 'month'): array
    {
        $query = $this->api->database()->table('vendor_commissions');
        
        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }
        
        // Date range based on period
        $dateRange = $this->getDateRangeForPeriod($period);
        $query->whereBetween('created_at', $dateRange);
        
        // Get aggregated data
        $data = $query->selectRaw('
            COUNT(*) as total_orders,
            SUM(order_total) as total_sales,
            SUM(commission_amount) as total_commissions,
            SUM(vendor_earnings) as total_vendor_earnings,
            AVG(commission_rate) as average_rate,
            DATE(created_at) as date
        ')
        ->groupBy('date')
        ->orderBy('date', 'ASC')
        ->get();
        
        return [
            'period' => $period,
            'date_range' => $dateRange,
            'summary' => [
                'total_orders' => $data->sum('total_orders'),
                'total_sales' => $data->sum('total_sales'),
                'total_commissions' => $data->sum('total_commissions'),
                'total_vendor_earnings' => $data->sum('total_vendor_earnings'),
                'average_commission_rate' => $data->avg('average_rate')
            ],
            'daily_data' => $data->toArray()
        ];
    }
    
    /**
     * Calculate additional fees
     */
    private function calculateAdditionalFees($vendor, $order): array
    {
        $fees = [];
        $totalFees = 0;
        
        // Transaction fee
        if ($transactionFee = $this->api->setting('marketplace.transaction_fee', 0)) {
            $fees['transaction'] = round($order->subtotal * ($transactionFee / 100), 2);
            $totalFees += $fees['transaction'];
        }
        
        // Payment processing fee
        if ($processingFee = $this->api->setting('marketplace.processing_fee', 2.9)) {
            $fees['processing'] = round($order->total * ($processingFee / 100), 2);
            $totalFees += $fees['processing'];
        }
        
        // Fixed fee per transaction
        if ($fixedFee = $this->api->setting('marketplace.fixed_fee', 0.30)) {
            $fees['fixed'] = $fixedFee;
            $totalFees += $fixedFee;
        }
        
        return [
            'fees' => $fees,
            'total' => $totalFees
        ];
    }
    
    /**
     * Get product override rate
     */
    private function getProductOverrideRate($order): ?float
    {
        // Check if any items have commission override
        $items = is_array($order->items) ? $order->items : json_decode($order->items, true);
        
        foreach ($items as $item) {
            $productOverride = $this->api->database()->table('vendor_products')
                ->where('product_id', $item['product_id'])
                ->where('vendor_id', $order->vendor_id)
                ->value('commission_override');
                
            if ($productOverride !== null) {
                return (float) $productOverride;
            }
        }
        
        return null;
    }
    
    /**
     * Get category-specific rate
     */
    private function getCategorySpecificRate($order): ?float
    {
        $items = is_array($order->items) ? $order->items : json_decode($order->items, true);
        $categoryRates = [];
        
        foreach ($items as $item) {
            $product = $this->api->database()->table('products')
                ->where('id', $item['product_id'])
                ->first();
                
            if ($product && $product['category_id']) {
                $rate = $this->getCategoryRate($product['category_id']);
                if ($rate !== null) {
                    $categoryRates[] = $rate;
                }
            }
        }
        
        // Return average of category rates if multiple
        return !empty($categoryRates) ? array_sum($categoryRates) / count($categoryRates) : null;
    }
    
    /**
     * Get volume-based rate
     */
    private function getVolumeBasedRate($vendor): ?float
    {
        // Get vendor's monthly sales
        $monthlySales = $this->api->database()->table('vendor_orders')
            ->where('vendor_id', $vendor['id'])
            ->where('created_at', '>=', date('Y-m-01'))
            ->sum('total');
            
        // Find applicable volume rule
        $rule = $this->api->database()->table('commission_rules')
            ->where('type', 'volume')
            ->where('volume_threshold', '<=', $monthlySales)
            ->where('active', true)
            ->orderBy('volume_threshold', 'DESC')
            ->first();
            
        return $rule ? (float) $rule['rate'] : null;
    }
    
    /**
     * Get vendor tier rate
     */
    private function getVendorTierRate($vendor): ?float
    {
        $tier = $vendor['settings']['tier'] ?? 'standard';
        
        $rule = $this->api->database()->table('commission_rules')
            ->where('type', 'tier')
            ->where('vendor_tier', $tier)
            ->where('active', true)
            ->first();
            
        return $rule ? (float) $rule['rate'] : null;
    }
    
    /**
     * Check if commission exists for order
     */
    private function commissionExists($orderId): bool
    {
        return $this->api->database()->table('vendor_commissions')
            ->where('order_id', $orderId)
            ->exists();
    }
    
    /**
     * Update vendor sales
     */
    private function updateVendorSales($vendorId, $amount): void
    {
        $this->api->database()->table('vendors')
            ->where('id', $vendorId)
            ->increment('total_sales', $amount);
    }
    
    /**
     * Validate commission data
     */
    private function validateCommissionData($data): void
    {
        $required = ['vendor_id', 'order_id', 'order_total', 'commission_rate', 'commission_amount', 'vendor_earnings'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new BusinessException("Missing required field: {$field}");
            }
        }
        
        if ($data['commission_amount'] < 0) {
            throw new BusinessException('Commission amount cannot be negative');
        }
        
        if ($data['vendor_earnings'] < 0) {
            throw new BusinessException('Vendor earnings cannot be negative');
        }
    }
    
    /**
     * Validate rule data
     */
    private function validateRuleData($data): void
    {
        if (empty($data['name'])) {
            throw new BusinessException('Rule name is required');
        }
        
        if (empty($data['type'])) {
            throw new BusinessException('Rule type is required');
        }
        
        if (!isset($data['rate']) || $data['rate'] < 0 || $data['rate'] > 100) {
            throw new BusinessException('Invalid commission rate');
        }
        
        $validTypes = ['category', 'vendor_tier', 'volume'];
        if (!in_array($data['type'], $validTypes)) {
            throw new BusinessException('Invalid rule type');
        }
    }
    
    /**
     * Get next priority
     */
    private function getNextPriority(): int
    {
        $maxPriority = $this->api->database()->table('commission_rules')->max('priority');
        return ($maxPriority ?? 0) + 1;
    }
    
    /**
     * Get date range for period
     */
    private function getDateRangeForPeriod($period): array
    {
        switch ($period) {
            case 'day':
                return [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')];
                
            case 'week':
                return [
                    date('Y-m-d 00:00:00', strtotime('monday this week')),
                    date('Y-m-d 23:59:59', strtotime('sunday this week'))
                ];
                
            case 'month':
                return [
                    date('Y-m-01 00:00:00'),
                    date('Y-m-t 23:59:59')
                ];
                
            case 'quarter':
                $quarter = ceil(date('n') / 3);
                $start = date('Y-m-d', mktime(0, 0, 0, ($quarter - 1) * 3 + 1, 1, date('Y')));
                $end = date('Y-m-d', mktime(23, 59, 59, $quarter * 3, date('t', mktime(0, 0, 0, $quarter * 3, 1, date('Y'))), date('Y')));
                return [$start, $end];
                
            case 'year':
                return [
                    date('Y-01-01 00:00:00'),
                    date('Y-12-31 23:59:59')
                ];
                
            default:
                return [
                    date('Y-m-01 00:00:00'),
                    date('Y-m-t 23:59:59')
                ];
        }
    }
    
    /**
     * Clear commission cache
     */
    private function clearCommissionCache($vendorId = null): void
    {
        if ($vendorId) {
            $this->cache->forget("commission_rate_{$vendorId}_*");
        } else {
            $this->cache->tags(['commissions', 'commission_rules'])->flush();
        }
    }
    
    /**
     * Get vendor manager instance
     */
    private function getVendorManager(): VendorManager
    {
        if (!$this->vendorManager) {
            $this->vendorManager = new VendorManager($this->api);
        }
        return $this->vendorManager;
    }
}