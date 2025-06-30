<?php

declare(strict_types=1);

namespace Shopologic\Plugins\Shared\Interfaces;

/**
 * Interface for plugins that provide currency and localization services
 * Enables multi-currency support across plugins
 */
interface CurrencyProviderInterface
{
    /**
     * Get current customer currency
     */
    public function getCurrentCurrency(): string;
    
    /**
     * Convert amount between currencies
     */
    public function convertCurrency(float $amount, string $fromCurrency, string $toCurrency): float;
    
    /**
     * Format amount according to currency and locale
     */
    public function formatCurrency(float $amount, string $currency = null, string $locale = null): string;
    
    /**
     * Get exchange rate between currencies
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float;
    
    /**
     * Get available currencies
     */
    public function getAvailableCurrencies(): array;
    
    /**
     * Get customer's detected location
     */
    public function getCustomerLocation(int $customerId = null): array;
    
    /**
     * Get localization settings for country/language
     */
    public function getLocalizationSettings(string $countryCode = null, string $languageCode = null): array;
    
    /**
     * Subscribe to currency rate changes
     */
    public function subscribeToRateChanges(string $currencyPair, callable $callback): void;
}