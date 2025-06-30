<?php

declare(strict_types=1);

namespace Shopologic\Plugins\MultiCurrencyLocalization\Services;

use MultiCurrencyLocalization\Repositories\ExchangeRateRepository;
use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Http\HttpClient;

class ExchangeRateProvider\n{
    private ExchangeRateRepository $exchangeRateRepository;
    private CacheInterface $cache;
    private HttpClient $httpClient;
    private string $provider;
    private array $apiKeys;

    public function __construct(
        ExchangeRateRepository $exchangeRateRepository,
        string $provider = 'fixer',
        array $apiKeys = []
    ) {
        $this->exchangeRateRepository = $exchangeRateRepository;
        $this->cache = app(CacheInterface::class);
        $this->httpClient = app(HttpClient::class);
        $this->provider = $provider;
        $this->apiKeys = $apiKeys;
    }

    /**
     * Get exchange rate between two currencies
     */
    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}";
        
        return $this->cache->remember($cacheKey, 3600, function() use ($fromCurrency, $toCurrency) {
            // Try to get from database first
            $rate = $this->exchangeRateRepository->getLatestRate($fromCurrency, $toCurrency);
            
            if ($rate && $this->isRateValid($rate)) {
                return $rate['rate'];
            }
            
            // Fetch from external provider
            return $this->fetchRateFromProvider($fromCurrency, $toCurrency);
        });
    }

    /**
     * Update all exchange rates
     */
    public function updateAllRates(): void
    {
        $activeCurrencies = $this->getActiveCurrencies();
        $baseCurrency = $this->getBaseCurrency();
        
        foreach ($activeCurrencies as $currency) {
            if ($currency === $baseCurrency) {
                continue;
            }
            
            try {
                $rate = $this->fetchRateFromProvider($baseCurrency, $currency);
                $inverseRate = 1 / $rate;
                
                $this->exchangeRateRepository->create([
                    'from_currency' => $baseCurrency,
                    'to_currency' => $currency,
                    'rate' => $rate,
                    'inverse_rate' => $inverseRate,
                    'provider' => $this->provider,
                    'rate_date' => date('Y-m-d H:i:s'),
                    'is_manual' => false
                ]);
                
                // Also store inverse rate
                $this->exchangeRateRepository->create([
                    'from_currency' => $currency,
                    'to_currency' => $baseCurrency,
                    'rate' => $inverseRate,
                    'inverse_rate' => $rate,
                    'provider' => $this->provider,
                    'rate_date' => date('Y-m-d H:i:s'),
                    'is_manual' => false
                ]);
                
                // Clear cache
                $this->cache->forget("exchange_rate_{$baseCurrency}_{$currency}");
                $this->cache->forget("exchange_rate_{$currency}_{$baseCurrency}");
                
            } catch (\RuntimeException $e) {
                error_log("Failed to update exchange rate for {$currency}: " . $e->getMessage());
            }
        }
        
        // Update cross rates
        $this->updateCrossRates($activeCurrencies, $baseCurrency);
    }

    /**
     * Update specific currency rates
     */
    public function updateCurrencyRates(string $currency): void
    {
        $activeCurrencies = $this->getActiveCurrencies();
        
        foreach ($activeCurrencies as $targetCurrency) {
            if ($currency === $targetCurrency) {
                continue;
            }
            
            try {
                $rate = $this->fetchRateFromProvider($currency, $targetCurrency);
                $inverseRate = 1 / $rate;
                
                $this->exchangeRateRepository->create([
                    'from_currency' => $currency,
                    'to_currency' => $targetCurrency,
                    'rate' => $rate,
                    'inverse_rate' => $inverseRate,
                    'provider' => $this->provider,
                    'rate_date' => date('Y-m-d H:i:s'),
                    'is_manual' => false
                ]);
                
                // Clear cache
                $this->cache->forget("exchange_rate_{$currency}_{$targetCurrency}");
                
            } catch (\RuntimeException $e) {
                error_log("Failed to update exchange rate {$currency} to {$targetCurrency}: " . $e->getMessage());
            }
        }
    }

    /**
     * Set manual exchange rate
     */
    public function setManualRate(string $fromCurrency, string $toCurrency, float $rate): bool
    {
        $inverseRate = 1 / $rate;
        
        $result = $this->exchangeRateRepository->create([
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'rate' => $rate,
            'inverse_rate' => $inverseRate,
            'provider' => 'manual',
            'rate_date' => date('Y-m-d H:i:s'),
            'is_manual' => true
        ]);
        
        if ($result) {
            // Clear cache
            $this->cache->forget("exchange_rate_{$fromCurrency}_{$toCurrency}");
            return true;
        }
        
        return false;
    }

    /**
     * Get historical rates
     */
    public function getHistoricalRates(string $fromCurrency, string $toCurrency, int $days = 30): array
    {
        return $this->exchangeRateRepository->getHistoricalRates($fromCurrency, $toCurrency, $days);
    }

    /**
     * Get rate volatility
     */
    public function getRateVolatility(string $fromCurrency, string $toCurrency, int $days = 30): float
    {
        $rates = $this->getHistoricalRates($fromCurrency, $toCurrency, $days);
        
        if (count($rates) < 2) {
            return 0.0;
        }
        
        $values = array_column($rates, 'rate');
        $mean = array_sum($values) / count($values);
        
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $variance /= count($values);
        $standardDeviation = sqrt($variance);
        
        return ($standardDeviation / $mean) * 100;
    }

    /**
     * Get rate trend
     */
    public function getRateTrend(string $fromCurrency, string $toCurrency, int $days = 7): string
    {
        $rates = $this->getHistoricalRates($fromCurrency, $toCurrency, $days);
        
        if (count($rates) < 2) {
            return 'stable';
        }
        
        $firstRate = $rates[count($rates) - 1]['rate'];
        $lastRate = $rates[0]['rate'];
        
        $change = (($lastRate - $firstRate) / $firstRate) * 100;
        
        if ($change > 1) {
            return 'up';
        } elseif ($change < -1) {
            return 'down';
        } else {
            return 'stable';
        }
    }

    /**
     * Get supported providers
     */
    public function getSupportedProviders(): array
    {
        return [
            'fixer' => [
                'name' => 'Fixer.io',
                'url' => 'https://fixer.io/',
                'requires_api_key' => true,
                'free_tier' => true,
                'update_frequency' => 'hourly'
            ],
            'openexchangerates' => [
                'name' => 'Open Exchange Rates',
                'url' => 'https://openexchangerates.org/',
                'requires_api_key' => true,
                'free_tier' => true,
                'update_frequency' => 'hourly'
            ],
            'currencylayer' => [
                'name' => 'CurrencyLayer',
                'url' => 'https://currencylayer.com/',
                'requires_api_key' => true,
                'free_tier' => true,
                'update_frequency' => 'hourly'
            ],
            'exchangerate-api' => [
                'name' => 'ExchangeRate-API',
                'url' => 'https://exchangerate-api.com/',
                'requires_api_key' => true,
                'free_tier' => true,
                'update_frequency' => 'daily'
            ],
            'manual' => [
                'name' => 'Manual Entry',
                'url' => null,
                'requires_api_key' => false,
                'free_tier' => true,
                'update_frequency' => 'manual'
            ]
        ];
    }

    /**
     * Test provider connection
     */
    public function testProvider(string $provider = null): bool
    {
        $provider = $provider ?? $this->provider;
        
        try {
            $this->fetchRateFromProvider('USD', 'EUR', $provider);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Get provider statistics
     */
    public function getProviderStatistics(): array
    {
        return [
            'current_provider' => $this->provider,
            'last_update' => $this->exchangeRateRepository->getLastUpdateTime(),
            'total_rates' => $this->exchangeRateRepository->getTotalRatesCount(),
            'success_rate' => $this->getProviderSuccessRate(),
            'average_response_time' => $this->getAverageResponseTime()
        ];
    }

    /**
     * Switch provider
     */
    public function switchProvider(string $provider, array $config = []): bool
    {
        $supportedProviders = array_keys($this->getSupportedProviders());
        
        if (!in_array($provider, $supportedProviders)) {
            throw new \InvalidArgumentException("Unsupported provider: {$provider}");
        }
        
        // Test new provider
        if ($provider !== 'manual' && !$this->testProvider($provider)) {
            throw new \Exception("Cannot connect to provider: {$provider}");
        }
        
        $this->provider = $provider;
        
        if (isset($config['api_key'])) {
            $this->apiKeys[$provider] = $config['api_key'];
        }
        
        return true;
    }

    /**
     * Fetch rate from external provider
     */
    private function fetchRateFromProvider(string $fromCurrency, string $toCurrency, string $provider = null): float
    {
        $provider = $provider ?? $this->provider;
        
        switch ($provider) {
            case 'fixer':
                return $this->fetchFromFixer($fromCurrency, $toCurrency);
            case 'openexchangerates':
                return $this->fetchFromOpenExchangeRates($fromCurrency, $toCurrency);
            case 'currencylayer':
                return $this->fetchFromCurrencyLayer($fromCurrency, $toCurrency);
            case 'exchangerate-api':
                return $this->fetchFromExchangeRateApi($fromCurrency, $toCurrency);
            default:
                throw new \InvalidArgumentException("Unsupported provider: {$provider}");
        }
    }

    /**
     * Fetch from Fixer.io
     */
    private function fetchFromFixer(string $fromCurrency, string $toCurrency): float
    {
        $apiKey = $this->apiKeys['fixer'] ?? '';
        if (empty($apiKey)) {
            throw new \Exception('Fixer.io API key not configured');
        }
        
        $url = "http://data.fixer.io/api/latest?access_key={$apiKey}&base={$fromCurrency}&symbols={$toCurrency}";
        
        $response = $this->httpClient->get($url);
        $data = json_decode($response, true);
        
        if (!$data['success']) {
            throw new \Exception('Fixer.io API error: ' . ($data['error']['info'] ?? 'Unknown error'));
        }
        
        return $data['rates'][$toCurrency];
    }

    /**
     * Fetch from Open Exchange Rates
     */
    private function fetchFromOpenExchangeRates(string $fromCurrency, string $toCurrency): float
    {
        $apiKey = $this->apiKeys['openexchangerates'] ?? '';
        if (empty($apiKey)) {
            throw new \Exception('Open Exchange Rates API key not configured');
        }
        
        $url = "https://openexchangerates.org/api/latest.json?app_id={$apiKey}&base={$fromCurrency}&symbols={$toCurrency}";
        
        $response = $this->httpClient->get($url);
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            throw new \Exception('Open Exchange Rates API error: ' . $data['description']);
        }
        
        return $data['rates'][$toCurrency];
    }

    /**
     * Fetch from CurrencyLayer
     */
    private function fetchFromCurrencyLayer(string $fromCurrency, string $toCurrency): float
    {
        $apiKey = $this->apiKeys['currencylayer'] ?? '';
        if (empty($apiKey)) {
            throw new \Exception('CurrencyLayer API key not configured');
        }
        
        $url = "http://api.currencylayer.com/live?access_key={$apiKey}&source={$fromCurrency}&currencies={$toCurrency}";
        
        $response = $this->httpClient->get($url);
        $data = json_decode($response, true);
        
        if (!$data['success']) {
            throw new \Exception('CurrencyLayer API error: ' . ($data['error']['info'] ?? 'Unknown error'));
        }
        
        $key = $fromCurrency . $toCurrency;
        return $data['quotes'][$key];
    }

    /**
     * Fetch from ExchangeRate-API
     */
    private function fetchFromExchangeRateApi(string $fromCurrency, string $toCurrency): float
    {
        $url = "https://api.exchangerate-api.com/v4/latest/{$fromCurrency}";
        
        $response = $this->httpClient->get($url);
        $data = json_decode($response, true);
        
        if (!isset($data['rates'][$toCurrency])) {
            throw new \Exception('ExchangeRate-API error: Currency not found');
        }
        
        return $data['rates'][$toCurrency];
    }

    /**
     * Update cross rates between non-base currencies
     */
    private function updateCrossRates(array $currencies, string $baseCurrency): void
    {
        foreach ($currencies as $fromCurrency) {
            if ($fromCurrency === $baseCurrency) {
                continue;
            }
            
            foreach ($currencies as $toCurrency) {
                if ($fromCurrency === $toCurrency || $toCurrency === $baseCurrency) {
                    continue;
                }
                
                // Calculate cross rate via base currency
                $fromToBase = $this->getRate($fromCurrency, $baseCurrency);
                $baseToTo = $this->getRate($baseCurrency, $toCurrency);
                $crossRate = $fromToBase * $baseToTo;
                
                $this->exchangeRateRepository->create([
                    'from_currency' => $fromCurrency,
                    'to_currency' => $toCurrency,
                    'rate' => $crossRate,
                    'inverse_rate' => 1 / $crossRate,
                    'provider' => $this->provider . '_cross',
                    'rate_date' => date('Y-m-d H:i:s'),
                    'is_manual' => false
                ]);
            }
        }
    }

    /**
     * Check if rate is valid (not too old)
     */
    private function isRateValid(array $rate): bool
    {
        $maxAge = 3600; // 1 hour
        $rateTime = strtotime($rate['rate_date']);
        
        return (time() - $rateTime) < $maxAge;
    }

    /**
     * Get active currencies
     */
    private function getActiveCurrencies(): array
    {
        $currencyManager = app(CurrencyManager::class);
        $currencies = $currencyManager->getActiveCurrencies();
        
        return array_column($currencies, 'code');
    }

    /**
     * Get base currency
     */
    private function getBaseCurrency(): string
    {
        $currencyManager = app(CurrencyManager::class);
        return $currencyManager->getBaseCurrency()->getCode();
    }

    /**
     * Get provider success rate
     */
    private function getProviderSuccessRate(): float
    {
        // This would require tracking success/failure rates
        // For now, return a default value
        return 95.0;
    }

    /**
     * Get average response time
     */
    private function getAverageResponseTime(): float
    {
        // This would require tracking response times
        // For now, return a default value
        return 250.0; // milliseconds
    }
}