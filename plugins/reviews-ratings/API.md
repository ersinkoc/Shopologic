# Product Reviews & Ratings API Documentation

## Overview

Add customer reviews and ratings to products with moderation, rich snippets, email notifications, and review incentives

## REST Endpoints

### `GET /api/v1/products/{id}/reviews`

Handler: `Controllers\ReviewController@getProductReviews`

Description: TODO - Add endpoint description

### `POST /api/v1/products/{id}/reviews`

Handler: `Controllers\ReviewController@createReview`

Description: TODO - Add endpoint description

### `PUT /api/v1/reviews/{id}`

Handler: `Controllers\ReviewController@updateReview`

Description: TODO - Add endpoint description

### `DELETE /api/v1/reviews/{id}`

Handler: `Controllers\ReviewController@deleteReview`

Description: TODO - Add endpoint description

### `POST /api/v1/reviews/{id}/helpful`

Handler: `Controllers\ReviewController@markHelpful`

Description: TODO - Add endpoint description

### `POST /api/v1/reviews/{id}/report`

Handler: `Controllers\ReviewController@reportReview`

Description: TODO - Add endpoint description

### `POST /api/v1/reviews/{id}/response`

Handler: `Controllers\ReviewController@respondToReview`

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
