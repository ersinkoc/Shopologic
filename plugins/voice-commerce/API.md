# Voice Commerce API Documentation

## Overview

Voice-powered shopping experience using browser Speech Recognition and Text-to-Speech APIs

## REST Endpoints

### `POST /api/v1/voice/process-command`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `GET /api/v1/voice/commands`

Handler: `handleGet`

Description: TODO - Add endpoint description

### `POST /api/v1/voice/feedback`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `GET /api/v1/voice/product-speech/{id}`

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
