# Shopologic Plugin Development Guide

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This guide covers plugin development for the enhanced Shopologic ecosystem featuring 47 advanced models, cross-plugin integration, real-time events, performance monitoring, and automated testing frameworks.

## 🚀 Quick Start with Enhanced Ecosystem

```bash
# Initialize complete plugin ecosystem
php bootstrap_plugins.php

# Run with integration demonstration
php bootstrap_plugins.php --demo
```

## Table of Contents
1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Plugin Structure](#plugin-structure)
4. [Plugin Manifest (plugin.json)](#plugin-manifest)
5. [Creating Your First Plugin](#creating-your-first-plugin)
6. [Hook System](#hook-system)
7. [Service Registration](#service-registration)
8. [Database Operations](#database-operations)
9. [API Development](#api-development)
10. [Best Practices](#best-practices)
11. [Testing](#testing)
12. [Distribution](#distribution)

## Introduction

Shopologic uses a microkernel plugin architecture that allows developers to extend the platform's functionality without modifying core code. Plugins are self-contained modules that can add features, modify behavior, or integrate with external services.

## Getting Started

### Prerequisites
- PHP 8.3 or higher
- Basic understanding of PHP OOP and namespaces
- Familiarity with PSR standards
- Knowledge of Composer autoloading

### Plugin Generator

The easiest way to create a new plugin is using the built-in generator:

```bash
# Generate a standard plugin
php cli/generate-plugin.php my-awesome-plugin

# Generate a full-featured plugin with API
php cli/generate-plugin.php payment-gateway --type=full

# Generate an API-focused plugin
php cli/generate-plugin.php api-integration --type=api

# Generate a theme plugin
php cli/generate-plugin.php custom-theme --type=theme

# Generate a minimal plugin
php cli/generate-plugin.php simple-feature --type=minimal
```

### Plugin Types

- **standard**: Basic plugin with migrations and templates
- **full**: Full-featured plugin with API, migrations, templates, and assets
- **api**: API-focused plugin with controllers and services
- **theme**: Theme plugin with templates and assets
- **minimal**: Minimal plugin with just the basics

## Plugin Structure

### Standard Directory Layout

```
my-plugin/
├── plugin.json          # Plugin manifest (required)
├── README.md           # Documentation
├── .gitignore          # Git ignore file
├── src/                # PHP source code
│   ├── MyPluginPlugin.php  # Main plugin class (required)
│   ├── Controllers/    # API/Web controllers
│   ├── Services/       # Business logic
│   ├── Models/         # Data models
│   └── Helpers/        # Utility classes
├── migrations/         # Database migrations
├── templates/          # Twig templates
├── assets/            # Static assets
│   ├── css/
│   ├── js/
│   └── images/
└── tests/             # PHPUnit tests
```

### Minimal Structure

For simple plugins, you can use a minimal structure:

```
my-plugin/
├── plugin.json
└── MyPluginPlugin.php
```

## Plugin Manifest

The `plugin.json` file defines your plugin's metadata and configuration:

```json
{
    "name": "my-plugin",
    "version": "1.0.0",
    "description": "A comprehensive description of what your plugin does",
    "author": "Your Name <your.email@example.com>",
    "main": "src/MyPluginPlugin.php",
    "namespace": "MyPlugin",
    "autoload": {
        "psr-4": {
            "MyPlugin\\": "src/"
        }
    },
    "requirements": {
        "php": ">=8.3",
        "shopologic": ">=1.0.0"
    },
    "dependencies": {
        "core-commerce": ">=1.0.0",
        "payment-processing": "^2.0"
    },
    "permissions": [
        "my-plugin.view",
        "my-plugin.manage",
        "my-plugin.settings"
    ],
    "hooks": {
        "actions": [
            "order_created",
            "payment_processed"
        ],
        "filters": [
            "product_price",
            "shipping_rates"
        ]
    },
    "api_endpoints": [
        {
            "method": "GET",
            "path": "/api/v1/my-plugin/items",
            "handler": "listItems",
            "description": "List all items"
        }
    ],
    "cron_jobs": [
        {
            "schedule": "0 2 * * *",
            "handler": "dailyCleanup",
            "description": "Daily cleanup task"
        }
    ],
    "widgets": [
        {
            "id": "my-plugin-stats",
            "title": "Plugin Statistics",
            "description": "Shows plugin usage statistics",
            "handler": "renderStatsWidget"
        }
    ],
    "settings": {
        "enabled": {
            "type": "boolean",
            "default": true,
            "label": "Enable Plugin"
        },
        "api_key": {
            "type": "string",
            "default": "",
            "label": "API Key",
            "encrypted": true
        }
    }
}
```

### Manifest Fields

- **name** (required): Unique plugin identifier (kebab-case)
- **version** (required): Semantic version (X.Y.Z)
- **description** (required): Plugin description
- **author** (required): Author name and optional email
- **main** (required): Path to main plugin class file
- **namespace**: PHP namespace for the plugin
- **autoload**: PSR-4 autoloading configuration
- **requirements**: System requirements
- **dependencies**: Other plugins this plugin depends on
- **permissions**: Permissions this plugin registers
- **hooks**: Hooks this plugin listens to
- **api_endpoints**: REST API endpoints
- **cron_jobs**: Scheduled tasks
- **widgets**: Dashboard widgets
- **settings**: Plugin settings schema

## Creating Your First Plugin

### Step 1: Generate the Plugin

```bash
php cli/generate-plugin.php hello-world
```

### Step 2: Implement the Main Class

Edit `src/HelloWorldPlugin.php`:

```php
<?php

declare(strict_types=1);

namespace HelloWorld;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;

class HelloWorldPlugin extends AbstractPlugin
{
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Set default options
        $this->setOption('hello_world_message', 'Hello, World!');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Clean up if needed
    }

    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Add action hook
        HookSystem::addAction('init', [$this, 'initialize']);
        
        // Add filter hook
        HookSystem::addFilter('page_title', [$this, 'modifyPageTitle']);
        
        // Register shortcode
        HookSystem::addShortcode('hello_world', [$this, 'renderShortcode']);
    }

    /**
     * Register services
     */
    protected function registerServices(): void
    {
        // Bind services to container
        $this->container->singleton(HelloWorldService::class);
    }

    /**
     * Register routes
     */
    protected function registerRoutes(): void
    {
        // Register a custom page
        $this->registerRoute('GET', '/hello-world', [$this, 'showHelloWorld']);
        
        // Register API endpoint
        $this->registerRoute('GET', '/api/v1/hello-world', [$this, 'apiHelloWorld']);
    }

    /**
     * Initialize plugin
     */
    public function initialize(): void
    {
        // Initialization logic
        $this->logger->info('Hello World plugin initialized');
    }

    /**
     * Modify page title
     */
    public function modifyPageTitle(string $title): string
    {
        if ($this->getOption('hello_world_enabled')) {
            return $title . ' - Hello World!';
        }
        return $title;
    }

    /**
     * Render shortcode
     */
    public function renderShortcode(array $attrs = []): string
    {
        $message = $attrs['message'] ?? $this->getOption('hello_world_message');
        return "<div class='hello-world'>{$message}</div>";
    }

    /**
     * Show hello world page
     */
    public function showHelloWorld(Request $request, Response $response): Response
    {
        return $this->render('hello-world', [
            'message' => $this->getOption('hello_world_message')
        ]);
    }

    /**
     * API endpoint
     */
    public function apiHelloWorld(Request $request, Response $response): Response
    {
        return $response->json([
            'message' => $this->getOption('hello_world_message'),
            'version' => $this->getVersion()
        ]);
    }
}
```

### Step 3: Create a Template

Create `templates/hello-world.twig`:

```twig
{% extends "base.twig" %}

{% block title %}Hello World Plugin{% endblock %}

{% block content %}
<div class="container">
    <h1>{{ message }}</h1>
    
    <div class="card">
        <div class="card-body">
            <p>This is a sample page from the Hello World plugin.</p>
            
            {% do action('hello_world_content') %}
        </div>
    </div>
</div>
{% endblock %}
```

### Step 4: Add a Migration

Create `migrations/001_create_hello_world_table.php`:

```php
<?php

declare(strict_types=1);

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Blueprint;
use Shopologic\Core\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hello_world_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->string('author')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
            
            $table->index('published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hello_world_messages');
    }
};
```

### Step 5: Activate the Plugin

```bash
php cli/plugin.php activate hello-world
```

## Hook System

Shopologic uses a WordPress-style hook system for extensibility:

### Action Hooks

Actions are hooks that allow you to execute code at specific points:

```php
// Add an action
HookSystem::addAction('order_created', function($order) {
    // Send notification email
    mail($order->email, 'Order Confirmation', 'Your order has been received.');
});

// Add action with priority
HookSystem::addAction('order_created', [$this, 'logOrder'], 10);
HookSystem::addAction('order_created', [$this, 'updateInventory'], 20);

// Execute actions
HookSystem::doAction('order_created', $order);
```

### Filter Hooks

Filters allow you to modify data:

```php
// Add a filter
HookSystem::addFilter('product_price', function($price, $product) {
    // Apply 10% discount
    return $price * 0.9;
}, 10, 2);

// Apply filters
$finalPrice = HookSystem::applyFilters('product_price', $price, $product);
```

### Common Hooks

#### Actions
- `init` - Plugin initialization
- `admin_init` - Admin area initialization
- `order_created` - After order creation
- `order_completed` - When order is completed
- `user_registered` - After user registration
- `payment_processed` - After successful payment
- `product_saved` - After product is saved
- `before_template_render` - Before rendering template

#### Filters
- `product_price` - Modify product price
- `shipping_rates` - Modify shipping rates
- `tax_rate` - Modify tax calculations
- `menu_items` - Add/modify menu items
- `permissions_list` - Register permissions
- `template_paths` - Add template directories
- `api_response` - Modify API responses

## Service Registration

Use the service container for dependency injection:

```php
protected function registerServices(): void
{
    // Simple binding
    $this->container->bind(PaymentInterface::class, StripePayment::class);
    
    // Singleton binding
    $this->container->singleton(CacheService::class);
    
    // Factory binding
    $this->container->bind(Logger::class, function($container) {
        return new Logger($container->get(Config::class)->get('log.level'));
    });
    
    // Tagged services
    $this->container->tag([
        EmailNotifier::class,
        SmsNotifier::class,
        PushNotifier::class
    ], 'notifiers');
}
```

## Database Operations

### Using Query Builder

```php
use Shopologic\Core\Database\DB;

// Select
$products = DB::table('products')
    ->where('status', 'active')
    ->where('price', '>', 100)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// Insert
$id = DB::table('products')->insertGetId([
    'name' => 'New Product',
    'price' => 99.99,
    'created_at' => now()
]);

// Update
DB::table('products')
    ->where('id', $id)
    ->update(['price' => 89.99]);

// Delete
DB::table('products')
    ->where('id', $id)
    ->delete();
```

### Using Models

```php
namespace MyPlugin\Models;

use Shopologic\Core\Database\Model;

class Product extends Model
{
    protected string $table = 'products';
    
    protected array $fillable = ['name', 'price', 'description'];
    
    protected array $casts = [
        'price' => 'float',
        'active' => 'boolean'
    ];
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}

// Usage
$product = Product::find(1);
$products = Product::where('active', true)->get();
$product = Product::create(['name' => 'New Product', 'price' => 99.99]);
```

## API Development

### Registering Endpoints

```php
protected function registerRoutes(): void
{
    // REST endpoints
    $this->registerRoute('GET', '/api/v1/products', [ProductController::class, 'index']);
    $this->registerRoute('POST', '/api/v1/products', [ProductController::class, 'store']);
    $this->registerRoute('GET', '/api/v1/products/{id}', [ProductController::class, 'show']);
    $this->registerRoute('PUT', '/api/v1/products/{id}', [ProductController::class, 'update']);
    $this->registerRoute('DELETE', '/api/v1/products/{id}', [ProductController::class, 'destroy']);
}
```

### Controller Example

```php
namespace MyPlugin\Controllers;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use MyPlugin\Services\ProductService;

class ProductController
{
    public function __construct(
        private ProductService $service
    ) {}
    
    public function index(Request $request, Response $response): Response
    {
        $products = $this->service->paginate(
            $request->query('page', 1),
            $request->query('limit', 20)
        );
        
        return $response->json([
            'success' => true,
            'data' => $products
        ]);
    }
    
    public function store(Request $request, Response $response): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string'
        ]);
        
        $product = $this->service->create($validated);
        
        return $response->json([
            'success' => true,
            'data' => $product
        ], 201);
    }
}
```

### GraphQL Support

```php
// Register GraphQL types
HookSystem::addFilter('graphql_types', function($types) {
    $types['Product'] = [
        'fields' => [
            'id' => ['type' => 'ID!'],
            'name' => ['type' => 'String!'],
            'price' => ['type' => 'Float!'],
            'reviews' => [
                'type' => '[Review]',
                'resolve' => function($product) {
                    return $product->reviews()->get();
                }
            ]
        ]
    ];
    return $types;
});
```

## Best Practices

### 1. Follow PSR Standards
- PSR-4 for autoloading
- PSR-12 for coding style
- PSR-7 for HTTP messages
- PSR-11 for containers

### 2. Use Dependency Injection
```php
// Good
public function __construct(
    private PaymentGateway $gateway,
    private Logger $logger
) {}

// Bad
public function process()
{
    $gateway = new PaymentGateway();
    $logger = new Logger();
}
```

### 3. Implement Proper Error Handling
```php
try {
    $result = $this->processPayment($order);
} catch (PaymentException $e) {
    $this->logger->error('Payment failed', ['error' => $e->getMessage()]);
    throw new UserException('Payment processing failed. Please try again.');
}
```

### 4. Use Configuration
```php
// Define in plugin.json settings
"settings": {
    "api_endpoint": {
        "type": "string",
        "default": "https://api.example.com"
    }
}

// Use in code
$endpoint = $this->getOption('api_endpoint');
```

### 5. Implement Caching
```php
$cacheKey = 'products_' . md5(serialize($filters));
$products = $this->cache->remember($cacheKey, 3600, function() use ($filters) {
    return $this->repository->findByFilters($filters);
});
```

### 6. Write Tests
```php
namespace Tests\MyPlugin;

use PHPUnit\Framework\TestCase;
use MyPlugin\Services\ProductService;

class ProductServiceTest extends TestCase
{
    public function testCreateProduct(): void
    {
        $service = new ProductService();
        $product = $service->create([
            'name' => 'Test Product',
            'price' => 99.99
        ]);
        
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals(99.99, $product->price);
    }
}
```

### 7. Document Your Code
```php
/**
 * Process a payment for the given order
 * 
 * @param Order $order The order to process payment for
 * @param array $paymentData Payment method data
 * @return PaymentResult
 * @throws PaymentException If payment fails
 */
public function processPayment(Order $order, array $paymentData): PaymentResult
{
    // Implementation
}
```

## Testing

### Unit Tests

Create tests in the `tests/` directory:

```php
namespace Tests\MyPlugin\Unit;

use PHPUnit\Framework\TestCase;
use MyPlugin\Services\CalculatorService;

class CalculatorServiceTest extends TestCase
{
    private CalculatorService $calculator;
    
    protected function setUp(): void
    {
        $this->calculator = new CalculatorService();
    }
    
    public function testCalculateDiscount(): void
    {
        $result = $this->calculator->calculateDiscount(100, 10);
        $this->assertEquals(90, $result);
    }
}
```

### Integration Tests

```php
namespace Tests\MyPlugin\Integration;

use Shopologic\Core\Testing\IntegrationTestCase;

class ProductApiTest extends IntegrationTestCase
{
    public function testCreateProduct(): void
    {
        $response = $this->json('POST', '/api/v1/products', [
            'name' => 'Test Product',
            'price' => 99.99
        ]);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Test Product'
                ]
            ]);
    }
}
```

### Running Tests

```bash
# Run all plugin tests
vendor/bin/phpunit plugins/my-plugin/tests

# Run with coverage
vendor/bin/phpunit plugins/my-plugin/tests --coverage-html coverage
```

## Distribution

### Packaging Your Plugin

1. **Clean up development files**:
   ```bash
   rm -rf node_modules vendor .git
   ```

2. **Create distribution package**:
   ```bash
   zip -r my-plugin-v1.0.0.zip my-plugin/
   ```

3. **Include installation instructions** in README.md

### Version Management

Follow semantic versioning (SemVer):
- MAJOR.MINOR.PATCH (e.g., 1.2.3)
- MAJOR: Breaking changes
- MINOR: New features (backwards compatible)
- PATCH: Bug fixes

### Plugin Updates

Implement update method in your plugin:

```php
public function update(string $previousVersion): void
{
    if (version_compare($previousVersion, '2.0.0', '<')) {
        // Run migration for 2.0.0
        $this->runMigration('update_to_2_0_0');
    }
    
    if (version_compare($previousVersion, '2.1.0', '<')) {
        // Update settings
        $this->updateSettings();
    }
}
```

## Troubleshooting

### Common Issues

1. **Plugin not loading**
   - Check plugin.json syntax
   - Verify main class file exists
   - Check PHP syntax errors
   - Review error logs

2. **Class not found errors**
   - Verify namespace matches directory structure
   - Check autoload configuration
   - Clear cache

3. **Database errors**
   - Verify table exists
   - Check column names
   - Review migration status

### Debugging

Enable debug mode in `.env`:
```
APP_DEBUG=true
APP_LOG_LEVEL=debug
```

Use the logger:
```php
$this->logger->debug('Processing order', ['order_id' => $order->id]);
$this->logger->error('Payment failed', ['error' => $e->getMessage()]);
```

### Validation

Always validate your plugin:
```bash
php cli/validate-plugins.php
```

## Resources

- [Shopologic Documentation](https://docs.shopologic.com)
- [API Reference](https://api.shopologic.com/docs)
- [Plugin Examples](https://github.com/shopologic/example-plugins)
- [Community Forum](https://community.shopologic.com)

## Conclusion

This guide covers the essentials of Shopologic plugin development. For more advanced topics and specific use cases, refer to the official documentation and example plugins.

Remember to:
- Start with the plugin generator
- Follow the coding standards
- Test your code thoroughly
- Document your plugin
- Validate before distribution

Happy coding!