# Email Marketing Hub API Documentation

## Overview

Comprehensive email marketing solution with campaign management, automation workflows, abandoned cart recovery, and integration with popular email services

## REST Endpoints

### `POST /api/v1/email/subscribe`

Handler: `Controllers\SubscriberController@subscribe`

Description: TODO - Add endpoint description

### `POST /api/v1/email/unsubscribe`

Handler: `Controllers\SubscriberController@unsubscribe`

Description: TODO - Add endpoint description

### `GET /api/v1/email/campaigns`

Handler: `Controllers\CampaignController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/email/campaigns`

Handler: `Controllers\CampaignController@create`

Description: TODO - Add endpoint description

### `POST /api/v1/email/campaigns/{id}/send`

Handler: `Controllers\CampaignController@send`

Description: TODO - Add endpoint description

### `GET /api/v1/email/automations`

Handler: `Controllers\AutomationController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/email/automations`

Handler: `Controllers\AutomationController@create`

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
