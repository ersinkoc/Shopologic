# Shopologic Architecture Overview

Shopologic is built with a modern, enterprise-grade architecture designed for scalability, maintainability, and zero external dependencies (except PSR standards).

## 🏗️ Architectural Principles

### Core Design Philosophy
1. **Zero Dependencies**: Pure PHP implementation with only PSR standards
2. **Plugin Architecture**: Microkernel with hot-swappable modules
3. **API-First**: REST and GraphQL APIs for all functionality
4. **Event-Driven**: Comprehensive hook system for extensibility
5. **Security-First**: Built-in security scanning and hardening
6. **Performance-Focused**: Multi-tier caching and optimization

### Architectural Patterns
- **Microkernel Architecture**: Core framework with plugin-based extensions
- **Dependency Injection**: PSR-11 compliant container with auto-wiring
- **Event Sourcing**: PSR-14 event dispatcher with hooks
- **Repository Pattern**: Data access abstraction
- **Service Layer**: Business logic separation
- **MVC Pattern**: Model-View-Controller structure

## 🏢 System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend Layer                           │
├─────────────────────────────────────────────────────────────┤
│  Web UI  │  Admin Panel  │  Mobile App  │  Third-party     │
└─────────────────────────────────────────────────────────────┘
                               │
┌─────────────────────────────────────────────────────────────┐
│                     API Layer                               │
├─────────────────────────────────────────────────────────────┤
│     REST API     │     GraphQL     │     WebSocket          │
└─────────────────────────────────────────────────────────────┘
                               │
┌─────────────────────────────────────────────────────────────┐
│                 Application Layer                           │
├─────────────────────────────────────────────────────────────┤
│  Controllers  │  Middleware  │  Validation  │  Auth         │
└─────────────────────────────────────────────────────────────┘
                               │
┌─────────────────────────────────────────────────────────────┐
│                  Business Layer                             │
├─────────────────────────────────────────────────────────────┤
│   Services   │   Models    │   Events    │   Plugins       │
└─────────────────────────────────────────────────────────────┘
                               │
┌─────────────────────────────────────────────────────────────┐
│                Infrastructure Layer                         │
├─────────────────────────────────────────────────────────────┤
│  Database  │  Cache  │  Queue  │  Storage  │  Search       │
└─────────────────────────────────────────────────────────────┘
```

## 🧩 Core Components

### 1. Kernel System
The application kernel bootstraps and manages the entire system:

```php
// Core application bootstrap
$app = new Application(SHOPOLOGIC_ROOT);
$app->boot();
$response = $app->handle($request);
```

**Key Features:**
- Service provider registration
- Plugin loading and management
- Request/response handling
- Error handling and logging

### 2. Dependency Injection Container
PSR-11 compliant container with advanced features:

```php
// Service registration
$container->bind(ServiceInterface::class, ServiceImplementation::class);
$container->singleton(CacheInterface::class, RedisCache::class);
$container->tag([Service1::class, Service2::class], 'tag.name');

// Service resolution
$service = $container->get(ServiceInterface::class);
$taggedServices = $container->tagged('tag.name');
```

**Features:**
- Auto-wiring
- Circular dependency detection
- Service tagging
- Lazy loading

### 3. Event System
PSR-14 compliant event dispatcher with WordPress-style hooks:

```php
// Register event listeners
$dispatcher->listen('order.created', OrderCreatedListener::class);

// Dispatch events
$dispatcher->dispatch(new OrderCreatedEvent($order));

// Hook system
HookSystem::addAction('order_created', $callback);
HookSystem::addFilter('product_price', $priceModifier);
```

### 4. HTTP Foundation
PSR-7 compliant HTTP abstraction:

```php
// Request handling
$request = Request::createFromGlobals();
$response = new JsonResponse($data);

// Middleware pipeline
$response = $middleware->process($request, $next);
```

### 5. Database Layer
Pure PHP PostgreSQL implementation:

```php
// Query builder
$users = DB::table('users')
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->paginate(20);

// ORM relationships
$product = Product::with(['category', 'images'])
    ->findOrFail($id);
```

## 🔌 Plugin Architecture

### Plugin System Overview
```
Core Framework
├── Plugin Manager
├── Plugin Repository
├── Hook System
└── Service Container
     │
     ├── Core Commerce Plugin
     ├── Payment Plugins (Stripe, PayPal)
     ├── Shipping Plugins (FedEx, UPS)
     ├── Marketing Plugins
     └── Custom Plugins
```

### Plugin Structure
```php
class MyPlugin extends AbstractPlugin
{
    protected function registerServices(): void
    {
        $this->container->bind(MyService::class);
    }
    
    protected function registerHooks(): void
    {
        HookSystem::addAction('order_created', [$this, 'handleOrder']);
        HookSystem::addFilter('product_price', [$this, 'modifyPrice']);
    }
    
    protected function registerRoutes(): void
    {
        $this->registerRoute('GET', '/api/my-plugin', [$this, 'endpoint']);
    }
}
```

### Plugin Manifest
```json
{
    "name": "my-plugin",
    "version": "1.0.0",
    "description": "My custom plugin",
    "main": "src/MyPlugin.php",
    "dependencies": ["core-commerce"],
    "permissions": ["manage_orders"],
    "hooks": ["order_created", "product_updated"]
}
```

## 🎨 Theme Architecture

### Theme System Components
```
Theme Engine
├── Template Parser (Twig-like)
├── Component System
├── Asset Manager
├── Live Editor
└── Theme Repository
```

### Template Engine
Pure PHP Twig-like template engine:

```twig
{# Base template #}
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}Shopologic{% endblock %}</title>
    {{ assets.styles() }}
</head>
<body>
    {% block content %}{% endblock %}
    {{ hook('body_end') }}
    {{ assets.scripts() }}
</body>
</html>

{# Page template #}
{% extends "layouts/base.twig" %}

{% block content %}
    <div class="product">
        <h1>{{ product.name }}</h1>
        {% if product.on_sale %}
            <span class="sale">On Sale!</span>
        {% endif %}
        
        {% for image in product.images %}
            <img src="{{ image.url }}" alt="{{ image.alt }}">
        {% endfor %}
    </div>
{% endblock %}
```

### Component System
```php
// Component registration
$componentManager->register('product-card', [
    'template' => 'components/product-card.twig',
    'settings' => [
        'show_price' => ['type' => 'boolean', 'default' => true],
        'show_description' => ['type' => 'boolean', 'default' => false]
    ]
]);

// Component usage
{{ component('product-card', {product: product, show_price: true}) }}
```

## 🗄️ Database Architecture

### Schema Design
```sql
-- Multi-tenant architecture
CREATE TABLE stores (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) UNIQUE,
    settings JSONB
);

-- All entities are store-aware
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    store_id INTEGER REFERENCES stores(id),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    price DECIMAL(10,2),
    data JSONB
);

-- Relationships
CREATE TABLE product_categories (
    product_id INTEGER REFERENCES products(id),
    category_id INTEGER REFERENCES categories(id),
    PRIMARY KEY (product_id, category_id)
);
```

### ORM Relationships
```php
class Product extends Model
{
    protected $fillable = ['name', 'slug', 'price'];
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
    
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
```

## 🚀 API Architecture

### REST API Design
Auto-generated RESTful endpoints following conventions:

```
GET    /api/v1/products           # List products
POST   /api/v1/products           # Create product
GET    /api/v1/products/{id}      # Get product
PUT    /api/v1/products/{id}      # Update product
DELETE /api/v1/products/{id}      # Delete product
```

### GraphQL Schema
```graphql
type Product {
    id: ID!
    name: String!
    slug: String!
    price: Float!
    category: Category
    images: [ProductImage!]!
    variants: [ProductVariant!]!
}

type Query {
    products(first: Int, after: String): ProductConnection!
    product(id: ID!): Product
}

type Mutation {
    createProduct(input: CreateProductInput!): Product!
    updateProduct(id: ID!, input: UpdateProductInput!): Product!
}
```

### API Middleware Stack
```php
$middleware = [
    CorsMiddleware::class,
    AuthenticationMiddleware::class,
    RateLimitMiddleware::class,
    ValidationMiddleware::class,
    ApiMiddleware::class
];
```

## 🔒 Security Architecture

### Security Layers
1. **Input Validation**: Request validation classes
2. **Authentication**: JWT, API keys, OAuth2
3. **Authorization**: Role-based access control
4. **Data Protection**: Encryption at rest
5. **Network Security**: HTTPS, security headers
6. **Monitoring**: Security scanning and alerts

### Security Components
```php
// Input validation
class CreateProductRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id'
        ];
    }
}

// Authorization
class ProductPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('products.create');
    }
    
    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission('products.update') && 
               $user->store_id === $product->store_id;
    }
}
```

## ⚡ Performance Architecture

### Caching Strategy
```
Application Cache
├── OpCode Cache (OPcache)
├── Application Cache (Redis/File)
├── Database Query Cache
├── HTTP Response Cache
└── CDN Cache
```

### Cache Implementation
```php
// Multi-tier caching
$cache->remember('products.featured', 3600, function() {
    return Product::where('featured', true)->get();
});

// Tagged cache
$cache->tags(['products', 'categories'])->put($key, $value);
$cache->tags(['products'])->flush();
```

### Queue System
```php
// Job dispatch
Queue::dispatch(new ProcessOrderJob($order));

// Job processing
class ProcessOrderJob implements Job
{
    public function handle(): void
    {
        // Process order asynchronously
    }
}
```

## 📊 Monitoring Architecture

### Performance Monitoring
```php
// Performance tracking
$monitor = new PerformanceMonitor();
$monitor->startTimer('database.query');
// ... database operation
$monitor->endTimer('database.query');

// Metrics collection
$monitor->increment('orders.created');
$monitor->gauge('memory.usage', memory_get_usage());
```

### Health Checks
```php
// System health endpoints
GET /health/live      # Liveness probe
GET /health/ready     # Readiness probe
GET /health/metrics   # Prometheus metrics
```

## 🔄 Request Lifecycle

1. **HTTP Request** → Web server (Apache/Nginx)
2. **URL Rewriting** → Route to appropriate front controller
3. **Bootstrap** → Load application and dependencies
4. **Middleware** → Authentication, rate limiting, CORS
5. **Routing** → Match URL to controller action
6. **Controller** → Handle business logic
7. **Service Layer** → Execute business operations
8. **Database** → Data persistence operations
9. **Response** → Format and return response
10. **Logging** → Record metrics and events

## 🌍 Multi-Store Architecture

### Store Isolation
```php
// Store detection middleware
class StoreDetectionMiddleware
{
    public function process(Request $request, Closure $next)
    {
        $store = $this->detectStore($request);
        app()->instance('current.store', $store);
        
        // Set database context
        DB::setDefaultStore($store->id);
        
        return $next($request);
    }
}

// Multi-tenant queries
Product::forStore($storeId)->where('active', true)->get();
```

## 📈 Scalability Considerations

### Horizontal Scaling
- **Load Balancers**: Distribute traffic across multiple servers
- **Database Sharding**: Partition data by store or geography
- **Microservices**: Split into independent services as needed
- **CDN Integration**: Global content distribution

### Vertical Scaling
- **OpCode Caching**: Reduce PHP compilation overhead
- **Connection Pooling**: Efficient database connections
- **Memory Optimization**: Efficient data structures
- **Query Optimization**: Database performance tuning

---

This architecture ensures Shopologic remains maintainable, scalable, and secure while providing enterprise-level features without external dependencies.