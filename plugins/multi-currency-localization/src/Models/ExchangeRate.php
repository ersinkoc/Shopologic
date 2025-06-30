<?php

declare(strict_types=1);

namespace Shopologic\Plugins\MultiCurrencyLocalization\Models;

use Shopologic\Core\Database\Model;

class ExchangeRate extends Model
{
    protected string $table = 'exchange_rates';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'inverse_rate',
        'provider',
        'source_data',
        'effective_from',
        'expires_at',
        'is_active',
        'priority',
        'spread_percentage',
        'commission_rate',
        'metadata'
    ];

    protected array $casts = [
        'rate' => 'decimal:8',
        'inverse_rate' => 'decimal:8',
        'source_data' => 'json',
        'effective_from' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'spread_percentage' => 'decimal:4',
        'commission_rate' => 'decimal:4',
        'metadata' => 'json'
    ];

    /**
     * Exchange rate providers
     */
    const PROVIDER_FIXER = 'fixer';
    const PROVIDER_OPEN_EXCHANGE = 'open_exchange';
    const PROVIDER_CURRENCY_API = 'currency_api';
    const PROVIDER_ECB = 'ecb';
    const PROVIDER_MANUAL = 'manual';
    const PROVIDER_BANK = 'bank';

    /**
     * Get from currency
     */
    public function fromCurrency()
    {
        return $this->belongsTo(Currency::class, 'from_currency', 'code');
    }

    /**
     * Get to currency
     */
    public function toCurrency()
    {
        return $this->belongsTo(Currency::class, 'to_currency', 'code');
    }

    /**
     * Get rate history
     */
    public function history()
    {
        return $this->hasMany(ExchangeRateHistory::class, 'exchange_rate_id');
    }

    /**
     * Scope active rates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->where('effective_from', '<=', now());
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
     * Scope current rates
     */
    public function scopeCurrent($query)
    {
        return $query->active()
            ->orderBy('priority', 'desc')
            ->orderBy('updated_at', 'desc');
    }

    /**
     * Check if rate is currently active
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        $now = now();
        
        if ($this->effective_from && $this->effective_from->gt($now)) {
            return false;
        }
        
        if ($this->expires_at && $this->expires_at->lt($now)) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if rate is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Calculate rate with spread
     */
    public function getRateWithSpread(): float
    {
        if (!$this->spread_percentage) {
            return (float)$this->rate;
        }
        
        $spread = $this->rate * ($this->spread_percentage / 100);
        return (float)($this->rate + $spread);
    }

    /**
     * Calculate rate with commission
     */
    public function getRateWithCommission(): float
    {
        if (!$this->commission_rate) {
            return (float)$this->rate;
        }
        
        $commission = $this->rate * ($this->commission_rate / 100);
        return (float)($this->rate - $commission);
    }

    /**
     * Get effective rate (with spread and commission)
     */
    public function getEffectiveRate(): float
    {
        $rate = (float)$this->rate;
        
        if ($this->spread_percentage) {
            $spread = $rate * ($this->spread_percentage / 100);
            $rate += $spread;
        }
        
        if ($this->commission_rate) {
            $commission = $rate * ($this->commission_rate / 100);
            $rate -= $commission;
        }
        
        return $rate;
    }

    /**
     * Update rate and create history entry
     */
    public function updateRate(float $newRate, array $metadata = []): void
    {
        $oldRate = $this->rate;
        
        // Create history entry
        ExchangeRateHistory::create([
            'exchange_rate_id' => $this->id,
            'from_currency' => $this->from_currency,
            'to_currency' => $this->to_currency,
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'rate_change' => $newRate - $oldRate,
            'rate_change_percentage' => $oldRate > 0 ? (($newRate - $oldRate) / $oldRate) * 100 : 0,
            'provider' => $this->provider,
            'change_date' => now(),
            'metadata' => $metadata
        ]);
        
        // Update current rate
        $this->rate = $newRate;
        $this->inverse_rate = $newRate > 0 ? 1 / $newRate : 0;
        $this->updated_at = now();
        
        if (!empty($metadata)) {
            $currentMetadata = $this->metadata ?? [];
            $this->metadata = array_merge($currentMetadata, $metadata);
        }
        
        $this->save();
    }

    /**
     * Get rate change since last update
     */
    public function getRateChangeSinceLastUpdate(): array
    {
        $lastHistory = $this->history()
            ->orderBy('change_date', 'desc')
            ->first();
        
        if (!$lastHistory) {
            return [
                'change' => 0,
                'percentage_change' => 0,
                'direction' => 'stable'
            ];
        }
        
        $change = $lastHistory->rate_change;
        $percentageChange = $lastHistory->rate_change_percentage;
        
        $direction = 'stable';
        if ($change > 0) {
            $direction = 'up';
        } elseif ($change < 0) {
            $direction = 'down';
        }
        
        return [
            'change' => $change,
            'percentage_change' => $percentageChange,
            'direction' => $direction,
            'last_updated' => $lastHistory->change_date
        ];
    }

    /**
     * Get rate trend over period
     */
    public function getRateTrend(int $days = 30): array
    {
        $history = $this->history()
            ->where('change_date', '>=', now()->subDays($days))
            ->orderBy('change_date', 'asc')
            ->get();
        
        return $history->map(function($entry) {
            return [
                'date' => $entry->change_date->format('Y-m-d'),
                'rate' => $entry->new_rate,
                'change' => $entry->rate_change,
                'percentage_change' => $entry->rate_change_percentage
            ];
        })->toArray();
    }

    /**
     * Calculate volatility
     */
    public function calculateVolatility(int $days = 30): float
    {
        $history = $this->history()
            ->where('change_date', '>=', now()->subDays($days))
            ->pluck('rate_change_percentage')
            ->toArray();
        
        if (count($history) < 2) {
            return 0;
        }
        
        $mean = array_sum($history) / count($history);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $history)) / count($history);
        
        return sqrt($variance);
    }

    /**
     * Get provider label
     */
    public function getProviderLabel(): string
    {
        $labels = [
            self::PROVIDER_FIXER => 'Fixer.io',
            self::PROVIDER_OPEN_EXCHANGE => 'Open Exchange Rates',
            self::PROVIDER_CURRENCY_API => 'Currency API',
            self::PROVIDER_ECB => 'European Central Bank',
            self::PROVIDER_MANUAL => 'Manual Entry',
            self::PROVIDER_BANK => 'Bank Feed'
        ];
        
        return $labels[$this->provider] ?? ucfirst($this->provider);
    }

    /**
     * Get age in hours
     */
    public function getAgeInHours(): int
    {
        return $this->updated_at->diffInHours(now());
    }

    /**
     * Check if rate is stale
     */
    public function isStale(int $maxAgeHours = 24): bool
    {
        return $this->getAgeInHours() > $maxAgeHours;
    }

    /**
     * Get source data value
     */
    public function getSourceData(string $key, $default = null)
    {
        $data = $this->source_data ?? [];
        return $data[$key] ?? $default;
    }

    /**
     * Set source data value
     */
    public function setSourceData(string $key, $value): void
    {
        $data = $this->source_data ?? [];
        $data[$key] = $value;
        $this->source_data = $data;
        $this->save();
    }

    /**
     * Format rate for display
     */
    public function getFormattedRate(int $precision = 4): string
    {
        return number_format($this->rate, $precision);
    }

    /**
     * Format effective rate for display
     */
    public function getFormattedEffectiveRate(int $precision = 4): string
    {
        return number_format($this->getEffectiveRate(), $precision);
    }

    /**
     * Get quality score
     */
    public function getQualityScore(): float
    {
        $score = 1.0;
        
        // Reduce score based on age
        $ageHours = $this->getAgeInHours();
        if ($ageHours > 24) {
            $score -= 0.2;
        }
        if ($ageHours > 48) {
            $score -= 0.3;
        }
        
        // Reduce score based on provider reliability
        $providerReliability = [
            self::PROVIDER_ECB => 1.0,
            self::PROVIDER_FIXER => 0.95,
            self::PROVIDER_OPEN_EXCHANGE => 0.95,
            self::PROVIDER_CURRENCY_API => 0.9,
            self::PROVIDER_BANK => 0.85,
            self::PROVIDER_MANUAL => 0.7
        ];
        
        $score *= $providerReliability[$this->provider] ?? 0.8;
        
        return max(0, min(1, $score));
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        $changeData = $this->getRateChangeSinceLastUpdate();
        
        return [
            'id' => $this->id,
            'from_currency' => $this->from_currency,
            'to_currency' => $this->to_currency,
            'currency_pair' => $this->from_currency . '/' . $this->to_currency,
            'rate' => $this->rate,
            'formatted_rate' => $this->getFormattedRate(),
            'effective_rate' => $this->getEffectiveRate(),
            'formatted_effective_rate' => $this->getFormattedEffectiveRate(),
            'inverse_rate' => $this->inverse_rate,
            'provider' => $this->provider,
            'provider_label' => $this->getProviderLabel(),
            'is_active' => $this->is_active,
            'is_currently_active' => $this->isCurrentlyActive(),
            'is_expired' => $this->isExpired(),
            'is_stale' => $this->isStale(),
            'age_hours' => $this->getAgeInHours(),
            'quality_score' => $this->getQualityScore(),
            'spread_percentage' => $this->spread_percentage,
            'commission_rate' => $this->commission_rate,
            'effective_from' => $this->effective_from?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'change_data' => $changeData,
            'volatility_30d' => $this->calculateVolatility(30)
        ];
    }

    /**
     * Find best rate for currency pair
     */
    public static function findBestRate(string $fromCurrency, string $toCurrency): ?self
    {
        return self::byCurrencyPair($fromCurrency, $toCurrency)
            ->current()
            ->first();
    }

    /**
     * Get cross rate through base currency
     */
    public static function getCrossRate(string $fromCurrency, string $toCurrency, string $baseCurrency = 'USD'): ?float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }
        
        // Try direct rate first
        $directRate = self::findBestRate($fromCurrency, $toCurrency);
        if ($directRate) {
            return $directRate->getEffectiveRate();
        }
        
        // Try inverse rate
        $inverseRate = self::findBestRate($toCurrency, $fromCurrency);
        if ($inverseRate) {
            return 1 / $inverseRate->getEffectiveRate();
        }
        
        // Calculate cross rate through base currency
        $fromToBase = self::findBestRate($fromCurrency, $baseCurrency);
        $baseToTarget = self::findBestRate($baseCurrency, $toCurrency);
        
        if ($fromToBase && $baseToTarget) {
            return $fromToBase->getEffectiveRate() * $baseToTarget->getEffectiveRate();
        }
        
        return null;
    }
}