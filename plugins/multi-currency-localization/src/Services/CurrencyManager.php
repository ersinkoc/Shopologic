<?php

declare(strict_types=1);

namespace Shopologic\Plugins\MultiCurrencyLocalization\Services;

use MultiCurrencyLocalization\Repositories\CurrencyRepository;
use MultiCurrencyLocalization\Repositories\ExchangeRateRepository;
use MultiCurrencyLocalization\Models\Currency;
use Shopologic\Core\Cache\CacheInterface;

class CurrencyManager\n{
    private CurrencyRepository $currencyRepository;
    private ExchangeRateRepository $exchangeRateRepository;
    private CacheInterface $cache;
    private array $config;
    private ?string $currentCurrency = null;
    private ?Currency $baseCurrency = null;

    public function __construct(
        CurrencyRepository $currencyRepository,
        ExchangeRateRepository $exchangeRateRepository,
        array $config = []
    ) {
        $this->currencyRepository = $currencyRepository;
        $this->exchangeRateRepository = $exchangeRateRepository;
        $this->config = $config;
        $this->cache = app(CacheInterface::class);
    }

    /**
     * Create a new currency
     */
    public function createCurrency(array $data): Currency
    {
        // Validate currency data
        $this->validateCurrencyData($data);
        
        // If this is set as base currency, unset others
        if ($data['is_base_currency'] ?? false) {
            $this->currencyRepository->unsetBaseCurrency();
        }
        
        $currency = $this->currencyRepository->create($data);
        
        // Clear cache
        $this->clearCurrencyCache();
        
        return $currency;
    }

    /**
     * Update currency
     */
    public function updateCurrency(string $code, array $data): bool
    {
        $this->validateCurrencyData($data, $code);
        
        // If this is set as base currency, unset others
        if ($data['is_base_currency'] ?? false) {
            $this->currencyRepository->unsetBaseCurrency();
        }
        
        $result = $this->currencyRepository->updateByCode($code, $data);
        
        if ($result) {
            $this->clearCurrencyCache();
        }
        
        return $result;
    }

    /**
     * Get currency by code
     */
    public function getCurrency(string $code): ?Currency
    {
        return $this->currencyRepository->findByCode($code);
    }

    /**
     * Get all active currencies
     */
    public function getActiveCurrencies(): array
    {
        return $this->cache->remember('active_currencies', 3600, function() {
            return $this->currencyRepository->getActiveCurrencies();
        });
    }

    /**
     * Get base currency
     */
    public function getBaseCurrency(): Currency
    {
        if ($this->baseCurrency === null) {
            $this->baseCurrency = $this->cache->remember('base_currency', 3600, function() {
                return $this->currencyRepository->getBaseCurrency();
            });
        }
        
        return $this->baseCurrency;
    }

    /**
     * Set base currency
     */
    public function setBaseCurrency(string $code): bool
    {
        $currency = $this->getCurrency($code);
        if (!$currency) {
            return false;
        }
        
        // Unset current base currency
        $this->currencyRepository->unsetBaseCurrency();
        
        // Set new base currency
        $result = $this->currencyRepository->updateByCode($code, ['is_base_currency' => true]);
        
        if ($result) {
            $this->baseCurrency = null;
            $this->clearCurrencyCache();
        }
        
        return $result;
    }

    /**
     * Get current currency
     */
    public function getCurrentCurrency(): string
    {
        if ($this->currentCurrency === null) {
            $this->currentCurrency = $this->detectCurrentCurrency();
        }
        
        return $this->currentCurrency;
    }

    /**
     * Set current currency
     */
    public function setCurrentCurrency(string $code): bool
    {
        if (!$this->isActiveCurrency($code)) {
            return false;
        }
        
        $this->currentCurrency = $code;
        
        // Store in session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['current_currency'] = $code;
        }
        
        // Store in cookie
        setcookie('current_currency', $code, time() + (30 * 24 * 60 * 60), '/');
        
        return true;
    }

    /**
     * Check if currency is active
     */
    public function isActiveCurrency(string $code): bool
    {
        $activeCurrencies = $this->getActiveCurrencies();
        
        foreach ($activeCurrencies as $currency) {
            if ($currency['code'] === $code) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Detect currency by location
     */
    public function detectCurrencyByLocation(array $location): ?string
    {
        $countryCode = $location['country_code'] ?? null;
        if (!$countryCode) {
            return null;
        }
        
        // Use country-to-currency mapping
        $currencyMap = $this->getCountryCurrencyMap();
        return $currencyMap[$countryCode] ?? null;
    }

    /**
     * Format price according to currency settings
     */
    public function formatPrice(float $amount, string $currencyCode, string $locale = null): string
    {
        $currency = $this->getCurrency($currencyCode);
        if (!$currency) {
            return (string)$amount;
        }
        
        // Round to currency precision
        $precision = $currency->getDecimalPlaces();
        $amount = round($amount, $precision);
        
        // Format number
        $formattedAmount = number_format(
            $amount,
            $precision,
            $currency->getDecimalSeparator(),
            $currency->getThousandsSeparator()
        );
        
        // Add currency symbol
        $symbol = $currency->getSymbol();
        
        if ($currency->getSymbolPosition() === 'before') {
            return $symbol . $formattedAmount;
        } else {
            return $formattedAmount . $symbol;
        }
    }

    /**
     * Get supported currencies list
     */
    public function getSupportedCurrencies(): array
    {
        return [
            'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
            'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
            'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$'],
            'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
            'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF'],
            'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥'],
            'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr'],
            'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
            'MXN' => ['name' => 'Mexican Peso', 'symbol' => '$'],
            'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$'],
            'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$'],
            'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr'],
            'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩'],
            'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺'],
            'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽'],
            'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
            'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$'],
            'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
            'PLN' => ['name' => 'Polish Zloty', 'symbol' => 'zł'],
            'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr'],
            'CZK' => ['name' => 'Czech Koruna', 'symbol' => 'Kč'],
            'HUF' => ['name' => 'Hungarian Forint', 'symbol' => 'Ft'],
            'ILS' => ['name' => 'Israeli Shekel', 'symbol' => '₪'],
            'CLP' => ['name' => 'Chilean Peso', 'symbol' => '$'],
            'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱'],
            'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ'],
            'COP' => ['name' => 'Colombian Peso', 'symbol' => '$'],
            'PEN' => ['name' => 'Peruvian Sol', 'symbol' => 'S/'],
            'EGP' => ['name' => 'Egyptian Pound', 'symbol' => '£'],
            'THB' => ['name' => 'Thai Baht', 'symbol' => '฿'],
            'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
            'RON' => ['name' => 'Romanian Leu', 'symbol' => 'lei'],
            'BGN' => ['name' => 'Bulgarian Lev', 'symbol' => 'лв'],
            'HRK' => ['name' => 'Croatian Kuna', 'symbol' => 'kn'],
            'ISK' => ['name' => 'Icelandic Krona', 'symbol' => 'kr'],
            'UYU' => ['name' => 'Uruguayan Peso', 'symbol' => '$'],
            'QAR' => ['name' => 'Qatari Riyal', 'symbol' => '﷼'],
            'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼']
        ];
    }

    /**
     * Get currency statistics
     */
    public function getCurrencyStatistics(): array
    {
        return [
            'total_currencies' => $this->currencyRepository->getTotalCount(),
            'active_currencies' => count($this->getActiveCurrencies()),
            'base_currency' => $this->getBaseCurrency()->getCode(),
            'most_used_currency' => $this->getMostUsedCurrency(),
            'exchange_rate_coverage' => $this->getExchangeRateCoverage()
        ];
    }

    /**
     * Activate currency
     */
    public function activateCurrency(string $code): bool
    {
        $result = $this->currencyRepository->updateByCode($code, ['is_active' => true]);
        
        if ($result) {
            $this->clearCurrencyCache();
        }
        
        return $result;
    }

    /**
     * Deactivate currency
     */
    public function deactivateCurrency(string $code): bool
    {
        // Prevent deactivating base currency
        $baseCurrency = $this->getBaseCurrency();
        if ($baseCurrency->getCode() === $code) {
            throw new \InvalidArgumentException('Cannot deactivate base currency');
        }
        
        $result = $this->currencyRepository->updateByCode($code, ['is_active' => false]);
        
        if ($result) {
            $this->clearCurrencyCache();
        }
        
        return $result;
    }

    /**
     * Get currency conversion history
     */
    public function getCurrencyHistory(string $code, int $days = 30): array
    {
        return $this->currencyRepository->getCurrencyHistory($code, $days);
    }

    /**
     * Get currency volatility
     */
    public function getCurrencyVolatility(string $code, int $days = 30): float
    {
        $history = $this->getCurrencyHistory($code, $days);
        
        if (count($history) < 2) {
            return 0.0;
        }
        
        $rates = array_column($history, 'rate_to_base');
        $mean = array_sum($rates) / count($rates);
        
        $variance = 0;
        foreach ($rates as $rate) {
            $variance += pow($rate - $mean, 2);
        }
        
        $variance /= count($rates);
        $standardDeviation = sqrt($variance);
        
        // Return volatility as percentage
        return ($standardDeviation / $mean) * 100;
    }

    /**
     * Bulk update currencies
     */
    public function bulkUpdateCurrencies(array $updates): array
    {
        $results = [];
        
        foreach ($updates as $code => $data) {
            try {
                $results[$code] = $this->updateCurrency($code, $data);
            } catch (\RuntimeException $e) {
                $results[$code] = false;
            }
        }
        
        return $results;
    }

    /**
     * Import currencies from external source
     */
    public function importCurrencies(array $currencies): array
    {
        $imported = [];
        $errors = [];
        
        foreach ($currencies as $currencyData) {
            try {
                $currency = $this->createCurrency($currencyData);
                $imported[] = $currency->getCode();
            } catch (\RuntimeException $e) {
                $errors[] = [
                    'code' => $currencyData['code'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }

    /**
     * Export currencies
     */
    public function exportCurrencies(array $codes = []): array
    {
        if (empty($codes)) {
            return $this->currencyRepository->getAll();
        }
        
        return $this->currencyRepository->getByCodes($codes);
    }

    /**
     * Get default currency for country
     */
    public function getDefaultCurrencyForCountry(string $countryCode): ?string
    {
        $currencyMap = $this->getCountryCurrencyMap();
        return $currencyMap[$countryCode] ?? null;
    }

    /**
     * Validate currency data
     */
    private function validateCurrencyData(array $data, string $existingCode = null): void
    {
        // Required fields
        $required = ['code', 'name', 'symbol'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
        
        // Currency code validation
        if (!preg_match('/^[A-Z]{3}$/', $data['code'])) {
            throw new \InvalidArgumentException('Currency code must be 3 uppercase letters');
        }
        
        // Check for duplicate codes (unless updating existing)
        if ($existingCode !== $data['code'] && $this->getCurrency($data['code'])) {
            throw new \InvalidArgumentException('Currency code already exists');
        }
        
        // Decimal places validation
        if (isset($data['decimal_places']) && ($data['decimal_places'] < 0 || $data['decimal_places'] > 4)) {
            throw new \InvalidArgumentException('Decimal places must be between 0 and 4');
        }
    }

    /**
     * Detect current currency
     */
    private function detectCurrentCurrency(): string
    {
        // Check session
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['current_currency'])) {
            $currency = $_SESSION['current_currency'];
            if ($this->isActiveCurrency($currency)) {
                return $currency;
            }
        }
        
        // Check cookie
        if (isset($_COOKIE['current_currency'])) {
            $currency = $_COOKIE['current_currency'];
            if ($this->isActiveCurrency($currency)) {
                return $currency;
            }
        }
        
        // Auto-detect by location if enabled
        if ($this->config['auto_detect_currency'] ?? true) {
            $geolocation = app(GeolocationService::class);
            $location = $geolocation->getCurrentLocation();
            
            if ($location) {
                $detectedCurrency = $this->detectCurrencyByLocation($location);
                if ($detectedCurrency && $this->isActiveCurrency($detectedCurrency)) {
                    return $detectedCurrency;
                }
            }
        }
        
        // Return base currency
        return $this->getBaseCurrency()->getCode();
    }

    /**
     * Clear currency cache
     */
    private function clearCurrencyCache(): void
    {
        $this->cache->forget('active_currencies');
        $this->cache->forget('base_currency');
        $this->baseCurrency = null;
    }

    /**
     * Get most used currency
     */
    private function getMostUsedCurrency(): string
    {
        // This would require tracking currency usage
        // For now, return the current currency
        return $this->getCurrentCurrency();
    }

    /**
     * Get exchange rate coverage
     */
    private function getExchangeRateCoverage(): float
    {
        $activeCurrencies = $this->getActiveCurrencies();
        $totalPossibleRates = count($activeCurrencies) * (count($activeCurrencies) - 1);
        
        if ($totalPossibleRates === 0) {
            return 100.0;
        }
        
        $availableRates = $this->exchangeRateRepository->getAvailableRatesCount();
        
        return ($availableRates / $totalPossibleRates) * 100;
    }

    /**
     * Get country to currency mapping
     */
    private function getCountryCurrencyMap(): array
    {
        return [
            'US' => 'USD', 'CA' => 'CAD', 'GB' => 'GBP', 'AU' => 'AUD', 'NZ' => 'NZD',
            'JP' => 'JPY', 'KR' => 'KRW', 'CN' => 'CNY', 'IN' => 'INR', 'TH' => 'THB',
            'MY' => 'MYR', 'SG' => 'SGD', 'HK' => 'HKD', 'PH' => 'PHP', 'ID' => 'IDR',
            'VN' => 'VND', 'TW' => 'TWD', 'KH' => 'KHR', 'LA' => 'LAK', 'MM' => 'MMK',
            'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR', 'NL' => 'EUR',
            'BE' => 'EUR', 'AT' => 'EUR', 'PT' => 'EUR', 'IE' => 'EUR', 'GR' => 'EUR',
            'FI' => 'EUR', 'EE' => 'EUR', 'LV' => 'EUR', 'LT' => 'EUR', 'SK' => 'EUR',
            'SI' => 'EUR', 'MT' => 'EUR', 'CY' => 'EUR', 'LU' => 'EUR', 'MC' => 'EUR',
            'CH' => 'CHF', 'NO' => 'NOK', 'SE' => 'SEK', 'DK' => 'DKK', 'IS' => 'ISK',
            'PL' => 'PLN', 'CZ' => 'CZK', 'HU' => 'HUF', 'RO' => 'RON', 'BG' => 'BGN',
            'HR' => 'HRK', 'RS' => 'RSD', 'BA' => 'BAM', 'MK' => 'MKD', 'AL' => 'ALL',
            'RU' => 'RUB', 'UA' => 'UAH', 'BY' => 'BYN', 'MD' => 'MDL', 'GE' => 'GEL',
            'AM' => 'AMD', 'AZ' => 'AZN', 'KZ' => 'KZT', 'UZ' => 'UZS', 'KG' => 'KGS',
            'TJ' => 'TJS', 'TM' => 'TMT', 'MN' => 'MNT', 'AF' => 'AFN', 'PK' => 'PKR',
            'BD' => 'BDT', 'LK' => 'LKR', 'NP' => 'NPR', 'BT' => 'BTN', 'MV' => 'MVR',
            'BR' => 'BRL', 'MX' => 'MXN', 'AR' => 'ARS', 'CL' => 'CLP', 'CO' => 'COP',
            'PE' => 'PEN', 'VE' => 'VES', 'UY' => 'UYU', 'PY' => 'PYG', 'BO' => 'BOB',
            'EC' => 'USD', 'GY' => 'GYD', 'SR' => 'SRD', 'CR' => 'CRC', 'GT' => 'GTQ',
            'HN' => 'HNL', 'NI' => 'NIO', 'PA' => 'PAB', 'BZ' => 'BZD', 'SV' => 'USD',
            'DO' => 'DOP', 'HT' => 'HTG', 'JM' => 'JMD', 'TT' => 'TTD', 'BB' => 'BBD',
            'ZA' => 'ZAR', 'NG' => 'NGN', 'EG' => 'EGP', 'KE' => 'KES', 'GH' => 'GHS',
            'MA' => 'MAD', 'TN' => 'TND', 'DZ' => 'DZD', 'LY' => 'LYD', 'SD' => 'SDG',
            'ET' => 'ETB', 'UG' => 'UGX', 'TZ' => 'TZS', 'RW' => 'RWF', 'BF' => 'XOF',
            'TR' => 'TRY', 'IL' => 'ILS', 'SA' => 'SAR', 'AE' => 'AED', 'QA' => 'QAR',
            'KW' => 'KWD', 'BH' => 'BHD', 'OM' => 'OMR', 'JO' => 'JOD', 'LB' => 'LBP',
            'SY' => 'SYP', 'IQ' => 'IQD', 'IR' => 'IRR', 'YE' => 'YER'
        ];
    }
}