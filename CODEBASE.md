# CODEBASE.md - Shopologic PHP Application Documentation

> **Last Updated**: December 2024  
> **PHP Version**: 8.3+  
> **Architecture**: Microkernel with Plugin System  
> **Dependencies**: Zero (except PSR standards)  
> **Total PHP Files**: 650+
> **Enhanced Plugin Ecosystem**: PRODUCTION READY

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

The codebase has been significantly enhanced with a comprehensive plugin ecosystem featuring:
- 47 advanced models with sophisticated business logic
- Cross-plugin integration via standardized interfaces
- Real-time event system with middleware support
- Performance monitoring and health checks
- Automated testing framework with multiple test types

## Table of Contents

1. [Project Overview](#project-overview) *(5 min read)*
2. [Directory Structure](#directory-structure) *(10 min read)*
3. [Core Architecture](#core-architecture) *(15 min read)*
4. [File Inventory](#file-inventory) *(20 min read)*
5. [Data Flow](#data-flow) *(10 min read)*
6. [Database Schema](#database-schema) *(10 min read)*
7. [API Endpoints](#api-endpoints) *(10 min read)*
8. [Configuration Guide](#configuration-guide) *(5 min read)*
9. [Dependencies](#dependencies) *(5 min read)*
10. [Development Workflow](#development-workflow) *(10 min read)*
11. [Plugin System](#plugin-system) *(10 min read)*
12. [Theme System](#theme-system) *(5 min read)*
13. [Security Implementation](#security-implementation) *(10 min read)*
14. [Performance Architecture](#performance-architecture) *(10 min read)*
15. [Quick Start Guide](#quick-start-guide) *(5 min read)*
16. [Troubleshooting](#troubleshooting) *(5 min read)*

## Project Overview

### Application Purpose
Shopologic is an enterprise-grade, self-contained e-commerce platform built with pure PHP 8.3+ and zero external dependencies. It features:

- **Microkernel Architecture**: Plugin-based extensibility similar to WordPress
- **Complete E-commerce Suite**: Products, orders, customers, inventory, payments
- **Multi-store Support**: Manage multiple stores from one installation
- **API-First Design**: Comprehensive REST and GraphQL APIs
- **Live Theme Editor**: Visual customization without coding
- **Advanced Analytics**: Built-in reporting and business intelligence
- **Plugin Ecosystem**: 20+ production-ready plugins included

### Technology Stack
- **Language**: PHP 8.3+ (with strict typing and modern features)
- **Database**: PostgreSQL (primary), MySQL, SQLite support
- **Frontend**: Theme-based with Twig-like templating
- **APIs**: RESTful + GraphQL with auto-generation
- **Caching**: Built-in cache abstraction (Redis/Memcached/File)
- **Queue**: Built-in job queue system with multiple drivers
- **Search**: Elasticsearch integration (via plugin)
- **Real-time**: WebSocket support via plugins

### Main Features
1. **Product Management**: Variants, bundles, digital products, subscriptions
2. **Order Processing**: Multi-step checkout, split payments, partial fulfillment
3. **Customer Management**: Profiles, segments, loyalty, CLV optimization
4. **Inventory Control**: Multi-location, forecasting, automation, real-time sync
5. **Marketing Tools**: Email campaigns, discounts, SEO, social commerce
6. **Analytics Dashboard**: Real-time metrics, custom reports, ML insights
7. **Plugin System**: Hot-swappable modules with dependency management
8. **Multi-channel**: B2C, B2B, marketplace, POS integration

### Target Audience
- **Enterprise E-commerce**: Large catalogs (1M+ products), high traffic (100K+ daily)
- **Multi-vendor Marketplaces**: Platform for multiple sellers with commission management
- **B2B Commerce**: Wholesale, custom pricing, quotes, approval workflows
- **Digital Commerce**: Subscriptions, downloads, licenses, streaming
- **Omnichannel Retail**: Unified commerce across web, mobile, POS

## Directory Structure

```
/shopologic
├── /cli                    → Command-line tools and scripts (12 files)
│   ├── backup.php         → Database and file backup utility
│   ├── cache.php          → Cache management commands
│   ├── checkout.php       → Checkout system testing
│   ├── cron.php           → Cron job runner
│   ├── dbinit.php         → Database initialization
│   ├── migrate.php        → Database migration runner
│   ├── plugin.php         → Plugin management CLI
│   ├── pluginmanager.php  → Alternative plugin manager
│   ├── queue.php          → Queue worker management
│   ├── seed.php           → Database seeder
│   ├── server.php         → Development server launcher
│   └── test.php           → Test runner
│
├── /config                → Configuration files (3 files)
│   ├── app.php           → Main application config
│   ├── database.php      → Database connections
│   └── services.php      → Service provider registration
│
├── /core                  → Framework core (320 files)
│   ├── /API              → API framework (15 files)
│   │   ├── /GraphQL      → GraphQL implementation
│   │   └── /REST         → RESTful API base
│   ├── /Admin            → Admin panel modules (52 files)
│   │   ├── /Analytics    → Analytics dashboard
│   │   ├── /Components   → UI components
│   │   ├── /Controllers  → Admin controllers
│   │   └── /Widgets      → Dashboard widgets
│   ├── /Analytics        → Analytics engine (23 files)
│   │   ├── /Collectors   → Data collectors
│   │   ├── /Processors   → Data processors
│   │   └── /Reports      → Report generators
│   ├── /Auth             → Authentication system (8 files)
│   │   ├── Guards.php    → Auth guards
│   │   ├── JWT.php       → JWT implementation
│   │   └── Providers     → Auth providers
│   ├── /Cache            → Caching abstraction (5 files)
│   │   └── Drivers       → Cache driver implementations
│   ├── /Container        → Dependency injection (4 files)
│   │   ├── Container.php → DI container (PSR-11)
│   │   └── ServiceProvider.php → Provider base
│   ├── /Database         → Database layer & ORM (38 files)
│   │   ├── /Migrations   → Migration system
│   │   ├── /ORM          → Active Record ORM
│   │   ├── /Query        → Query builder
│   │   └── /Schema       → Schema builder
│   ├── /Ecommerce        → E-commerce components (93 files)
│   │   ├── /Cart         → Shopping cart
│   │   ├── /Catalog      → Product catalog
│   │   ├── /Checkout     → Checkout process
│   │   ├── /Customer     → Customer management
│   │   ├── /Inventory    → Stock management
│   │   ├── /Order        → Order processing
│   │   ├── /Payment      → Payment processing
│   │   ├── /Pricing      → Price calculations
│   │   ├── /Product      → Product models
│   │   ├── /Shipping     → Shipping calculations
│   │   └── /Tax          → Tax calculations
│   ├── /Event            → Event dispatcher (PSR-14) (3 files)
│   ├── /Hook             → Hook system (WordPress-style) (3 files)
│   ├── /Http             → HTTP layer (PSR-7) (11 files)
│   │   ├── Request.php   → Request handling
│   │   ├── Response.php  → Response generation
│   │   └── Middleware    → HTTP middleware
│   ├── /I18n             → Internationalization (4 files)
│   │   └── Translators   → Translation drivers
│   ├── /Jobs             → Background jobs (5 files)
│   ├── /Mail             → Email system (7 files)
│   ├── /Notifications    → Notification system (6 files)
│   ├── /Plugin           → Plugin system (12 files)
│   │   ├── AbstractPlugin.php → Plugin base class
│   │   ├── PluginManager.php → Plugin lifecycle
│   │   └── Repository    → Plugin storage
│   ├── /Queue            → Job queue system (4 files)
│   ├── /Router           → Routing system (8 files)
│   ├── /Search           → Search engine (9 files)
│   ├── /Security         → Security components (6 files)
│   │   └── Scanner       → Code scanner
│   ├── /Session          → Session management (3 files)
│   ├── /Storage          → File storage (6 files)
│   ├── /Template         → Template engine (11 files)
│   │   ├── Engine.php    → Twig-like engine
│   │   └── Compiler      → Template compiler
│   ├── /Theme            → Theme system (18 files)
│   │   ├── Editor        → Live theme editor
│   │   └── Registry      → Theme registry
│   ├── /Validation       → Input validation (5 files)
│   └── Kernel.php        → Application kernel
│
├── /database             → Database files (11 files)
│   ├── /migrations       → Schema migrations (11 files)
│   ├── /seeds           → Data seeders
│   └── /schemas         → Schema definitions
│
├── /plugins              → Plugin modules (203 files)
│   ├── /advanced-analytics-dashboard (11 files)
│   ├── /advanced-inventory-intelligence (4 files)
│   ├── /advanced-personalization-engine (4 files)
│   ├── /ai-recommendations (11 files)
│   ├── /core-commerce (18 files)
│   ├── /customer-lifetime-value-optimizer (4 files)
│   ├── /email-marketing (6 files)
│   ├── /enterprise-supply-chain-management (4 files)
│   ├── /google-analytics (2 files)
│   ├── /inventory (2 files)
│   ├── /live-chat (2 files)
│   ├── /loyalty (2 files)
│   ├── /multi-currency (10 files)
│   ├── /multi-vendor (11 files)
│   ├── /payment-paypal (5 files)
│   ├── /payment-stripe (13 files)
│   ├── /reviews-ratings (11 files)
│   ├── /sales-dashboard (2 files)
│   ├── /seo-optimizer (2 files)
│   ├── /shipping-fedex (9 files)
│   ├── /social-commerce-integration (4 files)
│   └── ... (20+ more plugins)
│
├── /public               → Web-accessible files (3 files)
│   ├── index.php        → Main entry point
│   ├── api.php          → API entry point
│   ├── admin.php        → Admin entry point
│   └── /assets          → Static assets
│
├── /resources            → Resource files (2 files)
│   └── /lang            → Language files
│       └── /en          → English translations
│
├── /storage              → Runtime storage
│   ├── /cache           → Cache files
│   ├── /logs            → Application logs
│   ├── /sessions        → Session files
│   └── /uploads         → User uploads
│
├── /tests                → Test suites (24 files)
│   ├── /Unit            → Unit tests (8 files)
│   ├── /Integration     → Integration tests (8 files)
│   ├── /E2E             → End-to-end tests (8 files)
│   └── TestCase.php     → Base test class
│
├── /themes               → Frontend themes
│   └── /default         → Default theme
│       ├── /assets      → CSS, JS, images
│       ├── /components  → Reusable components
│       └── /templates   → Page templates
│
└── /vendor               → Autoloader only (no packages)
    └── autoload.php     → Custom PSR-4 autoloader
```

### Directory Conventions

- **Namespace Mapping**: Directory structure follows PSR-4 namespace conventions
- **Plugin Structure**: Each plugin is self-contained with its own src/, migrations/, templates/
- **Asset Organization**: Public assets in public/assets, private in storage/
- **Test Mirroring**: Test directory structure mirrors source code structure
- **Configuration**: Environment-specific configs use .env, defaults in /config

## Core Architecture

### Overall Architecture Pattern

Shopologic implements a **Microkernel Architecture** with these layers:

```
┌─────────────────────────────────────────────────────────────┐
│                     HTTP Request                              │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                  Entry Points                                 │
│         (index.php, api.php, admin.php)                      │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                Application Kernel                             │
│    • Service Container Bootstrap                              │
│    • Environment Detection                                    │
│    • Service Provider Registration                            │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│              HTTP Kernel & Middleware                         │
│    • CSRF Protection                                          │
│    • Authentication                                           │
│    • Request Validation                                       │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                    Router                                     │
│    • Route Matching                                           │
│    • Parameter Binding                                        │
│    • Controller Resolution                                    │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│              Controller / Handler                             │
│    • Request Processing                                       │
│    • Business Logic Orchestration                             │
│    • Response Generation                                      │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│              Service Layer                                    │
│    • Business Logic Implementation                            │
│    • Data Validation                                          │
│    • External Service Integration                             │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│           Repository / Model Layer                            │
│    • Database Queries                                         │
│    • Data Mapping                                             │
│    • Caching                                                  │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                  Database                                     │
│         (PostgreSQL / MySQL / SQLite)                         │
└─────────────────────────────────────────────────────────────┘
```

### Service Container (Dependency Injection)

The heart of Shopologic is its PSR-11 compliant service container:

```php
// Service Registration
$container->bind(PaymentInterface::class, StripePayment::class);
$container->singleton(CacheInterface::class, RedisCache::class);

// Auto-wiring with type hints
$container->make(OrderService::class); // Dependencies auto-resolved

// Service Tagging for grouping
$container->tag([StripeGateway::class, PayPalGateway::class], 'payment.gateway');

// Contextual binding
$container->when(PhotoController::class)
          ->needs(FilesystemInterface::class)
          ->give(S3Filesystem::class);
```

Key features:
- **Auto-wiring**: Automatic dependency resolution using reflection
- **Circular dependency detection**: Prevents infinite loops
- **Service tagging**: Group services by functionality
- **Contextual binding**: Different implementations based on context
- **Lazy loading**: Services instantiated only when needed

### Plugin System Architecture

The plugin system enables modular functionality:

```
Plugin Lifecycle:
1. Discovery     → Scan plugins/ directory
2. Registration  → Load plugin.json manifests
3. Installation  → Run migrations, copy assets
4. Activation    → Enable hooks, routes, services
5. Boot          → Initialize plugin functionality
6. Execution     → Handle requests, process hooks
7. Deactivation  → Disable functionality
8. Uninstallation → Clean up data and files
```

Each plugin:
- Extends `Core\Plugin\AbstractPlugin`
- Has a `plugin.json` manifest with metadata
- Can provide services, hooks, routes, widgets, API endpoints
- Includes its own migrations, templates, and assets
- Supports dependency management and version constraints

### Hook System (Event-Driven Architecture)

WordPress-style hooks enable deep customization:

```php
// Action Hooks (no return value)
HookSystem::addAction('order.created', function($order) {
    // Send order confirmation email
    // Update inventory
    // Trigger analytics
}, $priority = 10);

// Filter Hooks (modify values)
HookSystem::addFilter('product.price', function($price, $product) {
    // Apply discount logic
    return $price * 0.9;
}, $priority = 10);

// Conditional hooks
HookSystem::addConditionalAction('payment.failed', $condition, $callback);

// Bulk operations
HookSystem::bulkAddActions([
    'order.created' => [$callback1, $callback2],
    'order.shipped' => [$callback3]
]);
```

### Key Design Patterns

| Pattern | Purpose | Implementation | Example Usage |
|---------|---------|----------------|---------------|
| **Dependency Injection** | Loose coupling | Container with auto-wiring | All service resolution |
| **Service Provider** | Modular registration | Abstract provider class | Core services boot |
| **Repository** | Data access abstraction | Interface + implementations | Product queries |
| **Factory** | Complex object creation | Static/instance methods | Model hydration |
| **Singleton** | Shared instances | Container management | Cache, Database |
| **Strategy** | Interchangeable algorithms | Interface implementations | Payment gateways |
| **Chain of Responsibility** | Sequential processing | Middleware pipeline | Request handling |
| **Observer** | Event notifications | Event dispatcher | Order lifecycle |
| **Active Record** | Database ORM | Model base class | All models |
| **Template Method** | Algorithm skeleton | Abstract classes | Plugins, Providers |
| **Adapter** | Interface compatibility | Wrapper classes | Cache drivers |
| **Decorator** | Add functionality | Wrapper with same interface | Response caching |
| **Facade** | Simplified interface | Static proxy | DB, Cache, Auth |

### Model-View-Controller Implementation

While not strictly MVC, Shopologic follows similar separation:

- **Controllers**: Handle HTTP requests, coordinate responses
- **Services**: Contain business logic, reusable across contexts
- **Repositories**: Handle data access, complex queries
- **Models**: Represent domain entities with business rules
- **Views**: Twig-like templates for presentation
- **View Models**: Data transformation for views

### PSR Standards Compliance

- **PSR-1**: Basic coding standard
- **PSR-2**: Coding style guide (extended by PSR-12)
- **PSR-3**: Logger interface (Log facade)
- **PSR-4**: Autoloading standard (namespace mapping)
- **PSR-6**: Caching interface (Cache abstraction)
- **PSR-7**: HTTP message interfaces (Request/Response)
- **PSR-11**: Container interface (DI container)
- **PSR-12**: Extended coding style guide
- **PSR-14**: Event dispatcher (Event system)
- **PSR-15**: HTTP handlers (Middleware)
- **PSR-16**: Simple cache (Cache facade)
- **PSR-17**: HTTP factories (Request/Response creation)
- **PSR-18**: HTTP client (API integrations)

## File Inventory

### Core Framework Files (Key Components)

| File Path | Purpose | Key Classes/Functions | Dependencies | Used By | Notes |
|-----------|---------|----------------------|--------------|---------|-------|
| `/core/Kernel.php` | Application kernel | `Kernel`, `boot()`, `handle()` | Container, Router | Entry points | Main application bootstrapper |
| `/core/Container/Container.php` | DI Container | `Container`, `bind()`, `make()`, `singleton()` | PSR-11 interfaces | Entire application | Auto-wiring, circular detection |
| `/core/Router/Router.php` | HTTP routing | `Router`, `match()`, `dispatch()`, `group()` | Container, Request | HttpKernel | RESTful + named routes |
| `/core/Database/Connection.php` | DB connections | `Connection`, `query()`, `transaction()` | Driver interfaces | Models, QueryBuilder | Multi-DB support |
| `/core/Database/Model.php` | Active Record ORM | `Model`, relationships, scopes | QueryBuilder, Connection | All models | Eager loading, events |
| `/core/Database/QueryBuilder.php` | SQL builder | `select()`, `where()`, `join()`, `paginate()` | Connection, Grammar | Models, repositories | Fluent interface |
| `/core/Http/Request.php` | HTTP requests | `Request`, PSR-7 methods, `input()`, `file()` | PSR-7 interfaces | Controllers | Request abstraction |
| `/core/Http/Response.php` | HTTP responses | `Response`, PSR-7 methods, `json()`, `view()` | PSR-7 interfaces | Controllers | Response abstraction |
| `/core/Http/Middleware/Middleware.php` | Middleware base | `handle()`, `terminate()` | Request, Response | HTTP pipeline | Middleware interface |
| `/core/Plugin/PluginManager.php` | Plugin management | `PluginManager`, `discover()`, `activate()` | Container, Filesystem | Application boot | Plugin lifecycle |
| `/core/Plugin/AbstractPlugin.php` | Plugin base class | `boot()`, `install()`, `registerHooks()` | Container, HookSystem | All plugins | Plugin interface |
| `/core/Hook/HookSystem.php` | Hook system | `addAction()`, `doAction()`, `addFilter()` | EventDispatcher | Plugins, Core | WordPress-style |
| `/core/Template/Engine.php` | Template engine | `Engine`, `render()`, `compile()`, `extends()` | Cache, Compiler | Views, Themes | Twig-like syntax |
| `/core/Cache/CacheManager.php` | Cache abstraction | `get()`, `put()`, `forget()`, `remember()` | Driver interfaces | Services, Models | Multi-driver support |
| `/core/Auth/AuthManager.php` | Authentication | `attempt()`, `login()`, `logout()`, `user()` | Guards, Providers | Controllers | Multi-guard support |
| `/core/Event/EventDispatcher.php` | Event dispatcher | `dispatch()`, `listen()`, `subscribe()` | PSR-14 interfaces | Throughout app | Async support |
| `/core/Queue/QueueManager.php` | Job queues | `push()`, `later()`, `bulk()` | Driver interfaces | Services | Multiple drivers |
| `/core/Session/SessionManager.php` | Sessions | `get()`, `put()`, `flash()`, `regenerate()` | Store interfaces | HTTP layer | Multiple stores |

### Entry Points

| File Path | Purpose | Bootstrap Process | Context | Special Features |
|-----------|---------|-------------------|---------|------------------|
| `/public/index.php` | Main web entry | 1. Load autoloader<br>2. Create app<br>3. Handle request<br>4. Send response | 'web' | Customer-facing, theme support |
| `/public/api.php` | API entry point | 1. Load autoloader<br>2. Create app<br>3. Set API context<br>4. Handle request | 'api' | CORS, rate limiting, auth |
| `/public/admin.php` | Admin panel | 1. Load autoloader<br>2. Create app<br>3. Set admin context<br>4. Handle request | 'admin' | Admin auth required |

### CLI Tools

| File Path | Purpose | Key Commands | Dependencies | Notes |
|-----------|---------|--------------|--------------|-------|
| `/cli/migrate.php` | Database migrations | `up`, `down`, `reset`, `fresh`, `status`, `create` | Migration system | Schema management |
| `/cli/plugin.php` | Plugin management | `list`, `install`, `activate`, `deactivate`, `generate` | PluginManager | Plugin control |
| `/cli/cache.php` | Cache operations | `clear`, `warm`, `optimize`, `stats` | CacheManager | Performance tool |
| `/cli/queue.php` | Queue workers | `work`, `listen`, `failed`, `retry` | QueueManager | Background jobs |
| `/cli/seed.php` | Database seeding | `run`, `refresh`, `make` | Seeder system | Test data |
| `/cli/backup.php` | Backup system | `create`, `restore`, `list`, `cleanup` | Backup service | Data protection |
| `/cli/cron.php` | Cron runner | `run`, `schedule`, `list` | Scheduler | Task automation |

### Plugin Files (Selected Examples)

| Plugin | Main File | Purpose | Key Features | Dependencies |
|--------|-----------|---------|--------------|--------------|
| **core-commerce** | `CoreCommercePlugin.php` | Essential e-commerce | Cart, checkout, orders | AbstractPlugin |
| **payment-stripe** | `StripePlugin.php` | Stripe payments | Cards, subscriptions, webhooks | Core commerce |
| **multi-currency** | `MultiCurrencyPlugin.php` | Currency conversion | Real-time rates, auto-switching | Core commerce |
| **ai-recommendations** | `AIRecommendationsPlugin.php` | ML recommendations | Collaborative filtering, personalization | Core commerce |
| **multi-vendor** | `MultiVendorPlugin.php` | Marketplace features | Vendor management, commissions | Core commerce |
| **advanced-analytics-dashboard** | `AdvancedAnalyticsDashboardPlugin.php` | Analytics & BI | Real-time metrics, ML insights | Core commerce |
| **social-commerce-integration** | `SocialCommerceIntegrationPlugin.php` | Social selling | Shoppable posts, influencers | Core commerce |
| **customer-lifetime-value-optimizer** | `CustomerLifetimeValueOptimizerPlugin.php` | CLV optimization | Predictions, retention | Core commerce |

### Configuration Files

| File Path | Purpose | Key Settings | Environment Variables | Notes |
|-----------|---------|--------------|----------------------|-------|
| `/config/app.php` | Main config | App name, env, debug, timezone | APP_* variables | Core settings |
| `/config/database.php` | DB config | Connections, drivers, options | DB_* variables | Multi-DB support |
| `/config/services.php` | Service providers | Provider list, boot order | None | DI configuration |
| `/.env` | Environment | All env-specific settings | All variables | Not in version control |

### Database Migrations

| File | Purpose | Tables/Changes | Related Features | Notes |
|------|---------|----------------|------------------|-------|
| `create_users_table.php` | User auth | users table | Authentication | Base user system |
| `create_products_table.php` | Products | products, attributes | Product catalog | JSON attributes |
| `create_orders_table.php` | Orders | orders, order_items | Order processing | Status workflow |
| `create_stores_table.php` | Multi-store | stores table | Store isolation | Domain mapping |
| `create_customers_table.php` | Customers | customers table | Customer management | Separate from users |
| `create_categories_table.php` | Categories | categories table | Nested set model | Hierarchical |
| `create_plugins_table.php` | Plugin registry | plugins table | Plugin system | Activation tracking |

## Data Flow

### Request Lifecycle

```
1. HTTP Request arrives at entry point (index.php/api.php/admin.php)
   ↓
2. Application bootstraps:
   - Load configuration
   - Create service container
   - Register service providers
   - Boot service providers
   ↓
3. HTTP Kernel processes request:
   - Create Request object from globals
   - Run through middleware pipeline:
     • CSRF verification
     • Authentication check
     • Rate limiting
     • Store detection (multi-store)
     • Custom middleware
   ↓
4. Router matches request:
   - Find matching route
   - Extract parameters
   - Resolve controller/handler
   ↓
5. Controller executes:
   - Validate input
   - Call service layer
   - Orchestrate business logic
   ↓
6. Service layer processes:
   - Execute business rules
   - Interact with repositories
   - Trigger events/hooks
   ↓
7. Repository/Model layer:
   - Query database
   - Map results to models
   - Apply caching
   ↓
8. Response generation:
   - Controller returns data
   - View rendering (if needed)
   - Response formatting
   ↓
9. Response sent to client:
   - Headers sent
   - Body streamed
   - Connection closed
   ↓
10. Termination:
    - Log request
    - Clean up resources
    - Trigger termination hooks
```

### Authentication Flow

```
1. User submits credentials
   ↓
2. AuthController validates input
   ↓
3. AuthService processes:
   - Hash password
   - Query user by email
   - Verify password hash
   ↓
4. Success path:
   - Generate session/JWT
   - Store in cache/cookie
   - Return success + token
   ↓
5. Failure path:
   - Log attempt
   - Return error
   - Increment failure count
```

### Order Processing Flow

```
1. Customer adds items to cart
   ↓
2. Cart stored in session/database
   ↓
3. Checkout initiated:
   - Validate cart items
   - Calculate totals
   - Apply discounts
   - Calculate shipping
   - Calculate taxes
   ↓
4. Payment processing:
   - Select payment method
   - Process via plugin
   - Handle response
   ↓
5. Order creation:
   - Begin transaction
   - Create order record
   - Create order items
   - Update inventory
   - Process payment
   - Trigger hooks
   - Commit transaction
   ↓
6. Post-order:
   - Send confirmation email
   - Update analytics
   - Trigger fulfillment
   - Award loyalty points
```

### Plugin Data Flow

```
1. Plugin activated
   ↓
2. Plugin::boot() called:
   - Register services
   - Add hooks/filters
   - Register routes
   - Add menu items
   ↓
3. Runtime execution:
   - Hooks fired by core
   - Plugin methods called
   - Data filtered/modified
   ↓
4. Plugin interacts:
   - Use core services
   - Modify behavior
   - Add functionality
```

### API Request Flow

```
1. API Request → api.php
   ↓
2. API Context set
   ↓
3. Authentication:
   - JWT token validation
   - API key verification
   - OAuth2 flow
   ↓
4. Rate limiting check
   ↓
5. Route to API controller
   ↓
6. Process request:
   - Validate input
   - Execute business logic
   - Format response
   ↓
7. Return JSON/GraphQL response
```

### Multi-Store Request Flow

```
1. Request arrives
   ↓
2. Store detection:
   - Check domain
   - Check subdomain
   - Check path prefix
   - Check HTTP header
   ↓
3. Load store config
   ↓
4. Apply store context:
   - Set active store
   - Load store settings
   - Apply store theme
   - Filter data by store
   ↓
5. Process request normally
```

## Database Schema

### Core Tables Structure

```sql
-- Stores (Multi-store support)
stores
├── id (PK)
├── code (UNIQUE)
├── name
├── domain
├── subdomain
├── path_prefix
├── settings (JSON)
├── theme
├── locale
├── currency
└── status

-- Users & Authentication
users
├── id (PK)
├── email (UNIQUE)
├── password_hash
├── name
├── role
├── permissions (JSON)
├── last_login_at
└── created_at

-- Products
products
├── id (PK)
├── store_id (FK) → stores
├── sku (UNIQUE)
├── name
├── slug (UNIQUE)
├── description
├── short_description
├── price
├── compare_price
├── cost
├── status
├── attributes (JSON)
├── metadata (JSON)
└── created_at

-- Product Variants
product_variants
├── id (PK)
├── product_id (FK) → products
├── sku
├── name
├── price
├── stock_quantity
├── attributes (JSON)
└── position

-- Categories
categories
├── id (PK)
├── store_id (FK) → stores
├── parent_id (FK) → categories
├── name
├── slug (UNIQUE)
├── description
├── position
├── left (nested set)
├── right (nested set)
└── depth

-- Orders
orders
├── id (PK)
├── store_id (FK) → stores
├── customer_id (FK) → customers
├── order_number (UNIQUE)
├── status
├── payment_status
├── fulfillment_status
├── subtotal
├── tax_amount
├── shipping_amount
├── discount_amount
├── total_amount
├── currency
├── notes (JSON)
└── created_at

-- Order Items
order_items
├── id (PK)
├── order_id (FK) → orders
├── product_id (FK) → products
├── variant_id (FK) → product_variants
├── quantity
├── unit_price
├── total_price
├── discount_amount
└── metadata (JSON)

-- Customers
customers
├── id (PK)
├── store_id (FK) → stores
├── email (UNIQUE per store)
├── first_name
├── last_name
├── phone
├── accepts_marketing
├── customer_group_id
├── tags (JSON)
├── metadata (JSON)
└── created_at

-- Carts
carts
├── id (PK)
├── store_id (FK) → stores
├── customer_id (FK) → customers
├── session_id
├── status
├── items (JSON)
├── metadata (JSON)
├── expires_at
└── created_at

-- Plugins
plugins
├── id (PK)
├── name (UNIQUE)
├── version
├── status
├── settings (JSON)
├── permissions (JSON)
├── activated_at
└── updated_at
```

### Model Relationships

```
Store ──┐
        ├─── has many ──> Products
        ├─── has many ──> Orders
        ├─── has many ──> Customers
        └─── has many ──> Categories

Product ──┐
          ├─── belongs to ──> Store
          ├─── has many ──> ProductVariants
          ├─── belongs to many ──> Categories
          └─── has many ──> OrderItems

Order ──┐
        ├─── belongs to ──> Store
        ├─── belongs to ──> Customer
        └─── has many ──> OrderItems

Customer ──┐
           ├─── belongs to ──> Store
           ├─── has many ──> Orders
           └─── has many ──> Carts

Category ──┐
           ├─── belongs to ──> Store
           ├─── has many ──> Products
           └─── has many ──> Children Categories
```

### Query Patterns

```php
// Common product queries
$products = Product::with(['variants', 'categories'])
    ->where('status', 'active')
    ->where('store_id', $storeId)
    ->orderBy('created_at', 'desc')
    ->paginate(20);

// Order with relationships
$order = Order::with(['items.product', 'customer', 'payments'])
    ->where('store_id', $storeId)
    ->find($orderId);

// Category tree
$categories = Category::where('store_id', $storeId)
    ->whereNull('parent_id')
    ->with('children')
    ->orderBy('position')
    ->get();

// Customer search
$customers = Customer::where('store_id', $storeId)
    ->where(function($query) use ($search) {
        $query->where('email', 'like', "%{$search}%")
              ->orWhere('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%");
    })
    ->paginate(50);

// Inventory check
$lowStock = Product::where('store_id', $storeId)
    ->whereHas('variants', function($query) {
        $query->where('stock_quantity', '<', 10);
    })
    ->get();
```

### Database Indexes

Key indexes for performance:
- `products`: (store_id, status, created_at)
- `products`: (slug) UNIQUE
- `orders`: (store_id, status, created_at)
- `orders`: (customer_id, created_at)
- `categories`: (store_id, left, right)
- `customers`: (store_id, email)
- Full-text index on products.name, products.description

## API Endpoints

### REST API Structure

Base URL: `/api/v1/`

#### Authentication
- `POST /auth/login` - User login
- `POST /auth/logout` - User logout  
- `POST /auth/refresh` - Refresh JWT token
- `POST /auth/register` - User registration
- `POST /auth/forgot-password` - Password reset request
- `POST /auth/reset-password` - Password reset confirmation

#### Products
- `GET /products` - List products with filtering
  - Query params: `page`, `limit`, `sort`, `filter[status]`, `filter[category]`, `search`
- `GET /products/{id}` - Get single product with variants
- `POST /products` - Create product (admin)
- `PUT /products/{id}` - Update product (admin)
- `DELETE /products/{id}` - Delete product (admin)
- `GET /products/{id}/variants` - Get product variants
- `POST /products/{id}/variants` - Create variant (admin)

#### Orders
- `GET /orders` - List orders (authenticated)
- `GET /orders/{id}` - Get order details
- `POST /orders` - Create order
- `PUT /orders/{id}/status` - Update order status (admin)
- `POST /orders/{id}/cancel` - Cancel order
- `POST /orders/{id}/refund` - Process refund (admin)
- `GET /orders/{id}/invoice` - Download invoice

#### Cart
- `GET /cart` - Get current cart
- `POST /cart/items` - Add item to cart
- `PUT /cart/items/{id}` - Update quantity
- `DELETE /cart/items/{id}` - Remove item
- `POST /cart/clear` - Clear cart
- `POST /cart/apply-coupon` - Apply discount code
- `DELETE /cart/coupon` - Remove coupon

#### Customers
- `GET /customers` - List customers (admin)
- `GET /customers/{id}` - Get customer details
- `PUT /customers/{id}` - Update customer
- `DELETE /customers/{id}` - Delete customer (admin)
- `GET /customers/{id}/orders` - Get customer orders
- `GET /customers/{id}/addresses` - Get addresses
- `POST /customers/{id}/addresses` - Add address

#### Categories
- `GET /categories` - Get category tree
- `GET /categories/{id}` - Get category with products
- `POST /categories` - Create category (admin)
- `PUT /categories/{id}` - Update category (admin)
- `DELETE /categories/{id}` - Delete category (admin)

### GraphQL API

Endpoint: `/api/graphql`

#### Schema Overview

```graphql
type Query {
  # Products
  product(id: ID!): Product
  products(
    first: Int
    after: String
    filter: ProductFilter
    sort: ProductSort
  ): ProductConnection!
  
  # Categories
  category(id: ID!): Category
  categories: [Category!]!
  
  # Orders
  order(id: ID!): Order
  orders(
    first: Int
    after: String
    filter: OrderFilter
  ): OrderConnection!
  
  # Customer
  me: Customer
  customer(id: ID!): Customer
}

type Mutation {
  # Authentication
  login(email: String!, password: String!): AuthPayload!
  logout: Boolean!
  register(input: RegisterInput!): AuthPayload!
  
  # Cart
  addToCart(productId: ID!, quantity: Int!): Cart!
  updateCartItem(itemId: ID!, quantity: Int!): Cart!
  removeFromCart(itemId: ID!): Cart!
  clearCart: Cart!
  
  # Orders
  createOrder(input: CreateOrderInput!): Order!
  cancelOrder(id: ID!): Order!
  
  # Customer
  updateProfile(input: UpdateProfileInput!): Customer!
  addAddress(input: AddressInput!): Address!
}

type Subscription {
  # Real-time order updates
  orderStatusChanged(orderId: ID!): Order!
  
  # Inventory updates
  productStockChanged(productId: ID!): Product!
}
```

#### Example Queries

```graphql
# Get product with all details
query GetProduct($id: ID!) {
  product(id: $id) {
    id
    name
    description
    price
    images {
      url
      alt
    }
    variants {
      id
      name
      price
      stock
    }
    category {
      id
      name
    }
  }
}

# Search products
query SearchProducts($search: String!, $first: Int!) {
  products(
    filter: { search: $search }
    first: $first
  ) {
    edges {
      node {
        id
        name
        price
        image
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}

# Add to cart
mutation AddToCart($productId: ID!, $quantity: Int!) {
  addToCart(productId: $productId, quantity: $quantity) {
    items {
      product {
        name
        price
      }
      quantity
      subtotal
    }
    total
  }
}
```

### API Authentication

#### JWT Authentication
```
Authorization: Bearer {jwt_token}
```

#### API Key Authentication
```
X-API-Key: {api_key}
```

#### OAuth2 Support
- Authorization endpoint: `/oauth/authorize`
- Token endpoint: `/oauth/token`
- Supported grants: authorization_code, client_credentials

### Rate Limiting

- Anonymous: 60 requests/minute
- Authenticated: 600 requests/minute
- Admin: 6000 requests/minute

Headers returned:
- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `X-RateLimit-Reset`

### Webhooks

Configurable webhooks for events:
- `order.created`
- `order.updated`
- `order.cancelled`
- `product.created`
- `product.updated`
- `product.deleted`
- `customer.created`
- `inventory.low`

## Configuration Guide

### Environment Variables

```bash
# Application
APP_NAME=Shopologic
APP_ENV=production|development|testing
APP_DEBUG=false
APP_URL=https://example.com
APP_KEY=base64:generated_key_here
APP_TIMEZONE=UTC
APP_LOCALE=en

# Database
DB_CONNECTION=pgsql|mysql|sqlite
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=shopologic
DB_USERNAME=user
DB_PASSWORD=password
DB_PREFIX=

# Cache
CACHE_DRIVER=file|redis|memcached|array
CACHE_PREFIX=shopologic
CACHE_TTL=3600

# Session
SESSION_DRIVER=file|database|redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

# Queue
QUEUE_DRIVER=sync|database|redis
QUEUE_RETRY_AFTER=90

# Mail
MAIL_DRIVER=smtp|sendmail|log
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=Shopologic

# Multi-Store
MULTISTORE_ENABLED=true
MULTISTORE_DETECTION=domain,subdomain,path
MULTISTORE_DEFAULT=main

# Search
SEARCH_DRIVER=database|elasticsearch
ELASTICSEARCH_HOST=localhost:9200

# Storage
FILESYSTEM_DRIVER=local|s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=

# API
API_RATE_LIMIT=60
API_RATE_LIMIT_AUTHENTICATED=600
JWT_SECRET=
JWT_TTL=60

# Debug & Logging
LOG_CHANNEL=daily
LOG_LEVEL=debug
DEBUGBAR_ENABLED=false
```

### Configuration Files

#### Main Application Config (`/config/app.php`)
```php
return [
    'name' => env('APP_NAME', 'Shopologic'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => 'en',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
];
```

#### Database Configuration (`/config/database.php`)
```php
return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 5432),
            'database' => env('DB_DATABASE', 'shopologic'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => env('DB_PREFIX', ''),
            'schema' => 'public',
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'shopologic'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => env('DB_PREFIX', ''),
        ],
    ],
    'migrations' => 'migrations',
];
```

#### Service Providers (`/config/services.php`)
```php
return [
    'providers' => [
        // Core Providers
        Core\Providers\AppServiceProvider::class,
        Core\Providers\AuthServiceProvider::class,
        Core\Providers\DatabaseServiceProvider::class,
        Core\Providers\CacheServiceProvider::class,
        Core\Providers\SessionServiceProvider::class,
        Core\Providers\QueueServiceProvider::class,
        Core\Providers\MailServiceProvider::class,
        Core\Providers\EventServiceProvider::class,
        Core\Providers\RouteServiceProvider::class,
        Core\Providers\PluginServiceProvider::class,
        Core\Providers\ThemeServiceProvider::class,
    ],
];
```

### Plugin Configuration

Plugin settings are stored in the database but can have default configs:

```json
// plugins/{plugin-name}/config.json
{
    "settings": {
        "api_key": {
            "type": "string",
            "label": "API Key",
            "required": true,
            "encrypted": true
        },
        "webhook_url": {
            "type": "url",
            "label": "Webhook URL",
            "required": false
        },
        "enabled": {
            "type": "boolean",
            "label": "Enable Plugin",
            "default": true
        }
    }
}
```

### Security Configuration

```php
// config/security.php
return [
    'encryption_key' => env('APP_KEY'),
    'bcrypt_rounds' => 10,
    'password_timeout' => 10800, // 3 hours
    'cors' => [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 86400,
        'supports_credentials' => true,
    ],
    'csp' => [
        'enabled' => true,
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'"],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:', 'https:'],
    ],
];
```

## Dependencies

### Core Dependencies (Zero External)

Shopologic has **zero external package dependencies**. Everything is implemented in pure PHP:

- **PSR Standards**: Interfaces only (PSR-4, PSR-7, PSR-11, PSR-14)
- **Database Drivers**: Pure PHP implementations
- **HTTP Client**: Built-in PHP streams
- **Template Engine**: Custom Twig-like implementation
- **Cache Drivers**: Native PHP implementations

### PHP Requirements

- **PHP Version**: 8.3 or higher
- **Required Extensions**:
  - `json` - JSON encoding/decoding
  - `mbstring` - Multibyte string handling
  - `openssl` - Encryption and security
  - `pdo` - Database connectivity
  - `tokenizer` - PHP parsing
  - `xml` - XML processing
  - `ctype` - Character type checking
  - `fileinfo` - File type detection

- **Optional Extensions** (for better performance):
  - `opcache` - Bytecode caching
  - `redis` - Redis cache driver
  - `memcached` - Memcached driver
  - `gd` or `imagick` - Image processing
  - `zip` - Archive handling
  - `intl` - Internationalization

### Development Dependencies

For development only (not required for production):
```json
{
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "phpstan/phpstan": "^1.0",
        "squizlabs/php_codesniffer": "^3.7"
    }
}
```

### Zero-Dependency Philosophy

Benefits of zero external dependencies:
1. **Security**: No supply chain attacks through compromised packages
2. **Stability**: No breaking changes from external packages
3. **Performance**: Smaller footprint, faster autoloading
4. **Control**: Complete control over all code
5. **Compatibility**: No version conflicts
6. **Maintenance**: Easier long-term maintenance

Implementation approach:
- All functionality built in-house
- Only PSR interfaces imported (contracts, not implementations)
- Custom implementations of common patterns
- Extensive test coverage for reliability

## Development Workflow

### Setting Up Development Environment

```bash
# 1. Clone repository
git clone https://github.com/shopologic/shopologic.git
cd shopologic

# 2. Configure environment
cp .env.example .env
# Edit .env with your database and other settings

# 3. Set permissions
chmod -R 775 storage/
chmod -R 775 database/

# 4. Initialize database
php cli/dbinit.php

# 5. Run migrations
php cli/migrate.php up

# 6. Seed test data (optional)
php cli/seed.php run

# 7. Install core plugins
php cli/plugin.php install core-commerce
php cli/plugin.php activate core-commerce

# 8. Start development server
php -S localhost:8000 -t public/
# Or use the built-in server script
php cli/server.php
```

### Common Development Tasks

#### Creating a New Plugin

```bash
# Generate plugin skeleton
php cli/plugin.php generate my-awesome-plugin

# This creates:
# plugins/my-awesome-plugin/
# ├── plugin.json
# ├── MyAwesomePlugin.php
# ├── src/
# ├── migrations/
# ├── templates/
# └── assets/

# Edit plugin files
cd plugins/my-awesome-plugin
# Update plugin.json with metadata
# Implement functionality in MyAwesomePlugin.php

# Install and activate
php cli/plugin.php install my-awesome-plugin
php cli/plugin.php activate my-awesome-plugin
```

#### Database Migrations

```bash
# Create new migration
php cli/migrate.php create AddProductReviewsTable

# Edit the generated migration file
# database/migrations/2024_XX_XX_XXXXXX_add_product_reviews_table.php

# Run migrations
php cli/migrate.php up

# Rollback last batch
php cli/migrate.php down

# Reset all migrations
php cli/migrate.php reset

# Refresh (reset + migrate + seed)
php cli/migrate.php fresh --seed
```

#### Running Tests

```bash
# Run all tests
php cli/test.php

# Run specific test suite
php cli/test.php --suite=Unit
php cli/test.php --suite=Integration
php cli/test.php --suite=E2E

# Run specific test file
php cli/test.php --filter=ProductTest

# Run with code coverage
php cli/test.php --coverage

# Run with verbose output
php cli/test.php -v
```

#### Code Quality

```bash
# Check coding standards
vendor/bin/phpcs --standard=PSR12 core/ plugins/

# Auto-fix coding standard issues
vendor/bin/phpcbf --standard=PSR12 core/ plugins/

# Run static analysis
vendor/bin/phpstan analyse core/ plugins/ --level=8

# Check for security issues
php cli/security-scan.php
```

#### Cache Management

```bash
# Clear all caches
php cli/cache.php clear

# Clear specific cache types
php cli/cache.php clear --type=routes
php cli/cache.php clear --type=views
php cli/cache.php clear --type=config

# Warm up caches
php cli/cache.php warm

# View cache statistics
php cli/cache.php stats
```

### Git Workflow

```bash
# Feature branch workflow
git checkout -b feature/add-wishlist

# Make changes and commit
git add .
git commit -m "Add wishlist functionality"

# Push to remote
git push origin feature/add-wishlist

# Create pull request for review
```

### Debugging

#### Enable Debug Mode
```bash
# In .env
APP_DEBUG=true
APP_ENV=development
```

#### Debug Toolbar
When debug mode is enabled, a toolbar appears with:
- Execution time
- Memory usage
- Database queries
- Route information
- Session data
- Cache hits/misses

#### Logging
```php
// Log debug information
app('log')->debug('Product viewed', ['product_id' => $id]);

// Log errors
app('log')->error('Payment failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);

// Check logs
tail -f storage/logs/shopologic-{date}.log
```

#### Using Xdebug
```ini
; php.ini configuration
xdebug.mode=debug
xdebug.client_host=localhost
xdebug.client_port=9003
xdebug.start_with_request=yes
```

### Deployment Checklist

- [ ] Run tests: `php cli/test.php`
- [ ] Check code standards: `vendor/bin/phpcs`
- [ ] Run static analysis: `vendor/bin/phpstan analyse`
- [ ] Update dependencies (if any)
- [ ] Run migrations: `php cli/migrate.php up`
- [ ] Clear caches: `php cli/cache.php clear`
- [ ] Warm caches: `php cli/cache.php warm`
- [ ] Set APP_ENV=production
- [ ] Set APP_DEBUG=false
- [ ] Enable OPcache
- [ ] Configure proper file permissions
- [ ] Set up SSL certificates
- [ ] Configure backup strategy
- [ ] Set up monitoring

## Plugin System

### Plugin Architecture

#### Plugin Structure
```
/plugins/my-plugin/
├── plugin.json          → Plugin manifest
├── MyPlugin.php         → Main plugin class
├── /src                → Source code
│   ├── /Controllers    → HTTP controllers
│   ├── /Models         → Data models
│   ├── /Services       → Business logic
│   └── /Repositories   → Data access
├── /migrations         → Database migrations
├── /templates          → View templates
├── /assets             → Static assets
│   ├── /css
│   ├── /js
│   └── /images
├── /config             → Configuration files
├── /lang               → Language files
└── README.md           → Documentation
```

#### Plugin Manifest (plugin.json)
```json
{
    "name": "my-plugin",
    "version": "1.0.0",
    "description": "My awesome plugin",
    "author": "Your Name",
    "namespace": "MyPlugin",
    "class": "MyPlugin\\MyPlugin",
    "dependencies": {
        "shopologic/core": "^1.0",
        "core-commerce": "^1.0"
    },
    "permissions": [
        "hooks.order.*",
        "api.access",
        "dashboard.widget",
        "database.write"
    ],
    "settings": {
        "configurable": true,
        "api_enabled": true,
        "widget_enabled": true
    },
    "hooks": [
        "order.created",
        "product.updated"
    ],
    "api_endpoints": [
        "GET /api/v1/my-plugin/data",
        "POST /api/v1/my-plugin/action"
    ]
}
```

### Plugin Development

#### Basic Plugin Class
```php
namespace MyPlugin;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Hook\HookSystem;

class MyPlugin extends AbstractPlugin
{
    public function boot(): void
    {
        // Register hooks
        HookSystem::addAction('order.created', [$this, 'onOrderCreated'], 10);
        HookSystem::addFilter('product.price', [$this, 'filterProductPrice'], 10);
        
        // Register services
        $this->container->bind(MyService::class);
        $this->container->singleton(MyRepository::class);
        
        // Register routes
        $this->registerRoute('GET', '/my-plugin/dashboard', [MyController::class, 'dashboard']);
        $this->registerApiRoute('GET', '/my-plugin/data', [MyApiController::class, 'getData']);
        
        // Register widgets
        $this->registerWidget('my_widget', [$this, 'renderWidget']);
        
        // Register menu items
        $this->registerAdminMenu('My Plugin', '/admin/my-plugin', 'manage_plugins');
    }
    
    public function install(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Create default configuration
        $this->config->set('my_plugin.enabled', true);
        $this->config->set('my_plugin.api_key', '');
        
        // Copy assets
        $this->publishAssets();
    }
    
    public function uninstall(): void
    {
        // Remove configuration
        $this->config->forget('my_plugin');
        
        // Optional: Remove database tables
        // $this->rollbackMigrations();
    }
    
    public function activate(): void
    {
        // Enable plugin features
        $this->cache->forget('plugin.routes');
    }
    
    public function deactivate(): void
    {
        // Disable plugin features
        $this->cache->forget('plugin.routes');
    }
}
```

### Hook System

#### Actions (Side Effects)
```php
// Add action hook
HookSystem::addAction('order.created', function($order) {
    // Send notification
    app('mail')->send('order.confirmation', [
        'order' => $order,
        'customer' => $order->customer
    ]);
}, $priority = 10);

// Trigger action
HookSystem::doAction('order.created', $order);
```

#### Filters (Modify Values)
```php
// Add filter hook
HookSystem::addFilter('product.price', function($price, $product) {
    // Apply 10% discount
    return $price * 0.9;
}, $priority = 10);

// Apply filter
$finalPrice = HookSystem::applyFilters('product.price', $price, $product);
```

#### Available Core Hooks

**Product Hooks**:
- `product.creating` / `product.created`
- `product.updating` / `product.updated`
- `product.deleting` / `product.deleted`
- `product.price` (filter)
- `product.stock_check` (filter)

**Order Hooks**:
- `order.creating` / `order.created`
- `order.status_changing` / `order.status_changed`
- `order.completing` / `order.completed`
- `order.cancelling` / `order.cancelled`
- `order.total` (filter)

**Cart Hooks**:
- `cart.adding` / `cart.added`
- `cart.updating` / `cart.updated`
- `cart.removing` / `cart.removed`
- `cart.clearing` / `cart.cleared`

**Customer Hooks**:
- `customer.registering` / `customer.registered`
- `customer.logging_in` / `customer.logged_in`
- `customer.updating` / `customer.updated`

### Plugin Services

#### Registering Services
```php
// In plugin boot method
$this->container->bind(ServiceInterface::class, ServiceImplementation::class);
$this->container->singleton(SingletonService::class);

// With factory
$this->container->bind(ComplexService::class, function($container) {
    return new ComplexService(
        $container->get(DependencyOne::class),
        $container->get(DependencyTwo::class),
        $this->config->get('my_plugin.setting')
    );
});
```

#### Using Services
```php
// In controllers or other classes
public function __construct(
    private MyService $myService,
    private ProductRepository $products
) {}

// Or resolve manually
$service = $this->container->get(MyService::class);
```

### Plugin Routes

#### Web Routes
```php
// In plugin boot method
$this->router->group(['prefix' => 'my-plugin'], function($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->post('/settings', [SettingsController::class, 'update']);
});
```

#### API Routes
```php
// In plugin boot method
$this->router->group(['prefix' => 'api/v1/my-plugin'], function($router) {
    $router->get('/data', [ApiController::class, 'getData']);
    $router->post('/webhook', [WebhookController::class, 'handle']);
});
```

### Plugin Configuration

#### Setting Configuration
```php
// Set config value
$this->config->set('my_plugin.api_key', $apiKey);

// Get config value
$apiKey = $this->config->get('my_plugin.api_key');

// Check if exists
if ($this->config->has('my_plugin.enabled')) {
    // ...
}
```

#### Configuration UI
```php
// Register settings page
$this->registerSettingsPage('my-plugin', function() {
    return view('my-plugin::settings', [
        'settings' => $this->config->get('my_plugin')
    ]);
});
```

### Plugin Assets

#### Publishing Assets
```php
// In install method
$this->publishes([
    __DIR__.'/assets' => public_path('plugins/my-plugin')
]);
```

#### Using Assets in Templates
```twig
<!-- In template files -->
<link rel="stylesheet" href="{{ asset('plugins/my-plugin/css/style.css') }}">
<script src="{{ asset('plugins/my-plugin/js/script.js') }}"></script>
```

### Plugin Migrations

#### Creating Migrations
```php
// migrations/2024_01_01_create_my_plugin_tables.php
use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateMyPluginTables extends Migration
{
    public function up(): void
    {
        Schema::create('my_plugin_data', function($table) {
            $table->id();
            $table->string('key');
            $table->json('value');
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('my_plugin_data');
    }
}
```

### Plugin Best Practices

1. **Namespace Everything**: Use your plugin namespace to avoid conflicts
2. **Use Dependency Injection**: Don't hardcode dependencies
3. **Follow PSR Standards**: PSR-4 autoloading, PSR-12 coding style
4. **Document Your Hooks**: List all hooks your plugin provides
5. **Version Your Plugin**: Use semantic versioning
6. **Test Your Plugin**: Write unit and integration tests
7. **Handle Errors Gracefully**: Don't break the entire system
8. **Clean Up on Uninstall**: Remove data and settings

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
├── /emails            → Email templates
│   └── order-confirmation.twig
└── README.md
```

### Theme Configuration (theme.json)
```json
{
    "name": "My Theme",
    "version": "1.0.0",
    "description": "A modern e-commerce theme",
    "author": "Your Name",
    "parent": null,
    "regions": {
        "header": {
            "name": "Header",
            "accepts": ["navigation", "search", "cart"]
        },
        "content": {
            "name": "Main Content",
            "accepts": ["*"]
        },
        "sidebar": {
            "name": "Sidebar",
            "accepts": ["filters", "categories", "ads"]
        },
        "footer": {
            "name": "Footer",
            "accepts": ["navigation", "newsletter", "social"]
        }
    },
    "settings": {
        "colors": {
            "primary": "#007bff",
            "secondary": "#6c757d",
            "success": "#28a745",
            "danger": "#dc3545"
        },
        "fonts": {
            "body": "Inter, sans-serif",
            "heading": "Poppins, sans-serif"
        },
        "layout": {
            "container_width": "1200px",
            "sidebar_position": "right"
        }
    }
}
```

### Template Syntax (Twig-like)

```twig
{# Extends layout #}
{% extends "layouts/base.twig" %}

{# Define block #}
{% block content %}
    <div class="product-page">
        <h1>{{ product.name }}</h1>
        
        {# Output with escaping #}
        <div class="description">
            {{ product.description|safe }}
        </div>
        
        {# Conditional #}
        {% if product.on_sale %}
            <span class="sale-price">{{ product.sale_price|currency }}</span>
            <span class="original-price">{{ product.price|currency }}</span>
        {% else %}
            <span class="price">{{ product.price|currency }}</span>
        {% endif %}
        
        {# Loop #}
        <div class="images">
            {% for image in product.images %}
                <img src="{{ image.url }}" alt="{{ image.alt }}" />
            {% endfor %}
        </div>
        
        {# Include partial #}
        {% include "partials/product-reviews.twig" with {reviews: product.reviews} %}
        
        {# Hook integration #}
        {% hook "product.after_description" product %}
        
        {# Component #}
        {% component "add-to-cart" product=product %}
    </div>
{% endblock %}

{# Define additional blocks #}
{% block scripts %}
    <script src="{{ theme_asset('js/product.js') }}"></script>
{% endblock %}
```

### Available Template Functions

```twig
{# URL generation #}
{{ url('products.show', {id: product.id}) }}
{{ route('cart.index') }}

{# Assets #}
{{ asset('images/logo.png') }}
{{ theme_asset('css/style.css') }}

{# Translations #}
{{ __('cart.add_to_cart') }}
{{ trans('messages.welcome', {name: user.name}) }}

{# Formatting #}
{{ price|currency }}
{{ date|format('Y-m-d') }}
{{ text|truncate(100) }}
{{ content|markdown }}

{# Security #}
{{ csrf_token() }}
{{ csrf_field() }}

{# Debugging (development only) #}
{{ dump(variable) }}
```

### Component System

```json
// components/product-card/component.json
{
    "name": "product-card",
    "description": "Product card display",
    "parameters": {
        "product": {
            "type": "object",
            "required": true
        },
        "show_rating": {
            "type": "boolean",
            "default": true
        },
        "show_quick_view": {
            "type": "boolean",
            "default": false
        }
    }
}
```

```twig
{# components/product-card/product-card.twig #}
<div class="product-card">
    <a href="{{ url('products.show', {slug: product.slug}) }}">
        <img src="{{ product.thumbnail }}" alt="{{ product.name }}">
        <h3>{{ product.name }}</h3>
        <div class="price">{{ product.price|currency }}</div>
        
        {% if show_rating and product.average_rating %}
            <div class="rating">
                {% component "star-rating" rating=product.average_rating %}
            </div>
        {% endif %}
    </a>
    
    {% if show_quick_view %}
        <button class="quick-view" data-product-id="{{ product.id }}">
            {{ __('Quick View') }}
        </button>
    {% endif %}
</div>
```

### Theme Development

#### Creating a Child Theme
```json
// theme.json
{
    "name": "My Child Theme",
    "parent": "default",
    "version": "1.0.0"
}
```

Child themes inherit all templates and assets from parent but can override specific files.

#### Theme Hooks
```php
// In theme's functions.php (if exists)
use Shopologic\Core\Hook\HookSystem;

// Modify theme behavior
HookSystem::addFilter('theme.settings', function($settings) {
    $settings['custom_option'] = true;
    return $settings;
});

// Add custom functionality
HookSystem::addAction('theme.loaded', function() {
    // Register custom post types, widgets, etc.
});
```

#### Live Theme Editor Integration
Themes can define editable regions and settings:

```json
{
    "editor": {
        "sections": {
            "hero": {
                "name": "Hero Banner",
                "settings": {
                    "title": {
                        "type": "text",
                        "label": "Title",
                        "default": "Welcome to Our Store"
                    },
                    "background": {
                        "type": "image",
                        "label": "Background Image"
                    },
                    "height": {
                        "type": "select",
                        "label": "Height",
                        "options": {
                            "small": "Small (300px)",
                            "medium": "Medium (500px)",
                            "large": "Large (700px)"
                        },
                        "default": "medium"
                    }
                }
            }
        }
    }
}
```

## Security Implementation

### Authentication & Authorization

#### Authentication System
```php
// Multiple authentication guards
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
    'admin' => [
        'driver' => 'session',
        'provider' => 'admins',
    ],
]
```

#### JWT Implementation
- Token generation on login
- Refresh token mechanism
- Token blacklisting on logout
- Configurable expiration times

#### Permission System
```php
// Role-based permissions
$user->hasRole('admin');
$user->hasPermission('products.edit');
$user->can('edit', $product);

// Plugin permission checks
if ($plugin->requiresPermission('api.access')) {
    // Check if current context has permission
}
```

### Input Validation & Sanitization

#### Request Validation
```php
class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'sku' => 'required|unique:products,sku',
            'description' => 'string|max:5000',
            'images.*' => 'image|max:2048',
        ];
    }
    
    public function sanitize(): array
    {
        return [
            'name' => 'strip_tags|trim',
            'description' => 'purify', // HTML purifier
        ];
    }
}
```

#### XSS Prevention
- Automatic output escaping in templates
- HTML Purifier for rich content
- Content Security Policy headers
- Sanitization helpers

### SQL Injection Prevention

#### Query Builder Protection
```php
// Safe - Uses parameter binding
$products = DB::table('products')
    ->where('name', 'LIKE', "%{$search}%")
    ->where('price', '>', $minPrice)
    ->get();

// Model queries are safe by default
$product = Product::where('sku', $sku)->firstOrFail();

// Raw queries use binding
DB::select('SELECT * FROM products WHERE id = ?', [$id]);
```

### CSRF Protection

#### Implementation
```php
// Middleware automatically validates CSRF tokens
protected $middleware = [
    \Core\Http\Middleware\VerifyCsrfToken::class,
];

// Excluding specific routes
protected $except = [
    'api/*',
    'webhooks/*',
];
```

#### Template Integration
```twig
{# Automatic CSRF field in forms #}
<form method="POST">
    {{ csrf_field() }}
    <!-- form fields -->
</form>

{# AJAX requests #}
<meta name="csrf-token" content="{{ csrf_token() }}">
```

### File Upload Security

#### Upload Validation
```php
// File type validation
$request->validate([
    'file' => 'required|file|mimes:jpg,png,pdf|max:10240',
]);

// Additional checks
$file = $request->file('file');
$mimeType = $file->getMimeType(); // Actual MIME check
$extension = $file->getClientOriginalExtension();

// Sanitize filename
$filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
$filename = $filename . '_' . uniqid() . '.' . $extension;
```

#### Storage Security
- Files stored outside web root
- Served through controlled routes
- Permission checks before serving
- Virus scanning integration available

### API Security

#### Rate Limiting
```php
// Route-level rate limiting
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/api/products', 'ProductController@index');
});

// Custom rate limits
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
});
```

#### API Authentication
- JWT tokens for stateless auth
- API key management
- OAuth2 server implementation
- Signature verification for webhooks

### Session Security

#### Configuration
```php
'session' => [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => true,
    'secure' => true, // HTTPS only
    'http_only' => true, // No JavaScript access
    'same_site' => 'lax',
]
```

### Encryption

#### Data Encryption
```php
// Encrypt sensitive data
$encrypted = encrypt($sensitiveData);
$decrypted = decrypt($encrypted);

// Database encryption for sensitive fields
class Customer extends Model
{
    protected $casts = [
        'ssn' => 'encrypted',
        'credit_card' => 'encrypted',
    ];
}
```

### Security Headers

```php
// Middleware sets security headers
'headers' => [
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline';",
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
]
```

### Plugin Security

#### Code Scanning
```php
// Plugin code scanner checks for:
- eval() usage
- shell_exec() and system() calls
- File system operations outside allowed paths
- Network requests to unauthorized domains
- Database query construction
- Suspicious patterns
```

#### Sandboxing
- Plugins run with limited permissions
- Resource usage limits
- Network request restrictions
- File system access controls

### Audit Logging

```php
// Automatic audit logging for sensitive operations
AuditLog::record([
    'user_id' => $user->id,
    'action' => 'product.price.changed',
    'model' => Product::class,
    'model_id' => $product->id,
    'old_values' => ['price' => 99.99],
    'new_values' => ['price' => 89.99],
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
]);
```

## Performance Architecture
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