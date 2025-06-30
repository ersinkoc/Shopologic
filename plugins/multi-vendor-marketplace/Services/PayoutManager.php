<?php

declare(strict_types=1);
namespace MultiVendorMarketplace\Services;

use Shopologic\Core\Services\BaseService;
use Shopologic\Core\Exceptions\BusinessException;

/**
 * Payout Manager Service
 * 
 * Handles vendor payouts, balances, and payment processing
 */
class PayoutManager extends BaseService
{
    private $vendorManager;
    private $commissionEngine;
    private $paymentGateway;
    private $cache;
    
    public function __construct($api)
    {
        parent::__construct($api);
        $this->cache = $api->cache();
    }
    
    /**
     * Update vendor balance
     */
    public function updateVendorBalance($vendorId, $amount, $type = 'credit'): void
    {
        if ($type === 'credit') {
            $this->api->database()->table('vendors')
                ->where('id', $vendorId)
                ->increment('balance', $amount);
        } else {
            $this->api->database()->table('vendors')
                ->where('id', $vendorId)
                ->decrement('balance', $amount);
        }
        
        // Clear cache
        $this->clearBalanceCache($vendorId);
        
        // Log balance update
        $this->logBalanceUpdate($vendorId, $amount, $type);
    }
    
    /**
     * Get vendor balance
     */
    public function getVendorBalance($vendorId): float
    {
        return $this->cache->remember("vendor_balance_{$vendorId}", 300, function() use ($vendorId) {
            // Calculate balance from unpaid commissions
            $unpaidEarnings = $this->api->database()->table('vendor_commissions')
                ->where('vendor_id', $vendorId)
                ->where('status', 'pending')
                ->whereNull('payout_id')
                ->sum('vendor_earnings');
                
            // Subtract any pending payouts
            $pendingPayouts = $this->api->database()->table('vendor_payouts')
                ->where('vendor_id', $vendorId)
                ->where('status', 'pending')
                ->sum('amount');
                
            return (float) ($unpaidEarnings - $pendingPayouts);
        });
    }
    
    /**
     * Get earnings summary
     */
    public function getEarningsSummary($vendorId): array
    {
        $summary = [
            'current_balance' => $this->getVendorBalance($vendorId),
            'lifetime_earnings' => $this->getLifetimeEarnings($vendorId),
            'pending_payout' => $this->getPendingPayoutAmount($vendorId),
            'last_payout' => $this->getLastPayout($vendorId),
            'next_payout_date' => $this->getNextPayoutDate($vendorId),
            'monthly_earnings' => $this->getMonthlyEarnings($vendorId),
            'today_earnings' => $this->getTodayEarnings($vendorId)
        ];
        
        return $summary;
    }
    
    /**
     * Process scheduled payouts
     */
    public function processScheduledPayouts(): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'total_amount' => 0,
            'vendors' => []
        ];
        
        // Get vendors eligible for payout
        $eligibleVendors = $this->getEligibleVendors();
        
        foreach ($eligibleVendors as $vendor) {
            try {
                $payoutResult = $this->processVendorPayout($vendor['id']);
                
                $results['processed']++;
                $results['total_amount'] += $payoutResult['amount'];
                $results['vendors'][] = [
                    'vendor_id' => $vendor['id'],
                    'amount' => $payoutResult['amount'],
                    'payout_id' => $payoutResult['payout_id'],
                    'status' => 'success'
                ];
                
            } catch (\RuntimeException $e) {
                $results['failed']++;
                $results['vendors'][] = [
                    'vendor_id' => $vendor['id'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                
                $this->logPayoutError($vendor['id'], $e);
            }
        }
        
        // Send summary report
        $this->sendPayoutReport($results);
        
        return $results;
    }
    
    /**
     * Process individual vendor payout
     */
    public function processVendorPayout($vendorId, $amount = null): array
    {
        // Get vendor details
        $vendor = $this->getVendorManager()->getVendor($vendorId);
        
        if (!$vendor) {
            throw new BusinessException('Vendor not found');
        }
        
        if ($vendor['status'] !== 'active') {
            throw new BusinessException('Vendor is not active');
        }
        
        // Calculate payout amount if not specified
        if ($amount === null) {
            $amount = $this->calculatePayoutAmount($vendorId);
        }
        
        // Check minimum payout threshold
        $minimumPayout = $this->api->setting('marketplace.minimum_payout', 50);
        if ($amount < $minimumPayout) {
            throw new BusinessException("Payout amount below minimum threshold of {$minimumPayout}");
        }
        
        // Get unpaid commissions
        $commissions = $this->getCommissionEngine()->getPendingCommissions($vendorId);
        
        if (empty($commissions)) {
            throw new BusinessException('No pending commissions to pay out');
        }
        
        // Create payout record
        $payoutId = $this->createPayoutRecord($vendorId, $amount, $commissions);
        
        try {
            // Process payment through gateway
            $transactionId = $this->processPayment($vendor, $amount);
            
            // Update payout status
            $this->updatePayoutStatus($payoutId, 'completed', $transactionId);
            
            // Mark commissions as processed
            $commissionIds = array_column($commissions, 'id');
            $this->getCommissionEngine()->markAsProcessed($commissionIds, $payoutId);
            
            // Update vendor balance
            $this->updateVendorBalance($vendorId, $amount, 'debit');
            
            // Send notification
            $this->sendPayoutNotification($vendor, $amount, $transactionId);
            
            return [
                'payout_id' => $payoutId,
                'amount' => $amount,
                'transaction_id' => $transactionId,
                'commission_count' => count($commissions)
            ];
            
        } catch (\RuntimeException $e) {
            // Update payout status to failed
            $this->updatePayoutStatus($payoutId, 'failed', null, $e->getMessage());
            
            throw $e;
        }
    }
    
    /**
     * Request manual payout
     */
    public function requestPayout($vendorId, $amount = null): array
    {
        // Validate vendor can request payout
        $vendor = $this->getVendorManager()->getVendor($vendorId);
        
        if (!$vendor) {
            throw new BusinessException('Vendor not found');
        }
        
        // Check if vendor has pending payout request
        if ($this->hasPendingPayoutRequest($vendorId)) {
            throw new BusinessException('You already have a pending payout request');
        }
        
        // Get available balance
        $balance = $this->getVendorBalance($vendorId);
        
        if ($amount === null) {
            $amount = $balance;
        }
        
        if ($amount > $balance) {
            throw new BusinessException('Requested amount exceeds available balance');
        }
        
        // Check minimum payout
        $minimumPayout = $this->api->setting('marketplace.minimum_payout', 50);
        if ($amount < $minimumPayout) {
            throw new BusinessException("Minimum payout amount is {$minimumPayout}");
        }
        
        // Create payout request
        $payoutRequest = [
            'vendor_id' => $vendorId,
            'amount' => $amount,
            'status' => 'requested',
            'requested_at' => date('Y-m-d H:i:s')
        ];
        
        $requestId = $this->api->database()->table('vendor_payout_requests')->insertGetId($payoutRequest);
        
        // Notify admin
        $this->notifyAdminPayoutRequest($vendor, $amount);
        
        return [
            'request_id' => $requestId,
            'amount' => $amount,
            'status' => 'requested',
            'message' => 'Payout request submitted successfully'
        ];
    }
    
    /**
     * Get next payout date
     */
    public function getNextPayoutDate($vendorId): ?string
    {
        $vendor = $this->getVendorManager()->getVendor($vendorId);
        
        if (!$vendor) {
            return null;
        }
        
        $schedule = $vendor['payout_schedule'] ?? $this->api->setting('marketplace.payout_frequency', 'monthly');
        
        switch ($schedule) {
            case 'weekly':
                return date('Y-m-d', strtotime('next monday'));
                
            case 'bi-weekly':
                $lastPayout = $this->getLastPayout($vendorId);
                if ($lastPayout) {
                    return date('Y-m-d', strtotime($lastPayout['created_at'] . ' + 2 weeks'));
                }
                return date('Y-m-d', strtotime('next monday'));
                
            case 'monthly':
                return date('Y-m-01', strtotime('next month'));
                
            case 'quarterly':
                $quarter = ceil(date('n') / 3);
                $nextQuarter = ($quarter % 4) + 1;
                $year = $nextQuarter == 1 ? date('Y') + 1 : date('Y');
                return date('Y-m-01', mktime(0, 0, 0, ($nextQuarter - 1) * 3 + 1, 1, $year));
                
            default:
                return null;
        }
    }
    
    /**
     * Get pending payouts
     */
    public function getPendingPayouts(): array
    {
        return $this->api->database()->table('vendor_payouts as vp')
            ->join('vendors as v', 'vp.vendor_id', '=', 'v.id')
            ->where('vp.status', 'pending')
            ->select('vp.*', 'v.store_name', 'v.business_name', 'v.email')
            ->orderBy('vp.created_at', 'ASC')
            ->get()
            ->map(function($payout) {
                $payout['commission_ids'] = json_decode($payout['commission_ids'], true);
                return $payout;
            })
            ->toArray();
    }
    
    /**
     * Get payout history
     */
    public function getPayoutHistory($vendorId, $filters = []): array
    {
        $query = $this->api->database()->table('vendor_payouts')
            ->where('vendor_id', $vendorId);
            
        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'DESC';
        $query->orderBy($sortBy, $sortOrder);
        
        // Pagination
        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 20;
        
        return $query->paginate($perPage, ['*'], 'page', $page);
    }
    
    /**
     * Get payout analytics
     */
    public function getPayoutAnalytics($period = 'month'): array
    {
        $dateRange = $this->getDateRangeForPeriod($period);
        
        $data = $this->api->database()->table('vendor_payouts')
            ->whereBetween('created_at', $dateRange)
            ->where('status', 'completed')
            ->selectRaw('
                COUNT(*) as total_payouts,
                COUNT(DISTINCT vendor_id) as unique_vendors,
                SUM(amount) as total_amount,
                AVG(amount) as average_amount,
                MIN(amount) as min_amount,
                MAX(amount) as max_amount,
                DATE(created_at) as date
            ')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();
            
        return [
            'period' => $period,
            'date_range' => $dateRange,
            'summary' => [
                'total_payouts' => $data->sum('total_payouts'),
                'unique_vendors' => $data->max('unique_vendors'),
                'total_amount' => $data->sum('total_amount'),
                'average_amount' => $data->avg('average_amount'),
                'min_amount' => $data->min('min_amount'),
                'max_amount' => $data->max('max_amount')
            ],
            'daily_data' => $data->toArray(),
            'payment_methods' => $this->getPaymentMethodBreakdown($dateRange),
            'top_vendors' => $this->getTopVendorsByPayout($dateRange)
        ];
    }
    
    /**
     * Get eligible vendors for payout
     */
    private function getEligibleVendors(): array
    {
        $minimumPayout = $this->api->setting('marketplace.minimum_payout', 50);
        
        // Get vendors with sufficient balance
        $vendors = $this->api->database()->table('vendors as v')
            ->join('vendor_commissions as vc', 'v.id', '=', 'vc.vendor_id')
            ->where('v.status', 'active')
            ->where('vc.status', 'pending')
            ->whereNull('vc.payout_id')
            ->groupBy('v.id')
            ->havingRaw('SUM(vc.vendor_earnings) >= ?', [$minimumPayout])
            ->select('v.id', 'v.payout_schedule', $this->api->database()->raw('SUM(vc.vendor_earnings) as balance'))
            ->get()
            ->toArray();
            
        // Filter by payout schedule
        return array_filter($vendors, function($vendor) {
            return $this->isPayoutDue($vendor);
        });
    }
    
    /**
     * Check if payout is due for vendor
     */
    private function isPayoutDue($vendor): bool
    {
        $schedule = $vendor['payout_schedule'] ?? $this->api->setting('marketplace.payout_frequency', 'monthly');
        $lastPayout = $this->getLastPayout($vendor['id']);
        
        if (!$lastPayout) {
            return true; // First payout
        }
        
        $lastPayoutDate = strtotime($lastPayout['created_at']);
        
        switch ($schedule) {
            case 'weekly':
                return $lastPayoutDate < strtotime('-1 week');
                
            case 'bi-weekly':
                return $lastPayoutDate < strtotime('-2 weeks');
                
            case 'monthly':
                return date('Y-m', $lastPayoutDate) < date('Y-m');
                
            case 'quarterly':
                $lastQuarter = ceil(date('n', $lastPayoutDate) / 3);
                $currentQuarter = ceil(date('n') / 3);
                return $lastQuarter < $currentQuarter || date('Y', $lastPayoutDate) < date('Y');
                
            default:
                return false;
        }
    }
    
    /**
     * Calculate payout amount
     */
    private function calculatePayoutAmount($vendorId): float
    {
        $commissions = $this->getCommissionEngine()->getPendingCommissions($vendorId);
        return array_sum(array_column($commissions, 'vendor_earnings'));
    }
    
    /**
     * Create payout record
     */
    private function createPayoutRecord($vendorId, $amount, $commissions): int
    {
        $commissionIds = array_column($commissions, 'id');
        
        // Calculate period
        $dates = array_column($commissions, 'created_at');
        $periodStart = min($dates);
        $periodEnd = max($dates);
        
        $payoutData = [
            'vendor_id' => $vendorId,
            'amount' => $amount,
            'currency' => $this->api->setting('marketplace.currency', 'USD'),
            'method' => $this->getVendorPaymentMethod($vendorId),
            'status' => 'pending',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'commission_ids' => json_encode($commissionIds),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->api->database()->table('vendor_payouts')->insertGetId($payoutData);
    }
    
    /**
     * Process payment through gateway
     */
    private function processPayment($vendor, $amount): string
    {
        // Get payment gateway
        $gateway = $this->getPaymentGateway();
        
        // Get vendor payment details
        $paymentDetails = $this->getVendorPaymentDetails($vendor);
        
        // Process payment
        $result = $gateway->payout([
            'amount' => $amount,
            'currency' => $this->api->setting('marketplace.currency', 'USD'),
            'recipient' => $paymentDetails,
            'description' => "Marketplace payout for {$vendor['store_name']}",
            'metadata' => [
                'vendor_id' => $vendor['id'],
                'type' => 'vendor_payout'
            ]
        ]);
        
        if (!$result['success']) {
            throw new BusinessException('Payment processing failed: ' . $result['error']);
        }
        
        return $result['transaction_id'];
    }
    
    /**
     * Update payout status
     */
    private function updatePayoutStatus($payoutId, $status, $transactionId = null, $failureReason = null): void
    {
        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($status === 'completed') {
            $updateData['transaction_id'] = $transactionId;
            $updateData['processed_at'] = date('Y-m-d H:i:s');
        }
        
        if ($status === 'failed') {
            $updateData['failed_at'] = date('Y-m-d H:i:s');
            $updateData['failure_reason'] = $failureReason;
        }
        
        $this->api->database()->table('vendor_payouts')
            ->where('id', $payoutId)
            ->update($updateData);
    }
    
    /**
     * Get last payout
     */
    private function getLastPayout($vendorId): ?array
    {
        return $this->api->database()->table('vendor_payouts')
            ->where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'DESC')
            ->first();
    }
    
    /**
     * Get lifetime earnings
     */
    private function getLifetimeEarnings($vendorId): float
    {
        return (float) $this->api->database()->table('vendor_commissions')
            ->where('vendor_id', $vendorId)
            ->sum('vendor_earnings');
    }
    
    /**
     * Get pending payout amount
     */
    private function getPendingPayoutAmount($vendorId): float
    {
        return (float) $this->api->database()->table('vendor_payouts')
            ->where('vendor_id', $vendorId)
            ->where('status', 'pending')
            ->sum('amount');
    }
    
    /**
     * Get monthly earnings
     */
    private function getMonthlyEarnings($vendorId): float
    {
        return (float) $this->api->database()->table('vendor_commissions')
            ->where('vendor_id', $vendorId)
            ->where('created_at', '>=', date('Y-m-01'))
            ->sum('vendor_earnings');
    }
    
    /**
     * Get today earnings
     */
    private function getTodayEarnings($vendorId): float
    {
        return (float) $this->api->database()->table('vendor_commissions')
            ->where('vendor_id', $vendorId)
            ->whereDate('created_at', date('Y-m-d'))
            ->sum('vendor_earnings');
    }
    
    /**
     * Check if vendor has pending payout request
     */
    private function hasPendingPayoutRequest($vendorId): bool
    {
        return $this->api->database()->table('vendor_payout_requests')
            ->where('vendor_id', $vendorId)
            ->where('status', 'requested')
            ->exists();
    }
    
    /**
     * Get vendor payment method
     */
    private function getVendorPaymentMethod($vendorId): string
    {
        $vendor = $this->getVendorManager()->getVendor($vendorId);
        return $vendor['settings']['payout_method'] ?? 'bank_transfer';
    }
    
    /**
     * Get vendor payment details
     */
    private function getVendorPaymentDetails($vendor): array
    {
        $bankDetails = json_decode($vendor['bank_details'], true);
        
        if (empty($bankDetails)) {
            throw new BusinessException('Vendor payment details not configured');
        }
        
        return $bankDetails;
    }
    
    /**
     * Send payout notification
     */
    private function sendPayoutNotification($vendor, $amount, $transactionId): void
    {
        $this->api->notification()->send($vendor['user_id'], [
            'type' => 'payout_processed',
            'title' => 'Payout Processed',
            'message' => "Your payout of {$amount} has been processed. Transaction ID: {$transactionId}",
            'email' => true
        ]);
        
        // Create vendor notification
        $this->api->database()->table('vendor_notifications')->insert([
            'vendor_id' => $vendor['id'],
            'type' => 'payout',
            'title' => 'Payout Processed',
            'message' => "Your payout of {$amount} has been processed successfully.",
            'data' => json_encode([
                'amount' => $amount,
                'transaction_id' => $transactionId
            ])
        ]);
    }
    
    /**
     * Send payout report
     */
    private function sendPayoutReport($results): void
    {
        // Send to admin
        $adminEmails = $this->api->setting('marketplace.admin_emails', []);
        
        foreach ($adminEmails as $email) {
            $this->api->mail()->send($email, 'marketplace/payout-report', [
                'results' => $results,
                'date' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * Log payout error
     */
    private function logPayoutError($vendorId, \Exception $e): void
    {
        $this->api->logger()->error('Vendor payout failed', [
            'vendor_id' => $vendorId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    /**
     * Log balance update
     */
    private function logBalanceUpdate($vendorId, $amount, $type): void
    {
        $this->api->logger()->info('Vendor balance updated', [
            'vendor_id' => $vendorId,
            'amount' => $amount,
            'type' => $type,
            'new_balance' => $this->getVendorBalance($vendorId)
        ]);
    }
    
    /**
     * Notify admin about payout request
     */
    private function notifyAdminPayoutRequest($vendor, $amount): void
    {
        $adminUsers = $this->api->database()->table('users')
            ->whereIn('role', ['admin', 'marketplace_admin'])
            ->get();
            
        foreach ($adminUsers as $admin) {
            $this->api->notification()->send($admin['id'], [
                'type' => 'vendor_payout_request',
                'title' => 'Vendor Payout Request',
                'message' => "{$vendor['store_name']} has requested a payout of {$amount}",
                'priority' => 'high'
            ]);
        }
    }
    
    /**
     * Get payment method breakdown
     */
    private function getPaymentMethodBreakdown($dateRange): array
    {
        return $this->api->database()->table('vendor_payouts')
            ->whereBetween('created_at', $dateRange)
            ->where('status', 'completed')
            ->groupBy('method')
            ->selectRaw('method, COUNT(*) as count, SUM(amount) as total')
            ->get()
            ->toArray();
    }
    
    /**
     * Get top vendors by payout
     */
    private function getTopVendorsByPayout($dateRange, $limit = 10): array
    {
        return $this->api->database()->table('vendor_payouts as vp')
            ->join('vendors as v', 'vp.vendor_id', '=', 'v.id')
            ->whereBetween('vp.created_at', $dateRange)
            ->where('vp.status', 'completed')
            ->groupBy('vp.vendor_id', 'v.store_name')
            ->selectRaw('vp.vendor_id, v.store_name, COUNT(*) as payout_count, SUM(vp.amount) as total_amount')
            ->orderBy('total_amount', 'DESC')
            ->limit($limit)
            ->get()
            ->toArray();
    }
    
    /**
     * Get date range for period
     */
    private function getDateRangeForPeriod($period): array
    {
        // Implementation same as in CommissionEngine
        switch ($period) {
            case 'day':
                return [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')];
            case 'week':
                return [
                    date('Y-m-d 00:00:00', strtotime('monday this week')),
                    date('Y-m-d 23:59:59', strtotime('sunday this week'))
                ];
            case 'month':
                return [date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59')];
            case 'year':
                return [date('Y-01-01 00:00:00'), date('Y-12-31 23:59:59')];
            default:
                return [date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59')];
        }
    }
    
    /**
     * Clear balance cache
     */
    private function clearBalanceCache($vendorId): void
    {
        $this->cache->forget("vendor_balance_{$vendorId}");
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
    
    /**
     * Get commission engine instance
     */
    private function getCommissionEngine(): CommissionEngine
    {
        if (!$this->commissionEngine) {
            $this->commissionEngine = new CommissionEngine($this->api);
        }
        return $this->commissionEngine;
    }
    
    /**
     * Get payment gateway instance
     */
    private function getPaymentGateway()
    {
        if (!$this->paymentGateway) {
            // This would typically return the configured payment gateway
            // For now, return a mock gateway
            $this->paymentGateway = $this->api->paymentGateway('marketplace');
        }
        return $this->paymentGateway;
    }
}