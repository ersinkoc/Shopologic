# shipping-fedex API Documentation

## Overview

FedEx shipping integration for Shopologic

## REST Endpoints

### `POST /api/shipping/fedex/rates`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `POST /api/shipping/fedex/label`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `GET /api/shipping/fedex/track/{number}`

Handler: `handleGet`

Description: TODO - Add endpoint description

### `POST /api/shipping/fedex/pickup`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `POST /api/shipping/fedex/validate-address`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `GET /api/shipping/fedex/services`

Handler: `handleGet`

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
