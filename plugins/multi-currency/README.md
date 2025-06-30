# 💱 Multi-Currency Plugin

![Quality Badge](https://img.shields.io/badge/Quality-71%25%20(C)-yellow)


Comprehensive multi-currency support with real-time exchange rates, automatic conversion, localized pricing, and currency-specific payment processing for global e-commerce operations.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate Multi-Currency
php cli/plugin.php activate multi-currency
```

## ✨ Key Features

### 💵 Currency Management
- **150+ Currencies** - Global currency support
- **Real-Time Rates** - Live exchange rate updates
- **Historical Rates** - Rate history tracking
- **Custom Rates** - Manual rate overrides
- **Cryptocurrency Support** - Digital currency integration

### 🌍 Localization Features
- **GeoIP Detection** - Automatic currency selection
- **Regional Pricing** - Market-specific prices
- **Tax Integration** - Currency-specific taxes
- **Format Localization** - Number formatting
- **Symbol Positioning** - Cultural conventions

### 💳 Payment Processing
- **Multi-Currency Checkout** - Native currency payments
- **Settlement Options** - Preferred settlement currency
- **Exchange Fees** - Transparent fee display
- **Currency Hedging** - Risk management
- **Reconciliation** - Multi-currency accounting

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`MultiCurrencyPlugin.php`** - Core currency engine

### Services
- **Exchange Rate Service** - Rate management
- **Conversion Engine** - Price calculations
- **Localization Service** - Regional settings
- **Payment Processor** - Currency transactions
- **Analytics Tracker** - Currency analytics

### Models
- **Currency** - Currency definitions
- **ExchangeRate** - Rate history
- **PriceRule** - Regional pricing
- **CurrencyFormat** - Display formats
- **Transaction** - Multi-currency records

### Controllers
- **Currency API** - Conversion endpoints
- **Settings UI** - Currency configuration
- **Analytics Dashboard** - Performance metrics

## 🔧 Installation

### Requirements
- PHP 8.3+
- Exchange rate API access
- GeoIP database
- Payment gateway support
- Caching infrastructure

### Setup

```bash
# Activate plugin
php cli/plugin.php activate multi-currency

# Run migrations
php cli/migrate.php up

# Configure rate provider
php cli/currency.php setup-provider

# Import currencies
php cli/currency.php import-currencies
```

## 📚 API Endpoints

### REST API
- `GET /api/v1/currencies` - List currencies
- `GET /api/v1/rates` - Current exchange rates
- `POST /api/v1/convert` - Convert amounts
- `PUT /api/v1/currency/default` - Set default
- `GET /api/v1/currency/detect` - Auto-detect currency

## 🚀 Production Ready

This plugin is production-ready with:
- ✅ Real-time exchange rates
- ✅ Automatic conversion
- ✅ Regional pricing
- ✅ Payment integration
- ✅ Tax compliance
- ✅ Global scalability

---

**Multi-Currency** - Global commerce enablement for Shopologic