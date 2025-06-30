<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Repositories;

use Shopologic\Core\Database\QueryBuilder;

class EventRepository\n{
    private string $table = 'analytics_events';

    /**
     * Create a new event
     */
    public function create(array $data): array
    {
        $id = QueryBuilder::table($this->table)->insert($data);
        return $this->findById($id);
    }

    /**
     * Find event by ID
     */
    public function findById(int $id): ?array
    {
        return QueryBuilder::table($this->table)
            ->where('id', $id)
            ->first();
    }

    /**
     * Get events by type
     */
    public function getEventsByType(string $eventType, string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->where('event_type', $eventType)
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->orderBy('event_timestamp', 'DESC')
            ->get();
    }

    /**
     * Get events by customer ID
     */
    public function getEventsByCustomer(int $customerId, int $limit = 100, int $offset = 0): array
    {
        return QueryBuilder::table($this->table)
            ->where('customer_id', $customerId)
            ->orderBy('event_timestamp', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * Get events by session ID
     */
    public function getEventsBySession(string $sessionId): array
    {
        return QueryBuilder::table($this->table)
            ->where('session_id', $sessionId)
            ->orderBy('event_timestamp', 'ASC')
            ->get();
    }

    /**
     * Get events in date range
     */
    public function getEventsInRange(string $startDate, string $endDate, array $eventTypes = []): array
    {
        $query = QueryBuilder::table($this->table)
            ->whereBetween('event_timestamp', [$startDate, $endDate]);

        if (!empty($eventTypes)) {
            $query->whereIn('event_type', $eventTypes);
        }

        return $query->orderBy('event_timestamp', 'DESC')->get();
    }

    /**
     * Get event counts by type
     */
    public function getEventCountsByType(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select(['event_type', 'COUNT(*) as count'])
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->groupBy('event_type')
            ->orderBy('count', 'DESC')
            ->get();
    }

    /**
     * Get top events by value
     */
    public function getTopEventsByValue(string $startDate, string $endDate, int $limit = 10): array
    {
        return QueryBuilder::table($this->table)
            ->select(['*'])
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->whereNotNull('event_value')
            ->orderBy('event_value', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get events with pagination
     */
    public function getPaginated(int $page = 1, int $perPage = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $query = QueryBuilder::table($this->table);

        // Apply filters
        if (isset($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['start_date'])) {
            $query->where('event_timestamp', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('event_timestamp', '<=', $filters['end_date']);
        }

        if (isset($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (isset($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }

        $total = $query->count();
        $events = $query->orderBy('event_timestamp', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $events,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get unique event types
     */
    public function getUniqueEventTypes(): array
    {
        return QueryBuilder::table($this->table)
            ->select(['DISTINCT event_type'])
            ->orderBy('event_type')
            ->pluck('event_type');
    }

    /**
     * Get events by UTM parameters
     */
    public function getEventsByUtmParameters(array $utmParams, string $startDate, string $endDate): array
    {
        $query = QueryBuilder::table($this->table)
            ->whereBetween('event_timestamp', [$startDate, $endDate]);

        foreach ($utmParams as $param => $value) {
            $query->where("utm_{$param}", $value);
        }

        return $query->orderBy('event_timestamp', 'DESC')->get();
    }

    /**
     * Get conversion funnel data
     */
    public function getConversionFunnelData(array $steps, string $startDate, string $endDate): array
    {
        $funnelData = [];

        foreach ($steps as $step) {
            $funnelData[$step] = QueryBuilder::table($this->table)
                ->where('event_type', $step)
                ->whereBetween('event_timestamp', [$startDate, $endDate])
                ->count();
        }

        return $funnelData;
    }

    /**
     * Get events aggregate data
     */
    public function getAggregateData(string $groupBy, string $startDate, string $endDate): array
    {
        $validGroupBy = ['event_type', 'country', 'device_type', 'browser', 'utm_source'];
        
        if (!in_array($groupBy, $validGroupBy)) {
            throw new \InvalidArgumentException("Invalid group by field: {$groupBy}");
        }

        return QueryBuilder::table($this->table)
            ->select([
                $groupBy,
                'COUNT(*) as event_count',
                'COUNT(DISTINCT customer_id) as unique_customers',
                'COUNT(DISTINCT session_id) as unique_sessions',
                'AVG(event_value) as avg_value',
                'SUM(event_value) as total_value'
            ])
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->groupBy($groupBy)
            ->orderBy('event_count', 'DESC')
            ->get();
    }

    /**
     * Get real-time events
     */
    public function getRealtimeEvents(int $minutesAgo = 5): array
    {
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$minutesAgo} minutes"));

        return QueryBuilder::table($this->table)
            ->where('event_timestamp', '>=', $cutoffTime)
            ->orderBy('event_timestamp', 'DESC')
            ->limit(100)
            ->get();
    }

    /**
     * Delete old events
     */
    public function deleteOldEvents(string $beforeDate): int
    {
        return QueryBuilder::table($this->table)
            ->where('event_timestamp', '<', $beforeDate)
            ->delete();
    }

    /**
     * Get event statistics
     */
    public function getEventStatistics(string $startDate, string $endDate): array
    {
        $result = QueryBuilder::table($this->table)
            ->select([
                'COUNT(*) as total_events',
                'COUNT(DISTINCT customer_id) as unique_customers',
                'COUNT(DISTINCT session_id) as unique_sessions',
                'COUNT(DISTINCT event_type) as unique_event_types',
                'AVG(event_value) as avg_event_value',
                'SUM(event_value) as total_event_value'
            ])
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->first();

        return $result ?: [];
    }

    /**
     * Get top referrers
     */
    public function getTopReferrers(string $startDate, string $endDate, int $limit = 10): array
    {
        return QueryBuilder::table($this->table)
            ->select(['referrer_url', 'COUNT(*) as visits'])
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->whereNotNull('referrer_url')
            ->where('referrer_url', '!=', '')
            ->groupBy('referrer_url')
            ->orderBy('visits', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get top pages
     */
    public function getTopPages(string $startDate, string $endDate, int $limit = 10): array
    {
        return QueryBuilder::table($this->table)
            ->select(['page_url', 'COUNT(*) as views'])
            ->where('event_type', 'page_viewed')
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->whereNotNull('page_url')
            ->groupBy('page_url')
            ->orderBy('views', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get geographic data
     */
    public function getGeographicData(string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'country',
                'region',
                'city',
                'COUNT(*) as events',
                'COUNT(DISTINCT session_id) as sessions'
            ])
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->whereNotNull('country')
            ->groupBy(['country', 'region', 'city'])
            ->orderBy('events', 'DESC')
            ->get();
    }

    /**
     * Get technology data
     */
    public function getTechnologyData(string $startDate, string $endDate): array
    {
        return [
            'browsers' => $this->getTechnologyBreakdown('browser', $startDate, $endDate),
            'operating_systems' => $this->getTechnologyBreakdown('operating_system', $startDate, $endDate),
            'devices' => $this->getTechnologyBreakdown('device_type', $startDate, $endDate)
        ];
    }

    /**
     * Get technology breakdown
     */
    private function getTechnologyBreakdown(string $field, string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                $field,
                'COUNT(*) as events',
                'COUNT(DISTINCT session_id) as sessions'
            ])
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->whereNotNull($field)
            ->groupBy($field)
            ->orderBy('events', 'DESC')
            ->get();
    }

    /**
     * Search events
     */
    public function search(string $query, array $filters = []): array
    {
        $searchQuery = QueryBuilder::table($this->table);

        // Search in event data
        $searchQuery->where(function($q) use ($query) {
            $q->where('event_data', 'LIKE', "%{$query}%")
              ->orWhere('page_url', 'LIKE', "%{$query}%")
              ->orWhere('referrer_url', 'LIKE', "%{$query}%");
        });

        // Apply additional filters
        if (isset($filters['event_type'])) {
            $searchQuery->where('event_type', $filters['event_type']);
        }

        if (isset($filters['start_date'])) {
            $searchQuery->where('event_timestamp', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $searchQuery->where('event_timestamp', '<=', $filters['end_date']);
        }

        return $searchQuery->orderBy('event_timestamp', 'DESC')
            ->limit(100)
            ->get();
    }
}