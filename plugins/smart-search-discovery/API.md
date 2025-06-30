# smart-search-discovery API Documentation

## Overview

AI-powered search with natural language processing, visual search, voice search, and intelligent product discovery

## REST Endpoints

### `POST /api/v1/search/query`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `POST /api/v1/search/visual`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `POST /api/v1/search/voice`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `GET /api/v1/search/suggestions`

Handler: `handleGet`

Description: TODO - Add endpoint description

### `GET /api/v1/search/analytics`

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
