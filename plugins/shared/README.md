# 🔗 Shared Plugin Components

Shared libraries, interfaces, and utilities used across all Shopologic plugins providing cross-plugin integration, common functionality, and standardized development patterns.

**🎯 ENHANCED PLUGIN ECOSYSTEM - CORE INFRASTRUCTURE**

This directory contains the shared components that enable the enhanced Shopologic ecosystem with cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Overview

The shared directory provides essential infrastructure for all plugins:
- Common interfaces for cross-plugin communication
- Shared utilities and helper functions
- Integration management systems
- Event dispatching and handling
- Performance monitoring tools
- Testing framework components

## ✨ Key Components

### 🔌 Plugin Integration
- **`PluginIntegrationManager.php`** - Central integration hub
- **Integration Interfaces** - Standardized plugin contracts
- **Service Discovery** - Plugin capability detection
- **Data Exchange** - Cross-plugin data sharing
- **Workflow Orchestration** - Multi-plugin processes

### 📡 Event System
- **`PluginEventDispatcher.php`** - Global event bus
- **Event Middleware** - Event processing pipeline
- **Async Events** - Background processing
- **Event Subscribers** - Plugin listeners
- **Event History** - Audit trail

### 📊 Monitoring & Analytics
- **Performance Monitors** - Resource tracking
- **Health Checks** - Plugin status monitoring
- **Metrics Collection** - Usage analytics
- **Error Tracking** - Centralized logging
- **Dashboard Integration** - Unified monitoring

### 🧪 Testing Framework
- **`PluginTestSuite.php`** - Base test class
- **Mock Objects** - Testing utilities
- **Integration Tests** - Cross-plugin testing
- **Performance Tests** - Load testing
- **Test Data Factories** - Sample data generation

## 🏗️ Architecture

### Core Interfaces
```php
namespace Shopologic\Plugins\Shared\Interfaces;

interface PluginInterface {
    public function getName(): string;
    public function getVersion(): string;
    public function getCapabilities(): array;
}

interface IntegrationInterface {
    public function canIntegrateWith(string $pluginName): bool;
    public function getIntegrationPoints(): array;
}

interface DataProviderInterface {
    public function provideData(string $dataType, array $filters = []): array;
    public function subscribeToDataChanges(string $dataType, callable $callback): void;
}
```

### Integration Adapters
- **Analytics Adapter** - Analytics plugin integration
- **Payment Adapter** - Payment gateway integration
- **Inventory Adapter** - Stock management integration
- **Customer Adapter** - Customer data integration
- **Order Adapter** - Order processing integration

## 🔧 Usage

### Cross-Plugin Communication
```php
// Get integration manager
$integrationManager = app(PluginIntegrationManager::class);

// Discover available integrations
$availableIntegrations = $integrationManager->discoverIntegrations('analytics');

// Execute cross-plugin workflow
$result = $integrationManager->executeWorkflow('order_fulfillment', [
    'order_id' => 'ORDER_123',
    'plugins' => ['inventory', 'shipping', 'notification']
]);
```

### Event System Usage
```php
// Get event dispatcher
$dispatcher = PluginEventDispatcher::getInstance();

// Dispatch event
$dispatcher->dispatch('product.updated', [
    'product_id' => 'PROD_123',
    'changes' => ['price' => 99.99]
]);

// Listen to events
$dispatcher->listen('order.created', function($event) {
    // Handle order creation
});
```

## 📚 Available Services

### Utility Classes
- **Data Transformers** - Format conversion utilities
- **Validators** - Common validation rules
- **Formatters** - Output formatting helpers
- **Cache Helpers** - Caching utilities
- **API Clients** - HTTP client wrappers

### Security Components
- **Authentication Helpers** - Auth utilities
- **Encryption Services** - Data encryption
- **Permission Checkers** - Access control
- **Token Generators** - Security tokens
- **Input Sanitizers** - Data cleaning

## 🔧 Installation

The shared components are automatically available when the plugin ecosystem is initialized:

```bash
# Initialize plugin ecosystem (includes shared components)
php bootstrap_plugins.php
```

## 🚀 Production Ready

These shared components are production-ready with:
- ✅ Robust integration framework
- ✅ Scalable event system
- ✅ Comprehensive monitoring
- ✅ Full testing support
- ✅ Security best practices
- ✅ Performance optimized

---

**Shared Plugin Components** - The foundation of Shopologic's plugin ecosystem