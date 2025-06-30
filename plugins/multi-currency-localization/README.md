# 💱 Multi-Currency & Localization Plugin

![Quality Badge](https://img.shields.io/badge/Quality-57%25%20(F)-red)


Comprehensive multi-currency support with real-time exchange rates, advanced localization, and cultural formatting for global e-commerce operations.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring 47 advanced models, cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Run with integration demonstration
php bootstrap_plugins.php --demo
```

## ✨ Key Features

### 💰 Advanced Currency Management
- **Real-Time Exchange Rates** - Live rates from multiple providers (ECB, CurrencyAPI, etc.)
- **Historical Rate Tracking** - Complete rate history with volatility analysis
- **Cross-Rate Calculations** - Multi-hop currency conversions
- **Rate Volatility Analysis** - Risk assessment and trend monitoring
- **Custom Rate Providers** - Support for custom exchange rate sources

### 🌍 Comprehensive Localization
- **Cultural Formatting** - Region-specific number, date, and currency formatting
- **Address Formatting** - Country-specific address formats and validation
- **Phone Number Formatting** - International phone number standards
- **Postal Code Validation** - Country-specific postal code validation
- **Timezone Management** - Automatic timezone detection and conversion

### 📊 Localization Intelligence
- **Format Detection** - Automatic format recognition and suggestion
- **Validation Rules** - Country-specific validation logic
- **Cultural Preferences** - Regional shopping and payment preferences
- **Compliance Support** - Regional legal and tax compliance features

## 🏗️ Plugin Architecture

### Models
- **`ExchangeRate.php`** - Real-time exchange rate management with history
- **`ExchangeRateHistory.php`** - Rate change tracking with volatility analysis
- **`Localization.php`** - Comprehensive localization with formatting rules
- **`Currency.php`** - Currency definitions and properties

### Services
- **`CurrencyManager.php`** - Central currency operations orchestration
- **`ExchangeRateProvider.php`** - Multi-provider rate fetching and aggregation
- **`LocalizationService.php`** - Formatting and validation operations

### Controllers
- **`CurrencyController.php`** - REST API endpoints for currency operations

### Repositories
- **`CurrencyRepository.php`** - Currency data access layer
- **`ExchangeRateRepository.php`** - Exchange rate storage and retrieval

## 🔗 Cross-Plugin Integration

### Provider Interface
Implements `CurrencyProviderInterface` for seamless integration:

```php
interface CurrencyProviderInterface {
    public function convertCurrency(float $amount, string $from, string $to): float;
    public function formatCurrency(float $amount, string $currency = null): string;
    public function getExchangeRate(string $from, string $to): float;
    public function getCurrentCurrency(): string;
    public function getSupportedCurrencies(): array;
}
```

### Integration Examples

```php
// Get currency provider
$currencyProvider = $integrationManager->getCurrencyProvider();

// Convert currencies
$convertedAmount = $currencyProvider->convertCurrency(100.00, 'USD', 'EUR');

// Format for display
$formattedPrice = $currencyProvider->formatCurrency(99.99, 'USD'); // $99.99

// Get current exchange rate
$rate = $currencyProvider->getExchangeRate('USD', 'EUR');
```

## 💱 Advanced Features

### Dynamic Exchange Rate Management

```php
// Update exchange rates with provider metadata
$exchangeRate = ExchangeRate::create([
    'from_currency' => 'USD',
    'to_currency' => 'EUR',
    'rate' => 0.85,
    'provider' => 'ecb',
    'effective_at' => now()
]);

// Update rate with change tracking
$exchangeRate->updateRate(0.86, [
    'source' => 'live_feed',
    'confidence' => 'high',
    'volume_weighted' => true
]);

// Calculate volatility and risk metrics
$volatility = $exchangeRate->calculateVolatility(30); // 30-day volatility
$riskScore = $exchangeRate->getRiskScore();

// Get cross-rate calculations
$crossRate = ExchangeRate::getCrossRate('EUR', 'JPY', 'USD');
```

### Comprehensive Localization

```php
// Advanced localization formatting
$localization = Localization::findByCountry('US');

// Format prices with cultural preferences
$formattedPrice = $localization->formatPrice(1234.56, '$'); // $1,234.56

// Format addresses according to local standards
$formattedAddress = $localization->formatAddress([
    'street' => '123 Main St',
    'city' => 'New York',
    'state' => 'NY',
    'postal_code' => '10001',
    'country' => 'US'
]);

// Validate postal codes
$isValid = $localization->validatePostalCode('10001'); // true for US

// Format phone numbers
$formattedPhone = $localization->formatPhoneNumber('+1-555-123-4567');

// Get cultural shopping preferences
$preferences = $localization->getShoppingPreferences();
```

### Historical Rate Analysis

```php
// Track rate changes with detailed history
$history = ExchangeRateHistory::create([
    'exchange_rate_id' => $exchangeRate->id,
    'old_rate' => 0.85,
    'new_rate' => 0.86,
    'rate_change' => 0.01,
    'change_percent' => 1.18,
    'source' => 'automated_update',
    'metadata' => ['api_response_time' => 250]
]);

// Analyze rate trends
$trends = ExchangeRateHistory::analyzeTrends('USD', 'EUR', 90); // 90-day analysis
$forecast = ExchangeRateHistory::generateForecast('USD', 'EUR', 30); // 30-day forecast
```

## ⚡ Real-Time Events

### Event Listeners

```php
// Exchange rate change notifications
$eventDispatcher->listen('currency.rate_updated', function($event) {
    $data = $event->getData();
    // Update product prices if significant change
    if (abs($data['change_percent']) > 2.0) {
        $priceUpdateService = app(PriceUpdateService::class);
        $priceUpdateService->updatePricesForCurrency($data['currency_pair']);
    }
});

// Currency conversion tracking
$eventDispatcher->listen('currency.conversion_requested', function($event) {
    $data = $event->getData();
    // Track conversion analytics
    $analyticsProvider = app()->get(AnalyticsProviderInterface::class);
    $analyticsProvider->trackEvent('currency_conversion', $data);
});
```

### Event Dispatching

```php
// Dispatch currency events
$eventDispatcher->dispatch('currency.rate_updated', [
    'currency_pair' => 'USD/EUR',
    'old_rate' => 0.85,
    'new_rate' => 0.86,
    'change_percent' => 1.18,
    'provider' => 'ecb',
    'timestamp' => now()->toISOString()
]);
```

## 📈 Performance Monitoring

### Health Checks

```php
// Register currency-specific health checks
$healthMonitor->registerHealthCheck('currency', 'exchange_rate_freshness', function() {
    // Check if exchange rates are up-to-date
    return $this->checkExchangeRateFreshness();
});

$healthMonitor->registerHealthCheck('currency', 'provider_connectivity', function() {
    // Verify connection to rate providers
    return $this->testProviderConnectivity();
});
```

### Metrics Tracking

```php
// Record currency performance metrics
$healthMonitor->recordResponseTime('currency', 'rate_fetch', 180.5);
$healthMonitor->recordMemoryUsage('currency', 6.3);
$healthMonitor->recordDatabaseQueryTime('currency', 'SELECT * FROM exchange_rates', 12.1);
```

## 🧪 Automated Testing

### Test Coverage
- **Unit Tests** - Currency conversion and formatting logic
- **Integration Tests** - Cross-plugin currency workflows
- **Performance Tests** - Large-scale conversion processing
- **Security Tests** - Rate manipulation protection

### Example Tests

```php
class CurrencyTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_currency_conversion' => [$this, 'testCurrencyConversion'],
            'test_price_formatting' => [$this, 'testPriceFormatting'],
            'test_address_validation' => [$this, 'testAddressValidation']
        ];
    }
    
    public function testCurrencyConversion(): void
    {
        $rate = new ExchangeRate(['rate' => 0.85]);
        $converted = $rate->convert(100.00);
        Assert::assertEquals(85.00, $converted);
    }
}
```

## 🛠️ Configuration

### Plugin Settings

```json
{
    "default_currency": "USD",
    "supported_currencies": ["USD", "EUR", "GBP", "JPY", "CAD"],
    "rate_update_interval": 3600,
    "rate_providers": ["ecb", "currencyapi", "fixer"],
    "fallback_provider": "ecb",
    "rate_cache_ttl": 1800,
    "enable_volatility_alerts": true,
    "volatility_threshold": 5.0,
    "enable_historical_tracking": true,
    "localization_cache_ttl": 86400
}
```

### Database Tables
- `currencies` - Currency definitions and properties
- `exchange_rates` - Current exchange rate data
- `exchange_rate_history` - Historical rate changes
- `localizations` - Country-specific formatting rules

## 📚 API Endpoints

### REST API
- `GET /api/v1/currencies` - List supported currencies
- `GET /api/v1/currencies/rates` - Get current exchange rates
- `POST /api/v1/currencies/convert` - Convert between currencies
- `GET /api/v1/currencies/rates/history` - Get rate history
- `GET /api/v1/localization/{country}` - Get localization settings
- `POST /api/v1/localization/format` - Format data by locale

### Usage Examples

```bash
# Get exchange rates
curl -X GET /api/v1/currencies/rates \
  -H "Authorization: Bearer {token}"

# Convert currency
curl -X POST /api/v1/currencies/convert \
  -H "Content-Type: application/json" \
  -d '{"amount": 100, "from": "USD", "to": "EUR"}'

# Format address
curl -X POST /api/v1/localization/format \
  -H "Content-Type: application/json" \
  -d '{"type": "address", "country": "US", "data": {...}}'
```

## 🔧 Installation & Setup

### Requirements
- PHP 8.3+
- PostgreSQL database
- Shopologic Core Framework
- Internet connection for rate updates

### Installation

```bash
# Activate plugin
php cli/plugin.php activate multi-currency-localization

# Run migrations
php cli/migrate.php up

# Initialize plugin ecosystem
php bootstrap_plugins.php
```

### Rate Provider Setup

```bash
# Configure exchange rate providers
php cli/currency.php configure --provider=ecb
php cli/currency.php test-connection --all-providers
php cli/currency.php update-rates --force
```

## 📖 Documentation

- **Currency Setup Guide** - Configuration and rate provider setup
- **Localization Manual** - Country-specific formatting rules
- **Integration Examples** - Cross-plugin currency workflows
- **Performance Guide** - Optimization for global deployments

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Comprehensive model layer with sophisticated business logic
- ✅ Cross-plugin integration via standardized interfaces
- ✅ Real-time event system with middleware support
- ✅ Performance monitoring and health checks
- ✅ Automated testing framework
- ✅ Complete documentation and examples

---

**Multi-Currency & Localization** - Global commerce enablement for Shopologic