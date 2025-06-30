<?php

declare(strict_types=1);

namespace Shopologic\Plugins\MultiCurrencyLocalization\Models;

use Shopologic\Core\Database\Model;

class ExchangeRateHistory extends Model
{
    protected string $table = 'exchange_rate_history';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'exchange_rate_id',
        'from_currency',
        'to_currency',
        'old_rate',
        'new_rate',
        'rate_change',
        'rate_change_percentage',
        'provider',
        'change_date',
        'change_reason',
        'triggered_by',
        'metadata'
    ];

    protected array $casts = [
        'exchange_rate_id' => 'integer',
        'old_rate' => 'decimal:8',
        'new_rate' => 'decimal:8',
        'rate_change' => 'decimal:8',
        'rate_change_percentage' => 'decimal:4',
        'change_date' => 'datetime',
        'triggered_by' => 'integer',
        'metadata' => 'json'
    ];

    /**
     * Change reasons
     */
    const REASON_SCHEDULED_UPDATE = 'scheduled_update';
    const REASON_MANUAL_UPDATE = 'manual_update';
    const REASON_API_UPDATE = 'api_update';
    const REASON_PROVIDER_CHANGE = 'provider_change';
    const REASON_MARKET_EVENT = 'market_event';
    const REASON_CORRECTION = 'correction';

    /**
     * Get exchange rate
     */
    public function exchangeRate()
    {
        return $this->belongsTo(ExchangeRate::class, 'exchange_rate_id');
    }

    /**
     * Get user who triggered the change
     */
    public function triggeredByUser()
    {
        return $this->belongsTo('Shopologic\Core\Models\User', 'triggered_by');
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('change_date', [$startDate, $endDate]);
    }

    /**
     * Scope by currency pair
     */
    public function scopeByCurrencyPair($query, string $fromCurrency, string $toCurrency)
    {
        return $query->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency);
    }

    /**
     * Scope by provider
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope recent changes
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('change_date', '>=', now()->subHours($hours));
    }

    /**
     * Scope significant changes
     */
    public function scopeSignificant($query, float $threshold = 1.0)
    {
        return $query->where(function($q) use ($threshold) {
            $q->where('rate_change_percentage', '>=', $threshold)
              ->orWhere('rate_change_percentage', '<=', -$threshold);
        });
    }

    /**
     * Check if change is significant
     */
    public function isSignificant(float $threshold = 1.0): bool
    {
        return abs($this->rate_change_percentage) >= $threshold;
    }

    /**
     * Get change direction
     */
    public function getChangeDirection(): string
    {
        if ($this->rate_change > 0) {
            return 'increase';
        } elseif ($this->rate_change < 0) {
            return 'decrease';
        } else {
            return 'no_change';
        }
    }

    /**
     * Get change magnitude
     */
    public function getChangeMagnitude(): string
    {
        $absPercentage = abs($this->rate_change_percentage);
        
        if ($absPercentage >= 5) {
            return 'major';
        } elseif ($absPercentage >= 1) {
            return 'moderate';
        } elseif ($absPercentage >= 0.1) {
            return 'minor';
        } else {
            return 'negligible';
        }
    }

    /**
     * Format rate change for display
     */
    public function getFormattedRateChange(): string
    {
        $prefix = $this->rate_change >= 0 ? '+' : '';
        return $prefix . number_format($this->rate_change, 6);
    }

    /**
     * Format percentage change for display
     */
    public function getFormattedPercentageChange(): string
    {
        $prefix = $this->rate_change_percentage >= 0 ? '+' : '';
        return $prefix . number_format($this->rate_change_percentage, 2) . '%';
    }

    /**
     * Get change reason label
     */
    public function getChangeReasonLabel(): string
    {
        $labels = [
            self::REASON_SCHEDULED_UPDATE => 'Scheduled Update',
            self::REASON_MANUAL_UPDATE => 'Manual Update',
            self::REASON_API_UPDATE => 'API Update',
            self::REASON_PROVIDER_CHANGE => 'Provider Change',
            self::REASON_MARKET_EVENT => 'Market Event',
            self::REASON_CORRECTION => 'Correction'
        ];
        
        return $labels[$this->change_reason] ?? ucfirst(str_replace('_', ' ', $this->change_reason));
    }

    /**
     * Get change icon
     */
    public function getChangeIcon(): string
    {
        switch ($this->getChangeDirection()) {
            case 'increase':
                return '📈';
            case 'decrease':
                return '📉';
            default:
                return '➡️';
        }
    }

    /**
     * Get change color
     */
    public function getChangeColor(): string
    {
        switch ($this->getChangeDirection()) {
            case 'increase':
                return 'green';
            case 'decrease':
                return 'red';
            default:
                return 'gray';
        }
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
     * Format for display
     */
    public function formatForDisplay(): array
    {
        return [
            'id' => $this->id,
            'from_currency' => $this->from_currency,
            'to_currency' => $this->to_currency,
            'currency_pair' => $this->from_currency . '/' . $this->to_currency,
            'old_rate' => $this->old_rate,
            'new_rate' => $this->new_rate,
            'rate_change' => $this->rate_change,
            'formatted_rate_change' => $this->getFormattedRateChange(),
            'rate_change_percentage' => $this->rate_change_percentage,
            'formatted_percentage_change' => $this->getFormattedPercentageChange(),
            'change_direction' => $this->getChangeDirection(),
            'change_magnitude' => $this->getChangeMagnitude(),
            'is_significant' => $this->isSignificant(),
            'provider' => $this->provider,
            'change_date' => $this->change_date->format('Y-m-d H:i:s'),
            'change_reason' => $this->change_reason,
            'change_reason_label' => $this->getChangeReasonLabel(),
            'triggered_by' => $this->triggeredByUser?->name ?? 'System',
            'change_icon' => $this->getChangeIcon(),
            'change_color' => $this->getChangeColor()
        ];
    }

    /**
     * Get statistics for period
     */
    public static function getStatisticsForPeriod($startDate, $endDate): array
    {
        $changes = self::whereBetween('change_date', [$startDate, $endDate])->get();
        
        return [
            'total_changes' => $changes->count(),
            'currency_pairs_affected' => $changes->unique(function($change) {
                return $change->from_currency . '/' . $change->to_currency;
            })->count(),
            'significant_changes' => $changes->filter(function($change) {
                return $change->isSignificant();
            })->count(),
            'average_change_percentage' => $changes->avg('rate_change_percentage'),
            'largest_increase' => $changes->max('rate_change_percentage'),
            'largest_decrease' => $changes->min('rate_change_percentage'),
            'most_volatile_pair' => $changes->groupBy(function($change) {
                return $change->from_currency . '/' . $change->to_currency;
            })->map(function($pairChanges) {
                return $pairChanges->map(function($change) {
                    return abs($change->rate_change_percentage);
                })->avg();
            })->sortDesc()->keys()->first(),
            'changes_by_provider' => $changes->groupBy('provider')->map->count()->toArray(),
            'changes_by_reason' => $changes->groupBy('change_reason')->map->count()->toArray()
        ];
    }

    /**
     * Get rate volatility for currency pair
     */
    public static function getVolatility(string $fromCurrency, string $toCurrency, int $days = 30): float
    {
        $changes = self::byCurrencyPair($fromCurrency, $toCurrency)
            ->where('change_date', '>=', now()->subDays($days))
            ->pluck('rate_change_percentage')
            ->toArray();
        
        if (count($changes) < 2) {
            return 0;
        }
        
        $mean = array_sum($changes) / count($changes);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $changes)) / count($changes);
        
        return sqrt($variance);
    }

    /**
     * Clean up old history entries
     */
    public static function cleanupOldEntries(int $retentionDays = 365): int
    {
        return self::where('change_date', '<', now()->subDays($retentionDays))->delete();
    }
}