# api-mock-server API Documentation

## Overview

Advanced API mocking server for developers with request recording, response templating, scenario testing, latency simulation, error injection, and OpenAPI/Swagger integration

## REST Endpoints

### `GET /api/v1/mockserver/mocks`

Handler: `Controllers\MockController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/mockserver/mocks`

Handler: `Controllers\MockController@create`

Description: TODO - Add endpoint description

### `GET /api/v1/mockserver/mocks/{id}`

Handler: `Controllers\MockController@show`

Description: TODO - Add endpoint description

### `PUT /api/v1/mockserver/mocks/{id}`

Handler: `Controllers\MockController@update`

Description: TODO - Add endpoint description

### `DELETE /api/v1/mockserver/mocks/{id}`

Handler: `Controllers\MockController@delete`

Description: TODO - Add endpoint description

### `POST /api/v1/mockserver/record/start`

Handler: `Controllers\RecorderController@start`

Description: TODO - Add endpoint description

### `POST /api/v1/mockserver/record/stop`

Handler: `Controllers\RecorderController@stop`

Description: TODO - Add endpoint description

### `GET /api/v1/mockserver/scenarios`

Handler: `Controllers\ScenarioController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/mockserver/scenarios`

Handler: `Controllers\ScenarioController@create`

Description: TODO - Add endpoint description

### `POST /api/v1/mockserver/scenarios/{id}/run`

Handler: `Controllers\ScenarioController@run`

Description: TODO - Add endpoint description

### `GET /api/v1/mockserver/logs`

Handler: `Controllers\LogController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/mockserver/import/swagger`

Handler: `Controllers\ImportController@swagger`

Description: TODO - Add endpoint description

### `POST /api/v1/mockserver/import/postman`

Handler: `Controllers\ImportController@postman`

Description: TODO - Add endpoint description

### `GET /api/v1/mockserver/export/{format}`

Handler: `Controllers\ExportController@export`

Description: TODO - Add endpoint description

### `ANY /mock/*`

Handler: `Controllers\MockServerController@handle`

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
