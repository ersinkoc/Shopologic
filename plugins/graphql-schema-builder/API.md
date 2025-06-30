# graphql-schema-builder API Documentation

## Overview

Visual GraphQL schema builder with type generation, resolver mapping, real-time playground, subscription support, schema stitching, and automatic REST to GraphQL conversion

## REST Endpoints

### `POST /graphql`

Handler: `Controllers\GraphQLController@execute`

Description: TODO - Add endpoint description

### `GET /graphql`

Handler: `Controllers\GraphQLController@playground`

Description: TODO - Add endpoint description

### `GET /graphql/schema`

Handler: `Controllers\SchemaController@export`

Description: TODO - Add endpoint description

### `GET /api/v1/graphql/types`

Handler: `Controllers\TypeController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/graphql/types`

Handler: `Controllers\TypeController@create`

Description: TODO - Add endpoint description

### `PUT /api/v1/graphql/types/{name}`

Handler: `Controllers\TypeController@update`

Description: TODO - Add endpoint description

### `DELETE /api/v1/graphql/types/{name}`

Handler: `Controllers\TypeController@delete`

Description: TODO - Add endpoint description

### `GET /api/v1/graphql/resolvers`

Handler: `Controllers\ResolverController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/graphql/resolvers`

Handler: `Controllers\ResolverController@create`

Description: TODO - Add endpoint description

### `POST /api/v1/graphql/validate`

Handler: `Controllers\ValidationController@validate`

Description: TODO - Add endpoint description

### `POST /api/v1/graphql/rest-to-graphql`

Handler: `Controllers\ConversionController@convertRest`

Description: TODO - Add endpoint description

### `GET /api/v1/graphql/subscriptions`

Handler: `Controllers\SubscriptionController@index`

Description: TODO - Add endpoint description

### `WS /graphql-ws`

Handler: `Controllers\WebSocketController@handle`

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
