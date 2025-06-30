# 👋 Hello World Plugin

![Quality Badge](https://img.shields.io/badge/Quality-86%25%20(B+)-green)


A simple demonstration plugin showcasing the basic structure and functionality of Shopologic plugins.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate Hello World plugin
php cli/plugin.php activate HelloWorld
```

## ✨ Features

### 🎨 Basic Plugin Functionality
- **Plugin Registration** - Demonstrates plugin lifecycle management
- **Hook Integration** - Shows how to use the hook system
- **Service Registration** - Example service container usage
- **Event Handling** - Basic event system integration

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`HelloWorldPlugin.php`** - Core plugin implementation

### Configuration
- **`plugin.json`** - Plugin manifest and metadata

## 💻 Implementation Examples

### Basic Plugin Structure

```php
class HelloWorldPlugin extends AbstractPlugin
{
    public function activate(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
    }
    
    protected function registerServices(): void
    {
        // Register plugin services
        $this->container->singleton(HelloWorldService::class);
    }
    
    protected function registerHooks(): void
    {
        // Add action hooks
        HookSystem::addAction('init', [$this, 'initialize']);
        
        // Add filter hooks
        HookSystem::addFilter('hello_message', [$this, 'filterMessage']);
    }
    
    public function initialize(): void
    {
        echo "Hello World plugin initialized!\n";
    }
    
    public function filterMessage(string $message): string
    {
        return "Hello World: " . $message;
    }
}
```

## 🔗 Integration Examples

### Cross-Plugin Communication

```php
// Use with other plugins
$integrationManager = PluginIntegrationManager::getInstance();

// Example integration
$helloWorldService = app(HelloWorldService::class);
$message = $helloWorldService->getGreeting('Shopologic');
echo $message; // "Hello, Shopologic!"
```

## ⚡ Event Integration

```php
// Listen for events
$eventDispatcher = PluginEventDispatcher::getInstance();
$eventDispatcher->listen('plugin.hello_world.greeting', function($event) {
    $data = $event->getData();
    echo "Greeting sent: {$data['message']}\n";
});

// Dispatch events
$eventDispatcher->dispatch('plugin.hello_world.greeting', [
    'message' => 'Hello from HelloWorld plugin!',
    'timestamp' => now()->toISOString()
]);
```

## 🧪 Testing

### Example Tests

```php
class HelloWorldTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_greeting_generation' => [$this, 'testGreetingGeneration'],
            'test_hook_integration' => [$this, 'testHookIntegration']
        ];
    }
    
    public function testGreetingGeneration(): void
    {
        $service = new HelloWorldService();
        $greeting = $service->getGreeting('Test');
        Assert::assertEquals('Hello, Test!', $greeting);
    }
}
```

## 🛠️ Configuration

### Plugin Manifest

```json
{
    "name": "HelloWorld",
    "version": "1.0.0",
    "description": "A simple Hello World demonstration plugin",
    "author": {
        "name": "Shopologic Team"
    },
    "requires": {
        "php": ">=8.3",
        "shopologic/core": ">=1.0.0"
    },
    "autoload": {
        "psr-4": {
            "HelloWorld\\": "src/"
        }
    }
}
```

## 📚 API Integration

### Basic Endpoints

```php
// Register simple API route
$this->registerRoute('GET', '/api/hello', function() {
    return json_response(['message' => 'Hello World!']);
});
```

## 🔧 Installation

### Requirements
- PHP 8.3+
- Shopologic Core Framework

### Setup

```bash
# Plugin is included by default
php cli/plugin.php list

# Activate if needed
php cli/plugin.php activate HelloWorld
```

## 📖 Learning Resources

This plugin serves as:
- **Plugin Development Tutorial** - Learn basic plugin structure
- **Hook System Example** - Understand event-driven architecture
- **Service Container Demo** - Dependency injection patterns
- **Testing Framework Introduction** - Automated testing basics

## 🚀 Production Ready

This plugin demonstrates the enhanced Shopologic ecosystem features:
- ✅ Plugin lifecycle management
- ✅ Hook system integration
- ✅ Event-driven architecture
- ✅ Service container usage
- ✅ Testing framework compatibility

---

**Hello World Plugin** - Your first step into Shopologic plugin development