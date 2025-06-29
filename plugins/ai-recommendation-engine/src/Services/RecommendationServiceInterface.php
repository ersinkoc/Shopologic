<?php

namespace AiRecommendationEngine\Services;

interface RecommendationServiceInterface
{
    /**
     * Get personalized product recommendations for a customer
     */
    public function getPersonalizedRecommendations(int $customerId, int $limit = 10, string $context = 'general'): array;

    /**
     * Get products similar to a given product
     */
    public function getSimilarProducts(int $productId, ?int $customerId = null, int $limit = 8): array;

    /**
     * Get frequently bought together recommendations
     */
    public function getFrequentlyBoughtTogether(array $productIds, ?int $customerId = null, int $limit = 6): array;

    /**
     * Track customer interaction with products
     */
    public function trackInteraction(int $customerId, int $productId, string $type, array $metadata = []): void;

    /**
     * Process queued interactions for batch processing
     */
    public function processQueuedInteractions(): int;

    /**
     * Get recommendation analytics and performance metrics
     */
    public function getAnalytics(string $period = '7d'): array;

    /**
     * Record feedback on recommendations
     */
    public function recordFeedback(array $feedbackData): void;

    /**
     * Get recommendation explanation for transparency
     */
    public function getRecommendationExplanation(int $customerId, int $productId): array;
}