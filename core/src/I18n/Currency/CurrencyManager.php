<?php

declare(strict_types=1);

namespace Shopologic\Core\I18n\Currency;

use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\Session\SessionManager;

/**
 * Manages currency operations and conversions
 */
class CurrencyManager
{
    private CacheInterface $cache;
    private EventDispatcherInterface $eventDispatcher;
    private SessionManager $session;
    private ?Currency $currentCurrency = null;
    private array $config;
    private array $currencyCache = [];

    public function __construct(
        CacheInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        SessionManager $session,
        array $config = []
    ) {
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->session = $session;
        $this->config = array_merge([
            'auto_update_rates' => false,
            'update_interval' => 86400, // 24 hours
            'exchange_rate_provider' => null,
            'cache_ttl' => 3600
        ], $config);
        
        $this->initializeCurrency();
    }

    /**
     * Get current currency
     */
    public function getCurrentCurrency(): Currency
    {
        if (!$this->currentCurrency) {
            $this->initializeCurrency();
        }
        
        return $this->currentCurrency;
    }

    /**
     * Set current currency
     */
    public function setCurrentCurrency(string $code): bool
    {
        $currency = Currency::findByCode($code);
        
        if (!$currency) {
            return false;
        }
        
        $oldCurrency = $this->currentCurrency;
        $this->currentCurrency = $currency;
        
        // Store in session
        $this->session->set('currency', $code);
        
        // Trigger event
        $this->eventDispatcher->dispatch('currency.changed', [
            'old_currency' => $oldCurrency,
            'new_currency' => $currency
        ]);
        
        return true;
    }

    /**
     * Get all active currencies
     */
    public function getActiveCurrencies(): array
    {
        $cacheKey = 'active_currencies';
        
        if (isset($this->currencyCache[$cacheKey])) {
            return $this->currencyCache[$cacheKey];
        }
        
        $currencies = $this->cache->remember($cacheKey, $this->config['cache_ttl'], function () {
            return Currency::getActive();
        });
        
        $this->currencyCache[$cacheKey] = $currencies;
        
        return $currencies;
    }

    /**
     * Format amount in current currency
     */
    public function format(float $amount, ?string $currencyCode = null): string
    {
        if ($currencyCode) {
            $currency = Currency::findByCode($currencyCode);
            if (!$currency) {
                $currency = $this->getCurrentCurrency();
            }
        } else {
            $currency = $this->getCurrentCurrency();
        }
        
        return $currency->format($amount);
    }

    /**
     * Convert amount between currencies
     */
    public function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }
        
        $fromCurrency = Currency::findByCode($from);
        $toCurrency = Currency::findByCode($to);
        
        if (!$fromCurrency || !$toCurrency) {
            throw new CurrencyException('Invalid currency code');
        }
        
        return $fromCurrency->convertTo($amount, $toCurrency);
    }

    /**
     * Convert amount from base currency
     */
    public function convertFromBase(float $amount, ?string $to = null): float
    {
        $baseCurrency = Currency::getDefault();
        if (!$baseCurrency) {
            return $amount;
        }
        
        $toCurrency = $to ? Currency::findByCode($to) : $this->getCurrentCurrency();
        if (!$toCurrency) {
            return $amount;
        }
        
        if ($baseCurrency->code === $toCurrency->code) {
            return $amount;
        }
        
        return $baseCurrency->convertTo($amount, $toCurrency);
    }

    /**
     * Convert amount to base currency
     */
    public function convertToBase(float $amount, ?string $from = null): float
    {
        $baseCurrency = Currency::getDefault();
        if (!$baseCurrency) {
            return $amount;
        }
        
        $fromCurrency = $from ? Currency::findByCode($from) : $this->getCurrentCurrency();
        if (!$fromCurrency) {
            return $amount;
        }
        
        if ($fromCurrency->code === $baseCurrency->code) {
            return $amount;
        }
        
        return $fromCurrency->convertTo($amount, $baseCurrency);
    }

    /**
     * Update exchange rates
     */
    public function updateExchangeRates(): bool
    {
        if (!$this->config['exchange_rate_provider']) {
            return false;
        }
        
        try {
            $provider = $this->getExchangeRateProvider();
            $rates = $provider->fetchRates();
            
            foreach ($rates as $code => $rate) {
                $currency = Currency::findByCode($code);
                if ($currency) {
                    $currency->updateExchangeRate($rate);
                }
            }
            
            // Clear cache
            $this->cache->deleteByPrefix('currency_');
            $this->currencyCache = [];
            
            // Trigger event
            $this->eventDispatcher->dispatch('currency.rates_updated', [
                'rates' => $rates
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->eventDispatcher->dispatch('currency.rates_update_failed', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Check if rates need updating
     */
    public function shouldUpdateRates(): bool
    {
        if (!$this->config['auto_update_rates']) {
            return false;
        }
        
        $lastUpdate = $this->cache->get('currency_rates_last_update');
        if (!$lastUpdate) {
            return true;
        }
        
        return (time() - $lastUpdate) > $this->config['update_interval'];
    }

    /**
     * Create a new currency
     */
    public function createCurrency(array $data): Currency
    {
        // Validate currency code
        if (!preg_match('/^[A-Z]{3}$/', $data['code'] ?? '')) {
            throw new CurrencyException('Invalid currency code format');
        }
        
        // Check if currency already exists
        if (Currency::where('code', $data['code'])->exists()) {
            throw new CurrencyException('Currency already exists');
        }
        
        // Set defaults
        $data = array_merge([
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'exchange_rate' => 1.0,
            'is_active' => true,
            'is_default' => false
        ], $data);
        
        // If setting as default, unset other defaults
        if ($data['is_default']) {
            Currency::where('is_default', true)->update(['is_default' => false]);
        }
        
        $currency = new Currency($data);
        $currency->save();
        
        // Clear cache
        $this->cache->deleteByPrefix('currency_');
        $this->currencyCache = [];
        
        return $currency;
    }

    // Private methods

    private function initializeCurrency(): void
    {
        // Check session for saved currency
        $currencyCode = $this->session->get('currency');
        
        if ($currencyCode) {
            $currency = Currency::findByCode($currencyCode);
            if ($currency) {
                $this->currentCurrency = $currency;
                return;
            }
        }
        
        // Check store default currency
        $store = app('current_store');
        if ($store && $store->currency) {
            $currency = Currency::findByCode($store->currency);
            if ($currency) {
                $this->currentCurrency = $currency;
                return;
            }
        }
        
        // Fall back to default currency
        $this->currentCurrency = Currency::getDefault() ?? $this->createDefaultCurrency();
    }

    private function createDefaultCurrency(): Currency
    {
        return $this->createCurrency([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'symbol_position' => 'before',
            'is_default' => true
        ]);
    }

    private function getExchangeRateProvider(): ExchangeRateProviderInterface
    {
        $providerClass = $this->config['exchange_rate_provider'];
        
        if (is_string($providerClass)) {
            return new $providerClass($this->config);
        }
        
        return $providerClass;
    }
}

class CurrencyException extends \Exception {}