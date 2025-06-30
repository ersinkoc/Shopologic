# 🚀 Headless Commerce API Plugin

![Quality Badge](https://img.shields.io/badge/Quality-57%25%20(F)-red)


Modern headless commerce API platform providing comprehensive REST and GraphQL APIs for building custom storefronts, mobile apps, and omnichannel experiences with complete e-commerce functionality.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate Headless Commerce API
php cli/plugin.php activate headless-commerce-api
```

## ✨ Key Features

### 🔌 Comprehensive API Coverage
- **RESTful API** - Complete REST API with OpenAPI 3.0 specification
- **GraphQL API** - Flexible GraphQL schema with real-time subscriptions
- **Webhooks** - Event-driven architecture with webhook notifications
- **Batch Operations** - Bulk data processing and batch API requests
- **API Versioning** - Backward-compatible API versioning strategy

### 🛡️ Advanced Security
- **OAuth 2.0 Authentication** - Secure token-based authentication
- **API Key Management** - Multiple API key support with scopes
- **Rate Limiting** - Intelligent rate limiting and throttling
- **CORS Configuration** - Flexible cross-origin resource sharing
- **Request Signing** - HMAC-based request signature validation

### ⚡ Performance Optimization
- **Response Caching** - Multi-layer caching strategy
- **Field Selection** - GraphQL-style field filtering for REST
- **Pagination** - Cursor and offset-based pagination
- **Response Compression** - Automatic gzip/brotli compression
- **CDN Integration** - Edge caching and global distribution

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`HeadlessCommerceAPIPlugin.php`** - Core API engine and management

### Services
- **API Gateway** - Request routing and middleware management
- **Schema Manager** - GraphQL schema generation and validation
- **Authentication Service** - OAuth and API key authentication
- **Rate Limiter** - Request throttling and quota management
- **Webhook Manager** - Event subscription and delivery service

### Models
- **APIClient** - Client application registration and management
- **APIKey** - API key generation and permissions
- **WebhookSubscription** - Webhook endpoint configurations
- **RateLimitRule** - Rate limiting rules and quotas
- **APIVersion** - API version management and deprecation

### Controllers
- **REST Controllers** - RESTful endpoint implementations
- **GraphQL Resolver** - GraphQL query and mutation resolvers
- **Admin API** - API management and monitoring interface

## 🔌 RESTful API Implementation

### Comprehensive REST Endpoints

```php
// Advanced REST API implementation
$apiGateway = app(APIGateway::class);

// Product catalog REST endpoints
$productEndpoints = $apiGateway->registerRESTEndpoints([
    'resource' => 'products',
    'version' => 'v1',
    'endpoints' => [
        [
            'method' => 'GET',
            'path' => '/products',
            'handler' => 'ProductController@index',
            'description' => 'List all products with filtering and pagination',
            'parameters' => [
                'filter' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => ['type' => 'string', 'description' => 'Filter by category'],
                        'price_range' => ['type' => 'object', 'properties' => ['min' => 'number', 'max' => 'number']],
                        'availability' => ['type' => 'boolean', 'description' => 'Filter by stock availability'],
                        'attributes' => ['type' => 'object', 'description' => 'Filter by product attributes']
                    ]
                ],
                'sort' => [
                    'type' => 'string',
                    'enum' => ['name', 'price', 'created_at', 'popularity'],
                    'description' => 'Sort field'
                ],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'fields' => ['type' => 'string', 'description' => 'Comma-separated list of fields to include']
            ],
            'response' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Product']],
                        'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                        'links' => ['$ref' => '#/components/schemas/PaginationLinks']
                    ]
                ]
            ],
            'authentication' => ['required' => false],
            'rate_limit' => ['requests' => 1000, 'window' => '1_hour']
        ],
        [
            'method' => 'GET',
            'path' => '/products/{id}',
            'handler' => 'ProductController@show',
            'description' => 'Get single product details',
            'parameters' => [
                'id' => ['type' => 'string', 'required' => true, 'in' => 'path'],
                'include' => [
                    'type' => 'string',
                    'description' => 'Related resources to include',
                    'enum' => ['variants', 'images', 'reviews', 'related_products']
                ]
            ],
            'response_cache' => ['ttl' => 300, 'tags' => ['product_{id}']]
        ],
        [
            'method' => 'POST',
            'path' => '/products',
            'handler' => 'ProductController@create',
            'description' => 'Create new product',
            'request_body' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ProductCreateRequest']
                    ]
                ]
            ],
            'authentication' => ['required' => true, 'scopes' => ['products:write']],
            'validation' => [
                'name' => 'required|string|max:255',
                'sku' => 'required|unique:products',
                'price' => 'required|numeric|min:0',
                'category_id' => 'required|exists:categories,id'
            ]
        ]
    ],
    'middleware' => [
        'cors' => ['origins' => '*', 'methods' => ['GET', 'POST', 'PUT', 'DELETE']],
        'compression' => ['algorithms' => ['gzip', 'br']],
        'logging' => ['level' => 'info', 'include_body' => false]
    ]
]);

// Shopping cart REST endpoints with session handling
$cartEndpoints = $apiGateway->registerRESTEndpoints([
    'resource' => 'cart',
    'version' => 'v1',
    'endpoints' => [
        [
            'method' => 'GET',
            'path' => '/cart',
            'handler' => 'CartController@show',
            'description' => 'Get current cart contents',
            'authentication' => ['required' => false, 'optional' => true],
            'session_handling' => [
                'anonymous_cart' => true,
                'cart_token_header' => 'X-Cart-Token',
                'merge_on_login' => true
            ],
            'response_includes' => [
                'items' => ['product', 'variant', 'pricing'],
                'totals' => ['subtotal', 'tax', 'shipping', 'discounts', 'total'],
                'available_shipping_methods' => true,
                'applicable_coupons' => true
            ]
        ],
        [
            'method' => 'POST',
            'path' => '/cart/items',
            'handler' => 'CartController@addItem',
            'description' => 'Add item to cart',
            'request_validation' => [
                'product_id' => 'required|exists:products,id',
                'variant_id' => 'nullable|exists:product_variants,id',
                'quantity' => 'required|integer|min:1',
                'customization' => 'nullable|array'
            ],
            'business_logic' => [
                'stock_validation' => true,
                'price_calculation' => 'real_time',
                'promotion_application' => 'automatic',
                'bundle_detection' => true
            ]
        ],
        [
            'method' => 'PUT',
            'path' => '/cart/items/{item_id}',
            'handler' => 'CartController@updateItem',
            'description' => 'Update cart item quantity',
            'optimistic_locking' => [
                'enabled' => true,
                'version_header' => 'If-Match',
                'conflict_resolution' => 'last_write_wins'
            ]
        ]
    ]
]);

// Checkout API with payment processing
$checkoutEndpoints = $apiGateway->registerRESTEndpoints([
    'resource' => 'checkout',
    'version' => 'v1',
    'endpoints' => [
        [
            'method' => 'POST',
            'path' => '/checkout/sessions',
            'handler' => 'CheckoutController@createSession',
            'description' => 'Create checkout session',
            'request_body' => [
                'cart_id' => 'required|string',
                'customer_email' => 'required_without:customer_id|email',
                'customer_id' => 'required_without:customer_email|exists:customers,id',
                'shipping_address' => 'required|array',
                'billing_address' => 'nullable|array',
                'shipping_method_id' => 'required|string'
            ],
            'response' => [
                'session_id' => 'string',
                'expires_at' => 'datetime',
                'payment_intent_client_secret' => 'string',
                'order_summary' => 'object'
            ],
            'security' => [
                'pci_compliance' => 'saq_a',
                'payment_tokenization' => true,
                'fraud_detection' => 'real_time'
            ]
        ],
        [
            'method' => 'POST',
            'path' => '/checkout/sessions/{session_id}/complete',
            'handler' => 'CheckoutController@completeCheckout',
            'description' => 'Complete checkout and create order',
            'idempotency' => [
                'enabled' => true,
                'key_header' => 'Idempotency-Key',
                'ttl' => 86400 // 24 hours
            ],
            'distributed_transaction' => [
                'saga_pattern' => true,
                'compensating_actions' => true,
                'timeout' => 30000 // 30 seconds
            ]
        ]
    ]
]);
```

### GraphQL API Implementation

```php
// Advanced GraphQL schema and resolvers
$schemaManager = app(GraphQLSchemaManager::class);

// Define comprehensive GraphQL schema
$graphqlSchema = $schemaManager->buildSchema([
    'types' => [
        'Product' => [
            'fields' => [
                'id' => ['type' => 'ID!'],
                'name' => ['type' => 'String!'],
                'slug' => ['type' => 'String!'],
                'description' => ['type' => 'String'],
                'price' => ['type' => 'Money!'],
                'images' => ['type' => '[ProductImage!]!'],
                'variants' => ['type' => '[ProductVariant!]!'],
                'inventory' => ['type' => 'InventoryInfo'],
                'reviews' => [
                    'type' => 'ReviewConnection!',
                    'args' => [
                        'first' => ['type' => 'Int', 'defaultValue' => 10],
                        'after' => ['type' => 'String']
                    ]
                ],
                'relatedProducts' => [
                    'type' => '[Product!]!',
                    'resolve' => 'ProductResolver@relatedProducts'
                ]
            ],
            'interfaces' => ['Node', 'Timestamped']
        ],
        'Cart' => [
            'fields' => [
                'id' => ['type' => 'ID!'],
                'items' => ['type' => '[CartItem!]!'],
                'subtotal' => ['type' => 'Money!'],
                'tax' => ['type' => 'Money!'],
                'shipping' => ['type' => 'Money'],
                'discounts' => ['type' => '[Discount!]!'],
                'total' => ['type' => 'Money!'],
                'availableShippingMethods' => [
                    'type' => '[ShippingMethod!]!',
                    'resolve' => 'CartResolver@availableShippingMethods'
                ]
            ]
        ]
    ],
    'queries' => [
        'products' => [
            'type' => 'ProductConnection!',
            'args' => [
                'filter' => ['type' => 'ProductFilterInput'],
                'sort' => ['type' => 'ProductSortInput'],
                'first' => ['type' => 'Int', 'defaultValue' => 20],
                'after' => ['type' => 'String']
            ],
            'resolve' => 'ProductResolver@products',
            'complexity' => 'ConnectionComplexity::calculate'
        ],
        'product' => [
            'type' => 'Product',
            'args' => [
                'id' => ['type' => 'ID'],
                'slug' => ['type' => 'String']
            ],
            'resolve' => 'ProductResolver@product'
        ],
        'cart' => [
            'type' => 'Cart',
            'args' => [
                'id' => ['type' => 'ID!']
            ],
            'resolve' => 'CartResolver@cart',
            'directives' => ['@auth(optional: true)']
        ]
    ],
    'mutations' => [
        'addToCart' => [
            'type' => 'AddToCartPayload!',
            'args' => [
                'input' => ['type' => 'AddToCartInput!']
            ],
            'resolve' => 'CartResolver@addToCart',
            'validation' => [
                'input.productId' => 'required|exists:products,id',
                'input.quantity' => 'required|integer|min:1'
            ]
        ],
        'updateCartItem' => [
            'type' => 'UpdateCartItemPayload!',
            'args' => [
                'input' => ['type' => 'UpdateCartItemInput!']
            ],
            'resolve' => 'CartResolver@updateCartItem',
            'directives' => ['@rateLimit(max: 10, window: 60)']
        ],
        'checkout' => [
            'type' => 'CheckoutPayload!',
            'args' => [
                'input' => ['type' => 'CheckoutInput!']
            ],
            'resolve' => 'CheckoutResolver@checkout',
            'directives' => ['@auth', '@validateCheckout']
        ]
    ],
    'subscriptions' => [
        'orderStatusUpdated' => [
            'type' => 'Order!',
            'args' => [
                'orderId' => ['type' => 'ID!']
            ],
            'subscribe' => 'OrderSubscription@statusUpdated'
        ],
        'inventoryUpdated' => [
            'type' => 'Product!',
            'args' => [
                'productId' => ['type' => 'ID!']
            ],
            'subscribe' => 'InventorySubscription@updated'
        ]
    ],
    'directives' => [
        '@auth' => 'AuthDirective',
        '@rateLimit' => 'RateLimitDirective',
        '@cache' => 'CacheDirective',
        '@deprecated' => 'DeprecatedDirective'
    ]
]);

// GraphQL resolver implementation with DataLoader
$productResolver = $schemaManager->createResolver('Product', [
    'dataloader_enabled' => true,
    'batch_loading' => [
        'images' => 'ProductImageLoader',
        'variants' => 'ProductVariantLoader',
        'inventory' => 'InventoryLoader'
    ],
    'field_resolvers' => [
        'price' => function($product, $args, $context) {
            // Dynamic pricing based on customer context
            $pricingEngine = app(PricingEngine::class);
            return $pricingEngine->calculatePrice([
                'product_id' => $product->id,
                'customer_id' => $context->user?->id,
                'currency' => $context->currency,
                'includes_tax' => $context->region->includes_tax
            ]);
        },
        'relatedProducts' => function($product, $args, $context) {
            // AI-powered related products
            $recommendationEngine = app(RecommendationEngine::class);
            return $recommendationEngine->getRelatedProducts([
                'product_id' => $product->id,
                'limit' => $args['limit'] ?? 5,
                'customer_context' => $context->user
            ]);
        }
    ],
    'query_optimization' => [
        'eager_loading' => true,
        'select_fields' => true,
        'n_plus_one_detection' => true
    ]
]);
```

### Webhook System Implementation

```php
// Advanced webhook management system
$webhookManager = app(WebhookManager::class);

// Register webhook events
$webhookEvents = $webhookManager->registerWebhookEvents([
    'order.created' => [
        'description' => 'Triggered when a new order is created',
        'payload_schema' => [
            'order_id' => 'string',
            'customer_id' => 'string',
            'total_amount' => 'number',
            'currency' => 'string',
            'items' => 'array',
            'created_at' => 'datetime'
        ],
        'retry_policy' => [
            'max_attempts' => 5,
            'backoff_strategy' => 'exponential',
            'initial_delay' => 10, // seconds
            'max_delay' => 3600 // 1 hour
        ],
        'security' => [
            'signature_header' => 'X-Shopologic-Signature',
            'signature_algorithm' => 'sha256',
            'timestamp_tolerance' => 300 // 5 minutes
        ]
    ],
    'inventory.low_stock' => [
        'description' => 'Triggered when product inventory falls below threshold',
        'payload_schema' => [
            'product_id' => 'string',
            'variant_id' => 'string',
            'current_stock' => 'integer',
            'threshold' => 'integer',
            'warehouse_id' => 'string'
        ],
        'delivery_requirements' => [
            'guaranteed_delivery' => false,
            'deduplication' => true,
            'ordering' => 'best_effort'
        ]
    ],
    'customer.updated' => [
        'description' => 'Triggered when customer information is updated',
        'payload_transformation' => [
            'include_fields' => ['id', 'email', 'name', 'updated_fields'],
            'exclude_fields' => ['password_hash', 'payment_methods'],
            'custom_transformer' => 'CustomerWebhookTransformer'
        ]
    ]
]);

// Create webhook subscription
$webhookSubscription = $webhookManager->createSubscription([
    'client_id' => 'CLIENT_123',
    'endpoint_url' => 'https://partner.example.com/webhooks',
    'events' => ['order.created', 'order.shipped', 'order.cancelled'],
    'configuration' => [
        'active' => true,
        'secret_key' => Str::random(64),
        'headers' => [
            'X-Client-ID' => 'CLIENT_123',
            'X-Environment' => 'production'
        ],
        'timeout' => 30, // seconds
        'verify_ssl' => true
    ],
    'filtering' => [
        'order.created' => [
            'conditions' => [
                'total_amount' => ['operator' => '>', 'value' => 100],
                'shipping_country' => ['operator' => 'in', 'value' => ['US', 'CA']]
            ]
        ]
    ],
    'transformation' => [
        'format' => 'json',
        'compression' => 'gzip',
        'batch_enabled' => true,
        'batch_size' => 100,
        'batch_window' => 60 // seconds
    ]
]);

// Webhook delivery with circuit breaker
$webhookDelivery = $webhookManager->deliverWebhook([
    'subscription_id' => $webhookSubscription->id,
    'event' => 'order.created',
    'payload' => $orderData,
    'delivery_options' => [
        'circuit_breaker' => [
            'enabled' => true,
            'failure_threshold' => 5,
            'recovery_timeout' => 300, // 5 minutes
            'half_open_requests' => 3
        ],
        'monitoring' => [
            'track_latency' => true,
            'track_status_codes' => true,
            'alert_on_failure' => true
        ]
    ]
]);
```

## 🔗 Cross-Plugin Integration

### Integration with Authentication System

```php
// OAuth 2.0 implementation for headless commerce
$authenticationService = app(HeadlessAuthenticationService::class);

// Configure OAuth 2.0 server
$oauthConfiguration = $authenticationService->configureOAuth([
    'grant_types' => [
        'authorization_code' => [
            'enabled' => true,
            'code_lifetime' => 600, // 10 minutes
            'access_token_lifetime' => 3600, // 1 hour
            'refresh_token_lifetime' => 2592000 // 30 days
        ],
        'client_credentials' => [
            'enabled' => true,
            'access_token_lifetime' => 3600,
            'scope_required' => true
        ],
        'password' => [
            'enabled' => false // Disabled for security
        ],
        'refresh_token' => [
            'enabled' => true,
            'refresh_token_lifetime' => 2592000
        ]
    ],
    'scopes' => [
        'read:products' => 'Read product catalog',
        'write:products' => 'Manage products',
        'read:orders' => 'Read order information',
        'write:orders' => 'Create and manage orders',
        'read:customers' => 'Read customer information',
        'write:customers' => 'Manage customer data'
    ],
    'token_storage' => 'redis',
    'jwt_configuration' => [
        'algorithm' => 'RS256',
        'public_key_path' => '/keys/oauth-public.key',
        'private_key_path' => '/keys/oauth-private.key'
    ]
]);

// API key authentication for server-to-server
$apiKeyAuth = $authenticationService->configureAPIKeyAuth([
    'header_name' => 'X-API-Key',
    'key_format' => 'sk_live_[a-zA-Z0-9]{32}',
    'rate_limiting' => [
        'default_limit' => 1000,
        'window' => '1_hour',
        'burst_allowance' => 100
    ],
    'ip_whitelist_enabled' => true,
    'key_rotation' => [
        'enabled' => true,
        'rotation_period' => '90_days',
        'notification_period' => '14_days'
    ]
]);
```

### Integration with Analytics

```php
// API analytics and monitoring
$analyticsProvider = app()->get(AnalyticsProviderInterface::class);

// Track API usage metrics
$apiAnalytics = $analyticsProvider->trackAPIUsage([
    'api_type' => 'headless_commerce',
    'metrics' => [
        'request_count' => true,
        'response_time' => true,
        'error_rate' => true,
        'bandwidth_usage' => true,
        'unique_clients' => true
    ],
    'dimensions' => [
        'endpoint' => true,
        'http_method' => true,
        'api_version' => true,
        'client_id' => true,
        'response_code' => true
    ],
    'real_time_monitoring' => [
        'dashboard_enabled' => true,
        'alerting_rules' => [
            'high_error_rate' => ['threshold' => 0.05, 'window' => '5_minutes'],
            'slow_response_time' => ['threshold' => 2000, 'percentile' => 95],
            'rate_limit_exceeded' => ['threshold' => 10, 'window' => '1_minute']
        ]
    ]
]);

// GraphQL specific analytics
$graphqlAnalytics = $analyticsProvider->trackGraphQLUsage([
    'query_complexity' => true,
    'field_usage' => true,
    'resolver_performance' => true,
    'query_depth' => true,
    'deprecated_field_usage' => true
]);
```

## ⚡ Real-Time API Events

### API Event Processing

```php
// Process headless commerce API events
$eventDispatcher = PluginEventDispatcher::getInstance();

$eventDispatcher->listen('api.request.received', function($event) {
    $requestData = $event->getData();
    
    // API request logging
    $apiLogger = app(APIRequestLogger::class);
    $apiLogger->logRequest([
        'request_id' => $requestData['request_id'],
        'method' => $requestData['method'],
        'path' => $requestData['path'],
        'client_id' => $requestData['client_id'],
        'timestamp' => $requestData['timestamp']
    ]);
    
    // Rate limiting check
    $rateLimiter = app(RateLimiter::class);
    $rateLimitResult = $rateLimiter->checkLimit([
        'client_id' => $requestData['client_id'],
        'endpoint' => $requestData['path'],
        'method' => $requestData['method']
    ]);
    
    if (!$rateLimitResult->allowed) {
        throw new RateLimitExceededException($rateLimitResult);
    }
});

$eventDispatcher->listen('api.response.sent', function($event) {
    $responseData = $event->getData();
    
    // Performance monitoring
    $performanceMonitor = app(APIPerformanceMonitor::class);
    $performanceMonitor->recordMetrics([
        'request_id' => $responseData['request_id'],
        'response_time' => $responseData['response_time'],
        'response_size' => $responseData['response_size'],
        'cache_hit' => $responseData['cache_hit'],
        'database_queries' => $responseData['database_query_count']
    ]);
    
    // Usage tracking for billing
    $usageTracker = app(APIUsageTracker::class);
    $usageTracker->trackUsage([
        'client_id' => $responseData['client_id'],
        'endpoint' => $responseData['endpoint'],
        'response_code' => $responseData['status_code'],
        'billable_operations' => $responseData['operation_count']
    ]);
});
```

## 🧪 Testing Framework Integration

### Headless Commerce API Test Coverage

```php
class HeadlessCommerceAPITestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_rest_api_endpoints' => [$this, 'testRESTAPIEndpoints'],
            'test_graphql_queries' => [$this, 'testGraphQLQueries'],
            'test_authentication_flow' => [$this, 'testAuthenticationFlow'],
            'test_webhook_delivery' => [$this, 'testWebhookDelivery']
        ];
    }
    
    public function testRESTAPIEndpoints(): void
    {
        $apiClient = new APITestClient();
        
        // Test product listing
        $response = $apiClient->get('/api/v1/products', [
            'filter' => ['category' => 'electronics'],
            'sort' => 'price',
            'limit' => 10
        ]);
        
        Assert::assertEquals(200, $response->status());
        Assert::assertCount(10, $response->json('data'));
        Assert::assertNotNull($response->json('meta.total'));
    }
    
    public function testGraphQLQueries(): void
    {
        $graphqlClient = new GraphQLTestClient();
        
        $query = '
            query GetProduct($id: ID!) {
                product(id: $id) {
                    id
                    name
                    price {
                        amount
                        currency
                    }
                    inventory {
                        available
                        quantity
                    }
                }
            }
        ';
        
        $response = $graphqlClient->query($query, ['id' => 'PROD123']);
        
        Assert::assertNull($response->errors);
        Assert::assertNotNull($response->data['product']);
        Assert::assertEquals('PROD123', $response->data['product']['id']);
    }
}
```

## 🛠️ Configuration

### Headless Commerce API Settings

```json
{
    "api_configuration": {
        "rest_api_enabled": true,
        "graphql_api_enabled": true,
        "api_versioning": "uri",
        "default_version": "v1",
        "deprecation_period": "6_months"
    },
    "authentication": {
        "oauth2_enabled": true,
        "api_key_enabled": true,
        "jwt_enabled": true,
        "session_lifetime": 3600,
        "refresh_token_enabled": true
    },
    "rate_limiting": {
        "enabled": true,
        "default_limit": 1000,
        "window": "1_hour",
        "headers_included": true,
        "redis_backend": true
    },
    "caching": {
        "response_cache_enabled": true,
        "cache_ttl": 300,
        "cache_backend": "redis",
        "etag_support": true,
        "vary_headers": ["Accept", "Accept-Language"]
    },
    "webhooks": {
        "max_retries": 5,
        "timeout": 30,
        "signature_algorithm": "sha256",
        "event_retention_days": 30
    }
}
```

### Database Tables
- `api_clients` - OAuth client registrations
- `api_keys` - API key management
- `webhook_subscriptions` - Webhook endpoint configurations
- `api_rate_limits` - Rate limiting rules
- `api_usage_logs` - API usage tracking

## 📚 API Endpoints

### Core REST Endpoints
- `GET /api/v1/products` - List products
- `GET /api/v1/products/{id}` - Get product details
- `GET /api/v1/categories` - List categories
- `GET /api/v1/cart` - Get cart contents
- `POST /api/v1/cart/items` - Add to cart
- `POST /api/v1/checkout` - Create order

### GraphQL Endpoint
- `POST /graphql` - GraphQL queries and mutations
- `GET /graphql` - GraphQL playground (development only)

### Webhook Management
- `GET /api/v1/webhooks` - List webhook subscriptions
- `POST /api/v1/webhooks` - Create webhook subscription
- `DELETE /api/v1/webhooks/{id}` - Delete webhook subscription

### Usage Examples

```bash
# REST API - Get products
curl -X GET https://api.example.com/api/v1/products \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# GraphQL - Query products
curl -X POST https://api.example.com/graphql \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{"query": "{ products(first: 10) { edges { node { id name price } } } }"}'

# Create webhook subscription
curl -X POST https://api.example.com/api/v1/webhooks \
  -H "Content-Type: application/json" \
  -H "X-API-Key: {api_key}" \
  -d '{"url": "https://example.com/webhook", "events": ["order.created"]}'
```

## 🔧 Installation

### Requirements
- PHP 8.3+
- Redis for caching and rate limiting
- PostgreSQL with JSON support
- API documentation tools

### Setup

```bash
# Activate plugin
php cli/plugin.php activate headless-commerce-api

# Run migrations
php cli/migrate.php up

# Generate API documentation
php cli/api.php generate-docs

# Create OAuth keys
php cli/api.php generate-oauth-keys
```

## 📖 Documentation

- **API Reference** - Complete REST and GraphQL API documentation
- **Authentication Guide** - OAuth 2.0 and API key authentication
- **Webhook Integration** - Setting up and managing webhooks
- **Client SDKs** - JavaScript, Python, and PHP client libraries

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Comprehensive REST and GraphQL APIs
- ✅ Cross-plugin integration for complete commerce functionality
- ✅ Advanced authentication and security features
- ✅ Scalable architecture with caching and rate limiting
- ✅ Complete testing framework integration
- ✅ Enterprise-grade API management

---

**Headless Commerce API** - Modern API platform for Shopologic