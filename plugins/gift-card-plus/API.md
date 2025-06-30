# Gift Card Plus API Documentation

## Overview

Advanced gift card system with scheduling, personalization, digital delivery, and social gifting

## REST Endpoints

### `POST /api/v1/gift-cards/purchase`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `GET /api/v1/gift-cards/balance/{code}`

Handler: `handleGet`

Description: TODO - Add endpoint description

### `POST /api/v1/gift-cards/redeem`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `POST /api/v1/gift-cards/schedule`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `GET /api/v1/gift-cards/templates`

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
