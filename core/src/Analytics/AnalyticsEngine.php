<?php

declare(strict_types=1);

namespace Shopologic\Core\Analytics;

use Shopologic\Core\Database\DB;
use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Events\EventDispatcherInterface;

/**
 * Core analytics engine for data collection and processing
 */
class AnalyticsEngine
{
    private DB $db;
    private CacheInterface $cache;
    private EventDispatcherInterface $eventDispatcher;
    private array $config;
    private array $collectors = [];

    public function __construct(
        DB $db,
        CacheInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        array $config = []
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->config = array_merge([
            'retention_days' => 365,
            'aggregation_intervals' => ['hourly', 'daily', 'weekly', 'monthly'],
            'real_time_window' => 300, // 5 minutes
            'batch_size' => 1000
        ], $config);
    }

    /**
     * Register data collector
     */
    public function registerCollector(string $name, DataCollectorInterface $collector): void
    {
        $this->collectors[$name] = $collector;
    }

    /**
     * Track event
     */
    public function track(string $event, array $properties = [], ?string $userId = null): void
    {
        $timestamp = new \DateTime();
        
        // Create event record
        $eventData = [
            'event' => $event,
            'properties' => json_encode($properties),
            'user_id' => $userId,
            'session_id' => $this->getSessionId(),
            'timestamp' => $timestamp->format('Y-m-d H:i:s'),
            'date' => $timestamp->format('Y-m-d'),
            'hour' => (int)$timestamp->format('H')
        ];
        
        // Store raw event
        $this->db->table('analytics_events')->insert($eventData);
        
        // Update real-time metrics
        $this->updateRealTimeMetrics($event, $properties);
        
        // Trigger event for collectors
        $this->eventDispatcher->dispatch('analytics.event_tracked', [
            'event' => $event,
            'properties' => $properties,
            'user_id' => $userId,
            'timestamp' => $timestamp
        ]);
    }

    /**
     * Get real-time metrics
     */
    public function getRealTimeMetrics(): array
    {
        $cacheKey = 'analytics_realtime_' . date('Y-m-d-H-i', intval(time() / 60) * 60);
        
        return $this->cache->remember($cacheKey, 60, function () {
            $window = new \DateTime("-{$this->config['real_time_window']} seconds");
            
            // Active users
            $activeUsers = $this->db->table('analytics_events')
                ->where('timestamp', '>=', $window->format('Y-m-d H:i:s'))
                ->distinct('session_id')
                ->count('session_id');
            
            // Page views
            $pageViews = $this->db->table('analytics_events')
                ->where('event', 'page_view')
                ->where('timestamp', '>=', $window->format('Y-m-d H:i:s'))
                ->count();
            
            // Top pages
            $topPages = $this->db->table('analytics_events')
                ->select('properties->>"$.page" as page', 'COUNT(*) as views')
                ->where('event', 'page_view')
                ->where('timestamp', '>=', $window->format('Y-m-d H:i:s'))
                ->groupBy('page')
                ->orderBy('views', 'desc')
                ->limit(10)
                ->get();
            
            // Active events
            $activeEvents = $this->db->table('analytics_events')
                ->select('event', 'COUNT(*) as count')
                ->where('timestamp', '>=', $window->format('Y-m-d H:i:s'))
                ->groupBy('event')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();
            
            return [
                'active_users' => $activeUsers,
                'page_views' => $pageViews,
                'top_pages' => $topPages,
                'active_events' => $activeEvents,
                'timestamp' => time()
            ];
        });
    }

    /**
     * Get metrics for date range
     */
    public function getMetrics(
        \DateTime $startDate,
        \DateTime $endDate,
        array $metrics = [],
        array $dimensions = [],
        array $filters = []
    ): array {
        $query = $this->buildMetricsQuery($startDate, $endDate, $metrics, $dimensions, $filters);
        
        // Check cache
        $cacheKey = 'analytics_metrics_' . md5(serialize([
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $metrics,
            $dimensions,
            $filters
        ]));
        
        return $this->cache->remember($cacheKey, 3600, function () use ($query) {
            return $query->get()->toArray();
        });
    }

    /**
     * Get funnel analysis
     */
    public function getFunnelAnalysis(array $steps, \DateTime $startDate, \DateTime $endDate): array
    {
        $results = [];
        $previousStepUsers = null;
        
        foreach ($steps as $index => $step) {
            $query = $this->db->table('analytics_events')
                ->select('user_id')
                ->where('event', $step['event'])
                ->whereBetween('timestamp', [
                    $startDate->format('Y-m-d H:i:s'),
                    $endDate->format('Y-m-d H:i:s')
                ])
                ->distinct();
            
            // Apply step filters
            if (isset($step['filters'])) {
                foreach ($step['filters'] as $key => $value) {
                    $query->whereRaw("properties->>'$.{$key}' = ?", [$value]);
                }
            }
            
            // If not first step, only include users from previous step
            if ($previousStepUsers !== null) {
                $query->whereIn('user_id', $previousStepUsers);
            }
            
            $users = $query->pluck('user_id')->toArray();
            $userCount = count($users);
            
            $stepResult = [
                'step' => $step['name'],
                'event' => $step['event'],
                'users' => $userCount
            ];
            
            if ($index > 0) {
                $previousCount = $results[$index - 1]['users'];
                $stepResult['conversion_rate'] = $previousCount > 0 
                    ? ($userCount / $previousCount) * 100 
                    : 0;
                $stepResult['drop_off'] = $previousCount - $userCount;
                $stepResult['drop_off_rate'] = $previousCount > 0 
                    ? (($previousCount - $userCount) / $previousCount) * 100 
                    : 0;
            }
            
            $results[] = $stepResult;
            $previousStepUsers = $users;
        }
        
        // Calculate overall funnel conversion
        if (count($results) > 0) {
            $firstStep = $results[0]['users'];
            $lastStep = $results[count($results) - 1]['users'];
            
            $overallConversion = $firstStep > 0 
                ? ($lastStep / $firstStep) * 100 
                : 0;
        } else {
            $overallConversion = 0;
        }
        
        return [
            'steps' => $results,
            'overall_conversion' => $overallConversion,
            'total_drop_off' => isset($firstStep) && isset($lastStep) 
                ? $firstStep - $lastStep 
                : 0
        ];
    }

    /**
     * Get cohort analysis
     */
    public function getCohortAnalysis(
        string $cohortType,
        string $metricType,
        \DateTime $startDate,
        \DateTime $endDate,
        int $periods = 12
    ): array {
        $cohorts = [];
        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            $cohortKey = $this->getCohortKey($cohortType, $currentDate);
            
            // Get users in cohort
            $cohortUsers = $this->getUsersInCohort($cohortType, $currentDate);
            
            if (count($cohortUsers) > 0) {
                $cohortData = [
                    'cohort' => $cohortKey,
                    'users' => count($cohortUsers),
                    'periods' => []
                ];
                
                // Calculate metric for each period
                for ($period = 0; $period < $periods; $period++) {
                    $periodStart = clone $currentDate;
                    $periodEnd = clone $currentDate;
                    
                    switch ($cohortType) {
                        case 'daily':
                            $periodStart->modify("+{$period} days");
                            $periodEnd->modify("+" . ($period + 1) . " days");
                            break;
                        case 'weekly':
                            $periodStart->modify("+{$period} weeks");
                            $periodEnd->modify("+" . ($period + 1) . " weeks");
                            break;
                        case 'monthly':
                            $periodStart->modify("+{$period} months");
                            $periodEnd->modify("+" . ($period + 1) . " months");
                            break;
                    }
                    
                    $metric = $this->calculateCohortMetric(
                        $metricType,
                        $cohortUsers,
                        $periodStart,
                        $periodEnd
                    );
                    
                    $cohortData['periods'][] = [
                        'period' => $period,
                        'value' => $metric,
                        'percentage' => count($cohortUsers) > 0 
                            ? ($metric / count($cohortUsers)) * 100 
                            : 0
                    ];
                }
                
                $cohorts[] = $cohortData;
            }
            
            // Move to next cohort
            switch ($cohortType) {
                case 'daily':
                    $currentDate->modify('+1 day');
                    break;
                case 'weekly':
                    $currentDate->modify('+1 week');
                    break;
                case 'monthly':
                    $currentDate->modify('+1 month');
                    break;
            }
        }
        
        return $cohorts;
    }

    /**
     * Get user segments
     */
    public function getUserSegments(): array
    {
        $segments = [];
        
        // New vs Returning
        $newUsers = $this->db->table('analytics_users')
            ->whereRaw('DATE(first_seen) = DATE(last_seen)')
            ->count();
        
        $returningUsers = $this->db->table('analytics_users')
            ->whereRaw('DATE(first_seen) < DATE(last_seen)')
            ->count();
        
        $segments['user_type'] = [
            'new' => $newUsers,
            'returning' => $returningUsers
        ];
        
        // By device
        $segments['device'] = $this->db->table('analytics_sessions')
            ->select('device_type', 'COUNT(DISTINCT user_id) as users')
            ->groupBy('device_type')
            ->pluck('users', 'device_type')
            ->toArray();
        
        // By location
        $segments['location'] = $this->db->table('analytics_sessions')
            ->select('country', 'COUNT(DISTINCT user_id) as users')
            ->groupBy('country')
            ->orderBy('users', 'desc')
            ->limit(10)
            ->pluck('users', 'country')
            ->toArray();
        
        // By acquisition channel
        $segments['acquisition'] = $this->db->table('analytics_users')
            ->select('acquisition_channel', 'COUNT(*) as users')
            ->groupBy('acquisition_channel')
            ->pluck('users', 'acquisition_channel')
            ->toArray();
        
        return $segments;
    }

    /**
     * Generate custom report
     */
    public function generateReport(array $config): Report
    {
        $report = new Report();
        $report->fill([
            'name' => $config['name'],
            'type' => $config['type'],
            'config' => $config,
            'status' => 'processing'
        ]);
        $report->save();
        
        // Queue report generation
        $this->eventDispatcher->dispatch('analytics.report_requested', $report);
        
        return $report;
    }

    /**
     * Process aggregations
     */
    public function processAggregations(string $interval): void
    {
        $processor = new AggregationProcessor($this->db);
        
        switch ($interval) {
            case 'hourly':
                $processor->processHourlyAggregations();
                break;
            case 'daily':
                $processor->processDailyAggregations();
                break;
            case 'weekly':
                $processor->processWeeklyAggregations();
                break;
            case 'monthly':
                $processor->processMonthlyAggregations();
                break;
        }
        
        // Clear related caches
        $this->cache->deleteByPrefix('analytics_metrics_');
    }

    /**
     * Clean up old data
     */
    public function cleanup(): void
    {
        $cutoffDate = new \DateTime("-{$this->config['retention_days']} days");
        
        // Delete old events
        $deleted = $this->db->table('analytics_events')
            ->where('timestamp', '<', $cutoffDate->format('Y-m-d H:i:s'))
            ->delete();
        
        // Delete old sessions
        $this->db->table('analytics_sessions')
            ->where('ended_at', '<', $cutoffDate->format('Y-m-d H:i:s'))
            ->delete();
        
        $this->eventDispatcher->dispatch('analytics.cleanup_completed', [
            'deleted_events' => $deleted,
            'cutoff_date' => $cutoffDate
        ]);
    }

    // Private methods

    private function getSessionId(): string
    {
        return session_id() ?: $this->generateSessionId();
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function updateRealTimeMetrics(string $event, array $properties): void
    {
        $key = 'analytics_realtime_' . $event;
        $ttl = $this->config['real_time_window'];
        
        // Increment counter
        $current = $this->cache->get($key, 0);
        $this->cache->set($key, $current + 1, $ttl);
    }

    private function buildMetricsQuery(
        \DateTime $startDate,
        \DateTime $endDate,
        array $metrics,
        array $dimensions,
        array $filters
    ) {
        $query = $this->db->table('analytics_aggregations')
            ->whereBetween('date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ]);
        
        // Select metrics
        $selects = [];
        foreach ($dimensions as $dimension) {
            $selects[] = $dimension;
        }
        
        foreach ($metrics as $metric) {
            switch ($metric) {
                case 'users':
                    $selects[] = 'SUM(unique_users) as users';
                    break;
                case 'sessions':
                    $selects[] = 'SUM(sessions) as sessions';
                    break;
                case 'pageviews':
                    $selects[] = 'SUM(pageviews) as pageviews';
                    break;
                case 'bounce_rate':
                    $selects[] = 'AVG(bounce_rate) as bounce_rate';
                    break;
                case 'avg_session_duration':
                    $selects[] = 'AVG(avg_session_duration) as avg_session_duration';
                    break;
                default:
                    $selects[] = "SUM(metrics->>'$.{$metric}') as {$metric}";
            }
        }
        
        $query->selectRaw(implode(', ', $selects));
        
        // Group by dimensions
        if (!empty($dimensions)) {
            $query->groupBy(...$dimensions);
        }
        
        // Apply filters
        foreach ($filters as $filter) {
            $query->where($filter['field'], $filter['operator'], $filter['value']);
        }
        
        return $query;
    }

    private function getCohortKey(string $cohortType, \DateTime $date): string
    {
        switch ($cohortType) {
            case 'daily':
                return $date->format('Y-m-d');
            case 'weekly':
                return $date->format('Y-W');
            case 'monthly':
                return $date->format('Y-m');
            default:
                return $date->format('Y-m-d');
        }
    }

    private function getUsersInCohort(string $cohortType, \DateTime $date): array
    {
        $field = 'first_seen';
        
        switch ($cohortType) {
            case 'daily':
                $start = $date->format('Y-m-d 00:00:00');
                $end = $date->format('Y-m-d 23:59:59');
                break;
            case 'weekly':
                $start = clone $date;
                $start->modify('monday this week');
                $end = clone $start;
                $end->modify('+6 days');
                $start = $start->format('Y-m-d 00:00:00');
                $end = $end->format('Y-m-d 23:59:59');
                break;
            case 'monthly':
                $start = $date->format('Y-m-01 00:00:00');
                $end = $date->format('Y-m-t 23:59:59');
                break;
        }
        
        return $this->db->table('analytics_users')
            ->whereBetween($field, [$start, $end])
            ->pluck('id')
            ->toArray();
    }

    private function calculateCohortMetric(
        string $metricType,
        array $cohortUsers,
        \DateTime $periodStart,
        \DateTime $periodEnd
    ): float {
        switch ($metricType) {
            case 'retention':
                return $this->db->table('analytics_events')
                    ->whereIn('user_id', $cohortUsers)
                    ->whereBetween('timestamp', [
                        $periodStart->format('Y-m-d H:i:s'),
                        $periodEnd->format('Y-m-d H:i:s')
                    ])
                    ->distinct('user_id')
                    ->count('user_id');
                
            case 'revenue':
                return $this->db->table('analytics_events')
                    ->whereIn('user_id', $cohortUsers)
                    ->where('event', 'purchase')
                    ->whereBetween('timestamp', [
                        $periodStart->format('Y-m-d H:i:s'),
                        $periodEnd->format('Y-m-d H:i:s')
                    ])
                    ->sum("properties->>'$.value'");
                
            case 'engagement':
                return $this->db->table('analytics_events')
                    ->whereIn('user_id', $cohortUsers)
                    ->whereBetween('timestamp', [
                        $periodStart->format('Y-m-d H:i:s'),
                        $periodEnd->format('Y-m-d H:i:s')
                    ])
                    ->count() / count($cohortUsers);
                
            default:
                return 0;
        }
    }
}

/**
 * Data collector interface
 */
interface DataCollectorInterface
{
    public function collect(array $event): void;
    public function getName(): string;
    public function getMetrics(): array;
}