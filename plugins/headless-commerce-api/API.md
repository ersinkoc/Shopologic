# headless-commerce-api API Documentation

## Overview

Complete headless commerce API with REST and GraphQL endpoints, real-time webhooks, SDK generation, API versioning, rate limiting, and comprehensive e-commerce functionality

## REST Endpoints

### `GET /api/{version}/products`

Handler: `Controllers\ProductController@index`

Description: TODO - Add endpoint description

### `GET /api/{version}/products/{id}`

Handler: `Controllers\ProductController@show`

Description: TODO - Add endpoint description

### `POST /api/{version}/products`

Handler: `Controllers\ProductController@store`

Description: TODO - Add endpoint description

### `PUT /api/{version}/products/{id}`

Handler: `Controllers\ProductController@update`

Description: TODO - Add endpoint description

### `DELETE /api/{version}/products/{id}`

Handler: `Controllers\ProductController@destroy`

Description: TODO - Add endpoint description

### `GET /api/{version}/categories`

Handler: `Controllers\CategoryController@index`

Description: TODO - Add endpoint description

### `GET /api/{version}/categories/{id}/products`

Handler: `Controllers\CategoryController@products`

Description: TODO - Add endpoint description

### `POST /api/{version}/cart`

Handler: `Controllers\CartController@create`

Description: TODO - Add endpoint description

### `GET /api/{version}/cart/{id}`

Handler: `Controllers\CartController@show`

Description: TODO - Add endpoint description

### `POST /api/{version}/cart/{id}/items`

Handler: `Controllers\CartController@addItem`

Description: TODO - Add endpoint description

### `PUT /api/{version}/cart/{id}/items/{itemId}`

Handler: `Controllers\CartController@updateItem`

Description: TODO - Add endpoint description

### `DELETE /api/{version}/cart/{id}/items/{itemId}`

Handler: `Controllers\CartController@removeItem`

Description: TODO - Add endpoint description

### `POST /api/{version}/checkout`

Handler: `Controllers\CheckoutController@process`

Description: TODO - Add endpoint description

### `GET /api/{version}/orders`

Handler: `Controllers\OrderController@index`

Description: TODO - Add endpoint description

### `GET /api/{version}/orders/{id}`

Handler: `Controllers\OrderController@show`

Description: TODO - Add endpoint description

### `POST /api/{version}/customers`

Handler: `Controllers\CustomerController@register`

Description: TODO - Add endpoint description

### `POST /api/{version}/auth/login`

Handler: `Controllers\AuthController@login`

Description: TODO - Add endpoint description

### `POST /api/{version}/auth/logout`

Handler: `Controllers\AuthController@logout`

Description: TODO - Add endpoint description

### `POST /api/{version}/auth/refresh`

Handler: `Controllers\AuthController@refresh`

Description: TODO - Add endpoint description

### `GET /api/{version}/search`

Handler: `Controllers\SearchController@search`

Description: TODO - Add endpoint description

### `POST /api/{version}/webhooks`

Handler: `Controllers\WebhookController@create`

Description: TODO - Add endpoint description

### `GET /api/{version}/webhooks`

Handler: `Controllers\WebhookController@index`

Description: TODO - Add endpoint description

### `DELETE /api/{version}/webhooks/{id}`

Handler: `Controllers\WebhookController@destroy`

Description: TODO - Add endpoint description

### `GET /api/{version}/shipping/methods`

Handler: `Controllers\ShippingController@methods`

Description: TODO - Add endpoint description

### `POST /api/{version}/shipping/calculate`

Handler: `Controllers\ShippingController@calculate`

Description: TODO - Add endpoint description

### `GET /api/{version}/payment/methods`

Handler: `Controllers\PaymentController@methods`

Description: TODO - Add endpoint description

### `POST /api/{version}/payment/process`

Handler: `Controllers\PaymentController@process`

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
