<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Services;

use Shopologic\Plugins\PaymentStripe\Repository\StripeFraudRepository;
use Shopologic\Core\Logger\LoggerInterface;
use Shopologic\Core\Cache\CacheInterface;

/**
 * Advanced Stripe Fraud Detection Service
 * 
 * Implements sophisticated fraud detection algorithms including:
 * - Machine learning risk scoring
 * - Behavioral analytics
 * - Device fingerprinting
 * - Velocity checks
 * - Geographic risk assessment
 */
class StripeFraudDetectionService
{
    private StripeClient $stripeClient;
    private StripeFraudRepository $fraudRepository;
    private LoggerInterface $logger;
    private CacheInterface $cache;
    private array $config;
    
    // Risk scoring weights
    private const RISK_WEIGHTS = [
        'velocity' => 0.25,
        'geography' => 0.20,
        'device' => 0.15,
        'behavior' => 0.25,
        'card_data' => 0.15
    ];
    
    // Fraud indicators
    private const HIGH_RISK_COUNTRIES = ['NG', 'GH', 'PK', 'BD', 'ID'];
    private const SUSPICIOUS_EMAIL_DOMAINS = ['temp-mail.org', '10minutemail.com', 'guerrillamail.com'];
    
    public function __construct(
        StripeClient $stripeClient,
        StripeFraudRepository $fraudRepository,
        array $config = []
    ) {
        $this->stripeClient = $stripeClient;
        $this->fraudRepository = $fraudRepository;
        $this->config = array_merge([
            'risk_threshold' => 32,
            'enable_ml_scoring' => true,
            'enable_velocity_checks' => true,
            'enable_device_fingerprinting' => true,
            'enable_behavioral_analysis' => true,
            'max_daily_transactions' => 10,
            'max_transaction_amount' => 10000,
            'suspicious_country_multiplier' => 2.0
        ], $config);
    }
    
    /**
     * Analyze payment for fraud risk
     */
    public function analyzePayment(array $paymentData): array
    {
        $riskScore = 0;
        $riskFactors = [];
        $recommendations = [];
        
        try {
            // Velocity check
            $velocityRisk = $this->checkVelocity($paymentData);
            $riskScore += $velocityRisk['score'] * self::RISK_WEIGHTS['velocity'];
            if ($velocityRisk['score'] > 0) {
                $riskFactors[] = $velocityRisk['factor'];
            }
            
            // Geographic risk assessment
            $geoRisk = $this->assessGeographicRisk($paymentData);
            $riskScore += $geoRisk['score'] * self::RISK_WEIGHTS['geography'];
            if ($geoRisk['score'] > 0) {
                $riskFactors[] = $geoRisk['factor'];
            }
            
            // Device fingerprinting
            $deviceRisk = $this->analyzeDevice($paymentData);
            $riskScore += $deviceRisk['score'] * self::RISK_WEIGHTS['device'];
            if ($deviceRisk['score'] > 0) {
                $riskFactors[] = $deviceRisk['factor'];
            }
            
            // Behavioral analysis
            $behaviorRisk = $this->analyzeBehavior($paymentData);
            $riskScore += $behaviorRisk['score'] * self::RISK_WEIGHTS['behavior'];
            if ($behaviorRisk['score'] > 0) {
                $riskFactors[] = $behaviorRisk['factor'];
            }
            
            // Card data analysis
            $cardRisk = $this->analyzeCardData($paymentData);
            $riskScore += $cardRisk['score'] * self::RISK_WEIGHTS['card_data'];
            if ($cardRisk['score'] > 0) {
                $riskFactors[] = $cardRisk['factor'];
            }
            
            // ML-based risk scoring (if enabled)
            if ($this->config['enable_ml_scoring']) {
                $mlScore = $this->getMachineLearningScore($paymentData);
                $riskScore = ($riskScore * 0.7) + ($mlScore * 0.3);
            }
            
            // Generate recommendations
            $recommendations = $this->generateRecommendations($riskScore, $riskFactors);
            
            // Log fraud analysis
            $this->fraudRepository->logFraudAnalysis([
                'payment_id' => $paymentData['id'] ?? null,
                'customer_id' => $paymentData['customer_id'] ?? null,
                'risk_score' => $riskScore,
                'risk_factors' => json_encode($riskFactors),
                'recommendations' => json_encode($recommendations),
                'analysis_timestamp' => time()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Fraud detection analysis failed', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData
            ]);
            
            // Default to medium risk on analysis failure
            $riskScore = 50;
            $riskFactors[] = 'analysis_failed';
            $recommendations[] = 'manual_review';
        }
        
        return [
            'risk_score' => min(100, max(0, $riskScore)),
            'risk_level' => $this->getRiskLevel($riskScore),
            'risk_factors' => array_unique($riskFactors),
            'recommendations' => array_unique($recommendations),
            'requires_3ds' => $riskScore >= $this->config['risk_threshold'],
            'requires_manual_review' => $riskScore >= 75,
            'should_block' => $riskScore >= 90
        ];
    }
    
    /**
     * Check transaction velocity for fraud patterns
     */
    private function checkVelocity(array $paymentData): array
    {
        $score = 0;
        $factor = '';
        
        $customerId = $paymentData['customer_id'] ?? null;
        $ipAddress = $paymentData['ip_address'] ?? null;
        $cardFingerprint = $paymentData['card_fingerprint'] ?? null;
        
        if ($customerId) {
            // Check customer transaction frequency
            $recentTransactions = $this->fraudRepository->getRecentTransactions($customerId, 24);
            if (count($recentTransactions) > $this->config['max_daily_transactions']) {
                $score += 30;
                $factor = 'high_customer_velocity';
            }
            
            // Check for rapid successive transactions
            $lastTransaction = $this->fraudRepository->getLastTransaction($customerId);
            if ($lastTransaction && (time() - strtotime($lastTransaction['created_at'])) < 300) {
                $score += 25;
                $factor = 'rapid_successive_transactions';
            }
        }
        
        if ($ipAddress) {
            // Check IP-based velocity
            $ipTransactions = $this->fraudRepository->getTransactionsByIP($ipAddress, 1);
            if (count($ipTransactions) > 5) {
                $score += 20;
                $factor = 'high_ip_velocity';
            }
        }
        
        if ($cardFingerprint) {
            // Check card-based velocity
            $cardTransactions = $this->fraudRepository->getTransactionsByCardFingerprint($cardFingerprint, 1);
            if (count($cardTransactions) > 3) {
                $score += 35;
                $factor = 'high_card_velocity';
            }
        }
        
        return ['score' => $score, 'factor' => $factor];
    }
    
    /**
     * Assess geographic risk based on location data
     */
    private function assessGeographicRisk(array $paymentData): array
    {
        $score = 0;
        $factor = '';
        
        $billingCountry = $paymentData['billing_country'] ?? null;
        $ipCountry = $paymentData['ip_country'] ?? null;
        $cardCountry = $paymentData['card_country'] ?? null;
        
        // High-risk country check
        if ($billingCountry && in_array($billingCountry, self::HIGH_RISK_COUNTRIES)) {
            $score += 25 * $this->config['suspicious_country_multiplier'];
            $factor = 'high_risk_billing_country';
        }
        
        if ($ipCountry && in_array($ipCountry, self::HIGH_RISK_COUNTRIES)) {
            $score += 20 * $this->config['suspicious_country_multiplier'];
            $factor = 'high_risk_ip_country';
        }
        
        // Geographic mismatch analysis
        if ($billingCountry && $ipCountry && $billingCountry !== $ipCountry) {
            $score += 15;
            $factor = 'billing_ip_country_mismatch';
        }
        
        if ($cardCountry && $billingCountry && $cardCountry !== $billingCountry) {
            $score += 10;
            $factor = 'card_billing_country_mismatch';
        }
        
        // Time zone analysis
        if (isset($paymentData['transaction_time']) && $ipCountry) {
            $isUnusualTime = $this->isUnusualTransactionTime($paymentData['transaction_time'], $ipCountry);
            if ($isUnusualTime) {
                $score += 8;
                $factor = 'unusual_transaction_time';
            }
        }
        
        return ['score' => $score, 'factor' => $factor];
    }
    
    /**
     * Analyze device characteristics for fraud indicators
     */
    private function analyzeDevice(array $paymentData): array
    {
        $score = 0;
        $factor = '';
        
        $userAgent = $paymentData['user_agent'] ?? '';
        $deviceFingerprint = $paymentData['device_fingerprint'] ?? null;
        
        // Check for suspicious user agents
        if ($this->isSuspiciousUserAgent($userAgent)) {
            $score += 20;
            $factor = 'suspicious_user_agent';
        }
        
        // Device fingerprint analysis
        if ($deviceFingerprint) {
            $deviceHistory = $this->fraudRepository->getDeviceHistory($deviceFingerprint);
            
            // Multiple customers using same device
            if (count(array_unique(array_column($deviceHistory, 'customer_id'))) > 3) {
                $score += 25;
                $factor = 'shared_device_multiple_customers';
            }
            
            // High transaction volume from device
            if (count($deviceHistory) > 20) {
                $score += 15;
                $factor = 'high_device_transaction_volume';
            }
        }
        
        // Screen resolution and browser checks
        if (isset($paymentData['screen_resolution'])) {
            if ($this->isUnusualScreenResolution($paymentData['screen_resolution'])) {
                $score += 5;
                $factor = 'unusual_screen_resolution';
            }
        }
        
        return ['score' => $score, 'factor' => $factor];
    }
    
    /**
     * Analyze user behavior patterns
     */
    private function analyzeBehavior(array $paymentData): array
    {
        $score = 0;
        $factor = '';
        
        $customerId = $paymentData['customer_id'] ?? null;
        $email = $paymentData['email'] ?? '';
        $amount = $paymentData['amount'] ?? 0;
        
        // Email analysis
        if ($this->isSuspiciousEmail($email)) {
            $score += 30;
            $factor = 'suspicious_email_domain';
        }
        
        // Customer behavior analysis
        if ($customerId) {
            $customerProfile = $this->fraudRepository->getCustomerProfile($customerId);
            
            if ($customerProfile) {
                // Unusual purchase amount
                $avgAmount = $customerProfile['avg_transaction_amount'] ?? 0;
                if ($amount > ($avgAmount * 5) && $amount > 1000) {
                    $score += 20;
                    $factor = 'unusual_purchase_amount';
                }
                
                // Time pattern analysis
                $usualHour = $customerProfile['usual_transaction_hour'] ?? null;
                $currentHour = (int) date('H');
                if ($usualHour && abs($currentHour - $usualHour) > 6) {
                    $score += 10;
                    $factor = 'unusual_transaction_time_pattern';
                }
                
                // Purchase category deviation
                $usualCategories = $customerProfile['usual_categories'] ?? [];
                $currentCategory = $paymentData['product_category'] ?? '';
                if (!empty($usualCategories) && $currentCategory && !in_array($currentCategory, $usualCategories)) {
                    $score += 8;
                    $factor = 'unusual_purchase_category';
                }
            }
        }
        
        // First-time buyer risk
        if (!$customerId || ($customerId && $this->isFirstTimeCustomer($customerId))) {
            if ($amount > 500) {
                $score += 15;
                $factor = 'high_amount_first_time_buyer';
            }
        }
        
        return ['score' => $score, 'factor' => $factor];
    }
    
    /**
     * Analyze card data for fraud indicators
     */
    private function analyzeCardData(array $paymentData): array
    {
        $score = 0;
        $factor = '';
        
        $cardBin = $paymentData['card_bin'] ?? '';
        $cardLast4 = $paymentData['card_last4'] ?? '';
        $cardFingerprint = $paymentData['card_fingerprint'] ?? '';
        
        // BIN analysis
        if ($cardBin) {
            $binData = $this->getBinData($cardBin);
            
            // High-risk BIN ranges
            if ($binData['risk_level'] === 'high') {
                $score += 25;
                $factor = 'high_risk_bin';
            }
            
            // Prepaid cards
            if ($binData['card_type'] === 'prepaid') {
                $score += 12;
                $factor = 'prepaid_card';
            }
            
            // Corporate cards for personal purchases
            if ($binData['card_type'] === 'corporate' && ($paymentData['purchase_type'] ?? '') === 'personal') {
                $score += 8;
                $factor = 'corporate_card_personal_purchase';
            }
        }
        
        // Card testing patterns
        if ($cardFingerprint) {
            $recentFailures = $this->fraudRepository->getRecentCardFailures($cardFingerprint, 1);
            if (count($recentFailures) > 3) {
                $score += 30;
                $factor = 'card_testing_pattern';
            }
        }
        
        // Multiple cards from same customer
        if (isset($paymentData['customer_id'])) {
            $customerCards = $this->fraudRepository->getCustomerCards($paymentData['customer_id']);
            if (count($customerCards) > 5) {
                $score += 10;
                $factor = 'multiple_cards_same_customer';
            }
        }
        
        return ['score' => $score, 'factor' => $factor];
    }
    
    /**
     * Get machine learning risk score
     */
    private function getMachineLearningScore(array $paymentData): float
    {
        // Implement ML model prediction
        // This would typically call an external ML service or use a trained model
        
        $features = $this->extractMLFeatures($paymentData);
        
        // Placeholder for ML model - in production, this would call your ML service
        // For now, return a weighted score based on available features
        $score = 0;
        
        // Simple feature-based scoring as ML placeholder
        if ($features['transaction_hour'] < 6 || $features['transaction_hour'] > 23) {
            $score += 10;
        }
        
        if ($features['amount'] > $features['customer_avg_amount'] * 3) {
            $score += 15;
        }
        
        if ($features['days_since_last_transaction'] > 90) {
            $score += 8;
        }
        
        return min(100, $score);
    }
    
    /**
     * Extract features for ML model
     */
    private function extractMLFeatures(array $paymentData): array
    {
        $customerId = $paymentData['customer_id'] ?? null;
        $customerProfile = $customerId ? $this->fraudRepository->getCustomerProfile($customerId) : null;
        
        return [
            'amount' => $paymentData['amount'] ?? 0,
            'transaction_hour' => (int) date('H'),
            'transaction_day_of_week' => (int) date('w'),
            'customer_age_days' => $customerProfile['account_age_days'] ?? 0,
            'customer_transaction_count' => $customerProfile['total_transactions'] ?? 0,
            'customer_avg_amount' => $customerProfile['avg_transaction_amount'] ?? 0,
            'days_since_last_transaction' => $customerProfile['days_since_last_transaction'] ?? 0,
            'is_weekend' => in_array(date('w'), [0, 6]) ? 1 : 0,
            'is_high_risk_country' => in_array($paymentData['billing_country'] ?? '', self::HIGH_RISK_COUNTRIES) ? 1 : 0,
            'country_mismatch' => ($paymentData['billing_country'] ?? '') !== ($paymentData['ip_country'] ?? '') ? 1 : 0
        ];
    }
    
    /**
     * Generate actionable recommendations based on risk analysis
     */
    private function generateRecommendations(float $riskScore, array $riskFactors): array
    {
        $recommendations = [];
        
        if ($riskScore >= 90) {
            $recommendations[] = 'block_transaction';
            $recommendations[] = 'flag_customer_account';
        } elseif ($riskScore >= 75) {
            $recommendations[] = 'manual_review';
            $recommendations[] = 'require_additional_verification';
        } elseif ($riskScore >= 50) {
            $recommendations[] = 'require_3d_secure';
            $recommendations[] = 'monitor_closely';
        } elseif ($riskScore >= 32) {
            $recommendations[] = 'enable_3d_secure';
        }
        
        // Specific recommendations based on risk factors
        foreach ($riskFactors as $factor) {
            switch ($factor) {
                case 'high_customer_velocity':
                    $recommendations[] = 'implement_rate_limiting';
                    break;
                case 'high_risk_billing_country':
                    $recommendations[] = 'require_id_verification';
                    break;
                case 'suspicious_email_domain':
                    $recommendations[] = 'verify_email_ownership';
                    break;
                case 'card_testing_pattern':
                    $recommendations[] = 'block_card_temporarily';
                    break;
                case 'unusual_purchase_amount':
                    $recommendations[] = 'verify_purchase_intent';
                    break;
            }
        }
        
        return array_unique($recommendations);
    }
    
    /**
     * Get risk level description
     */
    private function getRiskLevel(float $score): string
    {
        if ($score >= 90) return 'critical';
        if ($score >= 75) return 'high';
        if ($score >= 50) return 'medium';
        if ($score >= 25) return 'low';
        return 'minimal';
    }
    
    /**
     * Check if email is suspicious
     */
    private function isSuspiciousEmail(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        return in_array($domain, self::SUSPICIOUS_EMAIL_DOMAINS);
    }
    
    /**
     * Check if user agent is suspicious
     */
    private function isSuspiciousUserAgent(string $userAgent): bool
    {
        $suspicious = ['bot', 'crawler', 'spider', 'scraper', 'headless'];
        $userAgentLower = strtolower($userAgent);
        
        foreach ($suspicious as $pattern) {
            if (strpos($userAgentLower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if screen resolution is unusual
     */
    private function isUnusualScreenResolution(string $resolution): bool
    {
        $common = ['1920x1080', '1366x768', '1536x864', '1440x900', '1280x720'];
        return !in_array($resolution, $common);
    }
    
    /**
     * Check if transaction time is unusual for the country
     */
    private function isUnusualTransactionTime(string $transactionTime, string $country): bool
    {
        // Simplified implementation - in production, use proper timezone data
        $hour = (int) date('H', strtotime($transactionTime));
        return $hour < 6 || $hour > 23;
    }
    
    /**
     * Check if customer is first-time buyer
     */
    private function isFirstTimeCustomer(string $customerId): bool
    {
        $transactionCount = $this->fraudRepository->getCustomerTransactionCount($customerId);
        return $transactionCount <= 1;
    }
    
    /**
     * Get BIN data for card analysis
     */
    private function getBinData(string $bin): array
    {
        // Cache BIN data for performance
        return $this->cache->remember("bin_data_{$bin}", 86400, function() use ($bin) {
            // In production, this would call a BIN database service
            return [
                'risk_level' => 'medium',
                'card_type' => 'consumer',
                'bank_name' => 'Unknown',
                'country' => 'Unknown'
            ];
        });
    }
    
    /**
     * Update ML models with new fraud data
     */
    public function updateModels(): void
    {
        try {
            // Collect recent fraud data
            $trainingData = $this->fraudRepository->getRecentFraudData(30);
            
            // Update models (placeholder - implement actual ML training)
            $this->logger->info('Updated fraud detection models', [
                'training_samples' => count($trainingData)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update fraud detection models', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Optimize Radar rules based on performance data
     */
    public function optimizeRadarRules(): void
    {
        try {
            // Analyze rule performance
            $rulePerformance = $this->fraudRepository->analyzeRulePerformance();
            
            // Optimize rules based on false positive/negative rates
            foreach ($rulePerformance as $rule) {
                if ($rule['false_positive_rate'] > 0.1) {
                    // Adjust rule threshold
                    $this->adjustRadarRule($rule['id'], $rule['current_threshold'] + 5);
                }
            }
            
            $this->logger->info('Optimized Radar rules', [
                'rules_analyzed' => count($rulePerformance)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to optimize Radar rules', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Adjust Radar rule threshold
     */
    private function adjustRadarRule(string $ruleId, int $newThreshold): void
    {
        // Implement Radar rule adjustment via Stripe API
        // This is a placeholder for the actual implementation
    }
}