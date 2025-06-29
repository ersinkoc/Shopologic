<?php

namespace PredictiveAnalyticsEngine\Services;

interface PredictionServiceInterface
{
    /**
     * Get sales forecast for specified period
     */
    public function getSalesForecast(array $options = []): array;

    /**
     * Get product-specific sales forecast
     */
    public function getProductSalesForecast(int $productId, string $period): array;

    /**
     * Get overall sales forecast
     */
    public function getOverallSalesForecast(string $period): array;

    /**
     * Predict customer lifetime value
     */
    public function predictCustomerLifetimeValue(int $customerId, array $options = []): float;

    /**
     * Calculate purchase probability for a customer
     */
    public function calculatePurchaseProbability(int $customerId): float;

    /**
     * Update sales data for predictions
     */
    public function updateSalesData(array $features): void;

    /**
     * Update customer behavior model
     */
    public function updateCustomerBehaviorModel(int $customerId, array $behavior): void;

    /**
     * Get customer insights
     */
    public function getCustomerInsights(): array;

    /**
     * Generate weekly insights report
     */
    public function generateWeeklyInsights(): array;

    /**
     * Train prediction models
     */
    public function trainModels(array $trainingData): array;

    /**
     * Evaluate model performance
     */
    public function evaluateModelPerformance(): array;

    /**
     * Deploy new models
     */
    public function deployNewModels(): void;

    /**
     * Detect anomalies in data
     */
    public function detectAnomalies(array $options = []): array;

    /**
     * Get accuracy metrics for predictions
     */
    public function getAccuracyMetrics(): array;

    /**
     * Get expected behavior for customer
     */
    public function getExpectedBehavior(int $customerId): array;

    /**
     * Update trend prediction
     */
    public function updateTrendPrediction(int $productId, array $trend): void;

    /**
     * Get anomaly count for period
     */
    public function getAnomalyCount(string $period): int;

    /**
     * Get overall prediction confidence
     */
    public function getOverallConfidence(): float;
}