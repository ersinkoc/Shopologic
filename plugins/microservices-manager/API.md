# microservices-manager API Documentation

## Overview

Complete microservices orchestration with service discovery, health monitoring, load balancing, circuit breakers, API gateway, distributed tracing, and service mesh management

## REST Endpoints

### `GET /api/v1/microservices/services`

Handler: `Controllers\ServiceController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/microservices/services`

Handler: `Controllers\ServiceController@register`

Description: TODO - Add endpoint description

### `GET /api/v1/microservices/services/{id}`

Handler: `Controllers\ServiceController@show`

Description: TODO - Add endpoint description

### `PUT /api/v1/microservices/services/{id}`

Handler: `Controllers\ServiceController@update`

Description: TODO - Add endpoint description

### `DELETE /api/v1/microservices/services/{id}`

Handler: `Controllers\ServiceController@deregister`

Description: TODO - Add endpoint description

### `GET /api/v1/microservices/services/{id}/health`

Handler: `Controllers\HealthController@check`

Description: TODO - Add endpoint description

### `POST /api/v1/microservices/services/{id}/health`

Handler: `Controllers\HealthController@report`

Description: TODO - Add endpoint description

### `GET /api/v1/microservices/topology`

Handler: `Controllers\TopologyController@map`

Description: TODO - Add endpoint description

### `GET /api/v1/microservices/traces`

Handler: `Controllers\TracingController@index`

Description: TODO - Add endpoint description

### `GET /api/v1/microservices/traces/{traceId}`

Handler: `Controllers\TracingController@show`

Description: TODO - Add endpoint description

### `GET /api/v1/microservices/metrics`

Handler: `Controllers\MetricsController@dashboard`

Description: TODO - Add endpoint description

### `POST /api/v1/microservices/gateway/routes`

Handler: `Controllers\GatewayController@addRoute`

Description: TODO - Add endpoint description

### `GET /api/v1/microservices/circuit-breakers`

Handler: `Controllers\CircuitBreakerController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/microservices/circuit-breakers/{serviceId}/reset`

Handler: `Controllers\CircuitBreakerController@reset`

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
