# visual-page-builder API Documentation

## Overview

Advanced drag-and-drop page builder with real-time preview, responsive design, custom blocks, templates library, CSS/JS editor, version control, and AI-powered layout suggestions

## REST Endpoints

### `GET /api/v1/pagebuilder/pages`

Handler: `Controllers\PageController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/pagebuilder/pages`

Handler: `Controllers\PageController@create`

Description: TODO - Add endpoint description

### `GET /api/v1/pagebuilder/pages/{id}`

Handler: `Controllers\PageController@show`

Description: TODO - Add endpoint description

### `PUT /api/v1/pagebuilder/pages/{id}`

Handler: `Controllers\PageController@update`

Description: TODO - Add endpoint description

### `POST /api/v1/pagebuilder/pages/{id}/preview`

Handler: `Controllers\PageController@preview`

Description: TODO - Add endpoint description

### `POST /api/v1/pagebuilder/pages/{id}/publish`

Handler: `Controllers\PageController@publish`

Description: TODO - Add endpoint description

### `GET /api/v1/pagebuilder/blocks`

Handler: `Controllers\BlockController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/pagebuilder/blocks`

Handler: `Controllers\BlockController@create`

Description: TODO - Add endpoint description

### `GET /api/v1/pagebuilder/templates`

Handler: `Controllers\TemplateController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/pagebuilder/templates`

Handler: `Controllers\TemplateController@create`

Description: TODO - Add endpoint description

### `POST /api/v1/pagebuilder/ai/suggest-layout`

Handler: `Controllers\AIController@suggestLayout`

Description: TODO - Add endpoint description

### `POST /api/v1/pagebuilder/export/{id}`

Handler: `Controllers\ExportController@export`

Description: TODO - Add endpoint description

### `POST /api/v1/pagebuilder/import`

Handler: `Controllers\ImportController@import`

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
