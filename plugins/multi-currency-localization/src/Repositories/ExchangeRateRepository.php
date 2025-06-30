<?php

declare(strict_types=1);

namespace Shopologic\Plugins\MultiCurrencyLocalization\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;

class ExchangeRateRepository extends Repository
{
    protected string $table = 'exchange_rates';
    protected string $primaryKey = 'id';

    /**
     * Get latest rate between currencies
     */
    public function getLatestRate(string $fromCurrency, string $toCurrency): ?array
    {
        return DB::table($this->table)
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->orderBy('rate_date', 'desc')
            ->first();
    }

    /**
     * Get historical rates
     */
    public function getHistoricalRates(string $fromCurrency, string $toCurrency, int $days = 30): array
    {
        return DB::table($this->table)
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('rate_date', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->orderBy('rate_date', 'desc')
            ->get();
    }

    /**
     * Get rates for date
     */
    public function getRatesForDate(string $date, string $baseCurrency = null): array
    {
        $query = DB::table($this->table)
            ->whereDate('rate_date', $date);

        if ($baseCurrency) {
            $query->where('from_currency', $baseCurrency);
        }

        return $query->get();
    }

    /**
     * Get available rates count
     */
    public function getAvailableRatesCount(): int
    {
        return DB::table($this->table)
            ->select('from_currency', 'to_currency')
            ->distinct()
            ->count();
    }

    /**
     * Get provider statistics
     */
    public function getProviderStatistics(string $provider): array
    {
        $stats = DB::table($this->table)
            ->where('provider', $provider)
            ->select(
                DB::raw('COUNT(*) as total_rates'),
                DB::raw('COUNT(DISTINCT from_currency) as currencies_count'),
                DB::raw('MAX(rate_date) as last_update'),
                DB::raw('AVG(CASE WHEN is_manual = 0 THEN 1 ELSE 0 END) * 100 as auto_rate_percentage')
            )
            ->first();

        return (array)$stats;
    }

    /**
     * Delete old records
     */
    public function deleteOldRecords(string $cutoffDate): int
    {
        return DB::table($this->table)
            ->where('rate_date', '<', $cutoffDate)
            ->where('is_manual', false)
            ->delete();
    }

    /**
     * Get rate volatility
     */
    public function getRateVolatility(string $fromCurrency, string $toCurrency, int $days = 30): array
    {
        $rates = $this->getHistoricalRates($fromCurrency, $toCurrency, $days);
        
        if (count($rates) < 2) {
            return [
                'volatility' => 0,
                'min_rate' => 0,
                'max_rate' => 0,
                'avg_rate' => 0,
                'std_dev' => 0
            ];
        }

        $values = array_column($rates, 'rate');
        $min = min($values);
        $max = max($values);
        $avg = array_sum($values) / count($values);
        
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $avg, 2);
        }
        $variance /= count($values);
        $stdDev = sqrt($variance);
        
        return [
            'volatility' => ($stdDev / $avg) * 100,
            'min_rate' => $min,
            'max_rate' => $max,
            'avg_rate' => $avg,
            'std_dev' => $stdDev
        ];
    }

    /**
     * Get rate trends
     */
    public function getRateTrends(string $fromCurrency, string $toCurrency, string $period = 'daily'): array
    {
        $groupBy = match($period) {
            'hourly' => "DATE_FORMAT(rate_date, '%Y-%m-%d %H:00:00')",
            'daily' => "DATE(rate_date)",
            'weekly' => "DATE(DATE_SUB(rate_date, INTERVAL WEEKDAY(rate_date) DAY))",
            'monthly' => "DATE_FORMAT(rate_date, '%Y-%m-01')",
            default => "DATE(rate_date)"
        };

        return DB::table($this->table)
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->select(
                DB::raw("{$groupBy} as period"),
                DB::raw('AVG(rate) as avg_rate'),
                DB::raw('MIN(rate) as min_rate'),
                DB::raw('MAX(rate) as max_rate'),
                DB::raw('COUNT(*) as data_points')
            )
            ->groupBy('period')
            ->orderBy('period', 'desc')
            ->limit(100)
            ->get();
    }

    /**
     * Get cross rates
     */
    public function getCrossRates(string $baseCurrency): array
    {
        $directRates = DB::table($this->table . ' as r1')
            ->join(DB::raw("(SELECT from_currency, to_currency, MAX(rate_date) as max_date FROM {$this->table} GROUP BY from_currency, to_currency) as latest"), function($join) {
                $join->on('r1.from_currency', '=', 'latest.from_currency')
                     ->on('r1.to_currency', '=', 'latest.to_currency')
                     ->on('r1.rate_date', '=', 'latest.max_date');
            })
            ->where('r1.from_currency', $baseCurrency)
            ->select('r1.to_currency as currency', 'r1.rate')
            ->get();

        $rates = [];
        foreach ($directRates as $rate) {
            $rates[$rate->currency] = $rate->rate;
        }

        // Calculate cross rates
        $crossRates = [];
        foreach ($rates as $currency1 => $rate1) {
            foreach ($rates as $currency2 => $rate2) {
                if ($currency1 !== $currency2) {
                    $crossRates["{$currency1}/{$currency2}"] = $rate2 / $rate1;
                }
            }
        }

        return $crossRates;
    }

    /**
     * Get missing rates
     */
    public function getMissingRates(array $requiredPairs): array
    {
        $missing = [];
        
        foreach ($requiredPairs as $pair) {
            $rate = $this->getLatestRate($pair['from'], $pair['to']);
            
            if (!$rate || (time() - strtotime($rate['rate_date'])) > 86400) {
                $missing[] = $pair;
            }
        }
        
        return $missing;
    }

    /**
     * Get rate comparison
     */
    public function getRateComparison(string $fromCurrency, array $toCurrencies, string $date = null): array
    {
        $query = DB::table($this->table)
            ->where('from_currency', $fromCurrency)
            ->whereIn('to_currency', $toCurrencies);

        if ($date) {
            $query->whereDate('rate_date', $date);
        } else {
            $query->whereRaw('(from_currency, to_currency, rate_date) IN (
                SELECT from_currency, to_currency, MAX(rate_date) 
                FROM ' . $this->table . ' 
                GROUP BY from_currency, to_currency
            )');
        }

        return $query->get();
    }

    /**
     * Get provider performance
     */
    public function getProviderPerformance(int $days = 7): array
    {
        return DB::table($this->table)
            ->where('rate_date', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->select(
                'provider',
                DB::raw('COUNT(*) as total_updates'),
                DB::raw('COUNT(DISTINCT from_currency) as currencies_covered'),
                DB::raw('AVG(CASE WHEN is_manual = 0 THEN 1 ELSE 0 END) * 100 as automation_rate')
            )
            ->groupBy('provider')
            ->get();
    }

    /**
     * Get rate by ID
     */
    public function findById(int $id): ?array
    {
        return DB::table($this->table)->where('id', $id)->first();
    }

    /**
     * Get last update time
     */
    public function getLastUpdateTime(): ?string
    {
        $result = DB::table($this->table)
            ->orderBy('created_at', 'desc')
            ->first();
            
        return $result ? $result['created_at'] : null;
    }

    /**
     * Get total rates count
     */
    public function getTotalRatesCount(): int
    {
        return DB::table($this->table)->count();
    }

    /**
     * Get provider-specific rates
     */
    public function getProviderRates(string $provider, string $date = null): array
    {
        $query = DB::table($this->table)
            ->where('provider', $provider);

        if ($date) {
            $query->whereDate('rate_date', $date);
        } else {
            $query->whereDate('rate_date', date('Y-m-d'));
        }

        return $query->get();
    }
}