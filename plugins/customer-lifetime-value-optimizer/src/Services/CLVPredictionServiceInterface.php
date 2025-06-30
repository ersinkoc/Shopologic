<?php

namespace CustomerLifetimeValueOptimizer\Services;

interface CLVPredictionServiceInterface
{
    /**
     * Calculate customer lifetime value prediction
     */
    public function calculateCLVPrediction(int $customerId, array $options = []): array;

    /**
     * Recalculate customer CLV with new data
     */
    public function recalculateCustomerCLV(int $customerId, array $data): array;

    /**
     * Get customer CLV predictions
     */
    public function getCLVPredictions(array $criteria = []): array;

    /**
     * Update CLV prediction model
     */
    public function updatePredictionModel(string $modelType, array $parameters): bool;

    /**
     * Get average CLV across all customers
     */
    public function getAverageCLV(array $filters = []): float;

    /**
     * Calculate CLV growth rate
     */
    public function getCLVGrowthRate(string $period): float;

    /**
     * Get top value customers
     */
    public function getTopValueCustomers(int $limit, array $criteria = []): array;

    /**
     * Predict future customer value
     */
    public function predictFutureValue(int $customerId, string $timeframe): array;

    /**
     * Generate CLV insights
     */
    public function generateCLVInsights(array $customerIds = []): array;

    /**
     * Calculate cohort CLV
     */
    public function calculateCohortCLV(array $cohortParams): array;

    /**
     * Get CLV distribution
     */
    public function getCLVDistribution(array $segments = []): array;

    /**
     * Validate CLV prediction accuracy
     */
    public function validatePredictionAccuracy(string $modelType, array $testData): array;
}