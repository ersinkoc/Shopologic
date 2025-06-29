<?php

declare(strict_types=1);

namespace Shopologic\Core\I18n\Currency;

use Shopologic\Core\Database\Model;

/**
 * Currency model
 */
class Currency extends Model
{
    protected string $table = 'currencies';
    
    protected array $fillable = [
        'code',
        'name',
        'symbol',
        'symbol_position',
        'decimal_places',
        'decimal_separator',
        'thousands_separator',
        'exchange_rate',
        'is_active',
        'is_default'
    ];
    
    protected array $casts = [
        'decimal_places' => 'integer',
        'exchange_rate' => 'decimal:6',
        'is_active' => 'boolean',
        'is_default' => 'boolean'
    ];
    
    /**
     * Get default currency
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Get currency by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper($code))
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Get all active currencies
     */
    public static function getActive(): array
    {
        return static::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->all();
    }
    
    /**
     * Format amount in this currency
     */
    public function format(float $amount): string
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
     * Convert amount from this currency to another
     */
    public function convertTo(float $amount, Currency $toCurrency): float
    {
        // Convert to base currency first
        $baseAmount = $amount / $this->exchange_rate;
        
        // Convert to target currency
        return $baseAmount * $toCurrency->exchange_rate;
    }
    
    /**
     * Convert amount from another currency to this one
     */
    public function convertFrom(float $amount, Currency $fromCurrency): float
    {
        return $fromCurrency->convertTo($amount, $this);
    }
    
    /**
     * Update exchange rate
     */
    public function updateExchangeRate(float $rate): bool
    {
        $this->exchange_rate = $rate;
        return $this->save();
    }
}