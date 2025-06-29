# API Reference

Shopologic provides comprehensive REST and GraphQL APIs for all platform functionality. This reference covers authentication, endpoints, and usage examples.

## 🌐 API Overview

### Base URLs
- **REST API**: `https://your-domain.com/api/v1`
- **GraphQL**: `https://your-domain.com/graphql`

### API Versions
- **v1**: Current stable version
- **v2**: Beta (coming soon)

### Response Format
All API responses follow a consistent JSON format:

```json
{
  "data": {},
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "version": "1.0.0"
  },
  "errors": []
}
```

## 🔐 Authentication

### Authentication Methods

#### 1. JWT Token Authentication
```bash
# Login to get token
curl -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'

# Response
{
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_at": "2024-01-15T11:30:00Z",
    "user": {
      "id": 1,
      "email": "user@example.com",
      "role": "admin"
    }
  }
}

# Use token in subsequent requests
curl -X GET https://your-domain.com/api/v1/products \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

#### 2. API Key Authentication
```bash
# Generate API key in admin panel
curl -X GET https://your-domain.com/api/v1/products \
  -H "X-API-Key: your-api-key"
```

#### 3. OAuth 2.0
```bash
# Authorization URL
https://your-domain.com/oauth/authorize?client_id=CLIENT_ID&response_type=code&scope=read_products&redirect_uri=REDIRECT_URI

# Exchange code for token
curl -X POST https://your-domain.com/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "authorization_code",
    "client_id": "CLIENT_ID",
    "client_secret": "CLIENT_SECRET",
    "code": "AUTHORIZATION_CODE",
    "redirect_uri": "REDIRECT_URI"
  }'
```

### Refresh Tokens
```bash
# Refresh expired JWT token
curl -X POST https://your-domain.com/api/v1/auth/refresh \
  -H "Authorization: Bearer expired-token"
```

## 📦 Products API

### List Products
```bash
GET /api/v1/products
```

**Parameters:**
- `page` (int): Page number (default: 1)
- `per_page` (int): Items per page (default: 20, max: 100)
- `category` (string): Filter by category slug
- `status` (string): Filter by status (active, inactive, draft)
- `search` (string): Search in name and description
- `sort` (string): Sort field (name, price, created_at)
- `direction` (string): Sort direction (asc, desc)
- `price_min` (float): Minimum price filter
- `price_max` (float): Maximum price filter

**Example:**
```bash
curl -X GET "https://your-domain.com/api/v1/products?category=electronics&sort=price&direction=asc&per_page=50" \
  -H "Authorization: Bearer your-token"
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "slug": "iphone-15-pro",
      "description": "Latest iPhone with titanium design",
      "short_description": "Premium smartphone",
      "sku": "APPLE-IP15P-128",
      "price": 999.00,
      "compare_price": 1099.00,
      "cost_price": 700.00,
      "track_quantity": true,
      "quantity": 50,
      "weight": 187,
      "dimensions": {
        "length": 146.6,
        "width": 70.6,
        "height": 8.25
      },
      "status": "active",
      "featured": true,
      "category": {
        "id": 2,
        "name": "Smartphones",
        "slug": "smartphones"
      },
      "images": [
        {
          "id": 1,
          "url": "https://your-domain.com/uploads/iphone-15-pro-1.jpg",
          "alt": "iPhone 15 Pro front view",
          "sort_order": 1
        }
      ],
      "variants": [
        {
          "id": 1,
          "name": "128GB Space Black",
          "sku": "APPLE-IP15P-128-SB",
          "price": 999.00,
          "quantity": 25
        }
      ],
      "attributes": [
        {
          "name": "Color",
          "value": "Space Black"
        },
        {
          "name": "Storage",
          "value": "128GB"
        }
      ],
      "seo": {
        "title": "iPhone 15 Pro - Premium Smartphone",
        "description": "Experience the latest iPhone with titanium design and advanced features",
        "keywords": ["iphone", "smartphone", "apple", "titanium"]
      },
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T11:15:00Z"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 150,
      "total_pages": 8,
      "has_more": true
    },
    "filters": {
      "applied": {
        "category": "electronics"
      },
      "available": {
        "categories": ["electronics", "clothing", "books"],
        "brands": ["Apple", "Samsung", "Google"],
        "price_range": {
          "min": 10.00,
          "max": 2999.00
        }
      }
    }
  }
}
```

### Get Product
```bash
GET /api/v1/products/{id}
```

**Example:**
```bash
curl -X GET https://your-domain.com/api/v1/products/1 \
  -H "Authorization: Bearer your-token"
```

### Create Product
```bash
POST /api/v1/products
```

**Request Body:**
```json
{
  "name": "New Product",
  "slug": "new-product",
  "description": "Product description",
  "short_description": "Short description",
  "sku": "NEW-PROD-001",
  "price": 99.99,
  "compare_price": 149.99,
  "cost_price": 50.00,
  "track_quantity": true,
  "quantity": 100,
  "weight": 500,
  "category_id": 1,
  "status": "active",
  "featured": false,
  "images": [
    {
      "url": "https://example.com/image1.jpg",
      "alt": "Product image 1",
      "sort_order": 1
    }
  ],
  "variants": [
    {
      "name": "Red - Medium",
      "sku": "NEW-PROD-001-R-M",
      "price": 99.99,
      "quantity": 50,
      "attributes": [
        {"name": "Color", "value": "Red"},
        {"name": "Size", "value": "Medium"}
      ]
    }
  ],
  "seo": {
    "title": "New Product - Amazing Features",
    "description": "This new product has amazing features",
    "keywords": ["new", "product", "amazing"]
  }
}
```

### Update Product
```bash
PUT /api/v1/products/{id}
PATCH /api/v1/products/{id}
```

**Example:**
```bash
curl -X PUT https://your-domain.com/api/v1/products/1 \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d '{"price": 899.99, "quantity": 75}'
```

### Delete Product
```bash
DELETE /api/v1/products/{id}
```

## 🛒 Orders API

### List Orders
```bash
GET /api/v1/orders
```

**Parameters:**
- `status` (string): Filter by status
- `customer_id` (int): Filter by customer
- `date_from` (date): Start date filter
- `date_to` (date): End date filter
- `payment_status` (string): Filter by payment status
- `fulfillment_status` (string): Filter by fulfillment status

**Example:**
```bash
curl -X GET "https://your-domain.com/api/v1/orders?status=processing&date_from=2024-01-01" \
  -H "Authorization: Bearer your-token"
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "order_number": "ORD-001",
      "status": "processing",
      "payment_status": "paid",
      "fulfillment_status": "unfulfilled",
      "customer": {
        "id": 1,
        "email": "customer@example.com",
        "first_name": "John",
        "last_name": "Doe"
      },
      "billing_address": {
        "first_name": "John",
        "last_name": "Doe",
        "company": "Example Corp",
        "address_1": "123 Main St",
        "address_2": "Apt 4B",
        "city": "New York",
        "state": "NY",
        "postal_code": "10001",
        "country": "US",
        "phone": "+1234567890"
      },
      "shipping_address": {
        "first_name": "John",
        "last_name": "Doe",
        "address_1": "123 Main St",
        "city": "New York",
        "state": "NY",
        "postal_code": "10001",
        "country": "US"
      },
      "line_items": [
        {
          "id": 1,
          "product_id": 1,
          "variant_id": 1,
          "quantity": 2,
          "price": 999.00,
          "total": 1998.00,
          "product": {
            "id": 1,
            "name": "iPhone 15 Pro",
            "sku": "APPLE-IP15P-128"
          }
        }
      ],
      "subtotal": 1998.00,
      "tax_amount": 159.84,
      "shipping_amount": 9.99,
      "discount_amount": 0.00,
      "total_amount": 2167.83,
      "currency": "USD",
      "notes": "Customer requested expedited shipping",
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T11:15:00Z"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 50,
      "total_pages": 3
    },
    "statistics": {
      "total_value": 125000.00,
      "average_value": 2500.00,
      "order_count": 50
    }
  }
}
```

### Create Order
```bash
POST /api/v1/orders
```

**Request Body:**
```json
{
  "customer_id": 1,
  "line_items": [
    {
      "product_id": 1,
      "variant_id": 1,
      "quantity": 2,
      "price": 999.00
    }
  ],
  "billing_address": {
    "first_name": "John",
    "last_name": "Doe",
    "address_1": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postal_code": "10001",
    "country": "US"
  },
  "shipping_address": {
    "first_name": "John",
    "last_name": "Doe",
    "address_1": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postal_code": "10001",
    "country": "US"
  },
  "shipping_method": "standard",
  "payment_method": "stripe",
  "notes": "Special delivery instructions"
}
```

### Update Order Status
```bash
PATCH /api/v1/orders/{id}/status
```

**Request Body:**
```json
{
  "status": "fulfilled",
  "notify_customer": true,
  "tracking_number": "1Z999AA1234567890",
  "tracking_url": "https://tracking.example.com/1Z999AA1234567890"
}
```

## 👥 Customers API

### List Customers
```bash
GET /api/v1/customers
```

**Parameters:**
- `search` (string): Search in name and email
- `created_after` (date): Filter by creation date
- `tags` (array): Filter by customer tags

### Get Customer
```bash
GET /api/v1/customers/{id}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "email": "customer@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+1234567890",
    "date_of_birth": "1990-01-15",
    "accepts_marketing": true,
    "email_verified_at": "2024-01-15T10:30:00Z",
    "tags": ["vip", "wholesale"],
    "addresses": [
      {
        "id": 1,
        "type": "shipping",
        "first_name": "John",
        "last_name": "Doe",
        "company": "Example Corp",
        "address_1": "123 Main St",
        "city": "New York",
        "state": "NY",
        "postal_code": "10001",
        "country": "US",
        "is_default": true
      }
    ],
    "orders_count": 15,
    "total_spent": 15000.00,
    "average_order_value": 1000.00,
    "last_order_at": "2024-01-10T14:30:00Z",
    "created_at": "2023-06-15T10:30:00Z",
    "updated_at": "2024-01-15T11:15:00Z"
  }
}
```

## 📊 Analytics API

### Sales Analytics
```bash
GET /api/v1/analytics/sales
```

**Parameters:**
- `period` (string): Time period (today, week, month, year, custom)
- `start_date` (date): Start date for custom period
- `end_date` (date): End date for custom period
- `group_by` (string): Grouping (day, week, month)

**Response:**
```json
{
  "data": {
    "summary": {
      "total_sales": 125000.00,
      "order_count": 500,
      "average_order_value": 250.00,
      "conversion_rate": 3.5
    },
    "chart_data": [
      {
        "date": "2024-01-15",
        "sales": 5000.00,
        "orders": 20,
        "customers": 18
      }
    ],
    "top_products": [
      {
        "product_id": 1,
        "name": "iPhone 15 Pro",
        "sales": 25000.00,
        "quantity": 25
      }
    ]
  }
}
```

### Traffic Analytics
```bash
GET /api/v1/analytics/traffic
```

## 🛍️ Cart API

### Get Cart
```bash
GET /api/v1/cart/{session_id}
```

### Add to Cart
```bash
POST /api/v1/cart/{session_id}/items
```

**Request Body:**
```json
{
  "product_id": 1,
  "variant_id": 1,
  "quantity": 2,
  "properties": {
    "engraving": "Happy Birthday"
  }
}
```

### Update Cart Item
```bash
PATCH /api/v1/cart/{session_id}/items/{item_id}
```

### Remove from Cart
```bash
DELETE /api/v1/cart/{session_id}/items/{item_id}
```

## 🔍 Search API

### Product Search
```bash
GET /api/v1/search/products
```

**Parameters:**
- `q` (string): Search query
- `filters` (object): Search filters
- `facets` (array): Requested facets
- `sort` (string): Sort order

**Example:**
```bash
curl -X GET "https://your-domain.com/api/v1/search/products?q=smartphone&filters[category]=electronics&facets[]=brand&facets[]=price" \
  -H "Authorization: Bearer your-token"
```

**Response:**
```json
{
  "data": {
    "products": [
      {
        "id": 1,
        "name": "iPhone 15 Pro",
        "price": 999.00,
        "image": "https://your-domain.com/uploads/iphone-15-pro.jpg",
        "score": 0.95
      }
    ],
    "facets": {
      "brand": [
        {"value": "Apple", "count": 25},
        {"value": "Samsung", "count": 18}
      ],
      "price": [
        {"range": "0-100", "count": 5},
        {"range": "100-500", "count": 15},
        {"range": "500-1000", "count": 12}
      ]
    },
    "total": 50,
    "took": 15
  }
}
```

## 🎨 GraphQL API

### GraphQL Endpoint
```
POST /graphql
```

### Schema Introspection
```graphql
query {
  __schema {
    types {
      name
      description
    }
  }
}
```

### Product Queries
```graphql
# Get products with relationships
query GetProducts($first: Int!, $after: String) {
  products(first: $first, after: $after) {
    edges {
      node {
        id
        name
        slug
        price
        category {
          name
          slug
        }
        images {
          url
          alt
        }
        variants {
          id
          name
          price
          inStock
        }
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

### Order Mutations
```graphql
# Create order
mutation CreateOrder($input: CreateOrderInput!) {
  createOrder(input: $input) {
    id
    orderNumber
    status
    total
    customer {
      email
    }
    lineItems {
      product {
        name
      }
      quantity
      price
    }
  }
}
```

**Variables:**
```json
{
  "input": {
    "customerId": 1,
    "lineItems": [
      {
        "productId": 1,
        "variantId": 1,
        "quantity": 2
      }
    ],
    "shippingAddress": {
      "firstName": "John",
      "lastName": "Doe",
      "address1": "123 Main St",
      "city": "New York",
      "state": "NY",
      "postalCode": "10001",
      "country": "US"
    }
  }
}
```

### Subscriptions (WebSocket)
```graphql
# Subscribe to order updates
subscription OrderUpdates($orderId: ID!) {
  orderUpdated(orderId: $orderId) {
    id
    status
    trackingNumber
    updatedAt
  }
}
```

## 📝 Webhooks

### Register Webhook
```bash
POST /api/v1/webhooks
```

**Request Body:**
```json
{
  "url": "https://your-app.com/webhook",
  "events": ["order.created", "order.updated", "product.created"],
  "secret": "your-webhook-secret"
}
```

### Webhook Events
Available webhook events:
- `order.created`
- `order.updated`
- `order.paid`
- `order.fulfilled`
- `order.cancelled`
- `product.created`
- `product.updated`
- `product.deleted`
- `customer.created`
- `customer.updated`

### Webhook Payload
```json
{
  "event": "order.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "id": 1,
    "order_number": "ORD-001",
    "status": "pending",
    "total": 999.00,
    "customer": {
      "id": 1,
      "email": "customer@example.com"
    }
  }
}
```

### Webhook Verification
```php
// Verify webhook signature
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'];
$expected = hash_hmac('sha256', $payload, $webhook_secret);

if (!hash_equals($signature, $expected)) {
    http_response_code(401);
    exit('Invalid signature');
}
```

## ⚡ Rate Limiting

### Rate Limits
- **Authenticated requests**: 1000 requests per hour
- **Unauthenticated requests**: 100 requests per hour
- **GraphQL**: 500 requests per hour

### Rate Limit Headers
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1642248000
```

### Rate Limit Response
```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Rate limit exceeded. Try again in 60 seconds.",
    "retry_after": 60
  }
}
```

## 🚫 Error Handling

### Error Response Format
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."],
      "price": ["The price must be a number."]
    }
  }
}
```

### Common Error Codes
- `VALIDATION_ERROR` (400): Invalid input data
- `UNAUTHORIZED` (401): Authentication required
- `FORBIDDEN` (403): Insufficient permissions
- `NOT_FOUND` (404): Resource not found
- `RATE_LIMIT_EXCEEDED` (429): Rate limit exceeded
- `INTERNAL_ERROR` (500): Server error

## 📱 SDK Examples

### JavaScript/Node.js
```javascript
// Install: npm install @shopologic/sdk

import Shopologic from '@shopologic/sdk';

const client = new Shopologic({
  apiUrl: 'https://your-domain.com/api/v1',
  token: 'your-jwt-token'
});

// Get products
const products = await client.products.list({
  category: 'electronics',
  limit: 20
});

// Create order
const order = await client.orders.create({
  customer_id: 1,
  line_items: [
    { product_id: 1, quantity: 2 }
  ]
});
```

### PHP
```php
// Install: composer require shopologic/sdk

use Shopologic\SDK\Client;

$client = new Client([
    'api_url' => 'https://your-domain.com/api/v1',
    'token' => 'your-jwt-token'
]);

// Get products
$products = $client->products()->list([
    'category' => 'electronics',
    'limit' => 20
]);

// Create order
$order = $client->orders()->create([
    'customer_id' => 1,
    'line_items' => [
        ['product_id' => 1, 'quantity' => 2]
    ]
]);
```

### Python
```python
# Install: pip install shopologic-sdk

from shopologic import Shopologic

client = Shopologic(
    api_url='https://your-domain.com/api/v1',
    token='your-jwt-token'
)

# Get products
products = client.products.list(
    category='electronics',
    limit=20
)

# Create order
order = client.orders.create({
    'customer_id': 1,
    'line_items': [
        {'product_id': 1, 'quantity': 2}
    ]
})
```

---

This comprehensive API reference covers all major endpoints and features. For more specific use cases, check the [Examples section](./api-examples.md) or explore the interactive API documentation at `https://your-domain.com/api/docs`.