# Google Analytics Integration API Documentation

## Overview

Integrate Google Analytics 4 (GA4) and Universal Analytics for comprehensive e-commerce tracking, including enhanced e-commerce events, conversion tracking, and custom dimensions

## REST Endpoints

### `POST /api/v1/analytics/event`

Handler: `Controllers\AnalyticsController@trackEvent`

Description: TODO - Add endpoint description

### `GET /api/v1/analytics/reports`

Handler: `Controllers\AnalyticsController@getReports`

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
