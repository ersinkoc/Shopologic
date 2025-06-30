# PayPal Payment Gateway API Documentation

## Overview

Accept payments via PayPal, including PayPal Checkout, credit cards, and PayPal Pay Later options

## REST Endpoints

### `POST /api/v1/paypal/create-order`

Handler: `Controllers\PayPalController@createOrder`

Description: TODO - Add endpoint description

### `POST /api/v1/paypal/capture-order`

Handler: `Controllers\PayPalController@captureOrder`

Description: TODO - Add endpoint description

### `POST /api/v1/paypal/webhook`

Handler: `Controllers\PayPalController@handleWebhook`

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
