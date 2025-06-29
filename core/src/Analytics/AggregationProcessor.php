<?php

declare(strict_types=1);

namespace Shopologic\Core\Analytics;

use Shopologic\Core\Database\DB;

/**
 * Processes analytics data aggregations
 */
class AggregationProcessor
{
    private DB $db;

    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    /**
     * Process hourly aggregations
     */
    public function processHourlyAggregations(): void
    {
        $lastProcessed = $this->getLastProcessedTime('hourly');
        $currentHour = new \DateTime();
        $currentHour->setTime((int)$currentHour->format('H'), 0, 0);
        
        while ($lastProcessed < $currentHour) {
            $hour = clone $lastProcessed;
            $this->aggregateHour($hour);
            $lastProcessed->modify('+1 hour');
        }
        
        $this->updateLastProcessedTime('hourly', $lastProcessed);
    }

    /**
     * Process daily aggregations
     */
    public function processDailyAggregations(): void
    {
        $lastProcessed = $this->getLastProcessedDate('daily');
        $yesterday = new \DateTime('yesterday');
        
        while ($lastProcessed < $yesterday) {
            $date = clone $lastProcessed;
            $this->aggregateDay($date);
            $lastProcessed->modify('+1 day');
        }
        
        $this->updateLastProcessedTime('daily', $lastProcessed);
    }

    /**
     * Process weekly aggregations
     */
    public function processWeeklyAggregations(): void
    {
        $lastProcessed = $this->getLastProcessedDate('weekly');
        $lastWeek = new \DateTime('last week');
        
        while ($lastProcessed < $lastWeek) {
            $week = clone $lastProcessed;
            $this->aggregateWeek($week);
            $lastProcessed->modify('+1 week');
        }
        
        $this->updateLastProcessedTime('weekly', $lastProcessed);
    }

    /**
     * Process monthly aggregations
     */
    public function processMonthlyAggregations(): void
    {
        $lastProcessed = $this->getLastProcessedDate('monthly');
        $lastMonth = new \DateTime('first day of last month');
        
        while ($lastProcessed < $lastMonth) {
            $month = clone $lastProcessed;
            $this->aggregateMonth($month);
            $lastProcessed->modify('+1 month');
        }
        
        $this->updateLastProcessedTime('monthly', $lastProcessed);
    }

    // Private aggregation methods

    private function aggregateHour(\DateTime $hour): void
    {
        $startTime = $hour->format('Y-m-d H:00:00');
        $endTime = $hour->format('Y-m-d H:59:59');
        
        // Basic metrics
        $metrics = $this->calculateBasicMetrics($startTime, $endTime);
        
        // Event metrics
        $eventMetrics = $this->calculateEventMetrics($startTime, $endTime);
        
        // User metrics
        $userMetrics = $this->calculateUserMetrics($startTime, $endTime);
        
        // Store aggregation
        $this->db->table('analytics_aggregations')->insert([
            'type' => 'hourly',
            'date' => $hour->format('Y-m-d'),
            'hour' => (int)$hour->format('H'),
            'unique_users' => $metrics['unique_users'],
            'sessions' => $metrics['sessions'],
            'pageviews' => $metrics['pageviews'],
            'events' => $metrics['events'],
            'bounce_rate' => $metrics['bounce_rate'],
            'avg_session_duration' => $metrics['avg_session_duration'],
            'metrics' => json_encode(array_merge($eventMetrics, $userMetrics)),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function aggregateDay(\DateTime $day): void
    {
        $date = $day->format('Y-m-d');
        
        // Aggregate from hourly data
        $hourlyData = $this->db->table('analytics_aggregations')
            ->where('type', 'hourly')
            ->where('date', $date)
            ->get();
        
        if ($hourlyData->isEmpty()) {
            // Fallback to raw data if hourly aggregations missing
            $this->aggregateDayFromRaw($day);
            return;
        }
        
        $metrics = [
            'unique_users' => 0,
            'sessions' => 0,
            'pageviews' => 0,
            'events' => 0,
            'bounce_rate' => 0,
            'avg_session_duration' => 0
        ];
        
        $bounceRates = [];
        $sessionDurations = [];
        
        foreach ($hourlyData as $hour) {
            $metrics['sessions'] += $hour->sessions;
            $metrics['pageviews'] += $hour->pageviews;
            $metrics['events'] += $hour->events;
            
            if ($hour->bounce_rate > 0) {
                $bounceRates[] = $hour->bounce_rate;
            }
            
            if ($hour->avg_session_duration > 0) {
                $sessionDurations[] = $hour->avg_session_duration;
            }
        }
        
        // Calculate unique users for the day
        $metrics['unique_users'] = $this->db->table('analytics_events')
            ->whereDate('timestamp', $date)
            ->distinct('user_id')
            ->count('user_id');
        
        // Calculate averages
        $metrics['bounce_rate'] = !empty($bounceRates) 
            ? array_sum($bounceRates) / count($bounceRates) 
            : 0;
            
        $metrics['avg_session_duration'] = !empty($sessionDurations) 
            ? array_sum($sessionDurations) / count($sessionDurations) 
            : 0;
        
        // Additional daily metrics
        $additionalMetrics = $this->calculateDailyMetrics($date);
        
        // Store daily aggregation
        $this->db->table('analytics_aggregations')->insert([
            'type' => 'daily',
            'date' => $date,
            'unique_users' => $metrics['unique_users'],
            'sessions' => $metrics['sessions'],
            'pageviews' => $metrics['pageviews'],
            'events' => $metrics['events'],
            'bounce_rate' => $metrics['bounce_rate'],
            'avg_session_duration' => $metrics['avg_session_duration'],
            'metrics' => json_encode($additionalMetrics),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function aggregateWeek(\DateTime $week): void
    {
        $startOfWeek = clone $week;
        $startOfWeek->modify('monday this week');
        $endOfWeek = clone $startOfWeek;
        $endOfWeek->modify('+6 days');
        
        // Aggregate from daily data
        $dailyData = $this->db->table('analytics_aggregations')
            ->where('type', 'daily')
            ->whereBetween('date', [
                $startOfWeek->format('Y-m-d'),
                $endOfWeek->format('Y-m-d')
            ])
            ->get();
        
        $metrics = $this->aggregateFromDaily($dailyData);
        
        // Store weekly aggregation
        $this->db->table('analytics_aggregations')->insert([
            'type' => 'weekly',
            'date' => $startOfWeek->format('Y-m-d'),
            'week' => $startOfWeek->format('W'),
            'year' => $startOfWeek->format('Y'),
            'unique_users' => $metrics['unique_users'],
            'sessions' => $metrics['sessions'],
            'pageviews' => $metrics['pageviews'],
            'events' => $metrics['events'],
            'bounce_rate' => $metrics['bounce_rate'],
            'avg_session_duration' => $metrics['avg_session_duration'],
            'metrics' => json_encode($metrics['additional']),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function aggregateMonth(\DateTime $month): void
    {
        $startOfMonth = clone $month;
        $startOfMonth->modify('first day of this month');
        $endOfMonth = clone $startOfMonth;
        $endOfMonth->modify('last day of this month');
        
        // Aggregate from daily data
        $dailyData = $this->db->table('analytics_aggregations')
            ->where('type', 'daily')
            ->whereBetween('date', [
                $startOfMonth->format('Y-m-d'),
                $endOfMonth->format('Y-m-d')
            ])
            ->get();
        
        $metrics = $this->aggregateFromDaily($dailyData);
        
        // Store monthly aggregation
        $this->db->table('analytics_aggregations')->insert([
            'type' => 'monthly',
            'date' => $startOfMonth->format('Y-m-d'),
            'month' => $startOfMonth->format('m'),
            'year' => $startOfMonth->format('Y'),
            'unique_users' => $metrics['unique_users'],
            'sessions' => $metrics['sessions'],
            'pageviews' => $metrics['pageviews'],
            'events' => $metrics['events'],
            'bounce_rate' => $metrics['bounce_rate'],
            'avg_session_duration' => $metrics['avg_session_duration'],
            'metrics' => json_encode($metrics['additional']),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // Calculation methods

    private function calculateBasicMetrics(string $startTime, string $endTime): array
    {
        // Unique users
        $uniqueUsers = $this->db->table('analytics_events')
            ->whereBetween('timestamp', [$startTime, $endTime])
            ->distinct('user_id')
            ->count('user_id');
        
        // Sessions
        $sessions = $this->db->table('analytics_sessions')
            ->whereBetween('started_at', [$startTime, $endTime])
            ->count();
        
        // Page views
        $pageviews = $this->db->table('analytics_events')
            ->where('event', 'page_view')
            ->whereBetween('timestamp', [$startTime, $endTime])
            ->count();
        
        // Total events
        $events = $this->db->table('analytics_events')
            ->whereBetween('timestamp', [$startTime, $endTime])
            ->count();
        
        // Bounce rate
        $bouncedSessions = $this->db->table('analytics_sessions')
            ->whereBetween('started_at', [$startTime, $endTime])
            ->where('page_count', 1)
            ->count();
        
        $bounceRate = $sessions > 0 ? ($bouncedSessions / $sessions) * 100 : 0;
        
        // Average session duration
        $avgDuration = $this->db->table('analytics_sessions')
            ->whereBetween('started_at', [$startTime, $endTime])
            ->avg('duration');
        
        return [
            'unique_users' => $uniqueUsers,
            'sessions' => $sessions,
            'pageviews' => $pageviews,
            'events' => $events,
            'bounce_rate' => $bounceRate,
            'avg_session_duration' => $avgDuration ?: 0
        ];
    }

    private function calculateEventMetrics(string $startTime, string $endTime): array
    {
        $metrics = [];
        
        // Event counts by type
        $eventCounts = $this->db->table('analytics_events')
            ->select('event', 'COUNT(*) as count')
            ->whereBetween('timestamp', [$startTime, $endTime])
            ->groupBy('event')
            ->pluck('count', 'event')
            ->toArray();
        
        $metrics['event_counts'] = $eventCounts;
        
        // Conversion events
        $conversions = $this->db->table('analytics_events')
            ->where('event', 'conversion')
            ->whereBetween('timestamp', [$startTime, $endTime])
            ->count();
        
        $metrics['conversions'] = $conversions;
        
        // Revenue
        $revenue = $this->db->table('analytics_events')
            ->where('event', 'purchase')
            ->whereBetween('timestamp', [$startTime, $endTime])
            ->sum("properties->>'$.value'");
        
        $metrics['revenue'] = $revenue ?: 0;
        
        return $metrics;
    }

    private function calculateUserMetrics(string $startTime, string $endTime): array
    {
        $metrics = [];
        
        // New vs returning users
        $newUsers = $this->db->table('analytics_users')
            ->whereBetween('first_seen', [$startTime, $endTime])
            ->count();
        
        $metrics['new_users'] = $newUsers;
        
        // Device breakdown
        $devices = $this->db->table('analytics_sessions')
            ->select('device_type', 'COUNT(*) as count')
            ->whereBetween('started_at', [$startTime, $endTime])
            ->groupBy('device_type')
            ->pluck('count', 'device_type')
            ->toArray();
        
        $metrics['devices'] = $devices;
        
        // Traffic sources
        $sources = $this->db->table('analytics_sessions')
            ->select('source', 'COUNT(*) as count')
            ->whereBetween('started_at', [$startTime, $endTime])
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();
        
        $metrics['traffic_sources'] = $sources;
        
        return $metrics;
    }

    private function calculateDailyMetrics(string $date): array
    {
        $metrics = [];
        
        // Peak hour
        $peakHour = $this->db->table('analytics_events')
            ->selectRaw('HOUR(timestamp) as hour, COUNT(*) as count')
            ->whereDate('timestamp', $date)
            ->groupBy('hour')
            ->orderBy('count', 'desc')
            ->first();
        
        $metrics['peak_hour'] = $peakHour ? $peakHour->hour : null;
        
        // Top pages
        $topPages = $this->db->table('analytics_events')
            ->select('properties->>"$.page" as page', 'COUNT(*) as views')
            ->where('event', 'page_view')
            ->whereDate('timestamp', $date)
            ->groupBy('page')
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
        
        $metrics['top_pages'] = $topPages;
        
        // User engagement
        $avgPagesPerSession = $this->db->table('analytics_sessions')
            ->whereDate('started_at', $date)
            ->avg('page_count');
        
        $metrics['avg_pages_per_session'] = $avgPagesPerSession ?: 0;
        
        return $metrics;
    }

    private function aggregateFromDaily($dailyData): array
    {
        $metrics = [
            'unique_users' => 0,
            'sessions' => 0,
            'pageviews' => 0,
            'events' => 0,
            'bounce_rate' => 0,
            'avg_session_duration' => 0,
            'additional' => []
        ];
        
        $bounceRates = [];
        $sessionDurations = [];
        $revenues = [];
        
        foreach ($dailyData as $day) {
            $metrics['sessions'] += $day->sessions;
            $metrics['pageviews'] += $day->pageviews;
            $metrics['events'] += $day->events;
            
            if ($day->bounce_rate > 0) {
                $bounceRates[] = $day->bounce_rate;
            }
            
            if ($day->avg_session_duration > 0) {
                $sessionDurations[] = $day->avg_session_duration;
            }
            
            $dayMetrics = json_decode($day->metrics, true);
            if (isset($dayMetrics['revenue'])) {
                $revenues[] = $dayMetrics['revenue'];
            }
        }
        
        // Calculate period unique users
        if (!$dailyData->isEmpty()) {
            $startDate = $dailyData->first()->date;
            $endDate = $dailyData->last()->date;
            
            $metrics['unique_users'] = $this->db->table('analytics_events')
                ->whereBetween('date', [$startDate, $endDate])
                ->distinct('user_id')
                ->count('user_id');
        }
        
        // Calculate averages
        $metrics['bounce_rate'] = !empty($bounceRates) 
            ? array_sum($bounceRates) / count($bounceRates) 
            : 0;
            
        $metrics['avg_session_duration'] = !empty($sessionDurations) 
            ? array_sum($sessionDurations) / count($sessionDurations) 
            : 0;
        
        // Additional metrics
        $metrics['additional']['total_revenue'] = array_sum($revenues);
        $metrics['additional']['avg_daily_revenue'] = !empty($revenues) 
            ? array_sum($revenues) / count($revenues) 
            : 0;
        
        return $metrics;
    }

    private function aggregateDayFromRaw(\DateTime $day): void
    {
        $date = $day->format('Y-m-d');
        $startTime = $date . ' 00:00:00';
        $endTime = $date . ' 23:59:59';
        
        $metrics = $this->calculateBasicMetrics($startTime, $endTime);
        $eventMetrics = $this->calculateEventMetrics($startTime, $endTime);
        $userMetrics = $this->calculateUserMetrics($startTime, $endTime);
        $dailyMetrics = $this->calculateDailyMetrics($date);
        
        $this->db->table('analytics_aggregations')->insert([
            'type' => 'daily',
            'date' => $date,
            'unique_users' => $metrics['unique_users'],
            'sessions' => $metrics['sessions'],
            'pageviews' => $metrics['pageviews'],
            'events' => $metrics['events'],
            'bounce_rate' => $metrics['bounce_rate'],
            'avg_session_duration' => $metrics['avg_session_duration'],
            'metrics' => json_encode(array_merge($eventMetrics, $userMetrics, $dailyMetrics)),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // Helper methods

    private function getLastProcessedTime(string $type): \DateTime
    {
        $record = $this->db->table('analytics_processing_status')
            ->where('type', $type)
            ->first();
        
        if ($record) {
            return new \DateTime($record->last_processed);
        }
        
        // Default to 30 days ago
        $date = new \DateTime('-30 days');
        
        switch ($type) {
            case 'hourly':
                $date->setTime((int)$date->format('H'), 0, 0);
                break;
            case 'daily':
            case 'weekly':
            case 'monthly':
                $date->setTime(0, 0, 0);
                break;
        }
        
        return $date;
    }

    private function getLastProcessedDate(string $type): \DateTime
    {
        $date = $this->getLastProcessedTime($type);
        $date->setTime(0, 0, 0);
        return $date;
    }

    private function updateLastProcessedTime(string $type, \DateTime $time): void
    {
        $this->db->table('analytics_processing_status')
            ->updateOrInsert(
                ['type' => $type],
                [
                    'last_processed' => $time->format('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );
    }
}