<?php

namespace SmartSearchDiscovery\Services;

interface SearchServiceInterface
{
    /**
     * Perform search with query
     */
    public function search(string $query, array $options = []): array;

    /**
     * Get user search profile
     */
    public function getUserSearchProfile(int $userId): array;

    /**
     * Personalize search ranking
     */
    public function personalizeRanking(array $results, array $profile): array;

    /**
     * Get personalized recommendations
     */
    public function getPersonalizedRecommendations(int $userId, string $query, int $limit): array;

    /**
     * Get base suggestions for query
     */
    public function getBaseSuggestions(string $query): array;

    /**
     * Get trending suggestions
     */
    public function getTrendingSuggestions(string $query): array;

    /**
     * Get personalized suggestions
     */
    public function getPersonalizedSuggestions(int $userId, string $query): array;

    /**
     * Track search behavior
     */
    public function trackSearchBehavior(array $behavior): void;

    /**
     * Update user search profile
     */
    public function updateUserSearchProfile(int $userId, array $behavior): void;

    /**
     * Log zero results query
     */
    public function logZeroResultsQuery(string $query, array $filters): void;

    /**
     * Check if query frequently returns zero results
     */
    public function isFrequentZeroResultsQuery(string $query): bool;

    /**
     * Get relevance training data
     */
    public function getRelevanceTrainingData(): array;

    /**
     * Train relevance model
     */
    public function trainRelevanceModel(array $data): array;

    /**
     * Get personalization training data
     */
    public function getPersonalizationTrainingData(): array;

    /**
     * Train personalization model
     */
    public function trainPersonalizationModel(array $data): array;

    /**
     * Analyze query patterns
     */
    public function analyzeQueryPatterns(array $options = []): array;

    /**
     * Analyze search journeys
     */
    public function analyzeSearchJourneys(): array;

    /**
     * Identify trending searches
     */
    public function identifyTrendingSearches(): array;

    /**
     * Detect search anomalies
     */
    public function detectSearchAnomalies(): array;

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(string $period): array;

    /**
     * Get underperforming queries
     */
    public function getUnderperformingQueries(array $criteria): array;

    /**
     * Optimize query relevance
     */
    public function optimizeQueryRelevance(array $query): void;

    /**
     * Get search volume
     */
    public function getSearchVolume(string $period): int;

    /**
     * Get popular searches
     */
    public function getPopularSearches(int $limit): array;

    /**
     * Get zero results rate
     */
    public function getZeroResultsRate(string $period): float;

    /**
     * Get average click position
     */
    public function getAverageClickPosition(string $period): float;

    /**
     * Get search conversion rate
     */
    public function getSearchConversionRate(string $period): float;

    /**
     * Get trending searches with limit
     */
    public function getTrendingSearches(int $limit): array;

    /**
     * Check if behavior matches refinement pattern
     */
    public function isRefinementPattern(array $behavior): bool;

    /**
     * Check if behavior matches exploration pattern
     */
    public function isExplorationPattern(array $behavior): bool;

    /**
     * Check if behavior matches abandonment pattern
     */
    public function isAbandonmentPattern(array $behavior): bool;
}