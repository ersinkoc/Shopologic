# CODEBASE.md - Complete Shopologic PHP Application Documentation

**Estimated Reading Time**: 45 minutes

## Table of Contents

1. [Project Overview](#project-overview)
2. [Directory Structure](#directory-structure)
3. [Core Architecture](#core-architecture)
4. [File Inventory](#file-inventory)
5. [Data Flow](#data-flow)
6. [Database Schema](#database-schema)
7. [API Endpoints](#api-endpoints)
8. [Configuration Guide](#configuration-guide)
9. [Dependencies](#dependencies)
10. [Development Workflow](#development-workflow)
11. [Plugin System](#plugin-system)
12. [Theme System](#theme-system)
13. [Quick Start Guide](#quick-start-guide)
14. [Troubleshooting](#troubleshooting)

## Project Overview

**Application**: Shopologic - Enterprise E-commerce Platform  
**Technology Stack**: PHP 8.3+  
**Architecture**: Microkernel with Plugin System  
**Database Support**: PostgreSQL, MySQL, SQLite  
**External Dependencies**: None (except PSR standards)  

### Main Features
- Multi-store capabilities with complete data isolation
- Plugin-based extensibility (WordPress-style hooks)
- Built-in theme engine with live editor
- REST and GraphQL API support
- Comprehensive admin panel
- Zero external dependencies architecture

### Target Audience
- Mid to large-scale e-commerce businesses
- Multi-brand retail operations
- Marketplace platforms
- B2B and B2C commerce

## Directory Structure

```
/Shopologic
├── /cli                    → Command line tools for development and maintenance
│   ├── backup.php         → Database and file backup utility
│   ├── cache.php          → Cache management (clear, warm, optimize)
│   ├── migrate.php        → Database migration runner
│   ├── plugin.php         → Plugin management (install, activate, generate)
│   ├── seed.php           → Database seeder
│   └── test.php           → Test runner with coverage support
│
├── /config                → Application-wide configuration files
│   ├── database.php       → Database connection settings
│   ├── multistore.php     → Multi-store configuration
│   └── backup.php         → Backup settings
│
├── /core                  → Framework foundation (custom-built, no dependencies)
│   ├── bootstrap.php      → Application initialization
│   ├── /config           → Core configuration defaults
│   └── /src              → Core framework classes
│       ├── /API          → REST and GraphQL implementation
│       ├── /Admin        → Admin panel framework
│       ├── /Auth         → Authentication and authorization
│       ├── /Cache        → Caching layer
│       ├── /Container    → Dependency injection container
│       ├── /Database     → ORM and query builder
│       ├── /Ecommerce    → Core e-commerce models
│       ├── /Events       → Event dispatcher (PSR-14)
│       ├── /Http         → HTTP layer (PSR-7)
│       ├── /Kernel       → Application kernel
│       ├── /Plugin       → Plugin system
│       ├── /Router       → HTTP routing
│       └── /Theme        → Template engine
│
├── /database              → Database schema management
│   ├── /migrations       → Schema version control
│   ├── /schemas          → Schema documentation
│   └── /seeds            → Test data seeders
│
├── /plugins               → Modular functionality extensions
│   ├── /core-commerce    → Core e-commerce functionality
│   ├── /payment-stripe   → Stripe payment integration
│   ├── /shipping-fedex   → FedEx shipping integration
│   └── /HelloWorld       → Example plugin
│
├── /public                → Web-accessible files (document root)
│   ├── index.php         → Storefront entry point
│   ├── api.php           → API entry point
│   └── admin.php         → Admin panel entry point
│
├── /storage               → Runtime storage (gitignored)
│   ├── /cache            → Application cache
│   ├── /logs             → Application logs
│   ├── /sessions         → PHP sessions
│   └── /uploads          → User uploads
│
├── /tests                 → Test suites
│   ├── /Unit             → Unit tests
│   ├── /Integration      → Integration tests
│   └── /E2E              → End-to-end tests
│
└── /themes                → Frontend templates
    └── /default          → Default theme
        ├── /assets       → CSS, JS, images
        ├── /components   → Reusable components
        └── /templates    → Page templates
```

### Directory Naming Conventions
- **Lowercase with hyphens**: Plugin directories (e.g., `payment-stripe`)
- **PascalCase**: PHP namespace directories (e.g., `/Container`, `/Database`)
- **Lowercase**: Configuration and public directories
- **Singular**: Model directories, Plural: Collection directories

## Core Architecture

### Architectural Patterns

1. **Microkernel Architecture**
   - Minimal core with essential services
   - Functionality extended through plugins
   - Hot-swappable components

2. **Service-Oriented Architecture (SOA)**
   - Everything is a service in the container
   - Services communicate through interfaces
   - Loose coupling between components

3. **Event-Driven Architecture**
   - PSR-14 compliant event dispatcher
   - Lifecycle events for all major operations
   - Asynchronous event processing support

4. **Repository Pattern**
   - Data access abstraction
   - Consistent API for data operations
   - Support for multiple data sources

### Design Patterns Implementation

| Pattern | Usage | Location |
|---------|-------|----------|
| **Dependency Injection** | Service resolution | `core/src/Container/Container.php` |
| **Service Provider** | Modular service registration | `core/src/Container/ServiceProvider.php` |
| **Factory** | Object creation | Container and Model classes |
| **Singleton** | Shared instances | Container bindings |
| **Chain of Responsibility** | Middleware pipeline | `core/src/Kernel/HttpKernel.php` |
| **Observer** | Event system | `core/src/Events/EventDispatcher.php` |
| **Strategy** | Payment/Shipping methods | Plugin implementations |
| **Active Record** | Database ORM | `core/src/Database/Model.php` |
| **Template Method** | Base classes | AbstractPlugin, ServiceProvider |
| **Facade** | Simplified interfaces | Helper functions |

### PSR Compliance
- **PSR-4**: Autoloading standard
- **PSR-7**: HTTP message interfaces
- **PSR-11**: Container interface
- **PSR-14**: Event dispatcher
- **PSR-3**: Logger interface

## File Inventory

### Core Framework Files

| File Path | Purpose | Key Classes/Functions | Dependencies | Used By | Notes |
|-----------|---------|----------------------|--------------|---------|-------|
| `/core/bootstrap.php` | Application initialization | Creates Application instance | Application class | All entry points | Registers HTTP kernel |
| `/core/src/Autoloader.php` | PSR-4 class autoloading | `Autoloader::register()`, `addNamespace()` | None | Bootstrap | Maps namespaces to directories |
| `/core/src/helpers.php` | Global helper functions | `env()`, `app()`, `database_path()` | None | Throughout app | Utility functions |
| `/core/src/Kernel/Application.php` | Main application container | Service provider management, booting | Container, EventManager | Bootstrap | Core application class |
| `/core/src/Kernel/HttpKernel.php` | HTTP request handler | `handle()`, middleware pipeline | Router, EventManager | Entry points | Processes all HTTP requests |
| `/core/src/Container/Container.php` | Dependency injection | `bind()`, `singleton()`, `get()` | PSR-11 interfaces | Throughout app | Auto-wiring support |
| `/core/src/Database/Model.php` | Active Record ORM | CRUD operations, relationships | QueryBuilder | All models | Database abstraction |
| `/core/src/Database/QueryBuilder.php` | SQL query builder | Fluent query interface | Database drivers | Model, direct usage | Chainable query methods |
| `/core/src/Router/Router.php` | HTTP routing | Route registration, matching | Route, RouteCompiler | HttpKernel | RESTful routing |
| `/core/src/Plugin/PluginManager.php` | Plugin lifecycle | Load, activate, deactivate plugins | PluginRepository | Application | Plugin discovery and management |
| `/core/src/Plugin/Hook.php` | WordPress-style hooks | `addAction()`, `addFilter()` | None | Plugins, themes | Extensibility system |
| `/core/src/Theme/TemplateEngine.php` | Template rendering | Compile, cache, render templates | Parser, Compiler | Controllers | Twig-like syntax |
| `/core/src/Events/EventDispatcher.php` | Event handling | `dispatch()`, `listen()` | PSR-14 interfaces | Throughout app | Loose coupling |
| `/core/src/Cache/CacheManager.php` | Cache abstraction | Store/retrieve cache data | Cache stores | Throughout app | Multiple driver support |
| `/core/src/Auth/AuthManager.php` | Authentication | Login, logout, user management | Guards, User model | Controllers, middleware | Multi-guard support |

### Entry Points

| File Path | Purpose | Context | Response Type | Special Features |
|-----------|---------|---------|---------------|------------------|
| `/public/index.php` | Storefront | Default | HTML | Customer-facing interface |
| `/public/api.php` | API endpoints | 'api' | JSON | CORS headers, REST/GraphQL |
| `/public/admin.php` | Admin panel | 'admin' | HTML | Admin authentication required |

### Database Migrations

| File Path | Purpose | Tables Created | Relationships | Special Features |
|-----------|---------|----------------|---------------|------------------|
| `2024_01_01_000001_create_users_table.php` | User authentication | users | - | Email verification support |
| `2024_01_01_000003_create_products_table.php` | Product catalog | products | category_id FK | Full-text search, JSON attributes |
| `2024_01_15_000001_create_stores_table.php` | Multi-store | stores | - | Domain/subdomain detection |
| `2024_01_15_000004_add_store_id_to_tables.php` | Store isolation | - | Adds store_id to multiple tables | Pivot tables for shared data |

### CLI Tools

| File Path | Purpose | Key Commands | Usage Example |
|-----------|---------|--------------|---------------|
| `/cli/migrate.php` | Database migrations | up, down, reset, fresh, status | `php cli/migrate.php up` |
| `/cli/plugin.php` | Plugin management | list, install, activate, generate | `php cli/plugin.php install payment-stripe` |
| `/cli/cache.php` | Cache management | clear, warm, optimize, stats | `php cli/cache.php clear` |
| `/cli/test.php` | Test runner | Run unit/integration/E2E tests | `php cli/test.php --coverage` |
| `/cli/seed.php` | Database seeding | run, refresh | `php cli/seed.php run` |
| `/cli/backup.php` | Backup system | create, restore, list | `php cli/backup.php create` |

### Configuration Files

| File Path | Purpose | Key Settings | Environment Variables |
|-----------|---------|--------------|----------------------|
| `/config/database.php` | Database connections | Driver, host, credentials | DB_* variables |
| `/config/multistore.php` | Multi-store settings | Detection order, isolation | MULTISTORE_* variables |
| `/core/config/app.php` | Application settings | Debug mode, timezone | APP_* variables |
| `/core/config/cache.php` | Cache configuration | Default store, TTL | CACHE_* variables |

## Data Flow

### Request Lifecycle

```
1. HTTP Request
   ↓
2. Entry Point (index.php/api.php/admin.php)
   ↓
3. Autoloader Registration
   ↓
4. Application Creation & Boot
   ↓
5. Service Provider Registration
   ↓
6. Request Creation (PSR-7)
   ↓
7. Middleware Pipeline
   - Authentication
   - CSRF Protection
   - Rate Limiting
   - Store Detection
   ↓
8. Route Matching
   ↓
9. Controller Execution
   ↓
10. Model/Service Layer
    ↓
11. Database Query
    ↓
12. Response Generation
    - View Rendering (HTML)
    - JSON Serialization (API)
    ↓
13. Response Middleware
    ↓
14. Send Response
    ↓
15. Terminate & Cleanup
```

### Data Flow Examples

#### Example 1: Product Page View
```
GET /products/laptop-pro-2024
→ index.php
→ Router matches: /products/{slug}
→ ProductController::show($slug)
→ Product::where('slug', $slug)->firstOrFail()
→ Load related: images, reviews, category
→ Render 'products/show.twig' with data
→ Apply theme layout
→ Send HTML response
```

#### Example 2: API Order Creation
```
POST /api/v1/orders
→ api.php
→ Authentication middleware (JWT)
→ Rate limit check
→ OrderApiController::store()
→ Validate JSON payload
→ Begin database transaction
→ Create Order model
→ Create OrderItems
→ Update inventory
→ Process payment
→ Commit transaction
→ Dispatch OrderCreated event
→ Return JSON response with order data
```

#### Example 3: Admin Dashboard
```
GET /admin/dashboard
→ admin.php
→ Admin authentication check
→ DashboardController::index()
→ Load statistics:
  - Order::whereDate('created_at', today())->count()
  - Product::where('stock_quantity', '<', 10)->get()
  - Customer::whereMonth('created_at', now()->month)->count()
→ Render 'admin/dashboard.twig'
→ Include admin layout and navigation
→ Send HTML response
```

### Event Flow

```
Order Creation Event Flow:
1. OrderController creates order
2. Dispatch 'order.creating' event
3. Listeners validate/modify order data
4. Save order to database
5. Dispatch 'order.created' event
6. Listeners:
   - Send confirmation email
   - Update inventory
   - Record analytics
   - Notify warehouse
   - Award loyalty points
```

## Database Schema

### Core Tables

#### users
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| name | VARCHAR(255) | User's display name |
| email | VARCHAR(255) | Unique email |
| email_verified_at | TIMESTAMP | Email verification |
| password | VARCHAR(255) | Hashed password |
| remember_token | VARCHAR(100) | Remember me token |
| created_at | TIMESTAMP | Creation time |
| updated_at | TIMESTAMP | Last update |

#### products
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| sku | VARCHAR(100) | Unique SKU |
| name | VARCHAR(255) | Product name |
| slug | VARCHAR(255) | URL slug |
| description | TEXT | Full description |
| price | DECIMAL(10,2) | Regular price |
| cost | DECIMAL(10,2) | Cost price |
| stock_quantity | INTEGER | Available stock |
| category_id | BIGINT | Category FK |
| attributes | JSON/JSONB | Custom attributes |
| meta_data | JSON/JSONB | SEO and meta |
| is_active | BOOLEAN | Active status |

#### stores
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| code | VARCHAR(50) | Unique store code |
| name | VARCHAR(100) | Store name |
| domain | VARCHAR(255) | Primary domain |
| subdomain | VARCHAR(100) | Subdomain |
| path_prefix | VARCHAR(100) | URL path |
| is_active | BOOLEAN | Active status |
| is_default | BOOLEAN | Default store |
| config | JSON | Store configuration |
| theme | VARCHAR(50) | Active theme |
| locale | VARCHAR(10) | Default locale |
| currency | VARCHAR(3) | Default currency |
| timezone | VARCHAR(50) | Store timezone |

### Relationships

```
User
  ↓ hasMany
Orders
  ↓ hasMany
OrderItems
  ↑ belongsTo
Products
  ↑ belongsToMany (through store_products)
Stores
```

### Multi-Store Pivot Tables

- **store_products**: Store-specific pricing and inventory
- **store_categories**: Store-specific category availability
- **store_payment_methods**: Store-specific payment configurations
- **store_shipping_methods**: Store-specific shipping options

## API Endpoints

### REST API Structure

Base URL: `/api/v1`

#### Products
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | /products | List products with filters | No |
| GET | /products/{id} | Get single product | No |
| POST | /products | Create product | Yes (Admin) |
| PUT | /products/{id} | Update product | Yes (Admin) |
| DELETE | /products/{id} | Delete product | Yes (Admin) |
| GET | /products/{id}/reviews | Get product reviews | No |
| POST | /products/{id}/reviews | Add review | Yes |

#### Orders
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | /orders | List user's orders | Yes |
| GET | /orders/{id} | Get order details | Yes |
| POST | /orders | Create order | Yes |
| PUT | /orders/{id}/status | Update order status | Yes (Admin) |
| POST | /orders/{id}/cancel | Cancel order | Yes |

#### Cart
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | /cart | Get current cart | Yes |
| POST | /cart/items | Add item to cart | Yes |
| PUT | /cart/items/{id} | Update quantity | Yes |
| DELETE | /cart/items/{id} | Remove item | Yes |
| POST | /cart/clear | Clear cart | Yes |

### GraphQL API

Endpoint: `/api/graphql`

#### Example Queries
```graphql
# Get product with related data
query GetProduct($id: ID!) {
  product(id: $id) {
    id
    name
    price
    category {
      name
    }
    images {
      url
    }
    reviews(limit: 10) {
      rating
      comment
      user {
        name
      }
    }
  }
}

# Search products
query SearchProducts($query: String!, $filters: ProductFilters) {
  products(search: $query, filters: $filters) {
    edges {
      node {
        id
        name
        price
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

## Configuration Guide

### Environment Variables

#### Core Settings
```bash
# Application
APP_NAME=Shopologic
APP_ENV=production|development|testing
APP_DEBUG=false
APP_URL=https://example.com
APP_KEY=base64:generated_key_here

# Database
DB_CONNECTION=pgsql|mysql|sqlite
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=shopologic
DB_USERNAME=user
DB_PASSWORD=password

# Cache
CACHE_DRIVER=file|array
CACHE_PREFIX=shopologic

# Session
SESSION_DRIVER=file|database
SESSION_LIFETIME=120

# Multi-Store
MULTISTORE_ENABLED=true
MULTISTORE_DETECTION=domain,subdomain,path
```

### Configuration Files

#### Database Configuration (`/config/database.php`)
- Supports PostgreSQL, MySQL, SQLite
- Read/write splitting support
- Connection pooling settings
- Migration table configuration

#### Multi-Store Configuration (`/config/multistore.php`)
- Store detection order
- Isolation settings per entity
- Default store settings
- Permission role definitions

## Dependencies

### PHP Requirements
- PHP 8.3 or higher
- Extensions: PDO, JSON, OpenSSL, Mbstring
- Optional: OPcache, Redis

### Zero External Dependencies Philosophy
- All functionality built in-house
- Only PSR interfaces imported
- No Composer packages required
- Benefits:
  - Complete control over code
  - No version conflicts
  - Smaller footprint
  - Better security

## Development Workflow

### Setting Up Development Environment

```bash
# 1. Clone repository
git clone https://github.com/example/shopologic.git
cd shopologic

# 2. Configure environment
cp .env.example .env
# Edit .env with your settings

# 3. Set permissions
chmod -R 775 storage/
chmod -R 775 database/

# 4. Run migrations
php cli/migrate.php install
php cli/migrate.php up

# 5. Seed database (optional)
php cli/seed.php run

# 6. Start development server
php -S localhost:8000 -t public/
```

### Common Development Tasks

#### Creating a New Feature
1. Create feature branch
2. Write tests first (TDD)
3. Implement functionality
4. Run tests: `php cli/test.php`
5. Check code style
6. Submit pull request

#### Adding a Database Table
```bash
# Create migration
php cli/migrate.php create AddCustomerNotesTable

# Edit migration file
# Run migration
php cli/migrate.php up
```

#### Creating a Plugin
```bash
# Generate plugin scaffold
php cli/plugin.php generate my-plugin

# Edit plugin files
# Install plugin
php cli/plugin.php install my-plugin

# Activate plugin
php cli/plugin.php activate my-plugin
```

### Testing Strategy

```bash
# Run all tests
php cli/test.php

# Run specific suite
php cli/test.php --suite=Unit

# Run with coverage
php cli/test.php --coverage

# Run specific test
php cli/test.php --filter=ProductTest
```

## Plugin System

### Plugin Architecture

#### Plugin Structure
```
/plugins/my-plugin/
├── plugin.json          → Plugin manifest
├── MyPlugin.php         → Main plugin class
├── /src                → Source code
├── /migrations         → Database migrations
├── /templates          → View templates
├── /assets             → CSS, JS, images
│   ├── /css
│   ├── /js
│   └── /images
└── README.md           → Documentation
```

#### Plugin Manifest (plugin.json)
```json
{
  "name": "my-plugin",
  "version": "1.0.0",
  "description": "Plugin description",
  "author": "Your Name",
  "class": "MyPlugin\\MyPlugin",
  "file": "MyPlugin.php",
  "dependencies": {
    "core-commerce": "^1.0"
  },
  "permissions": [
    "hooks.order.*",
    "database.access"
  ]
}
```

### Plugin Development

#### Basic Plugin Class
```php
namespace MyPlugin;

use Shopologic\Plugin\AbstractPlugin;

class MyPlugin extends AbstractPlugin
{
    public function boot(): void
    {
        // Register hooks
        $this->addAction('order.created', [$this, 'onOrderCreated']);
        $this->addFilter('product.price', [$this, 'filterPrice']);
        
        // Register routes
        $this->registerRoute('GET', '/my-plugin/data', [$this, 'getData']);
        
        // Register services
        $this->container->bind(MyService::class);
    }
    
    public function install(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Create default config
        $this->config->set('setting', 'value');
    }
}
```

### Hook System

#### Actions (Side Effects)
```php
// Add action
Hook::addAction('order.created', function($order) {
    // Send email
    // Log event
    // Update statistics
}, $priority = 10);

// Trigger action
Hook::doAction('order.created', $order);
```

#### Filters (Modify Values)
```php
// Add filter
Hook::addFilter('product.price', function($price, $product) {
    // Apply discount
    return $price * 0.9;
}, $priority = 10);

// Apply filter
$finalPrice = Hook::applyFilters('product.price', $price, $product);
```

## Theme System

### Theme Structure
```
/themes/my-theme/
├── theme.json          → Theme configuration
├── /assets            → Static assets
│   ├── /css
│   ├── /js
│   └── /images
├── /components        → Reusable components
│   └── /hero-banner
│       ├── component.json
│       └── hero-banner.twig
├── /templates         → Page templates
│   ├── /layouts
│   │   └── base.twig
│   ├── /pages
│   │   ├── home.twig
│   │   └── product.twig
│   └── /partials
│       ├── header.twig
│       └── footer.twig
└── README.md
```

### Theme Configuration (theme.json)
```json
{
  "name": "My Theme",
  "version": "1.0.0",
  "regions": {
    "header": {
      "name": "Header",
      "accepts": ["navigation", "search"]
    },
    "content": {
      "name": "Main Content",
      "accepts": ["*"]
    }
  },
  "settings": {
    "colors": {
      "primary": "#007bff",
      "secondary": "#6c757d"
    }
  }
}
```

### Template Syntax
```twig
{# Extends layout #}
{% extends "layouts/base.twig" %}

{# Define block #}
{% block content %}
    <h1>{{ product.name }}</h1>
    
    {# Conditional #}
    {% if product.on_sale %}
        <span class="sale">{{ product.sale_price|currency }}</span>
    {% endif %}
    
    {# Loop #}
    {% for image in product.images %}
        <img src="{{ image.url }}" alt="{{ image.alt }}">
    {% endfor %}
    
    {# Include partial #}
    {% include "partials/reviews.twig" with {reviews: product.reviews} %}
    
    {# Hook integration #}
    {% hook "product.after_price" product %}
{% endblock %}
```

## Quick Start Guide

### For Frontend Developers
1. Locate theme in `/themes/default/`
2. Edit templates in `/templates/`
3. Add styles in `/assets/css/`
4. Use live editor: `/admin/theme/editor`

### For Backend Developers
1. Create models in `/plugins/your-plugin/src/Models/`
2. Add controllers in `/plugins/your-plugin/src/Controllers/`
3. Register routes in plugin boot method
4. Use dependency injection for services

### For Plugin Developers
1. Generate scaffold: `php cli/plugin.php generate your-plugin`
2. Edit `plugin.json` with metadata
3. Implement main plugin class
4. Add hooks and filters
5. Test thoroughly
6. Document usage

### Common Code Patterns

#### Creating a Model
```php
namespace YourPlugin\Models;

use Shopologic\Database\Model;

class YourModel extends Model
{
    protected string $table = 'your_table';
    
    protected array $fillable = ['name', 'value'];
    
    protected array $casts = [
        'data' => 'json',
        'is_active' => 'boolean'
    ];
    
    public function relatedModel()
    {
        return $this->belongsTo(RelatedModel::class);
    }
}
```

#### Creating a Controller
```php
namespace YourPlugin\Controllers;

use Shopologic\Http\Request;
use Shopologic\Http\Response;

class YourController
{
    public function index(Request $request): Response
    {
        $items = YourModel::paginate(20);
        
        return response()->json([
            'data' => $items
        ]);
    }
}
```

#### Registering Services
```php
// In your plugin's boot method
$this->container->bind(ServiceInterface::class, ServiceImplementation::class);
$this->container->singleton(CacheService::class);
$this->container->tag([Service1::class, Service2::class], 'tagged-services');
```

## Troubleshooting

### Common Issues

#### 1. Class Not Found
- Check namespace matches directory structure
- Verify autoloader registration
- Run `php cli/cache.php clear`

#### 2. Database Connection Failed
- Check `.env` database credentials
- Verify database server is running
- Check database exists

#### 3. Plugin Not Loading
- Verify `plugin.json` is valid JSON
- Check plugin class implements PluginInterface
- Review error logs in `/storage/logs/`

#### 4. Template Not Found
- Check template path is correct
- Verify theme is activated
- Clear template cache

#### 5. Route Not Found
- Check route registration in plugin
- Verify HTTP method matches
- Clear route cache

### Debug Mode
Enable debug mode in `.env`:
```bash
APP_DEBUG=true
```

This will:
- Show detailed error messages
- Enable query logging
- Disable template caching
- Show development toolbar

### Logging
Check logs in `/storage/logs/`:
- `{date}.log` - Daily application logs
- `error.log` - Error-specific logs
- `query.log` - Database queries (debug mode)

### Performance Tips
1. Enable OPcache in production
2. Use database indexing appropriately
3. Cache expensive queries
4. Minimize N+1 queries with eager loading
5. Use CDN for static assets

---

This documentation represents the complete structure and functionality of the Shopologic e-commerce platform. For specific implementation details, refer to the source code comments and individual component documentation.