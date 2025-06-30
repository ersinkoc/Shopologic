<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Services;

use Shopologic\Core\Logger\LoggerInterface;
use Shopologic\Core\Queue\QueueInterface;

/**
 * Intelligent Payment Retry Service
 * 
 * Implements smart retry logic for failed payments using:
 * - Machine learning to predict retry success
 * - Adaptive retry intervals
 * - Decline code analysis
 * - Customer behavior patterns
 */
class StripeRetryService\n{
    private StripeClient $stripeClient;
    private LoggerInterface $logger;
    private QueueInterface $queue;
    private array $config;
    
    // Decline codes that should trigger retries
    private const RETRYABLE_DECLINE_CODES = [
        'generic_decline',
        'try_again_later',
        'temporary_failure',
        'processing_error',
        'issuer_not_available',
        'rate_limit'
    ];
    
    // Decline codes that should never be retried
    private const NON_RETRYABLE_DECLINE_CODES = [
        'card_declined',
        'insufficient_funds',
        'expired_card',
        'incorrect_cvc',
        'stolen_card',
        'lost_card',
        'pickup_card',
        'restricted_card',
        'security_violation',
        'service_not_allowed',
        'transaction_not_allowed'
    ];
    
    // Smart retry intervals (in seconds)
    private const SMART_RETRY_INTERVALS = [
        'immediate' => [60, 300, 900],           // 1min, 5min, 15min
        'short_term' => [300, 1800, 7200],      // 5min, 30min, 2hr
        'medium_term' => [1800, 7200, 21600],   // 30min, 2hr, 6hr
        'long_term' => [7200, 86400, 259200]    // 2hr, 1day, 3days
    ];
    
    public function __construct(
        StripeClient $stripeClient,
        array $config = []
    ) {
        $this->stripeClient = $stripeClient;
        $this->config = array_merge([
            'max_retry_attempts' => 3,
            'enable_smart_retry' => true,
            'enable_ml_prediction' => true,
            'adaptive_intervals' => true,
            'customer_notification_threshold' => 2,
            'exponential_backoff_multiplier' => 1.5,
            'max_retry_window_hours' => 72
        ], $config);
    }
    
    /**
     * Analyze failed payment and determine retry strategy
     */
    public function analyzeFailedPayment(array $paymentData): array
    {
        $analysis = [
            'should_retry' => false,
            'retry_strategy' => null,
            'retry_intervals' => [],
            'next_retry_at' => null,
            'max_attempts' => $this->config['max_retry_attempts'],
            'probability_of_success' => 0.0,
            'recommended_actions' => []
        ];
        
        try {
            $declineCode = $paymentData['decline_code'] ?? '';
            $failureReason = $paymentData['failure_reason'] ?? '';
            $customerId = $paymentData['customer_id'] ?? null;
            $attemptCount = $paymentData['retry_attempts'] ?? 0;
            
            // Check if decline code is retryable
            if (!$this->isRetryableDeclineCode($declineCode)) {
                $analysis['recommended_actions'][] = 'update_payment_method';
                return $analysis;
            }
            
            // Check retry attempt limit
            if ($attemptCount >= $this->config['max_retry_attempts']) {
                $analysis['recommended_actions'][] = 'contact_customer';
                return $analysis;
            }
            
            // Check retry window
            $originalFailure = strtotime($paymentData['failed_at'] ?? 'now');
            $maxRetryWindow = $originalFailure + ($this->config['max_retry_window_hours'] * 3600);
            if (time() > $maxRetryWindow) {
                $analysis['recommended_actions'][] = 'payment_expired';
                return $analysis;
            }
            
            // Determine retry strategy
            $retryStrategy = $this->determineRetryStrategy($paymentData);
            $analysis['retry_strategy'] = $retryStrategy;
            
            // Calculate retry intervals
            $retryIntervals = $this->calculateRetryIntervals($retryStrategy, $attemptCount);
            $analysis['retry_intervals'] = $retryIntervals;
            
            // Calculate next retry time
            $nextRetryAt = time() + $retryIntervals[0];
            $analysis['next_retry_at'] = date('c', $nextRetryAt);
            
            // Predict success probability
            if ($this->config['enable_ml_prediction']) {
                $analysis['probability_of_success'] = $this->predictRetrySuccess($paymentData);
            }
            
            // Only retry if probability is above threshold
            $probabilityThreshold = 0.3;
            if ($analysis['probability_of_success'] >= $probabilityThreshold || !$this->config['enable_ml_prediction']) {
                $analysis['should_retry'] = true;
            }
            
            // Generate recommendations
            $analysis['recommended_actions'] = $this->generateRetryRecommendations($paymentData, $analysis);
            
        } catch (\RuntimeException $e) {
            $this->logger->error('Failed to analyze payment for retry', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData
            ]);
        }
        
        return $analysis;
    }
    
    /**
     * Process failed payments and schedule retries
     */
    public function processFailedPayments(): void
    {
        try {
            // Get failed payments that are eligible for retry
            $failedPayments = $this->getEligibleFailedPayments();
            
            $processed = 0;
            $scheduled = 0;
            
            foreach ($failedPayments as $payment) {
                $analysis = $this->analyzeFailedPayment($payment);
                
                if ($analysis['should_retry']) {
                    $this->scheduleRetry($payment, $analysis);
                    $scheduled++;
                } else {
                    $this->handleNonRetryablePayment($payment, $analysis);
                }
                
                $processed++;
            }
            
            $this->logger->info('Processed failed payments for retry', [
                'processed' => $processed,
                'scheduled_for_retry' => $scheduled
            ]);
            
        } catch (\RuntimeException $e) {
            $this->logger->error('Failed to process failed payments', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Execute a scheduled retry attempt
     */
    public function executeRetry(array $retryData): array
    {
        $result = [
            'success' => false,
            'payment_intent_id' => null,
            'error' => null,
            'should_retry_again' => false,
            'next_retry_at' => null
        ];
        
        try {
            $paymentIntentId = $retryData['payment_intent_id'];
            $attemptNumber = $retryData['attempt_number'];
            
            // Retrieve the payment intent
            $paymentIntent = $this->stripeClient->retrievePaymentIntent($paymentIntentId);
            
            if (!$paymentIntent) {
                throw new \Exception('Payment intent not found');
            }
            
            // Apply retry optimizations
            $this->applyRetryOptimizations($paymentIntent, $retryData);
            
            // Attempt the retry
            $retryResult = $this->stripeClient->confirmPaymentIntent($paymentIntentId, [
                'payment_method' => $paymentIntent['payment_method'],
                'confirmation_method' => 'automatic',
                'metadata' => [
                    'retry_attempt' => $attemptNumber,
                    'original_failure' => $retryData['original_failure_reason'] ?? ''
                ]
            ]);
            
            if ($retryResult['status'] === 'succeeded') {
                $result['success'] = true;
                $result['payment_intent_id'] = $paymentIntentId;
                
                // Log successful retry
                $this->logger->info('Payment retry succeeded', [
                    'payment_intent_id' => $paymentIntentId,
                    'attempt_number' => $attemptNumber
                ]);\n                \n                // Update retry statistics\n                $this->updateRetryStatistics($paymentIntentId, $attemptNumber, 'success');\n                \n            } else {\n                // Retry failed, analyze for next attempt\n                $failureData = [\n                    'payment_intent_id' => $paymentIntentId,\n                    'decline_code' => $retryResult['last_payment_error']['decline_code'] ?? '',\n                    'failure_reason' => $retryResult['last_payment_error']['message'] ?? '',\n                    'retry_attempts' => $attemptNumber,\n                    'failed_at' => date('c')\n                ];\n                \n                $nextRetryAnalysis = $this->analyzeFailedPayment($failureData);\n                \n                if ($nextRetryAnalysis['should_retry'] && $attemptNumber < $this->config['max_retry_attempts']) {\n                    $result['should_retry_again'] = true;\n                    $result['next_retry_at'] = $nextRetryAnalysis['next_retry_at'];\n                }\n                \n                $result['error'] = $retryResult['last_payment_error']['message'] ?? 'Unknown error';\n                \n                // Update retry statistics\n                $this->updateRetryStatistics($paymentIntentId, $attemptNumber, 'failed');\n            }\n            \n        } catch (\\Exception $e) {\n            $result['error'] = $e->getMessage();\n            \n            $this->logger->error('Payment retry execution failed', [\n                'error' => $e->getMessage(),\n                'retry_data' => $retryData\n            ]);\n        }\n        \n        return $result;\n    }\n    \n    /**\n     * Determine the best retry strategy based on failure analysis\n     */\n    private function determineRetryStrategy(array $paymentData): string\n    {\n        $declineCode = $paymentData['decline_code'] ?? '';\n        $failureReason = $paymentData['failure_reason'] ?? '';\n        $customerHistory = $this->getCustomerRetryHistory($paymentData['customer_id'] ?? null);\n        \n        // Strategy based on decline code\n        switch ($declineCode) {\n            case 'generic_decline':\n            case 'try_again_later':\n                return 'immediate';\n                \n            case 'processing_error':\n            case 'issuer_not_available':\n                return 'short_term';\n                \n            case 'temporary_failure':\n                return 'medium_term';\n                \n            case 'rate_limit':\n                return 'long_term';\n                \n            default:\n                // Use customer history to determine strategy\n                if ($customerHistory['avg_retry_success_time'] < 300) {\n                    return 'immediate';\n                } elseif ($customerHistory['avg_retry_success_time'] < 3600) {\n                    return 'short_term';\n                } else {\n                    return 'medium_term';\n                }\n        }\n    }\n    \n    /**\n     * Calculate adaptive retry intervals\n     */\n    private function calculateRetryIntervals(string $strategy, int $attemptCount): array\n    {\n        $baseIntervals = self::SMART_RETRY_INTERVALS[$strategy] ?? self::SMART_RETRY_INTERVALS['short_term'];\n        \n        if (!$this->config['adaptive_intervals']) {\n            return $baseIntervals;\n        }\n        \n        // Apply exponential backoff\n        $multiplier = pow($this->config['exponential_backoff_multiplier'], $attemptCount);\n        \n        return array_map(function($interval) use ($multiplier) {\n            return (int) ($interval * $multiplier);\n        }, $baseIntervals);\n    }\n    \n    /**\n     * Predict retry success probability using ML\n     */\n    private function predictRetrySuccess(array $paymentData): float\n    {\n        // Extract features for ML model\n        $features = [\n            'decline_code' => $paymentData['decline_code'] ?? '',\n            'card_brand' => $paymentData['card_brand'] ?? '',\n            'card_funding' => $paymentData['card_funding'] ?? '',\n            'customer_retry_history' => $this->getCustomerRetrySuccessRate($paymentData['customer_id'] ?? null),\n            'time_since_failure' => time() - strtotime($paymentData['failed_at'] ?? 'now'),\n            'transaction_amount' => $paymentData['amount'] ?? 0,\n            'attempt_count' => $paymentData['retry_attempts'] ?? 0,\n            'day_of_week' => (int) date('w'),\n            'hour_of_day' => (int) date('H')\n        ];\n        \n        // Simple rule-based prediction (replace with actual ML model)\n        $probability = 0.5; // Base probability\n        \n        // Adjust based on decline code\n        switch ($features['decline_code']) {\n            case 'generic_decline':\n                $probability = 0.7;\n                break;\n            case 'try_again_later':\n                $probability = 0.8;\n                break;\n            case 'processing_error':\n                $probability = 0.6;\n                break;\n            case 'issuer_not_available':\n                $probability = 0.65;\n                break;\n            default:\n                $probability = 0.4;\n        }\n        \n        // Adjust based on customer history\n        if ($features['customer_retry_history'] > 0.7) {\n            $probability *= 1.2;\n        } elseif ($features['customer_retry_history'] < 0.3) {\n            $probability *= 0.8;\n        }\n        \n        // Adjust based on attempt count\n        $probability *= pow(0.8, $features['attempt_count']);\n        \n        // Adjust based on time of day (business hours are better)\n        if ($features['hour_of_day'] >= 9 && $features['hour_of_day'] <= 17) {\n            $probability *= 1.1;\n        }\n        \n        return min(1.0, max(0.0, $probability));\n    }\n    \n    /**\n     * Check if decline code is retryable\n     */\n    private function isRetryableDeclineCode(string $declineCode): bool\n    {\n        if (in_array($declineCode, self::NON_RETRYABLE_DECLINE_CODES)) {\n            return false;\n        }\n        \n        return in_array($declineCode, self::RETRYABLE_DECLINE_CODES) || \n               $this->config['enable_smart_retry'];\n    }\n    \n    /**\n     * Schedule a retry attempt\n     */\n    private function scheduleRetry(array $payment, array $analysis): void\n    {\n        $retryData = [\n            'payment_intent_id' => $payment['payment_intent_id'],\n            'customer_id' => $payment['customer_id'],\n            'attempt_number' => ($payment['retry_attempts'] ?? 0) + 1,\n            'original_failure_reason' => $payment['failure_reason'] ?? '',\n            'retry_strategy' => $analysis['retry_strategy'],\n            'scheduled_at' => time(),\n            'execute_at' => strtotime($analysis['next_retry_at'])\n        ];\n        \n        // Add to retry queue\n        $this->queue->push('stripe.retry_payment', $retryData, [\n            'delay' => strtotime($analysis['next_retry_at']) - time()\n        ]);\n        \n        // Log retry scheduling\n        $this->logger->info('Scheduled payment retry', [\n            'payment_intent_id' => $payment['payment_intent_id'],\n            'attempt_number' => $retryData['attempt_number'],\n            'next_retry_at' => $analysis['next_retry_at'],\n            'probability_of_success' => $analysis['probability_of_success']\n        ]);\n    }\n    \n    /**\n     * Handle payments that shouldn't be retried\n     */\n    private function handleNonRetryablePayment(array $payment, array $analysis): void\n    {\n        $actions = $analysis['recommended_actions'];\n        \n        if (in_array('update_payment_method', $actions)) {\n            // Notify customer to update payment method\n            $this->notifyCustomerUpdatePaymentMethod($payment);\n        }\n        \n        if (in_array('contact_customer', $actions)) {\n            // Create support ticket for manual follow-up\n            $this->createSupportTicket($payment);\n        }\n        \n        // Log non-retryable payment\n        $this->logger->info('Payment marked as non-retryable', [\n            'payment_intent_id' => $payment['payment_intent_id'],\n            'reason' => $payment['decline_code'] ?? 'unknown',\n            'recommended_actions' => $actions\n        ]);\n    }\n    \n    /**\n     * Apply optimizations before retry attempt\n     */\n    private function applyRetryOptimizations(array $paymentIntent, array $retryData): void\n    {\n        // Enable 3D Secure for higher success rate\n        if ($retryData['attempt_number'] >= 2) {\n            $this->stripeClient->updatePaymentIntent($paymentIntent['id'], [\n                'confirmation_method' => 'manual',\n                'payment_method_options' => [\n                    'card' => [\n                        'request_three_d_secure' => 'automatic'\n                    ]\n                ]\n            ]);\n        }\n        \n        // Add retry metadata\n        $this->stripeClient->updatePaymentIntent($paymentIntent['id'], [\n            'metadata' => array_merge($paymentIntent['metadata'] ?? [], [\n                'retry_attempt' => $retryData['attempt_number'],\n                'retry_strategy' => $retryData['retry_strategy']\n            ])\n        ]);\n    }\n    \n    /**\n     * Generate retry recommendations\n     */\n    private function generateRetryRecommendations(array $paymentData, array $analysis): array\n    {\n        $recommendations = [];\n        \n        if ($analysis['probability_of_success'] < 0.3) {\n            $recommendations[] = 'low_success_probability';\n        }\n        \n        if (($paymentData['retry_attempts'] ?? 0) >= 2) {\n            $recommendations[] = 'notify_customer';\n        }\n        \n        if ($analysis['retry_strategy'] === 'long_term') {\n            $recommendations[] = 'consider_alternative_payment';\n        }\n        \n        return $recommendations;\n    }\n    \n    /**\n     * Get eligible failed payments for retry processing\n     */\n    private function getEligibleFailedPayments(): array\n    {\n        // This would query your database for failed payments\n        // Placeholder implementation\n        return [];\n    }\n    \n    /**\n     * Get customer retry history\n     */\n    private function getCustomerRetryHistory(?string $customerId): array\n    {\n        if (!$customerId) {\n            return ['avg_retry_success_time' => 1800]; // 30 minutes default\n        }\n        \n        // Query customer's historical retry data\n        // Placeholder implementation\n        return [\n            'total_retries' => 0,\n            'successful_retries' => 0,\n            'avg_retry_success_time' => 1800\n        ];\n    }\n    \n    /**\n     * Get customer retry success rate\n     */\n    private function getCustomerRetrySuccessRate(?string $customerId): float\n    {\n        $history = $this->getCustomerRetryHistory($customerId);\n        \n        if ($history['total_retries'] === 0) {\n            return 0.5; // Default rate\n        }\n        \n        return $history['successful_retries'] / $history['total_retries'];\n    }\n    \n    /**\n     * Update retry statistics\n     */\n    private function updateRetryStatistics(string $paymentIntentId, int $attemptNumber, string $outcome): void\n    {\n        // Update retry statistics in database\n        // This helps improve the ML model over time\n    }\n    \n    /**\n     * Notify customer to update payment method\n     */\n    private function notifyCustomerUpdatePaymentMethod(array $payment): void\n    {\n        // Send notification to customer\n        // Implementation depends on your notification system\n    }\n    \n    /**\n     * Create support ticket for manual follow-up\n     */\n    private function createSupportTicket(array $payment): void\n    {\n        // Create support ticket for manual intervention\n        // Implementation depends on your support system\n    }\n}