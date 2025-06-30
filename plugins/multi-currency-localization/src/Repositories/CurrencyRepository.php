<?php

declare(strict_types=1);

namespace Shopologic\Plugins\MultiCurrencyLocalization\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;
use MultiCurrencyLocalization\Models\Currency;

class CurrencyRepository extends Repository
{
    protected string $table = 'currencies';
    protected string $primaryKey = 'id';
    protected string $modelClass = Currency::class;

    /**
     * Find currency by code
     */
    public function findByCode(string $code): ?Currency
    {
        return Currency::where('code', $code)->first();
    }

    /**
     * Get active currencies
     */
    public function getActiveCurrencies(): array
    {
        return Currency::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->toArray();
    }

    /**
     * Get base currency
     */
    public function getBaseCurrency(): Currency
    {
        $currency = Currency::where('is_base_currency', true)->first();
        
        if (!$currency) {
            throw new \RuntimeException('No base currency configured');
        }
        
        return $currency;
    }

    /**
     * Get default currency
     */
    public function getDefaultCurrency(): Currency
    {
        $currency = Currency::where('is_default', true)->first();
        
        if (!$currency) {
            return $this->getBaseCurrency();
        }
        
        return $currency;
    }

    /**
     * Update by code
     */
    public function updateByCode(string $code, array $data): bool
    {
        return Currency::where('code', $code)->update($data) > 0;
    }

    /**
     * Unset base currency
     */
    public function unsetBaseCurrency(): bool
    {
        return Currency::where('is_base_currency', true)
            ->update(['is_base_currency' => false]) >= 0;
    }

    /**
     * Get currencies by codes
     */
    public function getByCodes(array $codes): array
    {
        return Currency::whereIn('code', $codes)
            ->get()
            ->toArray();
    }

    /**
     * Get total count
     */
    public function getTotalCount(): int
    {
        return Currency::count();
    }

    /**
     * Get currency history
     */
    public function getCurrencyHistory(string $code, int $days = 30): array
    {
        return DB::table('exchange_rates')
            ->where('from_currency', $code)
            ->where('to_currency', $this->getBaseCurrency()->code)
            ->where('rate_date', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->orderBy('rate_date', 'desc')
            ->get();
    }

    /**
     * Get popular currencies
     */
    public function getPopularCurrencies(int $limit = 5): array
    {
        return DB::table('currency_usage_stats as s')
            ->join($this->table . ' as c', 's.currency_code', '=', 'c.code')
            ->select('c.*', 's.usage_count', 's.last_used_at')
            ->where('c.is_active', true)
            ->orderBy('s.usage_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Search currencies
     */
    public function searchCurrencies(string $query): array
    {
        return Currency::where(function($q) use ($query) {
                $q->where('code', 'LIKE', "%{$query}%")
                  ->orWhere('name', 'LIKE', "%{$query}%")
                  ->orWhere('symbol', 'LIKE', "%{$query}%");
            })
            ->orderBy('is_active', 'desc')
            ->orderBy('code')
            ->get()
            ->toArray();
    }

    /**
     * Get currencies for country
     */
    public function getCurrenciesForCountry(string $countryCode): array
    {
        $currencyMap = $this->getCountryCurrencyMap();
        
        $currencyCodes = [];
        foreach ($currencyMap as $currency => $countries) {
            if (in_array($countryCode, $countries)) {
                $currencyCodes[] = $currency;
            }
        }
        
        if (empty($currencyCodes)) {
            return [];
        }
        
        return Currency::whereIn('code', $currencyCodes)
            ->where('is_active', true)
            ->get()
            ->toArray();
    }

    /**
     * Update exchange rates
     */
    public function updateExchangeRates(array $rates): int
    {
        $updated = 0;
        
        DB::transaction(function() use ($rates, &$updated) {
            foreach ($rates as $code => $rate) {
                if ($this->updateByCode($code, ['rate_to_base' => $rate])) {
                    $updated++;
                }
            }
        });
        
        return $updated;
    }

    /**
     * Get currency pairs
     */
    public function getCurrencyPairs(): array
    {
        $currencies = $this->getActiveCurrencies();
        $pairs = [];
        
        foreach ($currencies as $from) {
            foreach ($currencies as $to) {
                if ($from['code'] !== $to['code']) {
                    $pairs[] = [
                        'from' => $from['code'],
                        'to' => $to['code'],
                        'rate' => $this->calculateCrossRate($from, $to)
                    ];
                }
            }
        }
        
        return $pairs;
    }

    /**
     * Bulk activate/deactivate
     */
    public function bulkUpdateStatus(array $codes, bool $active): int
    {
        return Currency::whereIn('code', $codes)
            ->update(['is_active' => $active]);
    }

    /**
     * Get currency statistics
     */
    public function getCurrencyStatistics(): array
    {
        return [
            'total' => $this->getTotalCount(),
            'active' => Currency::where('is_active', true)->count(),
            'base_currency' => $this->getBaseCurrency()->code,
            'default_currency' => $this->getDefaultCurrency()->code,
            'last_rate_update' => $this->getLastRateUpdate(),
            'popular_currencies' => $this->getPopularCurrencies(3)
        ];
    }

    /**
     * Calculate cross rate
     */
    private function calculateCrossRate(array $from, array $to): float
    {
        if ($from['is_base_currency']) {
            return $to['rate_to_base'];
        }
        
        if ($to['is_base_currency']) {
            return 1 / $from['rate_to_base'];
        }
        
        // Cross rate through base currency
        return $to['rate_to_base'] / $from['rate_to_base'];
    }

    /**
     * Get last rate update
     */
    private function getLastRateUpdate(): ?string
    {
        $result = Currency::where('is_base_currency', false)
            ->orderBy('updated_at', 'desc')
            ->first();
            
        return $result ? $result->updated_at->toDateTimeString() : null;
    }

    /**
     * Get country currency map
     */
    private function getCountryCurrencyMap(): array
    {
        return [
            'USD' => ['US', 'EC', 'SV', 'ZW', 'TL', 'FM', 'MH', 'PW'],
            'EUR' => ['AT', 'BE', 'CY', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PT', 'SK', 'SI', 'ES'],
            'GBP' => ['GB', 'IM', 'JE', 'GG'],
            'CAD' => ['CA'],
            'AUD' => ['AU', 'CC', 'CX', 'HM', 'KI', 'NF', 'NR', 'TV'],
            'JPY' => ['JP'],
            'CNY' => ['CN'],
            'INR' => ['IN'],
            'CHF' => ['CH', 'LI'],
            'SEK' => ['SE'],
            'NOK' => ['NO', 'BV', 'SJ'],
            'DKK' => ['DK', 'FO', 'GL'],
            'NZD' => ['NZ', 'CK', 'NU', 'PN', 'TK'],
            'SGD' => ['SG'],
            'HKD' => ['HK'],
            'KRW' => ['KR'],
            'MXN' => ['MX'],
            'BRL' => ['BR'],
            'ZAR' => ['ZA'],
            'RUB' => ['RU'],
            'TRY' => ['TR'],
            'PLN' => ['PL'],
            'THB' => ['TH'],
            'MYR' => ['MY'],
            'PHP' => ['PH'],
            'IDR' => ['ID'],
            'CZK' => ['CZ'],
            'HUF' => ['HU'],
            'ILS' => ['IL'],
            'AED' => ['AE'],
            'SAR' => ['SA'],
            'EGP' => ['EG'],
            'CLP' => ['CL'],
            'COP' => ['CO'],
            'PEN' => ['PE'],
            'ARS' => ['AR']
        ];
    }
}