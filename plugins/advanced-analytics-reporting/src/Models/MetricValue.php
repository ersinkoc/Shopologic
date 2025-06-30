<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Models;

use Shopologic\Core\Database\Model;

class MetricValue extends Model
{
    protected string $table = 'metric_values';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'metric_id',
        'value',
        'timestamp',
        'dimensions',
        'metadata',
        'source',
        'quality_score',
        'confidence_level',
        'aggregation_method',
        'sample_size'
    ];

    protected array $casts = [
        'metric_id' => 'integer',
        'value' => 'decimal:4',
        'timestamp' => 'datetime',
        'dimensions' => 'json',
        'metadata' => 'json',
        'quality_score' => 'decimal:2',
        'confidence_level' => 'decimal:2',
        'sample_size' => 'integer'
    ];

    /**
     * Aggregation methods
     */
    const AGGREGATION_SUM = 'sum';
    const AGGREGATION_AVERAGE = 'average';
    const AGGREGATION_COUNT = 'count';
    const AGGREGATION_MIN = 'min';
    const AGGREGATION_MAX = 'max';
    const AGGREGATION_MEDIAN = 'median';
    const AGGREGATION_PERCENTILE = 'percentile';

    /**
     * Get metric
     */
    public function metric()
    {
        return $this->belongsTo(Metric::class, 'metric_id');
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope recent values
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('timestamp', '>=', now()->subHours($hours));
    }

    /**
     * Scope by dimension
     */
    public function scopeByDimension($query, string $key, $value)
    {
        return $query->whereJsonContains('dimensions', [$key => $value]);
    }

    /**
     * Get dimension value
     */
    public function getDimension(string $key, $default = null)
    {
        $dimensions = $this->dimensions ?? [];
        return $dimensions[$key] ?? $default;
    }

    /**
     * Set dimension value
     */
    public function setDimension(string $key, $value): void
    {
        $dimensions = $this->dimensions ?? [];
        $dimensions[$key] = $value;
        $this->dimensions = $dimensions;
        $this->save();
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        $metadata = $this->metadata ?? [];
        return $metadata[$key] ?? $default;
    }

    /**
     * Set metadata value
     */
    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * Check if value is reliable
     */
    public function isReliable(): bool
    {
        return $this->quality_score >= 0.8 && $this->confidence_level >= 0.9;
    }

    /**
     * Get formatted value
     */
    public function getFormattedValue(): string
    {
        if (!$this->metric) {
            return number_format($this->value, 2);
        }
        
        switch ($this->metric->unit) {
            case Metric::UNIT_CURRENCY:
                return '$' . number_format($this->value, $this->metric->precision ?? 2);
            case Metric::UNIT_PERCENTAGE:
                return number_format($this->value, $this->metric->precision ?? 1) . '%';
            case Metric::UNIT_COUNT:
                return number_format($this->value, 0);
            default:
                return number_format($this->value, $this->metric->precision ?? 2);
        }
    }

    /**
     * Compare with another value
     */
    public function compareWith(MetricValue $other): array
    {
        $difference = $this->value - $other->value;
        $percentage = $other->value != 0 ? ($difference / $other->value) * 100 : 0;
        
        return [
            'difference' => $difference,
            'percentage_change' => $percentage,
            'direction' => $difference > 0 ? 'increase' : ($difference < 0 ? 'decrease' : 'no_change'),
            'is_significant' => abs($percentage) >= 5 // 5% threshold for significance
        ];
    }

    /**
     * Get quality indicator
     */
    public function getQualityIndicator(): array
    {
        $score = $this->quality_score ?? 1.0;
        $confidence = $this->confidence_level ?? 1.0;
        
        if ($score >= 0.9 && $confidence >= 0.95) {
            return ['level' => 'high', 'color' => 'green', 'label' => 'High Quality'];
        } elseif ($score >= 0.7 && $confidence >= 0.8) {
            return ['level' => 'medium', 'color' => 'orange', 'label' => 'Medium Quality'];
        } else {
            return ['level' => 'low', 'color' => 'red', 'label' => 'Low Quality'];
        }
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        $qualityIndicator = $this->getQualityIndicator();
        
        return [
            'id' => $this->id,
            'metric_name' => $this->metric?->name ?? 'Unknown',
            'value' => $this->value,
            'formatted_value' => $this->getFormattedValue(),
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'dimensions' => $this->dimensions ?? [],
            'source' => $this->source,
            'quality_score' => $this->quality_score,
            'confidence_level' => $this->confidence_level,
            'quality_indicator' => $qualityIndicator,
            'aggregation_method' => $this->aggregation_method,
            'sample_size' => $this->sample_size,
            'is_reliable' => $this->isReliable()
        ];
    }

    /**
     * Aggregate values for period
     */
    public static function aggregateForPeriod(
        int $metricId,
        $startDate,
        $endDate,
        string $method = self::AGGREGATION_AVERAGE
    ): ?float {
        $query = self::where('metric_id', $metricId)
            ->whereBetween('timestamp', [$startDate, $endDate]);
        
        switch ($method) {
            case self::AGGREGATION_SUM:
                return $query->sum('value');
            case self::AGGREGATION_AVERAGE:
                return $query->avg('value');
            case self::AGGREGATION_COUNT:
                return $query->count();
            case self::AGGREGATION_MIN:
                return $query->min('value');
            case self::AGGREGATION_MAX:
                return $query->max('value');
            case self::AGGREGATION_MEDIAN:
                $values = $query->pluck('value')->sort()->values();
                $count = $values->count();
                if ($count === 0) return null;
                if ($count % 2 === 0) {
                    return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
                } else {
                    return $values[intval($count / 2)];
                }
            default:
                return $query->avg('value');
        }
    }

    /**
     * Get trend analysis
     */
    public static function getTrendAnalysis(
        int $metricId,
        $startDate,
        $endDate,
        string $interval = 'day'
    ): array {
        $query = self::where('metric_id', $metricId)
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp');
        
        // Group by interval
        $values = $query->get()->groupBy(function($item) use ($interval) {
            switch ($interval) {
                case 'hour':
                    return $item->timestamp->format('Y-m-d H:00:00');
                case 'day':
                    return $item->timestamp->format('Y-m-d');
                case 'week':
                    return $item->timestamp->format('Y-W');
                case 'month':
                    return $item->timestamp->format('Y-m');
                default:
                    return $item->timestamp->format('Y-m-d');
            }
        });
        
        $trend = [];
        foreach ($values as $period => $periodValues) {
            $avgValue = $periodValues->avg('value');
            $trend[] = [
                'period' => $period,
                'value' => $avgValue,
                'count' => $periodValues->count(),
                'min' => $periodValues->min('value'),
                'max' => $periodValues->max('value')
            ];
        }
        
        return $trend;
    }

    /**
     * Clean up old values
     */
    public static function cleanupOldValues(int $retentionDays = 365): int
    {
        return self::where('timestamp', '<', now()->subDays($retentionDays))->delete();
    }
}