<?php

declare(strict_types=1);

namespace Shopologic\Plugins\Shared\Interfaces;

/**
 * Interface for plugins that provide analytics data
 * Allows other plugins to consume analytics information
 */
interface AnalyticsProviderInterface
{
    /**
     * Get analytics data for a specific metric
     */
    public function getMetricData(string $metricKey, array $filters = []): array;
    
    /**
     * Get available metrics from this provider
     */
    public function getAvailableMetrics(): array;
    
    /**
     * Subscribe to real-time metric updates
     */
    public function subscribeToMetric(string $metricKey, callable $callback): void;
    
    /**
     * Get historical data for trend analysis
     */
    public function getHistoricalData(string $metricKey, \DateTime $startDate, \DateTime $endDate): array;
    
    /**
     * Check if provider supports real-time updates
     */
    public function supportsRealtime(): bool;
}