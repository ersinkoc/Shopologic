# Loyalty Gamification API Documentation

## Overview

Gamified loyalty system with points, badges, achievements, leaderboards, and challenges

## REST Endpoints

### `GET /api/v1/loyalty/profile`

Handler: `handleGet`

Description: TODO - Add endpoint description

### `GET /api/v1/loyalty/leaderboard`

Handler: `handleGet`

Description: TODO - Add endpoint description

### `POST /api/v1/loyalty/redeem`

Handler: `handlePost`

Description: TODO - Add endpoint description

### `GET /api/v1/loyalty/challenges`

Handler: `handleGet`

Description: TODO - Add endpoint description

### `POST /api/v1/loyalty/challenge/{id}/complete`

Handler: `handlePost`

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
