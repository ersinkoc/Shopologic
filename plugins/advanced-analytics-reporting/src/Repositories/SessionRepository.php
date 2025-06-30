<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Repositories;

use Shopologic\Core\Database\QueryBuilder;

class SessionRepository\n{
    private string $table = 'analytics_sessions';

    /**
     * Create a new session
     */
    public function create(array $data): array
    {
        $id = QueryBuilder::table($this->table)->insert($data);
        return $this->findById($id);
    }

    /**
     * Find session by ID
     */
    public function findById(int $id): ?array
    {
        return QueryBuilder::table($this->table)
            ->where('id', $id)
            ->first();
    }

    /**
     * Find session by session ID
     */
    public function findBySessionId(string $sessionId): ?array
    {
        return QueryBuilder::table($this->table)
            ->where('session_id', $sessionId)
            ->first();
    }

    /**
     * Update session
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return QueryBuilder::table($this->table)
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Increment session counters
     */
    public function incrementCounters(string $sessionId, array $counters): bool
    {
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        
        foreach ($counters as $field => $increment) {
            $updateData[$field] = QueryBuilder::raw("{$field} + 1");
        }

        return QueryBuilder::table($this->table)
            ->where('session_id', $sessionId)
            ->update($updateData) > 0;
    }

    /**
     * Get sessions by customer ID
     */
    public function getSessionsByCustomer(int $customerId, int $limit = 50, int $offset = 0): array
    {
        return QueryBuilder::table($this->table)
            ->where('customer_id', $customerId)
            ->orderBy('session_start', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * Get sessions in date range
     */
    public function getSessionsInRange(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->whereBetween('session_start', [$startDate, $endDate])
            ->orderBy('session_start', 'DESC')
            ->get();
    }

    /**
     * Get active sessions
     */
    public function getActiveSessions(int $timeoutMinutes = 30): array
    {
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));
        
        return QueryBuilder::table($this->table)
            ->where(function($query) use ($cutoffTime) {
                $query->whereNull('session_end')
                      ->orWhere('updated_at', '>=', $cutoffTime);
            })
            ->orderBy('session_start', 'DESC')
            ->get();
    }

    /**
     * Get session statistics
     */
    public function getSessionStatistics(string $startDate, string $endDate): array
    {
        $result = QueryBuilder::table($this->table)
            ->select([
                'COUNT(*) as total_sessions',
                'COUNT(DISTINCT customer_id) as unique_customers',
                'AVG(duration_seconds) as avg_duration',
                'AVG(page_views) as avg_page_views',
                'AVG(events_count) as avg_events',
                'SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounce_sessions',
                'AVG(conversion_value) as avg_conversion_value',
                'SUM(conversion_value) as total_conversion_value'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->first();

        return $result ?: [];
    }

    /**
     * Get sessions by traffic source
     */
    public function getSessionsByTrafficSource(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'traffic_source',
                'COUNT(*) as sessions',
                'COUNT(DISTINCT customer_id) as unique_customers',
                'AVG(duration_seconds) as avg_duration',
                'SUM(conversion_value) as total_conversion_value'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->whereNotNull('traffic_source')
            ->groupBy('traffic_source')
            ->orderBy('sessions', 'DESC')
            ->get();
    }

    /**
     * Get sessions by device type
     */
    public function getSessionsByDevice(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'device_type',
                'COUNT(*) as sessions',
                'AVG(duration_seconds) as avg_duration',
                'AVG(page_views) as avg_page_views',
                'SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounce_sessions'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->whereNotNull('device_type')
            ->groupBy('device_type')
            ->orderBy('sessions', 'DESC')
            ->get();
    }

    /**
     * Get sessions by geographic location
     */
    public function getSessionsByLocation(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'country',
                'region',
                'city',
                'COUNT(*) as sessions',
                'COUNT(DISTINCT customer_id) as unique_customers',
                'AVG(duration_seconds) as avg_duration'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->whereNotNull('country')
            ->groupBy(['country', 'region', 'city'])
            ->orderBy('sessions', 'DESC')
            ->get();
    }

    /**
     * Get top landing pages
     */
    public function getTopLandingPages(string $startDate, string $endDate, int $limit = 10): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'landing_page',
                'COUNT(*) as sessions',
                'AVG(duration_seconds) as avg_duration',
                'SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounce_sessions'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->whereNotNull('landing_page')
            ->groupBy('landing_page')
            ->orderBy('sessions', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get top exit pages
     */
    public function getTopExitPages(string $startDate, string $endDate, int $limit = 10): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'exit_page',
                'COUNT(*) as exits',
                'AVG(duration_seconds) as avg_duration_before_exit'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->whereNotNull('exit_page')
            ->groupBy('exit_page')
            ->orderBy('exits', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get session duration distribution
     */
    public function getSessionDurationDistribution(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'CASE 
                    WHEN duration_seconds < 30 THEN "0-30s"
                    WHEN duration_seconds < 60 THEN "30-60s" 
                    WHEN duration_seconds < 180 THEN "1-3m"
                    WHEN duration_seconds < 600 THEN "3-10m"
                    WHEN duration_seconds < 1800 THEN "10-30m"
                    ELSE "30m+"
                 END as duration_range',
                'COUNT(*) as sessions'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->whereNotNull('duration_seconds')
            ->groupBy('duration_range')
            ->orderBy('sessions', 'DESC')
            ->get();
    }

    /**
     * Get bounce rate by source
     */
    public function getBounceRateBySource(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'traffic_source',
                'COUNT(*) as total_sessions',
                'SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounce_sessions',
                '(SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100 as bounce_rate'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->whereNotNull('traffic_source')
            ->groupBy('traffic_source')
            ->orderBy('bounce_rate', 'ASC')
            ->get();
    }

    /**
     * Get sessions with pagination
     */
    public function getPaginated(int $page = 1, int $perPage = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $query = QueryBuilder::table($this->table);

        // Apply filters
        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['start_date'])) {
            $query->where('session_start', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('session_start', '<=', $filters['end_date']);
        }

        if (isset($filters['traffic_source'])) {
            $query->where('traffic_source', $filters['traffic_source']);
        }

        if (isset($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }

        if (isset($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (isset($filters['is_bounce'])) {
            $query->where('is_bounce', $filters['is_bounce']);
        }

        $total = $query->count();
        $sessions = $query->orderBy('session_start', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $sessions,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get conversion rate by source
     */
    public function getConversionRateBySource(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'traffic_source',
                'COUNT(*) as total_sessions',
                'SUM(CASE WHEN conversion_value > 0 THEN 1 ELSE 0 END) as converted_sessions',
                '(SUM(CASE WHEN conversion_value > 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100 as conversion_rate',
                'AVG(conversion_value) as avg_conversion_value'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->whereNotNull('traffic_source')
            ->groupBy('traffic_source')
            ->orderBy('conversion_rate', 'DESC')
            ->get();
    }

    /**
     * Get sessions by hour of day
     */
    public function getSessionsByHourOfDay(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'HOUR(session_start) as hour',
                'COUNT(*) as sessions',
                'AVG(duration_seconds) as avg_duration'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
    }

    /**
     * Get sessions by day of week
     */
    public function getSessionsByDayOfWeek(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'DAYNAME(session_start) as day_name',
                'DAYOFWEEK(session_start) as day_number',
                'COUNT(*) as sessions',
                'AVG(duration_seconds) as avg_duration'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->groupBy(['day_name', 'day_number'])
            ->orderBy('day_number')
            ->get();
    }

    /**
     * End expired sessions
     */
    public function endExpiredSessions(int $timeoutMinutes = 30): int
    {
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));
        
        return QueryBuilder::table($this->table)
            ->whereNull('session_end')
            ->where('updated_at', '<', $cutoffTime)
            ->update([
                'session_end' => QueryBuilder::raw('updated_at'),
                'duration_seconds' => QueryBuilder::raw('TIMESTAMPDIFF(SECOND, session_start, updated_at)'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Delete old sessions
     */
    public function deleteOldSessions(string $beforeDate): int
    {
        return QueryBuilder::table($this->table)
            ->where('session_start', '<', $beforeDate)
            ->delete();
    }

    /**
     * Get customer session history
     */
    public function getCustomerSessionHistory(int $customerId): array
    {
        return QueryBuilder::table($this->table)
            ->where('customer_id', $customerId)
            ->orderBy('session_start', 'DESC')
            ->get();
    }

    /**
     * Get returning vs new sessions
     */
    public function getReturningVsNewSessions(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'CASE WHEN customer_id IS NOT NULL THEN "returning" ELSE "new" END as session_type',
                'COUNT(*) as sessions',
                'AVG(duration_seconds) as avg_duration',
                'AVG(page_views) as avg_page_views'
            ])
            ->whereBetween('session_start', [$startDate, $endDate])
            ->groupBy('session_type')
            ->get();
    }

    /**
     * Search sessions
     */
    public function search(string $query, array $filters = []): array
    {
        $searchQuery = QueryBuilder::table($this->table);

        // Search in session data
        $searchQuery->where(function($q) use ($query) {
            $q->where('landing_page', 'LIKE', "%{$query}%")
              ->orWhere('exit_page', 'LIKE', "%{$query}%")
              ->orWhere('campaign', 'LIKE', "%{$query}%")
              ->orWhere('session_id', 'LIKE', "%{$query}%");
        });

        // Apply additional filters
        if (isset($filters['traffic_source'])) {
            $searchQuery->where('traffic_source', $filters['traffic_source']);
        }

        if (isset($filters['start_date'])) {
            $searchQuery->where('session_start', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $searchQuery->where('session_start', '<=', $filters['end_date']);
        }

        return $searchQuery->orderBy('session_start', 'DESC')
            ->limit(100)
            ->get();
    }
}