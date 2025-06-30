# progressive-web-app-builder API Documentation

## Overview

Complete PWA builder with offline support, push notifications, app shell architecture, service workers, manifest generation, caching strategies, and app store deployment

## REST Endpoints

### `GET /manifest.json`

Handler: `Controllers\ManifestController@generate`

Description: TODO - Add endpoint description

### `GET /service-worker.js`

Handler: `Controllers\ServiceWorkerController@generate`

Description: TODO - Add endpoint description

### `POST /api/v1/pwa/notifications/subscribe`

Handler: `Controllers\NotificationController@subscribe`

Description: TODO - Add endpoint description

### `POST /api/v1/pwa/notifications/send`

Handler: `Controllers\NotificationController@send`

Description: TODO - Add endpoint description

### `GET /api/v1/pwa/analytics`

Handler: `Controllers\AnalyticsController@dashboard`

Description: TODO - Add endpoint description

### `POST /api/v1/pwa/cache/clear`

Handler: `Controllers\CacheController@clear`

Description: TODO - Add endpoint description

### `GET /api/v1/pwa/offline-pages`

Handler: `Controllers\OfflineController@pages`

Description: TODO - Add endpoint description

### `POST /api/v1/pwa/sync`

Handler: `Controllers\SyncController@sync`

Description: TODO - Add endpoint description

### `GET /api/v1/pwa/app-shell`

Handler: `Controllers\AppShellController@shell`

Description: TODO - Add endpoint description

### `POST /api/v1/pwa/deploy`

Handler: `Controllers\DeploymentController@deploy`

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
