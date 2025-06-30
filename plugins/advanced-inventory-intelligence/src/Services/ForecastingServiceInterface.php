<?php

namespace AdvancedInventoryIntelligence\Services;

interface ForecastingServiceInterface
{
    /**
     * Generate demand forecast for a product
     */
    public function generateDemandForecast(int $productId, array $options = []): array;

    /**
     * Get demand forecast for a specific timeframe
     */
    public function getDemandForecast(int $productId, string $timeframe): array;

    /**
     * Update demand pattern based on new data
     */
    public function updateDemandPattern(int $productId, float $demandChange): void;

    /**
     * Generate ARIMA forecast
     */
    public function generateARIMAForecast(int $productId, string $timeframe): array;

    /**
     * Generate neural network forecast
     */
    public function generateNeuralNetworkForecast(int $productId, string $timeframe): array;

    /**
     * Generate seasonal forecast
     */
    public function generateSeasonalForecast(int $productId, string $timeframe): array;

    /**
     * Get forecast accuracy for a product
     */
    public function getForecastAccuracy(int $productId): array;

    /**
     * Get overall forecast accuracy
     */
    public function getOverallForecastAccuracy(): float;

    /**
     * Calculate forecast confidence
     */
    public function calculateForecastConfidence(int $productId, array $forecast): float;

    /**
     * Detect seasonal patterns
     */
    public function detectSeasonalPatterns(int $productId): array;

    /**
     * Update forecast model
     */
    public function updateForecastModel(int $productId, array $newData): void;

    /**
     * Get forecast performance metrics
     */
    public function getForecastPerformanceMetrics(): array;

    /**
     * Validate forecast accuracy
     */
    public function validateForecastAccuracy(int $productId, array $actualData): array;
}