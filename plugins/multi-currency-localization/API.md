# Multi-Currency & Localization API Documentation

## Overview

Comprehensive multi-currency support with real-time exchange rates, localization, regional pricing, tax compliance, and international e-commerce features

## REST Endpoints

### `GET /api/v1/currencies`

Handler: `CurrencyController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/currencies`

Handler: `CurrencyController@create`

Description: TODO - Add endpoint description

### `PUT /api/v1/currencies/{id}`

Handler: `CurrencyController@update`

Description: TODO - Add endpoint description

### `GET /api/v1/exchange-rates`

Handler: `ExchangeRateController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/exchange-rates/update`

Handler: `ExchangeRateController@updateRates`

Description: TODO - Add endpoint description

### `GET /api/v1/localization/detect`

Handler: `LocalizationController@detectLocation`

Description: TODO - Add endpoint description

### `GET /api/v1/localization/translations`

Handler: `LocalizationController@getTranslations`

Description: TODO - Add endpoint description

### `POST /api/v1/localization/translations`

Handler: `LocalizationController@updateTranslations`

Description: TODO - Add endpoint description

### `GET /api/v1/regional-pricing`

Handler: `RegionalPricingController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/regional-pricing`

Handler: `RegionalPricingController@create`

Description: TODO - Add endpoint description

### `GET /api/v1/tax-compliance/rates`

Handler: `TaxComplianceController@getTaxRates`

Description: TODO - Add endpoint description

### `POST /api/v1/tax-compliance/calculate`

Handler: `TaxComplianceController@calculateTax`

Description: TODO - Add endpoint description

### `GET /api/v1/price/convert`

Handler: `PriceController@convertPrice`

Description: TODO - Add endpoint description

### `POST /api/v1/price/bulk-convert`

Handler: `PriceController@bulkConvertPrices`

Description: TODO - Add endpoint description

## Authentication

All endpoints require proper authentication.

## Error Responses

Standard error response format:

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Error description"
  }
}
```
