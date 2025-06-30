# Review Intelligence API Documentation

## Overview

Smart review management with sentiment analysis, fake review detection, and automated responses

## REST Endpoints

### `POST /api/v1/reviews/analyze`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `GET /api/v1/reviews/sentiment/{product_id}`

Handler: `handleGet`

Description: TODO - Add endpoint description

### `POST /api/v1/reviews/moderate`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `GET /api/v1/reviews/insights`

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
