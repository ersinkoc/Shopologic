# performance-profiler API Documentation

## Overview

Advanced performance profiling with real-time monitoring, bottleneck detection, memory analysis, database query profiling, cache optimization, and automated performance recommendations

## REST Endpoints

### `GET /api/v1/performance/current`

Handler: `Controllers\MonitoringController@current`

Description: TODO - Add endpoint description

### `POST /api/v1/performance/profile/start`

Handler: `Controllers\ProfilerController@start`

Description: TODO - Add endpoint description

### `POST /api/v1/performance/profile/stop`

Handler: `Controllers\ProfilerController@stop`

Description: TODO - Add endpoint description

### `GET /api/v1/performance/profiles`

Handler: `Controllers\ProfilerController@index`

Description: TODO - Add endpoint description

### `GET /api/v1/performance/profiles/{id}`

Handler: `Controllers\ProfilerController@show`

Description: TODO - Add endpoint description

### `GET /api/v1/performance/metrics`

Handler: `Controllers\MetricsController@index`

Description: TODO - Add endpoint description

### `GET /api/v1/performance/bottlenecks`

Handler: `Controllers\BottleneckController@index`

Description: TODO - Add endpoint description

### `GET /api/v1/performance/recommendations`

Handler: `Controllers\RecommendationController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/performance/optimize`

Handler: `Controllers\OptimizationController@optimize`

Description: TODO - Add endpoint description

### `GET /api/v1/performance/benchmarks`

Handler: `Controllers\BenchmarkController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/performance/benchmarks/run`

Handler: `Controllers\BenchmarkController@run`

Description: TODO - Add endpoint description

### `GET /api/v1/performance/timeline/{session}`

Handler: `Controllers\TimelineController@show`

Description: TODO - Add endpoint description

### `GET /api/v1/performance/flamegraph/{profile}`

Handler: `Controllers\FlamegraphController@generate`

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
