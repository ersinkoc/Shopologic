<?php
namespace AIRecommendations\Controllers;

use Shopologic\Core\HTTP\Request;
use Shopologic\Core\HTTP\Response;
use Shopologic\Core\HTTP\JsonResponse;
use AIRecommendations\Services\RecommendationEngine;
use AIRecommendations\Services\PerformanceAnalyzer;

/**
 * Recommendation API Controller
 * 
 * Handles API requests for product recommendations
 */
class RecommendationController
{
    private RecommendationEngine $engine;
    private PerformanceAnalyzer $analyzer;

    public function __construct(
        RecommendationEngine $engine,
        PerformanceAnalyzer $analyzer
    ) {
        $this->engine = $engine;
        $this->analyzer = $analyzer;
    }

    /**
     * Get product recommendations
     * GET /api/v1/recommendations/products/{id}
     */
    public function getProductRecommendations(Request $request, int $productId): JsonResponse
    {
        try {
            $options = [
                'algorithm' => $request->query('algorithm'),
                'types' => $request->query('types', []),
                'limit' => $request->query('limit', 8),
                'user_id' => $request->user()?->id,
                'min_confidence' => $request->query('min_confidence', 0.3)
            ];

            // Filter out null values
            $options = array_filter($options, fn($value) => $value !== null);

            // Parse types if string
            if (is_string($options['types'])) {
                $options['types'] = explode(',', $options['types']);
            }

            $recommendations = $this->engine->getProductRecommendations($productId, $options);

            // Track API usage
            $this->analyzer->trackApiCall('product_recommendations', [
                'product_id' => $productId,
                'result_count' => count($recommendations),
                'options' => $options
            ]);

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'product_id' => $productId,
                    'recommendations' => $recommendations,
                    'total_count' => count($recommendations),
                    'algorithm_used' => $options['algorithm'] ?? 'hybrid',
                    'generated_at' => date('c')
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'message' => 'Failed to get product recommendations',
                    'code' => 'RECOMMENDATION_ERROR'
                ]
            ], 500);
        }
    }

    /**
     * Get user recommendations
     * GET /api/v1/recommendations/user
     */
    public function getUserRecommendations(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'error' => ['message' => 'Authentication required', 'code' => 'AUTH_REQUIRED']
                ], 401);
            }

            $options = [
                'algorithm' => $request->query('algorithm'),
                'limit' => $request->query('limit', 12),
                'categories' => $request->query('categories', []),
                'price_range' => $this->parsePriceRange($request->query('price_range'))
            ];

            // Filter out null values
            $options = array_filter($options, fn($value) => $value !== null);

            $recommendations = $this->engine->getUserRecommendations($user->id, $options);

            // Track API usage
            $this->analyzer->trackApiCall('user_recommendations', [
                'user_id' => $user->id,
                'result_count' => count($recommendations),
                'options' => $options
            ]);

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'recommendations' => $recommendations,
                    'total_count' => count($recommendations),
                    'personalized' => true,
                    'generated_at' => date('c')
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'message' => 'Failed to get user recommendations',
                    'code' => 'USER_RECOMMENDATION_ERROR'
                ]
            ], 500);
        }
    }

    /**
     * Get cart recommendations
     * GET /api/v1/recommendations/cart
     */
    public function getCartRecommendations(Request $request): JsonResponse
    {
        try {
            // Get cart from session or request
            $cartId = $request->query('cart_id') ?? $request->session('cart_id');
            if (!$cartId) {
                return new JsonResponse([
                    'success' => false,
                    'error' => ['message' => 'Cart ID required', 'code' => 'CART_REQUIRED']
                ], 400);
            }

            // Load cart
            $cart = $this->loadCart($cartId);
            if (!$cart) {
                return new JsonResponse([
                    'success' => false,
                    'error' => ['message' => 'Cart not found', 'code' => 'CART_NOT_FOUND']
                ], 404);
            }

            $options = [
                'type' => $request->query('type', 'frequently_bought'),
                'limit' => $request->query('limit', 6),
                'exclude_categories' => $request->query('exclude_categories', [])
            ];

            $recommendations = $this->engine->getCartRecommendations($cart, $options);

            // Track API usage
            $this->analyzer->trackApiCall('cart_recommendations', [
                'cart_id' => $cartId,
                'cart_value' => $cart->getTotalValue(),
                'item_count' => $cart->getItemCount(),
                'result_count' => count($recommendations),
                'options' => $options
            ]);

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'cart_id' => $cartId,
                    'recommendations' => $recommendations,
                    'total_count' => count($recommendations),
                    'recommendation_type' => $options['type'],
                    'generated_at' => date('c')
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'message' => 'Failed to get cart recommendations',
                    'code' => 'CART_RECOMMENDATION_ERROR'
                ]
            ], 500);
        }
    }

    /**
     * Record recommendation feedback
     * POST /api/v1/recommendations/feedback
     */
    public function recordFeedback(Request $request): JsonResponse
    {
        try {
            $data = $request->json();
            
            // Validate required fields
            $required = ['recommendation_id', 'action'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return new JsonResponse([
                        'success' => false,
                        'error' => ['message' => "Field '{$field}' is required", 'code' => 'VALIDATION_ERROR']
                    ], 400);
                }
            }

            // Validate action type
            $validActions = ['clicked', 'purchased', 'added_to_cart', 'viewed', 'ignored', 'dismissed'];
            if (!in_array($data['action'], $validActions)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => ['message' => 'Invalid action type', 'code' => 'INVALID_ACTION']
                ], 400);
            }

            // Get user ID (can be null for anonymous users)
            $userId = $request->user()?->id ?? $data['user_id'] ?? null;

            // Record feedback
            $this->engine->recordFeedback(
                $userId,
                $data['recommendation_id'],
                $data['action'],
                $data['context'] ?? []
            );

            // Track feedback
            $this->analyzer->trackFeedback($data['action'], [
                'recommendation_id' => $data['recommendation_id'],
                'user_id' => $userId,
                'context' => $data['context'] ?? []
            ]);

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'message' => 'Feedback recorded successfully',
                    'recommendation_id' => $data['recommendation_id'],
                    'action' => $data['action']
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'message' => 'Failed to record feedback',
                    'code' => 'FEEDBACK_ERROR'
                ]
            ], 500);
        }
    }

    /**
     * Get recommendation explanations
     * GET /api/v1/recommendations/{id}/explain
     */
    public function explainRecommendation(Request $request, int $recommendationId): JsonResponse
    {
        try {
            $explanation = $this->engine->explainRecommendation($recommendationId);

            if (!$explanation) {
                return new JsonResponse([
                    'success' => false,
                    'error' => ['message' => 'Recommendation not found', 'code' => 'NOT_FOUND']
                ], 404);
            }

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'recommendation_id' => $recommendationId,
                    'explanation' => $explanation
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'message' => 'Failed to get explanation',
                    'code' => 'EXPLANATION_ERROR'
                ]
            ], 500);
        }
    }

    /**
     * Get trending recommendations
     * GET /api/v1/recommendations/trending
     */
    public function getTrending(Request $request): JsonResponse
    {
        try {
            $options = [
                'category_id' => $request->query('category_id'),
                'limit' => $request->query('limit', 10),
                'window_days' => $request->query('window_days', 7)
            ];

            $trending = $this->engine->getTrendingProducts(array_filter($options));

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'trending_products' => $trending,
                    'total_count' => count($trending),
                    'window_days' => $options['window_days'],
                    'generated_at' => date('c')
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'message' => 'Failed to get trending products',
                    'code' => 'TRENDING_ERROR'
                ]
            ], 500);
        }
    }

    /**
     * Parse price range from query parameter
     */
    private function parsePriceRange(?string $priceRange): ?array
    {
        if (!$priceRange) {
            return null;
        }

        if (strpos($priceRange, '-') !== false) {
            [$min, $max] = explode('-', $priceRange, 2);
            return [
                'min' => (float) $min,
                'max' => (float) $max
            ];
        }

        return null;
    }

    /**
     * Load cart by ID
     */
    private function loadCart(string $cartId)
    {
        // Implementation would load cart from repository
        // return $this->cartRepository->find($cartId);
        return null; // Placeholder
    }
}