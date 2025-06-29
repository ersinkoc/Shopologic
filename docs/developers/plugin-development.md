# Plugin Development Guide

Learn how to create powerful plugins for Shopologic using the built-in plugin architecture.

## 🎯 Plugin System Overview

Shopologic's plugin system is inspired by WordPress but built for modern enterprise applications. It provides:

- **Hot-swappable modules**: Enable/disable without restarts
- **Dependency management**: Automatic resolution and loading
- **Hook system**: WordPress-style actions and filters
- **Service injection**: Full access to the service container
- **API integration**: Automatic REST and GraphQL endpoints
- **Database migrations**: Plugin-specific schema changes

## 🚀 Quick Start

### Generate Plugin Scaffold
```bash
php cli/plugin.php generate MyAwesomePlugin
```

This creates:
```
plugins/MyAwesomePlugin/
├── plugin.json                # Plugin manifest
├── src/
│   └── MyAwesomePluginPlugin.php  # Main plugin class
├── templates/                 # Plugin templates
├── assets/                    # CSS/JS assets
├── migrations/               # Database migrations
├── config/                   # Plugin configuration
└── README.md                 # Plugin documentation
```

### Basic Plugin Structure
```php
<?php

namespace Shopologic\Plugins\MyAwesomePlugin;

use Shopologic\Core\Plugin\AbstractPlugin;

class MyAwesomePluginPlugin extends AbstractPlugin
{
    public function boot(): void
    {
        // Plugin initialization code
        $this->loadConfig();
        $this->registerViews();
    }
    
    protected function registerServices(): void
    {
        // Register plugin services
        $this->container->bind(MyService::class);
        $this->container->singleton(MyManager::class);
    }
    
    protected function registerHooks(): void
    {
        // Register event hooks
        HookSystem::addAction('order_created', [$this, 'handleOrderCreated']);
        HookSystem::addFilter('product_price', [$this, 'modifyProductPrice']);
    }
    
    protected function registerRoutes(): void
    {
        // Register plugin routes
        $this->registerRoute('GET', '/api/my-plugin/stats', [$this, 'getStats']);
        $this->registerRoute('POST', '/api/my-plugin/webhook', [$this, 'handleWebhook']);
    }
}
```

## 📋 Plugin Manifest

The `plugin.json` file defines your plugin's metadata and requirements:

```json
{
    "name": "my-awesome-plugin",
    "version": "1.0.0",
    "description": "An awesome plugin that does amazing things",
    "author": "Your Name",
    "license": "MIT",
    "homepage": "https://github.com/yourname/my-awesome-plugin",
    "main": "src/MyAwesomePluginPlugin.php",
    "namespace": "Shopologic\\Plugins\\MyAwesomePlugin",
    
    "dependencies": [
        "core-commerce@^1.0.0",
        "payment-stripe@^2.0.0"
    ],
    
    "permissions": [
        "manage_orders",
        "view_analytics",
        "modify_products"
    ],
    
    "hooks": [
        "order_created",
        "order_updated",
        "product_price"
    ],
    
    "api_endpoints": [
        "GET /api/my-plugin/stats",
        "POST /api/my-plugin/webhook"
    ],
    
    "settings": {
        "api_key": {
            "type": "string",
            "required": true,
            "description": "API key for external service"
        },
        "enable_notifications": {
            "type": "boolean",
            "default": true,
            "description": "Enable email notifications"
        }
    },
    
    "supports": {
        "multistore": true,
        "graphql": true,
        "webhooks": true
    }
}
```

## 🔧 Core Plugin Concepts

### 1. Plugin Lifecycle

```php
class MyPluginPlugin extends AbstractPlugin
{
    // Called when plugin is activated
    public function activate(): void
    {
        $this->createDatabaseTables();
        $this->seedDefaultData();
        $this->scheduleJobs();
    }
    
    // Called when plugin is deactivated
    public function deactivate(): void
    {
        $this->clearScheduledJobs();
        $this->cleanupTempData();
    }
    
    // Called when plugin is uninstalled
    public function uninstall(): void
    {
        $this->dropDatabaseTables();
        $this->removeSettings();
        $this->cleanupFiles();
    }
    
    // Called on every request when plugin is active
    public function boot(): void
    {
        $this->initializeServices();
        $this->registerEventListeners();
    }
}
```

### 2. Service Registration

```php
protected function registerServices(): void
{
    // Bind interfaces to implementations
    $this->container->bind(PaymentGatewayInterface::class, StripeGateway::class);
    
    // Register singletons
    $this->container->singleton(NotificationManager::class, function($container) {
        return new NotificationManager(
            $container->get(MailerInterface::class),
            $this->getConfig('notifications')
        );
    });
    
    // Tag services for discovery
    $this->container->tag([
        EmailNotifier::class,
        SmsNotifier::class,
        PushNotifier::class
    ], 'notifiers');
    
    // Register plugin-specific services
    $this->container->bind(AnalyticsCollector::class);
    $this->container->bind(ReportGenerator::class);
}
```

### 3. Hook System

#### Actions (Events without return values)
```php
protected function registerHooks(): void
{
    // Listen for order events
    HookSystem::addAction('order_created', [$this, 'sendOrderNotification']);
    HookSystem::addAction('order_shipped', [$this, 'trackShipment']);
    HookSystem::addAction('user_registered', [$this, 'welcomeUser']);
    
    // Listen with priority (higher = earlier execution)
    HookSystem::addAction('product_saved', [$this, 'indexProduct'], 20);
}

public function sendOrderNotification($order): void
{
    $this->notificationManager->send(
        'order.created',
        $order->customer->email,
        ['order' => $order]
    );
}
```

#### Filters (Modify values)
```php
protected function registerHooks(): void
{
    // Modify product prices
    HookSystem::addFilter('product_price', [$this, 'applyDiscounts']);
    HookSystem::addFilter('shipping_cost', [$this, 'calculateShipping']);
    HookSystem::addFilter('tax_rate', [$this, 'getTaxRate']);
}

public function applyDiscounts($price, $product): float
{
    // Apply bulk discounts
    if ($product->quantity >= 10) {
        $price *= 0.9; // 10% discount
    }
    
    // Apply member discounts
    if (auth()->user()?->isMember()) {
        $price *= 0.95; // 5% member discount
    }
    
    return $price;
}
```

#### Conditional Hooks
```php
protected function registerHooks(): void
{
    // Only execute if condition is met
    HookSystem::addConditionalAction(
        'payment_failed',
        fn($payment) => $payment->amount > 100,
        [$this, 'notifyHighValueFailure']
    );
}
```

### 4. Database Migrations

Create migration files in `migrations/` directory:

```php
<?php
// migrations/CreateAnalyticsTable.php

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateAnalyticsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('plugin_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('event_name');
            $table->json('properties')->nullable();
            $table->string('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['event_type', 'occurred_at']);
            $table->index('user_id');
        });
    }
    
    public function down(): void
    {
        $this->schema->dropIfExists('plugin_analytics_events');
    }
}
```

Run migrations:
```bash
php cli/migrate.php up
```

## 🛠️ Advanced Plugin Features

### 1. Configuration Management

```php
class MyPluginPlugin extends AbstractPlugin
{
    private array $config;
    
    public function boot(): void
    {
        $this->loadConfig();
    }
    
    private function loadConfig(): void
    {
        // Load from plugin settings
        $this->config = array_merge([
            'api_timeout' => 30,
            'retry_attempts' => 3,
            'debug_mode' => false
        ], $this->getSettings());
    }
    
    public function getConfig(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }
        
        return data_get($this->config, $key, $default);
    }
}
```

### 2. Custom Models

```php
<?php
// src/Models/AnalyticsEvent.php

namespace Shopologic\Plugins\MyAwesomePlugin\Models;

use Shopologic\Core\Database\Model;

class AnalyticsEvent extends Model
{
    protected $table = 'plugin_analytics_events';
    
    protected $fillable = [
        'event_type',
        'event_name', 
        'properties',
        'user_id',
        'session_id',
        'occurred_at'
    ];
    
    protected $casts = [
        'properties' => 'array',
        'occurred_at' => 'datetime'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }
}
```

### 3. API Endpoints

```php
protected function registerRoutes(): void
{
    // Public API endpoints
    $this->registerRoute('GET', '/api/my-plugin/public/stats', [$this, 'getPublicStats']);
    
    // Authenticated endpoints
    $this->registerRoute('GET', '/api/my-plugin/events', [$this, 'getEvents'])
        ->middleware(['auth:api']);
    
    // Admin-only endpoints
    $this->registerRoute('POST', '/api/my-plugin/settings', [$this, 'updateSettings'])
        ->middleware(['auth:api', 'permission:manage_plugins']);
    
    // Webhook endpoints
    $this->registerRoute('POST', '/api/my-plugin/webhook', [$this, 'handleWebhook'])
        ->middleware(['verify_signature']);
}

public function getEvents(Request $request): JsonResponse
{
    $events = AnalyticsEvent::ofType($request->get('type', 'pageview'))
        ->whereBetween('occurred_at', [
            $request->get('start_date'),
            $request->get('end_date')
        ])
        ->paginate(50);
    
    return new JsonResponse($events);
}

public function handleWebhook(Request $request): JsonResponse
{
    $payload = $request->getContent();
    $signature = $request->header('X-Signature');
    
    if (!$this->verifySignature($payload, $signature)) {
        return new JsonResponse(['error' => 'Invalid signature'], 401);
    }
    
    $data = json_decode($payload, true);
    $this->processWebhookData($data);
    
    return new JsonResponse(['status' => 'processed']);
}
```

### 4. GraphQL Integration

```php
// src/GraphQL/AnalyticsResolver.php

namespace Shopologic\Plugins\MyAwesomePlugin\GraphQL;

class AnalyticsResolver
{
    public function getAnalytics($root, array $args): array
    {
        $events = AnalyticsEvent::ofType($args['type'] ?? 'pageview')
            ->whereBetween('occurred_at', [
                $args['startDate'],
                $args['endDate']
            ])
            ->get();
        
        return [
            'totalEvents' => $events->count(),
            'uniqueUsers' => $events->pluck('user_id')->unique()->count(),
            'events' => $events->toArray()
        ];
    }
}

// Register GraphQL types and resolvers
protected function registerGraphQL(): void
{
    $this->graphql->addType('AnalyticsData', [
        'totalEvents' => ['type' => 'Int!'],
        'uniqueUsers' => ['type' => 'Int!'],
        'events' => ['type' => '[AnalyticsEvent!]!']
    ]);
    
    $this->graphql->addQuery('analytics', [
        'type' => 'AnalyticsData',
        'args' => [
            'type' => ['type' => 'String'],
            'startDate' => ['type' => 'DateTime!'],
            'endDate' => ['type' => 'DateTime!']
        ],
        'resolve' => [AnalyticsResolver::class, 'getAnalytics']
    ]);
}
```

### 5. Background Jobs

```php
// src/Jobs/ProcessAnalyticsJob.php

namespace Shopologic\Plugins\MyAwesomePlugin\Jobs;

use Shopologic\Core\Queue\Job;

class ProcessAnalyticsJob implements Job
{
    private array $eventData;
    
    public function __construct(array $eventData)
    {
        $this->eventData = $eventData;
    }
    
    public function handle(): void
    {
        // Process analytics data
        $event = AnalyticsEvent::create([
            'event_type' => $this->eventData['type'],
            'event_name' => $this->eventData['name'],
            'properties' => $this->eventData['properties'] ?? [],
            'user_id' => $this->eventData['user_id'] ?? null,
            'occurred_at' => now()
        ]);
        
        // Trigger additional processing
        HookSystem::fireAction('analytics_event_processed', $event);
    }
    
    public function failed(\Throwable $exception): void
    {
        // Handle job failure
        log_error('Analytics job failed', [
            'exception' => $exception->getMessage(),
            'data' => $this->eventData
        ]);
    }
}

// Queue the job
protected function registerHooks(): void
{
    HookSystem::addAction('page_viewed', [$this, 'queueAnalytics']);
}

public function queueAnalytics(array $data): void
{
    Queue::dispatch(new ProcessAnalyticsJob($data));
}
```

### 6. Templates and Views

```php
// Register template directory
public function boot(): void
{
    $this->registerViewPath(__DIR__ . '/../templates');
}

// Render plugin templates
public function renderDashboard(): string
{
    $stats = $this->getAnalyticsStats();
    
    return $this->render('dashboard', [
        'stats' => $stats,
        'charts' => $this->generateCharts($stats)
    ]);
}
```

Template file (`templates/dashboard.twig`):
```twig
<div class="analytics-dashboard">
    <h2>Analytics Dashboard</h2>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Page Views</h3>
            <span class="stat-number">{{ stats.pageviews }}</span>
        </div>
        
        <div class="stat-card">
            <h3>Unique Visitors</h3>
            <span class="stat-number">{{ stats.unique_visitors }}</span>
        </div>
        
        <div class="stat-card">
            <h3>Bounce Rate</h3>
            <span class="stat-number">{{ stats.bounce_rate }}%</span>
        </div>
    </div>
    
    <div class="charts">
        {% for chart in charts %}
            <div class="chart-container">
                {{ chart.render() | raw }}
            </div>
        {% endfor %}
    </div>
</div>
```

## 🧪 Testing Plugins

### Unit Tests
```php
// tests/AnalyticsEventTest.php

use Shopologic\Plugins\MyAwesomePlugin\Models\AnalyticsEvent;

TestFramework::describe('AnalyticsEvent Model', function() {
    TestFramework::it('should create event with properties', function() {
        $event = AnalyticsEvent::create([
            'event_type' => 'pageview',
            'event_name' => 'home_page_view',
            'properties' => ['url' => '/home', 'referrer' => 'google'],
            'occurred_at' => now()
        ]);
        
        TestFramework::expect($event->properties['url'])->toBe('/home');
        TestFramework::expect($event->event_type)->toBe('pageview');
    });
    
    TestFramework::it('should filter events by type', function() {
        AnalyticsEvent::create(['event_type' => 'pageview', 'occurred_at' => now()]);
        AnalyticsEvent::create(['event_type' => 'click', 'occurred_at' => now()]);
        
        $pageviews = AnalyticsEvent::ofType('pageview')->get();
        TestFramework::expect($pageviews)->toHaveCount(1);
    });
});
```

### Integration Tests
```php
// tests/AnalyticsApiTest.php

TestFramework::describe('Analytics API', function() {
    TestFramework::it('should return analytics data', function() {
        // Create test data
        AnalyticsEvent::create([
            'event_type' => 'pageview',
            'occurred_at' => now()->subDays(1)
        ]);
        
        // Make API request
        $response = $this->get('/api/my-plugin/events?type=pageview');
        
        TestFramework::expect($response->getStatusCode())->toBe(200);
        $data = json_decode($response->getContent(), true);
        TestFramework::expect($data['data'])->toHaveCount(1);
    });
});
```

## 📦 Plugin Distribution

### Package Your Plugin
```bash
# Create plugin package
php cli/plugin.php package my-awesome-plugin

# This creates:
# my-awesome-plugin-1.0.0.zip
```

### Plugin Repository
```json
{
    "name": "my-awesome-plugin",
    "version": "1.0.0",
    "download_url": "https://releases.example.com/my-awesome-plugin-1.0.0.zip",
    "checksum": "sha256:abc123...",
    "requires": {
        "shopologic": ">=1.0.0",
        "php": ">=8.3.0"
    }
}
```

## 🔧 Plugin Management Commands

```bash
# List all plugins
php cli/plugin.php list

# Install plugin
php cli/plugin.php install my-awesome-plugin

# Activate plugin
php cli/plugin.php activate my-awesome-plugin

# Deactivate plugin
php cli/plugin.php deactivate my-awesome-plugin

# Update plugin
php cli/plugin.php update my-awesome-plugin

# Uninstall plugin
php cli/plugin.php uninstall my-awesome-plugin

# Get plugin info
php cli/plugin.php info my-awesome-plugin

# Validate plugin
php cli/plugin.php validate my-awesome-plugin
```

## 🎯 Best Practices

### 1. Naming Conventions
- Plugin names: `kebab-case` (e.g., `my-awesome-plugin`)
- Class names: `PascalCase` (e.g., `MyAwesomePluginPlugin`)
- Hook names: `snake_case` (e.g., `order_created`)
- Database tables: `plugin_name_table` (e.g., `analytics_events`)

### 2. Security Guidelines
- Always validate input data
- Use prepared statements for database queries
- Escape output in templates
- Implement proper authentication for API endpoints
- Follow principle of least privilege

### 3. Performance Tips
- Use database indexes for frequently queried columns
- Implement caching for expensive operations
- Use background jobs for heavy processing
- Optimize database queries

### 4. Error Handling
```php
public function processData(array $data): void
{
    try {
        $this->validateData($data);
        $this->saveData($data);
        
    } catch (ValidationException $e) {
        log_warning('Plugin validation failed', [
            'plugin' => 'my-awesome-plugin',
            'errors' => $e->getErrors()
        ]);
        throw $e;
        
    } catch (\Exception $e) {
        log_error('Plugin processing failed', [
            'plugin' => 'my-awesome-plugin',
            'exception' => $e->getMessage()
        ]);
        throw new PluginException('Data processing failed', 0, $e);
    }
}
```

## 📚 Examples

### Example 1: Discount Plugin
A plugin that applies automatic discounts based on cart value:

[View complete example →](./examples/discount-plugin.md)

### Example 2: Analytics Plugin
A plugin that tracks user behavior and generates reports:

[View complete example →](./examples/analytics-plugin.md)

### Example 3: Shipping Plugin
A plugin that integrates with a custom shipping provider:

[View complete example →](./examples/shipping-plugin.md)

---

You now have everything you need to create powerful plugins for Shopologic! 🚀