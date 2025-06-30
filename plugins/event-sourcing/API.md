# event-sourcing API Documentation

## Overview

Complete event sourcing implementation with event store, projections, snapshots, CQRS support, event replay, time travel debugging, and distributed event streaming

## REST Endpoints

### `GET /api/v1/events/streams`

Handler: `Controllers\StreamController@index`

Description: TODO - Add endpoint description

### `GET /api/v1/events/streams/{streamId}`

Handler: `Controllers\StreamController@show`

Description: TODO - Add endpoint description

### `POST /api/v1/events/streams/{streamId}/events`

Handler: `Controllers\EventController@append`

Description: TODO - Add endpoint description

### `GET /api/v1/events/streams/{streamId}/events`

Handler: `Controllers\EventController@index`

Description: TODO - Add endpoint description

### `GET /api/v1/events/projections`

Handler: `Controllers\ProjectionController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/events/projections`

Handler: `Controllers\ProjectionController@create`

Description: TODO - Add endpoint description

### `POST /api/v1/events/projections/{name}/rebuild`

Handler: `Controllers\ProjectionController@rebuild`

Description: TODO - Add endpoint description

### `GET /api/v1/events/snapshots/{aggregateId}`

Handler: `Controllers\SnapshotController@show`

Description: TODO - Add endpoint description

### `POST /api/v1/events/snapshots/{aggregateId}`

Handler: `Controllers\SnapshotController@create`

Description: TODO - Add endpoint description

### `POST /api/v1/events/replay`

Handler: `Controllers\ReplayController@start`

Description: TODO - Add endpoint description

### `GET /api/v1/events/replay/{jobId}`

Handler: `Controllers\ReplayController@status`

Description: TODO - Add endpoint description

### `GET /api/v1/events/audit/{entityType}/{entityId}`

Handler: `Controllers\AuditController@trail`

Description: TODO - Add endpoint description

### `POST /api/v1/events/time-travel`

Handler: `Controllers\TimeTravelController@query`

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
