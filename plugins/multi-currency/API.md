# Multi-Currency Support API Documentation

## Overview

Display prices in multiple currencies with real-time exchange rates, automatic geo-detection, and currency switcher widget

## REST Endpoints

### `GET /api/v1/currencies`

Handler: `Controllers\CurrencyController@index`

Description: TODO - Add endpoint description

### `GET /api/v1/currencies/active`

Handler: `Controllers\CurrencyController@getActive`

Description: TODO - Add endpoint description

### `POST /api/v1/currencies/set`

Handler: `Controllers\CurrencyController@setCurrency`

Description: TODO - Add endpoint description

### `GET /api/v1/currencies/rates`

Handler: `Controllers\CurrencyController@getRates`

Description: TODO - Add endpoint description

### `POST /api/v1/currencies/convert`

Handler: `Controllers\CurrencyController@convert`

Description: TODO - Add endpoint description

### `GET /api/v1/currencies/detect`

Handler: `Controllers\CurrencyController@detectCurrency`

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
