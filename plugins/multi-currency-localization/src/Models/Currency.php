<?php

declare(strict_types=1);

namespace Shopologic\Plugins\MultiCurrencyLocalization\Models;

use Shopologic\Core\Database\Model;

class Currency extends Model
{
    protected string $table = 'currencies';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'code',
        'name',
        'symbol',
        'symbol_position',
        'decimal_places',
        'decimal_separator',
        'thousands_separator',
        'rate_to_base',
        'is_base_currency',
        'is_active',
        'is_default',
        'format_template',
        'locale_code',
        'flag_icon',
        'display_order'
    ];

    protected array $casts = [
        'decimal_places' => 'integer',
        'rate_to_base' => 'float',
        'is_base_currency' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'display_order' => 'integer'
    ];

    /**
     * Get exchange rates
     */
    public function exchangeRates()
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency', 'code');
    }

    /**
     * Get price formats
     */
    public function priceFormats()
    {
        return $this->hasMany(PriceFormat::class, 'currency_code', 'code');
    }

    /**
     * Scope active currencies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope base currency
     */
    public function scopeBase($query)
    {
        return $query->where('is_base_currency', true);
    }

    /**
     * Scope default currency
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get formatted amount
     */
    public function formatAmount(float $amount): string
    {
        $formatted = number_format(
            $amount,
            $this->decimal_places,
            $this->decimal_separator,
            $this->thousands_separator
        );

        if ($this->symbol_position === 'before') {
            return $this->symbol . $formatted;
        } else {
            return $formatted . $this->symbol;
        }
    }

    /**
     * Convert amount from base currency
     */
    public function convertFromBase(float $amount): float
    {
        if ($this->is_base_currency) {
            return $amount;
        }

        return round($amount * $this->rate_to_base, $this->decimal_places);
    }

    /**
     * Convert amount to base currency
     */
    public function convertToBase(float $amount): float
    {
        if ($this->is_base_currency) {
            return $amount;
        }

        if ($this->rate_to_base == 0) {
            return 0;
        }

        return round($amount / $this->rate_to_base, 2);
    }

    /**
     * Get display name with symbol
     */
    public function getDisplayName(): string
    {
        return $this->name . ' (' . $this->symbol . ')';
    }

    /**
     * Get full display name
     */
    public function getFullDisplayName(): string
    {
        return $this->code . ' - ' . $this->name . ' (' . $this->symbol . ')';
    }

    /**
     * Check if currency needs update
     */
    public function needsRateUpdate(): bool
    {
        if ($this->is_base_currency) {
            return false;
        }

        $lastUpdate = $this->updated_at;
        $updateInterval = config('currency.update_interval', 3600); // 1 hour default

        return $lastUpdate->diffInSeconds(now()) > $updateInterval;
    }

    /**
     * Update exchange rate
     */
    public function updateRate(float $newRate): bool
    {
        if ($this->is_base_currency) {
            return false;
        }

        $this->rate_to_base = $newRate;
        return $this->save();
    }

    /**
     * Get format pattern
     */
    public function getFormatPattern(): string
    {
        if ($this->format_template) {
            return $this->format_template;
        }

        $pattern = $this->symbol_position === 'before' ? '{symbol}{amount}' : '{amount}{symbol}';
        
        return str_replace(['{symbol}', '{amount}'], [$this->symbol, '#,##0.' . str_repeat('0', $this->decimal_places)], $pattern);
    }

    /**
     * Get localized currency name
     */
    public function getLocalizedName(string $locale = null): string
    {
        // This would integrate with localization system
        return $this->name;
    }

    /**
     * Check if major currency
     */
    public function isMajorCurrency(): bool
    {
        $majorCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD'];
        return in_array($this->code, $majorCurrencies);
    }

    /**
     * Get currency statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_transactions' => $this->getTotalTransactions(),
            'total_volume' => $this->getTotalVolume(),
            'average_transaction' => $this->getAverageTransaction(),
            'last_used' => $this->getLastUsed()
        ];
    }

    /**
     * Get total transactions in this currency
     */
    private function getTotalTransactions(): int
    {
        // This would query orders/transactions table
        return 0;
    }

    /**
     * Get total volume in this currency
     */
    private function getTotalVolume(): float
    {
        // This would sum transaction amounts
        return 0.0;
    }

    /**
     * Get average transaction amount
     */
    private function getAverageTransaction(): float
    {
        $total = $this->getTotalTransactions();
        if ($total === 0) {
            return 0.0;
        }

        return $this->getTotalVolume() / $total;
    }

    /**
     * Get last used date
     */
    private function getLastUsed(): ?string
    {
        // This would query last transaction date
        return null;
    }

    /**
     * Clone currency with new code
     */
    public function cloneWithCode(string $newCode): self
    {
        $clone = $this->replicate();
        $clone->code = $newCode;
        $clone->is_base_currency = false;
        $clone->is_default = false;
        $clone->save();
        
        return $clone;
    }

    /**
     * Get supported countries
     */
    public function getSupportedCountries(): array
    {
        // Map of currency codes to countries
        $currencyCountryMap = [
            'USD' => ['US', 'EC', 'SV', 'ZW', 'TL', 'FM', 'MH', 'PW'],
            'EUR' => ['AT', 'BE', 'CY', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PT', 'SK', 'SI', 'ES'],
            'GBP' => ['GB', 'IM', 'JE', 'GG'],
            // Add more mappings as needed
        ];

        return $currencyCountryMap[$this->code] ?? [];
    }
}