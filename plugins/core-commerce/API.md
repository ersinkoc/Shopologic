# Core Commerce API Documentation

## Overview

The Core Commerce plugin provides comprehensive REST APIs for managing products, categories, shopping carts, orders, and customers. All endpoints follow RESTful conventions and return JSON responses.

## Authentication

All API endpoints require authentication using JWT tokens or API keys.

```bash
Authorization: Bearer <token>
# or
X-API-Key: <api-key>
```

## Base URL

```
https://your-domain.com/api/v1
```

## Product Management

### List Products

```http
GET /api/v1/products
```

Query parameters:
- `page` (int): Page number (default: 1)
- `per_page` (int): Items per page (default: 20, max: 100)
- `sort` (string): Sort field and order (e.g., "price_asc", "created_desc")
- `filter[category_id]` (int): Filter by category
- `filter[status]` (string): Filter by status (active, inactive, draft)
- `filter[price_min]` (float): Minimum price
- `filter[price_max]` (float): Maximum price
- `include` (string): Include relationships (category,images,reviews)

Response:
```json
{
  "data": [
    {
      "id": 1,
      "name": "Product Name",
      "slug": "product-name",
      "price": 99.99,
      "sku": "PROD-001",
      "status": "active",
      "category": {...},
      "images": [...]
    }
  ],
  "meta": {
    "current_page": 1,
    "total_pages": 10,
    "total_items": 200
  }
}
```

### Get Product

```http
GET /api/v1/products/{id}
```

Response:
```json
{
  "data": {
    "id": 1,
    "name": "Product Name",
    "description": "Full product description",
    "price": 99.99,
    "sale_price": 79.99,
    "sku": "PROD-001",
    "status": "active",
    "stock_quantity": 100,
    "category": {
      "id": 5,
      "name": "Electronics"
    },
    "images": [...],
    "attributes": {...},
    "meta_data": {...}
  }
}
```

### Create Product

```http
POST /api/v1/products
```

Request:
```json
{
  "name": "New Product",
  "description": "Product description",
  "price": 149.99,
  "sku": "PROD-002",
  "category_id": 5,
  "stock_quantity": 50,
  "status": "active"
}
```

### Update Product

```http
PUT /api/v1/products/{id}
```

### Delete Product

```http
DELETE /api/v1/products/{id}
```

### Get Product Recommendations

```http
GET /api/v1/products/{id}/recommendations
```

Returns AI-powered product recommendations based on the specified product.

### Track Product View

```http
POST /api/v1/products/{id}/track-view
```

Track product view for analytics and recommendations.

## Category Management

### List Categories

```http
GET /api/v1/categories
```

### Get Category

```http
GET /api/v1/categories/{id}
```

### Category Performance Analytics

```http
GET /api/v1/categories/{id}/performance
```

Returns performance metrics for the category including sales, views, and conversion rates.

## Shopping Cart

### Get Cart

```http
GET /api/v1/cart
```

Response:
```json
{
  "data": {
    "id": "cart-uuid",
    "items": [
      {
        "id": 1,
        "product": {...},
        "quantity": 2,
        "price": 99.99,
        "subtotal": 199.98
      }
    ],
    "subtotal": 199.98,
    "tax": 20.00,
    "shipping": 10.00,
    "total": 229.98
  }
}
```

### Add Item to Cart

```http
POST /api/v1/cart/items
```

Request:
```json
{
  "product_id": 1,
  "quantity": 2,
  "options": {
    "size": "L",
    "color": "Blue"
  }
}
```

### Update Cart Item

```http
PUT /api/v1/cart/items/{id}
```

### Remove Cart Item

```http
DELETE /api/v1/cart/items/{id}
```

### Clear Cart

```http
POST /api/v1/cart/clear
```

### Get Cart Recommendations

```http
GET /api/v1/cart/recommendations
```

Returns personalized product recommendations based on cart contents.

### Cart Abandonment Prediction

```http
GET /api/v1/cart/abandonment-prediction
```

Returns AI-powered prediction of cart abandonment likelihood.

## Order Management

### List Orders

```http
GET /api/v1/orders
```

### Get Order

```http
GET /api/v1/orders/{id}
```

### Create Order

```http
POST /api/v1/orders
```

Request:
```json
{
  "customer_id": 123,
  "shipping_address": {...},
  "billing_address": {...},
  "items": [...],
  "payment_method": "stripe",
  "shipping_method": "standard"
}
```

### Update Order Status

```http
PUT /api/v1/orders/{id}/status
```

Request:
```json
{
  "status": "processing",
  "notify_customer": true
}
```

### Get Order Tracking

```http
GET /api/v1/orders/{id}/tracking
```

### Fulfill Order

```http
POST /api/v1/orders/{id}/fulfill
```

## Customer Management

### List Customers

```http
GET /api/v1/customers
```

### Get Customer

```http
GET /api/v1/customers/{id}
```

### Get Customer Profile

```http
GET /api/v1/customers/{id}/profile
```

Returns enriched customer profile with behavior analytics.

### Get Customer Recommendations

```http
GET /api/v1/customers/{id}/recommendations
```

### Get Customer Lifetime Value

```http
GET /api/v1/customers/{id}/lifetime-value
```

## Analytics & Insights

### Analytics Dashboard

```http
GET /api/v1/analytics/dashboard
```

Returns comprehensive analytics dashboard data.

### Sales Forecast

```http
GET /api/v1/analytics/sales-forecast
```

Returns AI-powered sales forecasting data.

### Customer Segments

```http
GET /api/v1/analytics/customer-segments
```

Returns customer segmentation analysis.

### Product Performance

```http
GET /api/v1/analytics/product-performance
```

## Search & Discovery

### Search Products

```http
GET /api/v1/search?q=laptop&filters[brand]=Apple
```

### Autocomplete

```http
GET /api/v1/search/autocomplete?q=lap
```

### Track Search

```http
POST /api/v1/search/track
```

### Get Trending

```http
GET /api/v1/search/trending
```

## Inventory Management

### Get Inventory

```http
GET /api/v1/inventory
```

### Get Low Stock Items

```http
GET /api/v1/inventory/low-stock
```

### Reorder Products

```http
POST /api/v1/inventory/reorder
```

### Get Inventory Forecast

```http
GET /api/v1/inventory/forecast
```

## Performance Monitoring

### Get Performance Metrics

```http
GET /api/v1/performance/metrics
```

### Health Check

```http
GET /api/v1/performance/health
```

### Optimize Performance

```http
POST /api/v1/performance/optimize
```

## Error Responses

All errors follow a consistent format:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "errors": {
      "name": ["The name field is required."],
      "price": ["The price must be greater than 0."]
    }
  }
}
```

Common error codes:
- `VALIDATION_ERROR`: Invalid input data
- `NOT_FOUND`: Resource not found
- `UNAUTHORIZED`: Authentication required
- `FORBIDDEN`: Insufficient permissions
- `RATE_LIMITED`: Too many requests
- `SERVER_ERROR`: Internal server error

## Rate Limiting

API requests are rate limited to:
- 1000 requests per hour for authenticated users
- 100 requests per hour for unauthenticated users

Rate limit headers:
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1609459200
```

## Webhooks

The Core Commerce plugin supports webhooks for real-time event notifications. Configure webhooks in the admin panel to receive notifications for:

- Product events (created, updated, deleted)
- Order events (created, paid, shipped, delivered)
- Customer events (registered, updated)
- Inventory events (low stock, out of stock)

Webhook payload example:
```json
{
  "event": "order.created",
  "timestamp": "2024-01-01T12:00:00Z",
  "data": {
    "order": {...}
  }
}