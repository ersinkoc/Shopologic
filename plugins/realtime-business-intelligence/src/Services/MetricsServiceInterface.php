<?php

namespace RealtimeBusinessIntelligence\Services;

interface MetricsServiceInterface
{
    /**
     * Get a metric value
     */
    public function getMetric(string $metricName): float;

    /**
     * Update a metric value
     */
    public function updateMetric(string $metricName, float $value): void;

    /**
     * Increment a metric value
     */
    public function incrementMetric(string $metricName, float $increment): void;

    /**
     * Get metric history
     */
    public function getMetricHistory(string $metricName, string $period): array;

    /**
     * Get multiple metrics
     */
    public function getMetrics(array $metricNames): array;

    /**
     * Calculate derived metric
     */
    public function calculateDerivedMetric(string $formula, array $variables): float;

    /**
     * Get real-time metrics
     */
    public function getRealtimeMetrics(): array;

    /**
     * Store metric snapshot
     */
    public function storeSnapshot(string $period): void;

    /**
     * Get metric trends
     */
    public function getMetricTrends(array $metrics, string $period): array;

    /**
     * Aggregate metrics by period
     */
    public function aggregateMetrics(string $period, array $metrics): array;

    /**
     * Get metric percentiles
     */
    public function getMetricPercentiles(string $metricName, string $period): array;

    /**
     * Compare metrics between periods
     */
    public function compareMetrics(array $metrics, string $period1, string $period2): array;
}