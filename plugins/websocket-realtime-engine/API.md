# websocket-realtime-engine API Documentation

## Overview

High-performance WebSocket server for real-time communication with channels, presence, broadcasting, clustering support, message queuing, and client SDKs

## REST Endpoints

### `POST /api/v1/websocket/broadcast`

Handler: `Controllers\BroadcastController@send`

Description: TODO - Add endpoint description

### `GET /api/v1/websocket/channels`

Handler: `Controllers\ChannelController@index`

Description: TODO - Add endpoint description

### `GET /api/v1/websocket/channels/{channel}/users`

Handler: `Controllers\PresenceController@users`

Description: TODO - Add endpoint description

### `GET /api/v1/websocket/connections`

Handler: `Controllers\ConnectionController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/websocket/auth`

Handler: `Controllers\AuthController@authenticate`

Description: TODO - Add endpoint description

### `POST /api/v1/websocket/channels/{channel}/auth`

Handler: `Controllers\AuthController@authorizeChannel`

Description: TODO - Add endpoint description

### `GET /api/v1/websocket/stats`

Handler: `Controllers\StatsController@overview`

Description: TODO - Add endpoint description

### `POST /api/v1/websocket/whisper`

Handler: `Controllers\WhisperController@send`

Description: TODO - Add endpoint description

### `WS /websocket`

Handler: `WebSocketHandler@handle`

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
