# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Shopologic is an enterprise-grade, self-contained e-commerce platform built with pure PHP 8.3+ and zero external dependencies (except PSR standards). It features a microkernel plugin architecture, comprehensive API framework (REST + GraphQL), live theme editor, and full multi-store capabilities.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

The platform now includes a comprehensive plugin ecosystem with:
- 47 advanced models with sophisticated business logic
- Cross-plugin integration via standardized interfaces  
- Real-time event system with middleware support
- Performance monitoring and health checks
- Automated testing framework with multiple test types
- Complete documentation and working demonstrations

## Core Architecture

### Directory Structure
- `core/` - Framework foundation (PSR implementations, kernel, container, events, HTTP, database)
- `plugins/` - Modular functionality (payment gateways, shipping, analytics, etc.)
- `themes/` - Frontend templates with live editor support
- `public/` - Web entry points (index.php, api.php, admin.php)
- `storage/` - Runtime data (cache, logs, uploads, sessions)
- `database/` - Schema management (migrations, seeds, schemas)

### Key Components
- **Microkernel**: Plugin-based architecture with hot-swappable modules
- **Service Container**: PSR-11 compliant DI with auto-wiring and service tagging
- **Event System**: PSR-14 dispatcher with sync/async processing and hook system
- **Database Layer**: Pure PHP PostgreSQL driver with master-slave support
- **ORM**: Active Record pattern with relationships and eager loading
- **Template Engine**: Twig-like syntax with component system and hook integration
- **API Framework**: Auto-generated REST endpoints with GraphQL server
- **Plugin System**: WordPress-style hooks with dependency resolution

## Development Commands

### Database Operations
```bash
# Run migrations
php cli/migrate.php up

# Create new migration
php cli/migrate.php create CreateProductsTable

# Seed database
php cli/seed.php run

# Reset database
php cli/migrate.php reset
```

### Plugin Ecosystem Bootstrap
```bash
# Initialize complete plugin ecosystem
php bootstrap_plugins.php

# Run with integration demonstration
php bootstrap_plugins.php --demo

# Check system health
php -r "require 'bootstrap_plugins.php'; var_dump(getSystemStatus());"
```

### Plugin Management
```bash
# Install plugin
php cli/plugin.php install payment-stripe

# Activate plugin
php cli/plugin.php activate payment-stripe

# List plugins
php cli/plugin.php list

# Generate plugin scaffold
php cli/plugin.php generate MyPlugin
```

### Cache Operations
```bash
# Clear all caches
php cli/cache.php clear

# Clear specific cache
php cli/cache.php clear --type=routes

# Warm cache
php cli/cache.php warm
```

### Testing
```bash
# Run all tests
php cli/test.php

# Run specific test suite
php cli/test.php --suite=Unit

# Run with coverage
php cli/test.php --coverage
```

### Development Server
```bash
# Start development server
php -S localhost:17000 -t public/

# With specific environment
APP_ENV=development php -S localhost:17000 -t public/
```

## Plugin Development

### Plugin Structure
Each plugin must include:
- `plugin.json` - Manifest with metadata, dependencies, permissions
- Main plugin class extending `Plugin` base class
- Database migrations in `migrations/` directory
- Templates in `templates/` directory
- Assets in `assets/` directory

### Hook System
```php
// Action hooks (no return value)
HookSystem::addAction('order_created', $callback, $priority);

// Filter hooks (modify values)  
HookSystem::addFilter('product_price', $callback, $priority);

// Conditional hooks
HookSystem::addConditionalAction('payment_failed', $condition, $callback);
```

### Plugin Registration
```php
class MyPlugin extends Plugin {
    protected function registerServices(): void {
        $this->container->bind(ServiceInterface::class, ServiceImplementation::class);
    }
    
    protected function registerHooks(): void {
        HookSystem::addAction('order_created', [$this, 'handleOrderCreated']);
    }
    
    protected function registerRoutes(): void {
        $this->registerRoute('POST', '/api/my-plugin/endpoint', [$this, 'apiEndpoint']);
    }
}
```

## Theme Development

### Component System
- Components defined in JSON with settings schema
- Twig templates with hook integration
- SCSS styling with auto-compilation
- JavaScript with module system

### Live Editor Integration
- Real-time preview with iframe isolation
- Drag-drop component placement
- Visual style editor
- Responsive design testing

## API Development

### REST Endpoints
Auto-generated endpoints following convention:
- `GET /api/v1/products` - List products with filtering/pagination
- `POST /api/v1/products` - Create product  
- `GET /api/v1/products/{id}` - Get specific product
- `PUT /api/v1/products/{id}` - Update product
- `DELETE /api/v1/products/{id}` - Delete product

### GraphQL Schema
- Type-safe schema with resolvers
- Cursor-based pagination
- Subscription support for real-time updates
- Query complexity analysis

## Security Considerations

### Authentication
- JWT tokens with refresh mechanism
- API key authentication for server-to-server
- OAuth2 for third-party integrations
- Session-based for web interface

### Input Validation
- All inputs validated through request classes
- Parameterized queries exclusively
- CSRF protection on state-changing operations
- Rate limiting per user/IP

### Plugin Security
- Code scanning for malicious patterns
- Permission boundary enforcement
- Resource usage limiting
- Network request monitoring

## Performance Guidelines

### Caching Strategy
- OpCode caching (OPcache) enabled
- Application cache (Redis preferred)
- Database query result caching
- HTTP response caching with ETags
- CDN integration for static assets

### Database Optimization
- Use query builder for complex queries
- Implement eager loading for relationships
- Index frequently queried columns
- Use read replicas for scaling

### Memory Management
- Implement pagination for large datasets
- Use generators for processing large collections
- Clear objects from memory when done
- Monitor memory usage in long-running processes

## Development Workflow

1. **Feature Development**: Create feature branch, implement in phases
2. **Plugin Development**: Use plugin generator, follow manifest schema
3. **Theme Development**: Use component system, test in live editor
4. **API Development**: Follow REST conventions, update GraphQL schema
5. **Testing**: Write unit tests, integration tests, and E2E tests
6. **Documentation**: Update API docs, plugin guides, and user manuals

## Common Patterns

### Service Registration
```php
$container->bind(PaymentGatewayInterface::class, StripePaymentGateway::class);
$container->singleton(CacheInterface::class, RedisCache::class);
$container->tag([StripeGateway::class, PayPalGateway::class], 'payment.gateway');
```

### Event Usage
```php
// Dispatch event
event(new OrderCreatedEvent($order));

// Listen for event
EventDispatcher::listen(OrderCreatedEvent::class, OrderCreatedListener::class);
```

### Database Queries
```php
// Query builder
$products = DB::table('products')
    ->where('status', 'active')
    ->whereIn('category_id', [1, 2, 3])
    ->orderBy('created_at', 'desc')
    ->paginate(20);

// ORM relationships
$product = Product::with(['category', 'images', 'reviews'])
    ->findOrFail($id);
```

This architecture ensures Shopologic remains maintainable, scalable, and extensible while providing enterprise-level features without external dependencies.