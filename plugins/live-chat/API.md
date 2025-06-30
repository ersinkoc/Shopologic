# Live Chat Support API Documentation

## Overview

Real-time customer support chat with agent dashboard, canned responses, file sharing, visitor tracking, and chatbot integration

## REST Endpoints

### `GET /api/v1/chat/widget/config`

Handler: `Controllers\WidgetController@getConfig`

Description: TODO - Add endpoint description

### `POST /api/v1/chat/conversations`

Handler: `Controllers\ChatController@startConversation`

Description: TODO - Add endpoint description

### `GET /api/v1/chat/conversations/{id}`

Handler: `Controllers\ChatController@getConversation`

Description: TODO - Add endpoint description

### `POST /api/v1/chat/conversations/{id}/messages`

Handler: `Controllers\ChatController@sendMessage`

Description: TODO - Add endpoint description

### `POST /api/v1/chat/conversations/{id}/typing`

Handler: `Controllers\ChatController@updateTyping`

Description: TODO - Add endpoint description

### `POST /api/v1/chat/conversations/{id}/end`

Handler: `Controllers\ChatController@endConversation`

Description: TODO - Add endpoint description

### `GET /api/v1/chat/agent/conversations`

Handler: `Controllers\AgentController@getConversations`

Description: TODO - Add endpoint description

### `POST /api/v1/chat/agent/conversations/{id}/accept`

Handler: `Controllers\AgentController@acceptConversation`

Description: TODO - Add endpoint description

### `POST /api/v1/chat/agent/conversations/{id}/transfer`

Handler: `Controllers\AgentController@transferConversation`

Description: TODO - Add endpoint description

### `GET /api/v1/chat/canned-responses`

Handler: `Controllers\CannedResponseController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/chat/upload`

Handler: `Controllers\ChatController@uploadFile`

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
