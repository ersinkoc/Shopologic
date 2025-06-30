<?php

declare(strict_types=1);
namespace Shopologic\Plugins\AiRecommendationEngine\Controllers;

use Shopologic\Core\Controller\AbstractController;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AiRecommendationEngine\Services\RecommendationServiceInterface;
use AiRecommendationEngine\Services\MachineLearningServiceInterface;

class ApiController extends AbstractController
{
    private RecommendationServiceInterface $recommendationService;
    private MachineLearningServiceInterface $mlService;

    public function __construct(
        RecommendationServiceInterface $recommendationService,
        MachineLearningServiceInterface $mlService
    ) {
        $this->recommendationService = $recommendationService;
        $this->mlService = $mlService;
    }

    public function getPersonalizedRecommendations(Request $request, int $customerId): Response
    {
        $this->authorize('ai.recommendations.view');
        
        $limit = $request->query('limit', 10);
        $context = $request->query('context', 'general');
        
        $recommendations = $this->recommendationService->getPersonalizedRecommendations(
            $customerId, 
            $limit, 
            $context
        );
        
        return $this->success([
            'recommendations' => $recommendations,
            'algorithm' => 'hybrid_collaborative_content',
            'generated_at' => now()->toISOString()
        ]);
    }

    public function trackInteraction(Request $request): Response
    {
        $data = $request->validate([
            'customer_id' => 'required|integer',
            'product_id' => 'required|integer',
            'interaction_type' => 'required|string|in:view,click,add_to_cart,purchase,like,share',
            'metadata' => 'array'
        ]);
        
        $this->recommendationService->trackInteraction(
            $data['customer_id'],
            $data['product_id'],
            $data['interaction_type'],
            $data['metadata'] ?? []
        );
        
        return $this->success(['message' => 'Interaction tracked successfully']);
    }

    public function getSimilarProducts(Request $request, int $productId): Response
    {
        $this->authorize('ai.recommendations.view');
        
        $limit = $request->query('limit', 8);
        $customerId = $request->query('customer_id');
        
        $similarProducts = $this->recommendationService->getSimilarProducts(
            $productId, 
            $customerId, 
            $limit
        );
        
        return $this->success([
            'similar_products' => $similarProducts,
            'algorithm' => 'content_based_with_collaborative_boost',
            'base_product_id' => $productId
        ]);
    }

    public function triggerModelTraining(Request $request): Response
    {
        $this->authorize('ai.models.train');
        
        $force = $request->query('force', false);
        
        if (!$force && !$this->mlService->shouldRetrain()) {
            return $this->error('Model training not needed yet', 400);
        }
        
        $job = $this->mlService->scheduleTraining();
        
        return $this->success([
            'message' => 'Model training scheduled',
            'job_id' => $job->getId(),
            'estimated_completion' => now()->addMinutes(30)->toISOString()
        ]);
    }

    public function getAnalytics(Request $request): Response
    {
        $this->authorize('ai.analytics.view');
        
        $period = $request->query('period', '7d');
        $analytics = $this->recommendationService->getAnalytics($period);
        
        return $this->success($analytics);
    }

    public function getTrendingProducts(Request $request): Response
    {
        $limit = $request->query('limit', 10);
        $category = $request->query('category');
        
        $trending = $this->mlService->getTrendingProducts($limit, $category);
        
        return $this->success([
            'trending_products' => $trending,
            'period' => 'last_24h',
            'algorithm' => 'weighted_popularity_with_decay'
        ]);
    }

    public function submitRecommendationFeedback(Request $request): Response
    {
        $data = $request->validate([
            'recommendation_id' => 'required|string',
            'customer_id' => 'required|integer',
            'feedback_type' => 'required|string|in:helpful,not_helpful,irrelevant,purchased',
            'rating' => 'integer|min:1|max:5'
        ]);
        
        $this->recommendationService->recordFeedback($data);
        
        return $this->success(['message' => 'Feedback recorded successfully']);
    }
}