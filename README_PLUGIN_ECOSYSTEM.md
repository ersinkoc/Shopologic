# 🚀 Shopologic Plugin Ecosystem - Complete Guide

Welcome to the enhanced Shopologic Plugin Ecosystem - a comprehensive, enterprise-grade e-commerce platform built with pure PHP and zero external dependencies.

## 📖 Table of Contents

1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [Core Plugins](#core-plugins)
4. [Integration System](#integration-system)
5. [Event System](#event-system)
6. [Performance Monitoring](#performance-monitoring)
7. [Testing Framework](#testing-framework)
8. [API Reference](#api-reference)
9. [Development Guide](#development-guide)
10. [Production Deployment](#production-deployment)

## 🎯 Overview

The Shopologic Plugin Ecosystem provides a complete e-commerce solution with:

- **🏪 Complete E-commerce Features** - Inventory, loyalty programs, analytics, multi-currency
- **🔗 Seamless Integration** - Plugins communicate seamlessly through standardized interfaces
- **⚡ Real-time Processing** - Event-driven architecture with async capabilities
- **📊 Performance Monitoring** - Comprehensive health checks and metrics tracking
- **🧪 Automated Testing** - Complete testing framework with multiple test types
- **🌍 Zero Dependencies** - Pure PHP implementation with no external packages

### System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Shopologic Core Framework                    │
├─────────────────────────────────────────────────────────────────┤
│  Plugin Integration Manager  │  Event Dispatcher  │  Monitoring │
├─────────────────────────────────────────────────────────────────┤
│  Analytics  │  Inventory  │  Loyalty  │  Multi-Currency  │ ...  │
├─────────────────────────────────────────────────────────────────┤
│            Models  │  Services  │  Controllers  │  APIs          │
└─────────────────────────────────────────────────────────────────┘
```

## 🚀 Quick Start

### 1. Bootstrap the System

```bash
# Initialize the complete plugin ecosystem
php bootstrap_plugins.php

# Run with integration demonstration
php bootstrap_plugins.php --demo
```

### 2. Basic Usage Example

```php
<?php
use Shopologic\Plugins\Shared\PluginIntegrationManager;

// Get the integration manager
$manager = PluginIntegrationManager::getInstance();

// Check inventory
$inventoryProvider = $manager->getInventoryProvider();
$inStock = $inventoryProvider->isInStock('PRODUCT-123', 5);

// Award loyalty points
$loyaltyProvider = $manager->getLoyaltyProvider();
$loyaltyProvider->awardPoints(12345, 100, 'Purchase bonus');

// Convert currency
$currencyProvider = $manager->getCurrencyProvider();
$convertedPrice = $currencyProvider->convertCurrency(99.99, 'USD', 'EUR');

// Track analytics
$analyticsProvider = $manager->getAnalyticsProvider();
$analyticsProvider->trackEvent('product_viewed', ['product_id' => 'PRODUCT-123']);
```

### 3. Execute Integrated Workflows

```php
// Complete order processing workflow
$result = $manager->executeWorkflow('order_completed', [
    'customer_id' => 12345,
    'order_id' => 'ORD-2024-001',
    'order_total' => 299.99,
    'order_items' => $items
]);

// Results in automatic:
// - Inventory reservation
// - Loyalty points awarded
// - Analytics tracking
// - Email notifications
// - Currency conversion
```

## 🔧 Core Plugins

### 📦 Advanced Inventory Management

**Models:** `StockLevel`, `LocationZone`, `InventoryMovement`

**Key Features:**
- Real-time stock tracking across multiple locations
- Automated reorder point calculations with seasonal adjustments
- ABC/XYZ classification for inventory optimization
- Demand forecasting and trend analysis
- Low stock alerts and automated reordering

**Usage:**
```php
// Check stock levels
$stockLevel = StockLevel::find(1);
$needsReorder = $stockLevel->needsReorder();
$recommendedQuantity = $stockLevel->getRecommendedOrderQuantity();

// Record inventory movement
$movement = InventoryMovement::create([
    'type' => 'sale',
    'quantity' => -5,
    'reason' => 'Customer order'
]);

// Manage warehouse zones
$zone = LocationZone::find(1);
$canStore = $zone->canStoreProduct(['temperature' => 20]);
```

### ⭐ Customer Loyalty & Rewards

**Models:** `LoyaltyTier`, `PointTransaction`, `Reward`, `RewardRedemption`, `TierUpgrade`

**Key Features:**
- Dynamic tier progression with qualification tracking
- Comprehensive point transaction management
- Multiple reward types (discounts, gift cards, experiences)
- Advanced redemption rules and restrictions
- Automated tier upgrades with bonus rewards

**Usage:**
```php
// Manage loyalty members
$member = LoyaltyMember::find(1);
$currentPoints = $member->current_points;
$tier = $member->getCurrentTier();

// Award points
$transaction = PointTransaction::create([
    'loyalty_member_id' => 1,
    'type' => 'earned',
    'points' => 100,
    'reason' => 'purchase'
]);

// Check tier upgrade eligibility
$nextTier = $tier->getNextTier();
if ($nextTier && $nextTier->memberQualifies($member)) {
    $upgrade = TierUpgrade::create([
        'loyalty_member_id' => 1,
        'previous_tier_id' => $tier->id,
        'new_tier_id' => $nextTier->id
    ]);
}
```

### 📊 Advanced Analytics & Reporting

**Models:** `Report`, `ReportExecution`, `Dashboard`, `DashboardView`, `Metric`, `MetricValue`

**Key Features:**
- Scheduled report generation with multiple formats
- Real-time dashboard updates with widget management
- Advanced metric calculation and trending
- Performance tracking and optimization
- User interaction analytics

**Usage:**
```php
// Create and schedule reports
$report = Report::create([
    'name' => 'Daily Sales Report',
    'type' => 'sales',
    'is_scheduled' => true,
    'schedule_config' => ['frequency' => 'daily']
]);

// Manage dashboards
$dashboard = Dashboard::create([
    'name' => 'Executive Dashboard',
    'widgets' => $widgetConfig
]);
$dashboard->shareWith($userId, ['view']);

// Track metrics
$metric = Metric::create([
    'key' => 'daily_revenue',
    'name' => 'Daily Revenue',
    'category' => 'sales'
]);
$metric->updateValue(12345.67);
```

### 💱 Multi-Currency & Localization

**Models:** `ExchangeRate`, `ExchangeRateHistory`, `Localization`

**Key Features:**
- Real-time exchange rate updates from multiple providers
- Historical rate tracking and volatility analysis
- Comprehensive localization with cultural formatting
- Address, phone, and postal code formatting
- Timezone conversion and management

**Usage:**
```php
// Manage exchange rates
$rate = ExchangeRate::create([
    'from_currency' => 'USD',
    'to_currency' => 'EUR',
    'rate' => 0.85,
    'provider' => 'ecb'
]);
$rate->updateRate(0.86, ['source' => 'live_feed']);

// Apply localization
$localization = Localization::findByCountry('US');
$formattedPrice = $localization->formatPrice(99.99, '$');
$formattedAddress = $localization->formatAddress($addressData);
```

## 🔗 Integration System

### Provider Interfaces

The integration system uses standardized interfaces for cross-plugin communication:

```php
// Analytics Provider
interface AnalyticsProviderInterface {
    public function getMetricData(string $metricKey, array $filters = []): array;
    public function trackEvent(string $eventName, array $properties): void;
    public function subscribeToMetric(string $metricKey, callable $callback): void;
}

// Inventory Provider  
interface InventoryProviderInterface {
    public function getStockLevel(string $productId, string $locationId = null): int;
    public function reserveInventory(string $productId, int $quantity, string $orderId): bool;
    public function getLowStockAlerts(): array;
}

// Loyalty Provider
interface LoyaltyProviderInterface {
    public function getPointBalance(int $customerId): int;
    public function awardPoints(int $customerId, int $points, string $reason): bool;
    public function getAvailableRewards(int $customerId): array;
}

// Currency Provider
interface CurrencyProviderInterface {
    public function convertCurrency(float $amount, string $from, string $to): float;
    public function formatCurrency(float $amount, string $currency = null): string;
    public function getExchangeRate(string $from, string $to): float;
}
```

### Integration Manager Usage

```php
$manager = PluginIntegrationManager::getInstance();

// Register providers
$manager->registerProvider(AnalyticsProviderInterface::class, $analyticsAdapter);
$manager->registerProvider(InventoryProviderInterface::class, $inventoryAdapter);

// Use providers
$analytics = $manager->getAnalyticsProvider();
$inventory = $manager->getInventoryProvider();

// Execute workflows
$result = $manager->executeWorkflow('order_completed', $orderData);

// Cache frequently used data
$data = $manager->getCached('product_metrics', function() {
    return $this->calculateMetrics();
}, 300); // 5 minute TTL
```

## ⚡ Event System

### Event Dispatcher

The event system provides real-time communication between plugins:

```php
$dispatcher = PluginEventDispatcher::getInstance();

// Listen for events
$dispatcher->listen('order.created', function($event) {
    $orderData = $event->getData();
    // Process order...
}, 10); // Priority 10

// Dispatch events
$results = $dispatcher->dispatch('customer.registered', [
    'customer_id' => 123,
    'email' => 'customer@example.com'
]);

// Schedule future events
$jobId = $dispatcher->schedule('send_reminder', $data, 3600); // 1 hour delay

// Enable async processing
$dispatcher->enableAsync('memory');
```

### Event Middleware

Add middleware for enhanced event processing:

```php
// Logging middleware
$dispatcher->addMiddleware(new LoggingMiddleware('info', ['noisy.event']));

// Rate limiting
$dispatcher->addMiddleware(new RateLimitingMiddleware(100, 60));

// Authentication for secure events
$dispatcher->addMiddleware(new AuthenticationMiddleware(['admin', 'system'], [
    'user.delete',
    'system.config_change'
]));

// Caching for expensive operations
$dispatcher->addMiddleware(new CachingMiddleware(['analytics.report'], 300));
```

## 📊 Performance Monitoring

### Health Monitor

Comprehensive monitoring for all plugins:

```php
$monitor = PluginHealthMonitor::getInstance();

// Record metrics
$monitor->recordResponseTime('inventory', 'stock_check', 45.2);
$monitor->recordMemoryUsage('analytics', 25.5);
$monitor->recordDatabaseQueryTime('loyalty', 'SELECT * FROM points', 12.3);

// Register health checks
$monitor->registerHealthCheck('inventory', 'database', function() {
    return DB::connection()->getPdo() !== null;
});

// Get system health
$health = $monitor->getSystemHealth();
$alerts = $monitor->getAlerts(60); // Last 60 minutes

// Performance tracking
$trackingId = $monitor->startTracking('loyalty', 'calculate_points');
// ... perform operation ...
$monitor->endTracking($trackingId, 'loyalty', 'calculate_points', true);
```

### Custom Thresholds

```php
$monitor = PluginHealthMonitor::getInstance([
    'response_time_ms' => 500,      // Custom response time threshold
    'memory_usage_mb' => 25,        // Memory usage limit
    'error_rate_percent' => 2,      // Error rate tolerance
    'cpu_usage_percent' => 70,      // CPU usage threshold
    'cache_hit_rate_percent' => 95  // Expected cache performance
]);
```

## 🧪 Testing Framework

### Test Suite Creation

```php
class MyPluginTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_basic_functionality' => [$this, 'testBasicFunctionality'],
            'test_edge_cases' => [$this, 'testEdgeCases']
        ];
    }
    
    public function getIntegrationTests(): array
    {
        return [
            'test_cross_plugin_communication' => [$this, 'testCrossPluginCommunication']
        ];
    }
    
    public function testBasicFunctionality(): void
    {
        $result = $this->performOperation();
        Assert::assertTrue($result, 'Operation should succeed');
    }
}
```

### Running Tests

```php
$framework = new PluginTestFramework();
$framework->registerTestSuite('my_plugin', new MyPluginTestSuite());
$framework->setVerbose(true);

// Run tests for specific plugin
$result = $framework->runTests('my_plugin');

// Run all tests
$allResults = $framework->runAllTests();

// Generate report
$report = $framework->generateReport($allResults);
echo $report;
```

### Mock Objects

```php
// Create mocks for testing
$mock = $framework->createMock(InventoryProviderInterface::class);
$mock->expects('getStockLevel')
     ->with('PRODUCT-123')
     ->willReturn(50);

// Use mock in tests
$stockLevel = $mock->getStockLevel('PRODUCT-123');
Assert::assertEquals(50, $stockLevel);
```

## 📚 API Reference

### Core Classes

- **`PluginIntegrationManager`** - Central hub for plugin communication
- **`PluginEventDispatcher`** - Event-driven communication system
- **`PluginHealthMonitor`** - Performance monitoring and health checks
- **`PluginTestFramework`** - Automated testing capabilities

### Model Base Classes

All plugin models extend `Shopologic\Core\Database\Model` and provide:

- **Relationships** - `belongsTo()`, `hasMany()`, `hasOne()`
- **Scopes** - `scopeActive()`, `scopeByDate()`, custom scopes
- **Casting** - Automatic type conversion for JSON, dates, decimals
- **Events** - Model lifecycle events for hooks

### Common Patterns

```php
// Repository pattern
$repository = new InventoryItemRepository();
$items = $repository->findByLocation('warehouse-1');

// Service layer
$service = new LoyaltyCalculationService();
$points = $service->calculateOrderPoints($order);

// Event handling
Hook::addAction('order.created', [$this, 'handleOrderCreated']);
Hook::addFilter('price.display', [$this, 'formatPrice']);
```

## 🛠️ Development Guide

### Creating a New Plugin

1. **Directory Structure**
```
plugins/my-plugin/
├── plugin.json
├── src/
│   ├── Models/
│   ├── Services/
│   ├── Controllers/
│   ├── Repositories/
│   └── MyPlugin.php
├── migrations/
├── tests/
└── README.md
```

2. **Plugin Manifest (plugin.json)**
```json
{
    "name": "my-plugin",
    "version": "1.0.0",
    "description": "My custom plugin",
    "author": "Developer Name",
    "dependencies": ["shopologic/core"],
    "autoload": {
        "psr-4": {
            "MyPlugin\\": "src/"
        }
    }
}
```

3. **Main Plugin Class**
```php
class MyPlugin extends AbstractPlugin implements PluginInterface
{
    public function activate(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
    }
    
    protected function registerServices(): void
    {
        $container = $this->getContainer();
        $container->singleton(MyService::class);
    }
}
```

### Adding Integration Support

```php
// Create provider adapter
class MyProviderAdapter implements CustomProviderInterface
{
    public function customMethod(): array
    {
        // Implementation
    }
}

// Register with integration manager
$adapter = new MyProviderAdapter($dependencies);
$integrationManager->registerProvider(CustomProviderInterface::class, $adapter);
```

### Custom Event Listeners

```php
// Register event listeners
$eventDispatcher->listen('my_plugin.custom_event', function($event) {
    $data = $event->getData();
    // Handle event
}, 10);

// Dispatch custom events
$eventDispatcher->dispatch('my_plugin.custom_event', [
    'custom_data' => $value
]);
```

## 🚀 Production Deployment

### System Requirements

- **PHP 8.3+** with required extensions
- **PostgreSQL 12+** (preferred) or MySQL 8.0+
- **Redis** (optional, for caching)
- **Web server** (Apache, Nginx)

### Environment Configuration

```bash
# Environment variables
APP_ENV=production
APP_DEBUG=false
DATABASE_URL=postgresql://user:pass@localhost/shopologic
REDIS_URL=redis://localhost:6379
CACHE_DRIVER=redis
QUEUE_DRIVER=redis
```

### Performance Optimization

1. **Enable OpCache**
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

2. **Configure Caching**
```php
// Enable application caching
$config['cache'] = [
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache'
        ]
    ]
];
```

3. **Database Optimization**
```sql
-- Create indexes for better performance
CREATE INDEX idx_inventory_product_location ON inventory_items(product_id, location_id);
CREATE INDEX idx_point_transactions_member ON point_transactions(loyalty_member_id);
CREATE INDEX idx_metrics_timestamp ON analytics_metrics(timestamp);
```

### Monitoring Setup

```php
// Production monitoring configuration
$healthMonitor = PluginHealthMonitor::getInstance([
    'response_time_ms' => 200,      // Strict performance requirements
    'memory_usage_mb' => 50,        // Memory limits
    'error_rate_percent' => 0.5,    // Very low error tolerance
    'cpu_usage_percent' => 60,      // CPU usage limits
    'cache_hit_rate_percent' => 98  // High cache performance
]);

// Enable comprehensive logging
$eventDispatcher->addMiddleware(new LoggingMiddleware('warning'));
```

### Backup Strategy

```bash
# Database backup
pg_dump shopologic > backup_$(date +%Y%m%d_%H%M%S).sql

# Plugin data backup
tar -czf plugins_backup_$(date +%Y%m%d_%H%M%S).tar.gz plugins/

# Configuration backup
cp -r config/ config_backup_$(date +%Y%m%d_%H%M%S)/
```

## 🎯 Best Practices

### Code Quality
- Follow PSR-12 coding standards
- Use type declarations and return types
- Implement comprehensive error handling
- Write unit tests for all functionality

### Performance
- Use database indexes appropriately
- Implement caching where beneficial
- Monitor memory usage and optimize queries
- Use async processing for heavy operations

### Security
- Validate all inputs
- Use parameterized queries
- Implement proper access controls
- Log security-related events

### Maintainability
- Use dependency injection
- Follow single responsibility principle
- Document public APIs
- Keep plugins loosely coupled

## 🆘 Troubleshooting

### Common Issues

1. **Plugin Not Loading**
```bash
# Check plugin.json syntax
php -l plugins/my-plugin/plugin.json

# Verify autoload configuration
composer dump-autoload
```

2. **Integration Not Working**
```php
// Check provider registration
$status = $integrationManager->getIntegrationStatus();
var_dump($status);

// Verify interface implementation
$provider = $integrationManager->getProvider(MyInterface::class);
var_dump($provider instanceof MyInterface);
```

3. **Performance Issues**
```php
// Check health monitor alerts
$alerts = $healthMonitor->getAlerts(60);
foreach ($alerts as $alert) {
    echo "Alert: {$alert['message']}\n";
}

// Review performance metrics
$summary = $healthMonitor->getPerformanceSummary('my_plugin', 60);
var_dump($summary);
```

### Debug Mode

```php
// Enable verbose logging
$eventDispatcher->addMiddleware(new LoggingMiddleware('debug'));

// Enable test framework verbose output
$testFramework->setVerbose(true);

// Monitor all events
$eventDispatcher->listen('*', function($event) {
    echo "Event: {$event->getName()}\n";
    var_dump($event->getData());
});
```

## 📞 Support

For technical support and questions:
- Review the comprehensive documentation
- Check the integration examples
- Run the built-in testing framework
- Monitor system health and performance metrics

The Shopologic Plugin Ecosystem provides a complete, enterprise-ready e-commerce solution with zero dependencies and comprehensive integration capabilities.