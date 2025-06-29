<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Repository;

use Shopologic\Core\Database\DatabaseInterface;
use Shopologic\Core\Cache\CacheInterface;

/**
 * Stripe Fraud Detection Repository
 * 
 * Handles storage and retrieval of fraud detection data
 */
class StripeFraudRepository
{
    private DatabaseInterface $database;
    private CacheInterface $cache;
    
    public function __construct(DatabaseInterface $database, CacheInterface $cache)
    {
        $this->database = $database;
        $this->cache = $cache;
    }
    
    /**
     * Log fraud analysis results
     */
    public function logFraudAnalysis(array $data): int
    {
        return $this->database->table('stripe_fraud_analysis')->insert($data);
    }
    
    /**
     * Get recent transactions for velocity checking
     */
    public function getRecentTransactions(string $customerId, int $hours): array
    {
        return $this->database->table('stripe_payments')
            ->where('customer_id', $customerId)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$hours} hours")))
            ->get()
            ->toArray();
    }
    
    /**
     * Get last transaction for a customer
     */
    public function getLastTransaction(string $customerId): ?array
    {
        $result = $this->database->table('stripe_payments')
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->first();
            
        return $result ? $result->toArray() : null;
    }
    
    /**
     * Get transactions by IP address
     */
    public function getTransactionsByIP(string $ipAddress, int $hours): array
    {
        return $this->database->table('stripe_payments')
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$hours} hours")))
            ->get()
            ->toArray();
    }
    
    /**
     * Get transactions by card fingerprint
     */
    public function getTransactionsByCardFingerprint(string $fingerprint, int $hours): array
    {
        return $this->database->table('stripe_payments')
            ->where('card_fingerprint', $fingerprint)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$hours} hours")))
            ->get()
            ->toArray();
    }
    
    /**
     * Get device history
     */
    public function getDeviceHistory(string $deviceFingerprint): array
    {
        return $this->cache->remember("device_history_{$deviceFingerprint}", 3600, function() use ($deviceFingerprint) {
            return $this->database->table('stripe_payments')
                ->where('device_fingerprint', $deviceFingerprint)
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
                ->get()
                ->toArray();
        });
    }
    
    /**
     * Get customer profile for behavioral analysis
     */
    public function getCustomerProfile(string $customerId): ?array
    {
        return $this->cache->remember("customer_profile_{$customerId}", 1800, function() use ($customerId) {
            // Get customer statistics
            $stats = $this->database->table('stripe_payments')
                ->selectRaw('
                    COUNT(*) as total_transactions,
                    AVG(amount) as avg_transaction_amount,
                    MAX(created_at) as last_transaction_at,
                    MIN(created_at) as first_transaction_at
                ')
                ->where('customer_id', $customerId)
                ->where('status', 'succeeded')
                ->first();
                
            if (!$stats) {
                return null;
            }
            
            // Calculate account age in days
            $accountAgeDays = $stats->first_transaction_at ? 
                (time() - strtotime($stats->first_transaction_at)) / 86400 : 0;
                
            // Calculate days since last transaction
            $daysSinceLastTransaction = $stats->last_transaction_at ?
                (time() - strtotime($stats->last_transaction_at)) / 86400 : 999;
            
            // Get usual transaction hour
            $usualHour = $this->database->table('stripe_payments')
                ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                ->where('customer_id', $customerId)
                ->groupBy('hour')
                ->orderBy('count', 'desc')
                ->first();
                
            // Get usual categories
            $usualCategories = $this->database->table('stripe_payments')
                ->join('order_items', 'stripe_payments.order_id', '=', 'order_items.order_id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->selectRaw('categories.name, COUNT(*) as count')
                ->where('stripe_payments.customer_id', $customerId)
                ->groupBy('categories.name')
                ->orderBy('count', 'desc')
                ->limit(3)
                ->get()
                ->pluck('name')
                ->toArray();
            
            return [
                'total_transactions' => (int) $stats->total_transactions,
                'avg_transaction_amount' => (float) $stats->avg_transaction_amount,
                'account_age_days' => (int) $accountAgeDays,
                'days_since_last_transaction' => (int) $daysSinceLastTransaction,
                'usual_transaction_hour' => $usualHour ? (int) $usualHour->hour : null,
                'usual_categories' => $usualCategories
            ];
        });
    }
    
    /**
     * Get recent card failures for testing pattern detection
     */
    public function getRecentCardFailures(string $cardFingerprint, int $hours): array
    {
        return $this->database->table('stripe_payments')
            ->where('card_fingerprint', $cardFingerprint)
            ->where('status', 'failed')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$hours} hours")))
            ->get()
            ->toArray();
    }
    
    /**
     * Get customer cards count
     */
    public function getCustomerCards(string $customerId): array
    {
        return $this->database->table('stripe_payment_methods')
            ->where('customer_id', $customerId)
            ->where('type', 'card')
            ->get()
            ->toArray();
    }
    
    /**
     * Get customer transaction count
     */
    public function getCustomerTransactionCount(string $customerId): int
    {
        return $this->database->table('stripe_payments')
            ->where('customer_id', $customerId)
            ->count();
    }
    
    /**
     * Get recent fraud data for ML training
     */
    public function getRecentFraudData(int $days): array
    {
        return $this->database->table('stripe_fraud_analysis')
            ->where('analysis_timestamp', '>=', time() - ($days * 86400))
            ->get()
            ->toArray();
    }
    
    /**
     * Analyze rule performance
     */
    public function analyzeRulePerformance(): array
    {
        // This would analyze Radar rule performance
        // Placeholder implementation
        return [
            [
                'id' => 'rule_1',
                'name' => 'High Risk Country Block',
                'false_positive_rate' => 0.05,
                'false_negative_rate' => 0.02,
                'current_threshold' => 75
            ]
        ];
    }
    
    /**
     * Store fraud detection statistics
     */
    public function storeFraudStats(array $stats): void
    {
        $this->database->table('stripe_fraud_stats')->insert([
            'period' => $stats['period'],
            'total_analyzed' => $stats['total_analyzed'],
            'blocked_count' => $stats['blocked_count'],
            'false_positive_count' => $stats['false_positive_count'],
            'accuracy_rate' => $stats['accuracy_rate'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get fraud detection accuracy metrics
     */
    public function getFraudAccuracyMetrics(int $days): array
    {
        return $this->database->table('stripe_fraud_stats')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$days} days")))
            ->selectRaw('
                AVG(accuracy_rate) as avg_accuracy,
                AVG(false_positive_count / total_analyzed) as avg_false_positive_rate,
                SUM(blocked_count) as total_blocked,
                SUM(total_analyzed) as total_analyzed
            ')
            ->first()
            ->toArray();
    }
}