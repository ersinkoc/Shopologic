<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Repositories;

use Shopologic\Core\Database\QueryBuilder;

class MetricsRepository\n{
    private string $table = 'analytics_metrics';

    /**
     * Store a metric value
     */
    public function store(array $data): array
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Check if metric already exists for this date/dimension
        $existing = $this->findExisting(
            $data['metric_name'],
            $data['metric_date'],
            $data['dimension_type'] ?? null,
            $data['dimension_value'] ?? null
        );

        if ($existing) {
            // Update existing metric
            $this->update($existing['id'], [
                'metric_value' => $data['metric_value'],
                'sample_size' => $data['sample_size'] ?? null,
                'metadata' => $data['metadata'] ?? null
            ]);
            return $this->findById($existing['id']);
        } else {
            // Create new metric
            $id = QueryBuilder::table($this->table)->insert($data);
            return $this->findById($id);
        }
    }

    /**
     * Find metric by ID
     */
    public function findById(int $id): ?array
    {
        return QueryBuilder::table($this->table)
            ->where('id', $id)
            ->first();
    }

    /**
     * Find existing metric
     */
    public function findExisting(string $metricName, string $metricDate, ?string $dimensionType, ?string $dimensionValue): ?array
    {
        $query = QueryBuilder::table($this->table)
            ->where('metric_name', $metricName)
            ->where('metric_date', $metricDate);

        if ($dimensionType !== null) {
            $query->where('dimension_type', $dimensionType);
        } else {
            $query->whereNull('dimension_type');
        }

        if ($dimensionValue !== null) {
            $query->where('dimension_value', $dimensionValue);
        } else {
            $query->whereNull('dimension_value');
        }

        return $query->first();
    }

    /**
     * Update metric
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return QueryBuilder::table($this->table)
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Get metrics by name
     */
    public function getByName(string $metricName, string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->where('metric_name', $metricName)
            ->whereBetween('metric_date', [$startDate, $endDate])
            ->orderBy('metric_date')
            ->get();
    }

    /**
     * Get metrics by type
     */
    public function getByType(string $metricType, string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->where('metric_type', $metricType)
            ->whereBetween('metric_date', [$startDate, $endDate])
            ->orderBy('metric_date')
            ->get();
    }

    /**
     * Get metrics with dimension
     */
    public function getWithDimension(
        string $metricName,
        string $dimensionType,
        string $startDate,
        string $endDate
    ): array {
        return QueryBuilder::table($this->table)
            ->where('metric_name', $metricName)
            ->where('dimension_type', $dimensionType)
            ->whereBetween('metric_date', [$startDate, $endDate])
            ->orderBy('metric_date')
            ->orderBy('dimension_value')
            ->get();
    }

    /**
     * Get metric value for specific date
     */
    public function getValueForDate(
        string $metricName,
        string $date,
        ?string $dimensionType = null,
        ?string $dimensionValue = null
    ): ?float {
        $query = QueryBuilder::table($this->table)
            ->select(['metric_value'])
            ->where('metric_name', $metricName)
            ->where('metric_date', $date);

        if ($dimensionType !== null) {
            $query->where('dimension_type', $dimensionType);
        } else {
            $query->whereNull('dimension_type');
        }

        if ($dimensionValue !== null) {
            $query->where('dimension_value', $dimensionValue);
        } else {
            $query->whereNull('dimension_value');
        }

        $result = $query->first();
        return $result ? (float)$result['metric_value'] : null;
    }

    /**
     * Get metrics summary
     */
    public function getMetricsSummary(string $metricName, string $startDate, string $endDate): array
    {
        $result = QueryBuilder::table($this->table)
            ->select([
                'COUNT(*) as data_points',
                'AVG(metric_value) as avg_value',
                'MIN(metric_value) as min_value',
                'MAX(metric_value) as max_value',
                'SUM(metric_value) as total_value',
                'STDDEV(metric_value) as std_deviation'
            ])
            ->where('metric_name', $metricName)
            ->whereBetween('metric_date', [$startDate, $endDate])
            ->first();

        return $result ?: [];
    }

    /**
     * Get trend data
     */
    public function getTrendData(string $metricName, string $startDate, string $endDate): array
    {
        return QueryBuilder::table($this->table)
            ->select(['metric_date', 'metric_value'])
            ->where('metric_name', $metricName)
            ->whereBetween('metric_date', [$startDate, $endDate])
            ->whereNull('dimension_type')
            ->orderBy('metric_date')
            ->get();
    }

    /**
     * Get top performers by dimension
     */
    public function getTopPerformers(
        string $metricName,
        string $dimensionType,
        string $startDate,
        string $endDate,
        int $limit = 10
    ): array {
        return QueryBuilder::table($this->table)
            ->select([
                'dimension_value',
                'AVG(metric_value) as avg_value',
                'SUM(metric_value) as total_value',
                'COUNT(*) as data_points'
            ])
            ->where('metric_name', $metricName)
            ->where('dimension_type', $dimensionType)
            ->whereBetween('metric_date', [$startDate, $endDate])
            ->groupBy('dimension_value')
            ->orderBy('total_value', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Compare periods
     */
    public function comparePeriods(
        string $metricName,
        string $currentStart,
        string $currentEnd,
        string $previousStart,
        string $previousEnd
    ): array {
        $currentValue = $this->getPeriodValue($metricName, $currentStart, $currentEnd);
        $previousValue = $this->getPeriodValue($metricName, $previousStart, $previousEnd);

        $change = $currentValue - $previousValue;
        $changePercent = $previousValue != 0 ? ($change / $previousValue) * 100 : 0;

        return [
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'absolute_change' => $change,
            'percent_change' => $changePercent,
            'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable')
        ];
    }

    /**
     * Get period value
     */
    private function getPeriodValue(string $metricName, string $startDate, string $endDate): float
    {
        $result = QueryBuilder::table($this->table)
            ->select(['AVG(metric_value) as avg_value'])
            ->where('metric_name', $metricName)
            ->whereBetween('metric_date', [$startDate, $endDate])
            ->whereNull('dimension_type')
            ->first();

        return $result ? (float)$result['avg_value'] : 0;
    }

    /**
     * Get metrics by category
     */
    public function getMetricsByCategory(string $category, string $startDate, string $endDate): array
    {
        $metricCategories = [
            'sales' => ['revenue', 'orders', 'average_order_value'],
            'traffic' => ['sessions', 'page_views', 'unique_visitors'],
            'conversion' => ['conversion_rate', 'bounce_rate'],
            'customer' => ['new_customers', 'returning_customers', 'customer_lifetime_value']
        ];

        if (!isset($metricCategories[$category])) {
            return [];
        }

        return QueryBuilder::table($this->table)
            ->whereIn('metric_name', $metricCategories[$category])
            ->whereBetween('metric_date', [$startDate, $endDate])
            ->orderBy('metric_date')
            ->orderBy('metric_name')
            ->get();
    }

    /**
     * Get latest metrics
     */
    public function getLatestMetrics(array $metricNames): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'metric_name',
                'metric_value',
                'metric_date',
                'dimension_type',
                'dimension_value'
            ])
            ->whereIn('metric_name', $metricNames)
            ->whereIn('id', function($query) {
                $query->select([QueryBuilder::raw('MAX(id)')])
                    ->from($this->table)
                    ->groupBy(['metric_name', 'dimension_type', 'dimension_value']);
            })
            ->get();
    }

    /**
     * Get metric history
     */
    public function getMetricHistory(
        string $metricName,
        int $days = 30,
        ?string $dimensionType = null,
        ?string $dimensionValue = null
    ): array {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $endDate = date('Y-m-d');

        $query = QueryBuilder::table($this->table)
            ->select(['metric_date', 'metric_value'])
            ->where('metric_name', $metricName)
            ->whereBetween('metric_date', [$startDate, $endDate]);

        if ($dimensionType !== null) {
            $query->where('dimension_type', $dimensionType);
        } else {
            $query->whereNull('dimension_type');
        }

        if ($dimensionValue !== null) {
            $query->where('dimension_value', $dimensionValue);
        } else {
            $query->whereNull('dimension_value');
        }

        return $query->orderBy('metric_date')->get();
    }

    /**
     * Calculate growth rate
     */
    public function calculateGrowthRate(string $metricName, int $periods = 7): array
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$periods} days"));

        $metrics = $this->getByName($metricName, $startDate, $endDate);
        
        if (count($metrics) < 2) {
            return ['growth_rate' => 0, 'trend' => 'stable'];
        }

        $values = array_column($metrics, 'metric_value');
        $firstValue = reset($values);
        $lastValue = end($values);

        $growthRate = $firstValue != 0 ? (($lastValue - $firstValue) / $firstValue) * 100 : 0;

        return [
            'growth_rate' => $growthRate,
            'trend' => $growthRate > 0 ? 'up' : ($growthRate < 0 ? 'down' : 'stable'),
            'first_value' => $firstValue,
            'last_value' => $lastValue,
            'periods' => count($metrics)
        ];
    }

    /**
     * Get available metrics
     */
    public function getAvailableMetrics(): array
    {
        return QueryBuilder::table($this->table)
            ->select(['DISTINCT metric_name', 'metric_type'])
            ->orderBy('metric_name')
            ->get();
    }

    /**
     * Get available dimensions
     */
    public function getAvailableDimensions(): array
    {
        return QueryBuilder::table($this->table)
            ->select(['DISTINCT dimension_type'])
            ->whereNotNull('dimension_type')
            ->orderBy('dimension_type')
            ->pluck('dimension_type');
    }

    /**
     * Get dimension values
     */
    public function getDimensionValues(string $dimensionType): array
    {
        return QueryBuilder::table($this->table)
            ->select(['DISTINCT dimension_value'])
            ->where('dimension_type', $dimensionType)
            ->whereNotNull('dimension_value')
            ->orderBy('dimension_value')
            ->pluck('dimension_value');
    }

    /**
     * Delete old metrics
     */
    public function deleteOldMetrics(string $beforeDate): int
    {
        return QueryBuilder::table($this->table)
            ->where('metric_date', '<', $beforeDate)
            ->delete();
    }

    /**
     * Bulk insert metrics
     */
    public function bulkInsert(array $metrics): bool
    {
        if (empty($metrics)) {
            return true;
        }

        $timestamp = date('Y-m-d H:i:s');
        foreach ($metrics as &$metric) {
            $metric['created_at'] = $timestamp;
            $metric['updated_at'] = $timestamp;
        }

        return QueryBuilder::table($this->table)->insertBatch($metrics);
    }

    /**
     * Get metrics dashboard data
     */
    public function getDashboardData(array $metricNames, string $date): array
    {
        $data = [];
        
        foreach ($metricNames as $metricName) {
            $current = $this->getValueForDate($metricName, $date);
            $previous = $this->getValueForDate($metricName, date('Y-m-d', strtotime($date . ' -1 day')));
            
            $change = $current !== null && $previous !== null ? $current - $previous : 0;
            $changePercent = $previous !== null && $previous != 0 ? ($change / $previous) * 100 : 0;
            
            $data[$metricName] = [
                'value' => $current,
                'previous_value' => $previous,
                'change' => $change,
                'change_percent' => $changePercent,
                'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable')
            ];
        }
        
        return $data;
    }

    /**
     * Get correlation between metrics
     */
    public function getMetricCorrelation(string $metric1, string $metric2, string $startDate, string $endDate): float
    {
        $data1 = $this->getTrendData($metric1, $startDate, $endDate);
        $data2 = $this->getTrendData($metric2, $startDate, $endDate);

        if (count($data1) < 2 || count($data2) < 2) {
            return 0;
        }

        // Create arrays indexed by date for easy comparison
        $values1 = [];
        $values2 = [];
        
        foreach ($data1 as $row) {
            $values1[$row['metric_date']] = (float)$row['metric_value'];
        }
        
        foreach ($data2 as $row) {
            $values2[$row['metric_date']] = (float)$row['metric_value'];
        }

        // Get common dates
        $commonDates = array_intersect(array_keys($values1), array_keys($values2));
        
        if (count($commonDates) < 2) {
            return 0;
        }

        // Calculate correlation coefficient
        $x = [];
        $y = [];
        
        foreach ($commonDates as $date) {
            $x[] = $values1[$date];
            $y[] = $values2[$date];
        }

        return $this->calculatePearsonCorrelation($x, $y);
    }

    /**
     * Calculate Pearson correlation coefficient
     */
    private function calculatePearsonCorrelation(array $x, array $y): float
    {
        $n = count($x);
        if ($n !== count($y) || $n < 2) {
            return 0;
        }

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }

        $numerator = ($n * $sumXY) - ($sumX * $sumY);
        $denominator = sqrt((($n * $sumX2) - ($sumX * $sumX)) * (($n * $sumY2) - ($sumY * $sumY)));

        return $denominator != 0 ? $numerator / $denominator : 0;
    }
}