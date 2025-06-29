<?php
namespace AIRecommendations\Services;

use AIRecommendations\Repository\RecommendationRepository;
use AIRecommendations\Repository\BehaviorRepository;
use AIRecommendations\Algorithms\CollaborativeFiltering;
use AIRecommendations\Algorithms\ContentBasedFiltering;
use AIRecommendations\Algorithms\HybridEngine;
use AIRecommendations\Algorithms\DeepLearningEngine;

/**
 * AI Recommendation Engine
 * 
 * Core service for generating product recommendations using multiple algorithms
 */
class RecommendationEngine
{
    private RecommendationRepository $recommendationRepo;
    private BehaviorRepository $behaviorRepo;
    private array $config;
    private array $algorithms;
    private array $userPreferences = [];

    public function __construct(
        RecommendationRepository $recommendationRepo,
        BehaviorRepository $behaviorRepo,
        array $config
    ) {
        $this->recommendationRepo = $recommendationRepo;
        $this->behaviorRepo = $behaviorRepo;
        $this->config = $config;
        $this->initializeAlgorithms();
    }

    /**
     * Initialize recommendation algorithms
     */
    private function initializeAlgorithms(): void
    {
        $this->algorithms = [
            'collaborative_filtering' => new CollaborativeFiltering($this->behaviorRepo),
            'content_based' => new ContentBasedFiltering($this->recommendationRepo),
            'hybrid' => new HybridEngine($this->behaviorRepo, $this->recommendationRepo),
            'deep_learning' => new DeepLearningEngine($this->behaviorRepo, $this->recommendationRepo)
        ];
    }

    /**
     * Get product recommendations
     */
    public function getProductRecommendations(int $productId, array $options = []): array
    {
        $cacheKey = "product_recs_{$productId}_" . md5(serialize($options));
        
        // Check cache first
        if ($cached = $this->getCached($cacheKey)) {
            return $cached;
        }

        $algorithm = $options['algorithm'] ?? $this->config['algorithm'] ?? 'hybrid';
        $types = $options['types'] ?? $this->config['recommendation_types'] ?? ['similar_products'];
        $limit = $options['limit'] ?? $this->config['max_recommendations'] ?? 8;
        $userId = $options['user_id'] ?? $this->getCurrentUserId();

        $recommendations = [];

        foreach ($types as $type) {
            $typeRecs = $this->getRecommendationsByType($productId, $type, $algorithm, $userId);
            $recommendations = array_merge($recommendations, $typeRecs);
        }

        // Sort by confidence score and apply filters
        $recommendations = $this->scoreAndFilterRecommendations($recommendations, $options);
        
        // Limit results
        $recommendations = array_slice($recommendations, 0, $limit);

        // Cache results
        $this->cache($cacheKey, $recommendations, $this->config['cache_duration'] ?? 60);

        return $recommendations;
    }

    /**
     * Get user-specific recommendations
     */
    public function getUserRecommendations(int $userId, array $options = []): array
    {
        $cacheKey = "user_recs_{$userId}_" . md5(serialize($options));
        
        if ($cached = $this->getCached($cacheKey)) {
            return $cached;
        }

        $algorithm = $options['algorithm'] ?? $this->config['algorithm'] ?? 'collaborative_filtering';
        $limit = $options['limit'] ?? $this->config['max_recommendations'] ?? 12;

        // Load user behavior history
        $userBehavior = $this->behaviorRepo->getUserBehavior($userId, [
            'window_days' => $this->config['tracking_window'] ?? 30
        ]);

        if (empty($userBehavior)) {
            // Return trending products for new users
            return $this->getTrendingProducts($limit);
        }

        // Get recommendations based on user behavior
        $engine = $this->algorithms[$algorithm];
        $recommendations = $engine->recommendForUser($userId, $userBehavior, $limit * 2);

        // Apply personalization weights
        $recommendations = $this->applyPersonalization($recommendations, $userId);

        // Filter and score
        $recommendations = $this->scoreAndFilterRecommendations($recommendations, $options);
        $recommendations = array_slice($recommendations, 0, $limit);

        $this->cache($cacheKey, $recommendations, $this->config['cache_duration'] ?? 60);

        return $recommendations;
    }

    /**
     * Get cart-based recommendations
     */
    public function getCartRecommendations($cart, array $options = []): array
    {
        $cartItems = $cart->getItems();
        if (empty($cartItems)) {
            return [];
        }

        $productIds = array_map(fn($item) => $item->product_id, $cartItems);
        $cacheKey = "cart_recs_" . md5(implode(',', $productIds) . serialize($options));

        if ($cached = $this->getCached($cacheKey)) {
            return $cached;
        }

        $type = $options['type'] ?? 'frequently_bought';
        $limit = $options['limit'] ?? $this->config['max_recommendations'] ?? 6;

        $recommendations = [];

        switch ($type) {
            case 'frequently_bought':
                $recommendations = $this->getFrequentlyBoughtTogether($productIds, $limit);
                break;
                
            case 'upsell':
                if ($this->config['enable_upselling'] ?? true) {
                    $recommendations = $this->getUpsellProducts($productIds, $limit);
                }
                break;
                
            case 'cross_sell':
                if ($this->config['enable_cross_selling'] ?? true) {
                    $recommendations = $this->getCrossSellProducts($productIds, $limit);
                }
                break;
        }

        // Exclude products already in cart
        $recommendations = array_filter($recommendations, function($rec) use ($productIds) {
            return !in_array($rec['product_id'], $productIds);
        });

        $this->cache($cacheKey, $recommendations, $this->config['cache_duration'] ?? 60);

        return $recommendations;
    }

    /**
     * Get recommendations by type
     */
    private function getRecommendationsByType(int $productId, string $type, string $algorithm, ?int $userId): array
    {
        switch ($type) {
            case 'similar_products':
                return $this->getSimilarProducts($productId, $algorithm);
                
            case 'frequently_bought':
                return $this->getFrequentlyBoughtWith($productId);
                
            case 'personalized':
                return $userId ? $this->getPersonalizedForProduct($productId, $userId) : [];
                
            case 'trending':
                return $this->getTrendingInCategory($productId);
                
            case 'new_arrivals':
                return $this->getNewArrivalsInCategory($productId);
                
            case 'best_sellers':
                return $this->getBestSellersInCategory($productId);
                
            case 'recently_viewed':
                return $userId ? $this->getRecentlyViewedByUser($userId) : [];
                
            case 'cross_category':
                return $this->getCrossCategoryRecommendations($productId);
                
            default:
                return [];
        }
    }

    /**
     * Get similar products using selected algorithm
     */
    private function getSimilarProducts(int $productId, string $algorithm): array
    {
        $engine = $this->algorithms[$algorithm];
        return $engine->findSimilarProducts($productId, 10);
    }

    /**
     * Get frequently bought together products
     */
    private function getFrequentlyBoughtWith(int $productId): array
    {
        return $this->recommendationRepo->getFrequentlyBoughtTogether($productId, [
            'min_confidence' => 0.3,
            'limit' => 8
        ]);
    }

    /**
     * Get frequently bought together for multiple products
     */
    private function getFrequentlyBoughtTogether(array $productIds, int $limit): array
    {
        return $this->recommendationRepo->getFrequentlyBoughtTogether($productIds, [
            'min_confidence' => 0.2,
            'limit' => $limit
        ]);
    }

    /**
     * Get upsell products (higher value alternatives)
     */
    private function getUpsellProducts(array $productIds, int $limit): array
    {
        $avgPrice = $this->calculateAverageCartPrice($productIds);
        $priceRange = $this->config['price_range_factor'] ?? 50;
        
        return $this->recommendationRepo->getUpsellProducts($productIds, [
            'min_price' => $avgPrice * 1.1, // At least 10% higher
            'max_price' => $avgPrice * (1 + $priceRange / 100),
            'limit' => $limit
        ]);
    }

    /**
     * Get cross-sell products (complementary items)
     */
    private function getCrossSellProducts(array $productIds, int $limit): array
    {
        return $this->recommendationRepo->getCrossSellProducts($productIds, [
            'exclude_same_category' => true,
            'limit' => $limit
        ]);
    }

    /**
     * Apply personalization weights
     */
    private function applyPersonalization(array $recommendations, int $userId): array
    {
        if (!isset($this->userPreferences[$userId])) {
            $this->loadUserPreferences($userId);
        }

        $preferences = $this->userPreferences[$userId] ?? [];
        $weight = $this->config['personalization_weight'] ?? 0.7;

        foreach ($recommendations as &$rec) {
            // Adjust score based on user preferences
            $personalizedScore = $this->calculatePersonalizationScore($rec, $preferences);
            $rec['confidence_score'] = ($rec['confidence_score'] * (1 - $weight)) + 
                                     ($personalizedScore * $weight);
        }

        return $recommendations;
    }

    /**
     * Calculate personalization score
     */
    private function calculatePersonalizationScore(array $recommendation, array $preferences): float
    {
        $score = 0.5; // Base score

        // Category preference
        if (isset($preferences['categories'][$recommendation['category_id']])) {
            $score += $preferences['categories'][$recommendation['category_id']] * 0.3;
        }

        // Brand preference
        if (isset($preferences['brands'][$recommendation['brand']])) {
            $score += $preferences['brands'][$recommendation['brand']] * 0.2;
        }

        // Price range preference
        $priceScore = $this->calculatePricePreferenceScore($recommendation['price'], $preferences['price_range'] ?? []);
        $score += $priceScore * 0.2;

        // Time-based preferences
        $timeScore = $this->calculateTimeBasedScore($recommendation, $preferences);
        $score += $timeScore * 0.1;

        return min(1.0, max(0.0, $score));
    }

    /**
     * Score and filter recommendations
     */
    private function scoreAndFilterRecommendations(array $recommendations, array $options): array
    {
        // Remove duplicates
        $recommendations = $this->removeDuplicates($recommendations);

        // Apply filters
        $recommendations = $this->applyFilters($recommendations, $options);

        // Sort by confidence score
        usort($recommendations, fn($a, $b) => $b['confidence_score'] <=> $a['confidence_score']);

        // Apply boost factors
        $recommendations = $this->applyBoostFactors($recommendations);

        return $recommendations;
    }

    /**
     * Apply various filters to recommendations
     */
    private function applyFilters(array $recommendations, array $options): array
    {
        // Minimum confidence filter
        $minConfidence = $options['min_confidence'] ?? $this->config['min_confidence_score'] ?? 0.3;
        $recommendations = array_filter($recommendations, fn($rec) => $rec['confidence_score'] >= $minConfidence);

        // Stock filter
        if ($this->config['exclude_out_of_stock'] ?? true) {
            $recommendations = array_filter($recommendations, fn($rec) => $rec['stock_quantity'] > 0);
        }

        // Price range filter
        if (isset($options['price_range'])) {
            $recommendations = array_filter($recommendations, function($rec) use ($options) {
                return $rec['price'] >= $options['price_range']['min'] && 
                       $rec['price'] <= $options['price_range']['max'];
            });
        }

        return $recommendations;
    }

    /**
     * Apply boost factors for new products, popular items, etc.
     */
    private function applyBoostFactors(array $recommendations): array
    {
        $newProductDays = $this->config['new_product_days'] ?? 30;
        $boostNew = $this->config['boost_new_products'] ?? true;

        foreach ($recommendations as &$rec) {
            // Boost new products
            if ($boostNew && $this->isNewProduct($rec['created_at'], $newProductDays)) {
                $rec['confidence_score'] *= 1.1;
            }

            // Boost trending products
            if ($rec['is_trending'] ?? false) {
                $rec['confidence_score'] *= 1.05;
            }
        }

        return $recommendations;
    }

    /**
     * Load user preferences
     */
    public function loadUserPreferences(int $userId): void
    {
        $this->userPreferences[$userId] = $this->behaviorRepo->getUserPreferences($userId);
    }

    /**
     * Record recommendation feedback
     */
    public function recordFeedback(int $userId, int $recommendationId, string $action, array $context = []): void
    {
        $this->recommendationRepo->recordFeedback([
            'user_id' => $userId,
            'recommendation_id' => $recommendationId,
            'action' => $action, // 'clicked', 'purchased', 'ignored', 'dismissed'
            'context' => $context,
            'timestamp' => time()
        ]);

        // Trigger model update if needed
        if ($action === 'purchased') {
            $this->scheduleModelUpdate();
        }
    }

    /**
     * Get trending products
     */
    private function getTrendingProducts(int $limit): array
    {
        return $this->recommendationRepo->getTrendingProducts([
            'window_days' => 7,
            'limit' => $limit
        ]);
    }

    /**
     * Remove duplicate recommendations
     */
    private function removeDuplicates(array $recommendations): array
    {
        $seen = [];
        return array_filter($recommendations, function($rec) use (&$seen) {
            if (isset($seen[$rec['product_id']])) {
                return false;
            }
            $seen[$rec['product_id']] = true;
            return true;
        });
    }

    /**
     * Cache recommendations
     */
    private function cache(string $key, array $data, int $minutes): void
    {
        // Implementation would use the caching system
        // cache()->put($key, $data, $minutes);
    }

    /**
     * Get cached recommendations
     */
    private function getCached(string $key): ?array
    {
        // Implementation would use the caching system
        // return cache()->get($key);
        return null;
    }

    /**
     * Get current user ID
     */
    private function getCurrentUserId(): ?int
    {
        // Implementation would get current authenticated user
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Check if product is new
     */
    private function isNewProduct(string $createdAt, int $days): bool
    {
        $created = strtotime($createdAt);
        $threshold = time() - ($days * 24 * 60 * 60);
        return $created > $threshold;
    }

    /**
     * Schedule model update
     */
    private function scheduleModelUpdate(): void
    {
        // Queue job for background processing
        // dispatch(new UpdateRecommendationModelJob());
    }
}