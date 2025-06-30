<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Integrations;

use Shopologic\Plugins\Shared\Interfaces\AnalyticsProviderInterface;
use AdvancedAnalytics\Services\AnalyticsEngine;
use AdvancedAnalytics\Services\MetricsCalculator;
use AdvancedAnalytics\Repositories\MetricsRepository;

/**
 * Adapter to expose Advanced Analytics functionality to other plugins
 */
class AnalyticsProviderAdapter implements AnalyticsProviderInterface
{
    private AnalyticsEngine $analyticsEngine;
    private MetricsCalculator $metricsCalculator;
    private MetricsRepository $metricsRepository;
    private array $subscribers = [];
    
    public function __construct(
        AnalyticsEngine $analyticsEngine,
        MetricsCalculator $metricsCalculator,
        MetricsRepository $metricsRepository
    ) {
        $this->analyticsEngine = $analyticsEngine;
        $this->metricsCalculator = $metricsCalculator;
        $this->metricsRepository = $metricsRepository;
    }
    
    /**
     * Get analytics data for a specific metric
     */
    public function getMetricData(string $metricKey, array $filters = []): array
    {
        $metric = $this->metricsRepository->findByKey($metricKey);
        
        if (!$metric) {
            return [];
        }
        
        $startDate = $filters['start_date'] ?? now()->subDays(30);
        $endDate = $filters['end_date'] ?? now();
        $granularity = $filters['granularity'] ?? 'day';
        
        return [
            'metric' => $metric->formatForDisplay(),
            'current_value' => $metric->value,
            'previous_value' => $metric->previous_value,
            'change_percentage' => $metric->change_percentage,
            'trend' => $metric->trend,
            'historical_data' => $metric->getHistoricalTrend(30),
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'granularity' => $granularity
            ]
        ];
    }
    
    /**
     * Get available metrics from this provider
     */
    public function getAvailableMetrics(): array
    {
        return $this->metricsRepository->getActive()
            ->map(function($metric) {
                return [
                    'key' => $metric->key,
                    'name' => $metric->name,
                    'category' => $metric->category,
                    'description' => $metric->description,
                    'unit' => $metric->unit,
                    'supports_realtime' => true
                ];
            })
            ->toArray();
    }
    
    /**
     * Subscribe to real-time metric updates
     */
    public function subscribeToMetric(string $metricKey, callable $callback): void
    {
        if (!isset($this->subscribers[$metricKey])) {
            $this->subscribers[$metricKey] = [];
        }
        
        $this->subscribers[$metricKey][] = $callback;
    }
    
    /**
     * Get historical data for trend analysis
     */
    public function getHistoricalData(string $metricKey, \DateTime $startDate, \DateTime $endDate): array
    {
        $metric = $this->metricsRepository->findByKey($metricKey);
        
        if (!$metric) {
            return [];
        }
        
        return $metric->values()
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp')
            ->get()
            ->map(function($value) {
                return [
                    'timestamp' => $value->timestamp->format('Y-m-d H:i:s'),
                    'value' => $value->value,
                    'quality_score' => $value->quality_score,
                    'confidence_level' => $value->confidence_level
                ];
            })
            ->toArray();
    }
    
    /**
     * Check if provider supports real-time updates
     */
    public function supportsRealtime(): bool
    {
        return true;
    }
    
    /**
     * Notify subscribers of metric updates
     */
    public function notifyMetricUpdate(string $metricKey, array $data): void
    {
        if (!isset($this->subscribers[$metricKey])) {
            return;
        }
        
        foreach ($this->subscribers[$metricKey] as $callback) {
            try {
                call_user_func($callback, $data);
            } catch (\RuntimeException $e) {
                // Log error but continue with other subscribers
                error_log("Analytics provider callback error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get comprehensive dashboard data
     */
    public function getDashboardData(array $metricKeys = []): array
    {
        if (empty($metricKeys)) {
            $metricKeys = ['revenue', 'orders', 'customers', 'conversion_rate'];
        }
        
        $data = [];
        foreach ($metricKeys as $key) {
            $data[$key] = $this->getMetricData($key);
        }
        
        return $data;
    }
    
    /**
     * Get real-time metrics summary
     */
    public function getRealtimeMetrics(): array
    {
        return $this->metricsCalculator->getRealtimeMetrics();
    }
    
    /**
     * Track custom event for analytics
     */
    public function trackEvent(string $eventName, array $properties): void
    {
        $this->analyticsEngine->trackEvent($eventName, $properties);
    }
    
    /**
     * Get conversion funnel data
     */
    public function getFunnelData(array $steps, array $filters = []): array
    {
        return $this->analyticsEngine->calculateFunnelConversion($steps, $filters);
    }
    
    /**
     * Get cohort analysis data
     */
    public function getCohortData(string $cohortType = 'registration', array $filters = []): array
    {
        return $this->analyticsEngine->getCohortAnalysis($cohortType, $filters);
    }
}