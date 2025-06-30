<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Models;

use Shopologic\Core\Database\Model;

class Metric extends Model
{
    protected string $table = 'analytics_metrics';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'name',
        'key',
        'category',
        'description',
        'value',
        'previous_value',
        'change_percentage',
        'trend',
        'unit',
        'precision',
        'calculation_method',
        'data_source',
        'filters',
        'dimensions',
        'timestamp',
        'period_type',
        'period_value',
        'is_active',
        'metadata',
        'benchmark_value',
        'target_value',
        'threshold_warning',
        'threshold_critical'
    ];

    protected array $casts = [
        'value' => 'decimal:4',
        'previous_value' => 'decimal:4',
        'change_percentage' => 'decimal:2',
        'precision' => 'integer',
        'filters' => 'json',
        'dimensions' => 'json',
        'timestamp' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'json',
        'benchmark_value' => 'decimal:4',
        'target_value' => 'decimal:4',
        'threshold_warning' => 'decimal:4',
        'threshold_critical' => 'decimal:4'
    ];

    /**
     * Metric categories
     */
    const CATEGORY_SALES = 'sales';
    const CATEGORY_REVENUE = 'revenue';
    const CATEGORY_CUSTOMERS = 'customers';
    const CATEGORY_PRODUCTS = 'products';
    const CATEGORY_TRAFFIC = 'traffic';
    const CATEGORY_CONVERSION = 'conversion';
    const CATEGORY_ENGAGEMENT = 'engagement';
    const CATEGORY_PERFORMANCE = 'performance';
    const CATEGORY_INVENTORY = 'inventory';
    const CATEGORY_MARKETING = 'marketing';

    /**
     * Trend directions
     */
    const TREND_UP = 'up';
    const TREND_DOWN = 'down';
    const TREND_STABLE = 'stable';
    const TREND_UNKNOWN = 'unknown';

    /**
     * Period types
     */
    const PERIOD_HOUR = 'hour';
    const PERIOD_DAY = 'day';
    const PERIOD_WEEK = 'week';
    const PERIOD_MONTH = 'month';
    const PERIOD_QUARTER = 'quarter';
    const PERIOD_YEAR = 'year';

    /**
     * Units
     */
    const UNIT_COUNT = 'count';
    const UNIT_CURRENCY = 'currency';
    const UNIT_PERCENTAGE = 'percentage';
    const UNIT_SECONDS = 'seconds';
    const UNIT_BYTES = 'bytes';
    const UNIT_RATE = 'rate';

    /**
     * Get metric values (historical)
     */
    public function values()
    {
        return $this->hasMany(MetricValue::class, 'metric_id');
    }

    /**
     * Get recent values
     */
    public function recentValues()
    {
        return $this->values()->orderBy('timestamp', 'desc')->limit(100);
    }

    /**
     * Scope active metrics
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by period
     */
    public function scopeByPeriod($query, string $periodType)
    {
        return $query->where('period_type', $periodType);
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('timestamp', [$startDate, $endDate]);
    }

    /**
     * Update metric value
     */
    public function updateValue(float $newValue, ?\DateTime $timestamp = null): void
    {
        $timestamp = $timestamp ?? now();
        
        // Store previous value
        $this->previous_value = $this->value;
        $this->value = $newValue;
        $this->timestamp = $timestamp;
        
        // Calculate change percentage
        if ($this->previous_value && $this->previous_value != 0) {
            $this->change_percentage = (($newValue - $this->previous_value) / $this->previous_value) * 100;
        } else {
            $this->change_percentage = 0;
        }
        
        // Determine trend
        $this->trend = $this->calculateTrend();
        
        $this->save();
        
        // Store historical value
        MetricValue::create([
            'metric_id' => $this->id,
            'value' => $newValue,
            'timestamp' => $timestamp,
            'dimensions' => $this->dimensions,
            'metadata' => ['change_percentage' => $this->change_percentage]
        ]);
    }

    /**
     * Calculate trend direction
     */
    private function calculateTrend(): string
    {
        if (!$this->previous_value || $this->change_percentage === null) {
            return self::TREND_UNKNOWN;
        }
        
        $threshold = 0.1; // 0.1% threshold for stability
        
        if (abs($this->change_percentage) < $threshold) {
            return self::TREND_STABLE;
        }
        
        return $this->change_percentage > 0 ? self::TREND_UP : self::TREND_DOWN;
    }

    /**
     * Get formatted value
     */
    public function getFormattedValue(): string
    {
        return $this->formatValue($this->value);
    }

    /**
     * Get formatted previous value
     */
    public function getFormattedPreviousValue(): string
    {
        return $this->formatValue($this->previous_value);
    }

    /**
     * Format value based on unit
     */
    private function formatValue(?float $value): string
    {
        if ($value === null) {
            return 'N/A';
        }
        
        switch ($this->unit) {
            case self::UNIT_CURRENCY:
                return '$' . number_format($value, $this->precision ?? 2);
            case self::UNIT_PERCENTAGE:
                return number_format($value, $this->precision ?? 1) . '%';
            case self::UNIT_COUNT:
                return number_format($value, 0);
            case self::UNIT_SECONDS:
                return $this->formatDuration($value);
            case self::UNIT_BYTES:
                return $this->formatBytes($value);
            case self::UNIT_RATE:
                return number_format($value, $this->precision ?? 2) . '/s';
            default:
                return number_format($value, $this->precision ?? 2);
        }
    }

    /**
     * Format duration in seconds
     */
    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return number_format($seconds, 1) . 's';
        }
        
        $minutes = $seconds / 60;
        if ($minutes < 60) {
            return number_format($minutes, 1) . 'm';
        }
        
        $hours = $minutes / 60;
        return number_format($hours, 1) . 'h';
    }

    /**
     * Format bytes
     */
    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return number_format($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get change indicator
     */
    public function getChangeIndicator(): array
    {
        $indicator = [
            'direction' => $this->trend,
            'percentage' => abs($this->change_percentage ?? 0),
            'color' => 'gray',
            'icon' => '→'
        ];
        
        switch ($this->trend) {
            case self::TREND_UP:
                $indicator['color'] = $this->isPositiveTrend() ? 'green' : 'red';
                $indicator['icon'] = '↗';
                break;
            case self::TREND_DOWN:
                $indicator['color'] = $this->isPositiveTrend() ? 'red' : 'green';
                $indicator['icon'] = '↘';
                break;
            case self::TREND_STABLE:
                $indicator['color'] = 'blue';
                $indicator['icon'] = '→';
                break;
        }
        
        return $indicator;
    }

    /**
     * Check if upward trend is positive for this metric
     */
    private function isPositiveTrend(): bool
    {
        // Most metrics benefit from upward trends
        $negativeTrendMetrics = [
            'bounce_rate',
            'cart_abandonment_rate',
            'churn_rate',
            'cost_per_acquisition',
            'refund_rate',
            'return_rate',
            'load_time',
            'error_rate'
        ];
        
        return !in_array($this->key, $negativeTrendMetrics);
    }

    /**
     * Check if value is above warning threshold
     */
    public function isAboveWarningThreshold(): bool
    {
        return $this->threshold_warning !== null && $this->value > $this->threshold_warning;
    }

    /**
     * Check if value is above critical threshold
     */
    public function isAboveCriticalThreshold(): bool
    {
        return $this->threshold_critical !== null && $this->value > $this->threshold_critical;
    }

    /**
     * Get alert level
     */
    public function getAlertLevel(): string
    {
        if ($this->isAboveCriticalThreshold()) {
            return 'critical';
        }
        
        if ($this->isAboveWarningThreshold()) {
            return 'warning';
        }
        
        return 'normal';
    }

    /**
     * Get performance vs target
     */
    public function getPerformanceVsTarget(): ?array
    {
        if ($this->target_value === null) {
            return null;
        }
        
        $percentage = ($this->value / $this->target_value) * 100;
        $status = 'on_track';
        
        if ($percentage < 80) {
            $status = 'behind';
        } elseif ($percentage >= 110) {
            $status = 'ahead';
        }
        
        return [
            'percentage' => $percentage,
            'status' => $status,
            'difference' => $this->value - $this->target_value,
            'formatted_difference' => $this->formatValue($this->value - $this->target_value)
        ];
    }

    /**
     * Get performance vs benchmark
     */
    public function getPerformanceVsBenchmark(): ?array
    {
        if ($this->benchmark_value === null) {
            return null;
        }
        
        $percentage = ($this->value / $this->benchmark_value) * 100;
        $status = 'at_benchmark';
        
        if ($percentage < 90) {
            $status = 'below_benchmark';
        } elseif ($percentage > 110) {
            $status = 'above_benchmark';
        }
        
        return [
            'percentage' => $percentage,
            'status' => $status,
            'difference' => $this->value - $this->benchmark_value,
            'formatted_difference' => $this->formatValue($this->value - $this->benchmark_value)
        ];
    }

    /**
     * Get historical trend data
     */
    public function getHistoricalTrend(int $periods = 30): array
    {
        $values = $this->values()
            ->orderBy('timestamp', 'desc')
            ->limit($periods)
            ->get()
            ->reverse();
        
        return $values->map(function ($value) {
            return [
                'timestamp' => $value->timestamp->format('Y-m-d H:i:s'),
                'value' => $value->value,
                'formatted_value' => $this->formatValue($value->value)
            ];
        })->values()->toArray();
    }

    /**
     * Get category label
     */
    public function getCategoryLabel(): string
    {
        $labels = [
            self::CATEGORY_SALES => 'Sales',
            self::CATEGORY_REVENUE => 'Revenue',
            self::CATEGORY_CUSTOMERS => 'Customers',
            self::CATEGORY_PRODUCTS => 'Products',
            self::CATEGORY_TRAFFIC => 'Traffic',
            self::CATEGORY_CONVERSION => 'Conversion',
            self::CATEGORY_ENGAGEMENT => 'Engagement',
            self::CATEGORY_PERFORMANCE => 'Performance',
            self::CATEGORY_INVENTORY => 'Inventory',
            self::CATEGORY_MARKETING => 'Marketing'
        ];
        
        return $labels[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        $changeIndicator = $this->getChangeIndicator();
        $targetPerformance = $this->getPerformanceVsTarget();
        $benchmarkPerformance = $this->getPerformanceVsBenchmark();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'category' => $this->category,
            'category_label' => $this->getCategoryLabel(),
            'description' => $this->description,
            'value' => $this->value,
            'formatted_value' => $this->getFormattedValue(),
            'previous_value' => $this->previous_value,
            'formatted_previous_value' => $this->getFormattedPreviousValue(),
            'change_percentage' => $this->change_percentage,
            'trend' => $this->trend,
            'change_indicator' => $changeIndicator,
            'unit' => $this->unit,
            'alert_level' => $this->getAlertLevel(),
            'target_performance' => $targetPerformance,
            'benchmark_performance' => $benchmarkPerformance,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'period_type' => $this->period_type
        ];
    }
}