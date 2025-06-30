# Shopologic Plugin Architecture Guide

## Overview

The Shopologic plugin system provides a powerful, extensible architecture that allows developers to add functionality without modifying core code. This guide explains the plugin architecture, development process, and best practices.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

The plugin architecture has been significantly enhanced with enterprise-grade capabilities:
- 47 advanced models with sophisticated business logic
- Cross-plugin integration via standardized interfaces
- Real-time event system with middleware support  
- Performance monitoring and health checks
- Automated testing framework with multiple test types
- Complete bootstrap and demonstration system

## Architecture Principles

### 1. **Isolation & Sandboxing**
- Plugins run in isolated namespaces
- Limited access to core system via PluginAPI
- Permission-based access control
- Resource usage limits

### 2. **Hook System**
Based on WordPress-style hooks with two types:
- **Actions**: Execute code at specific points (no return value)
- **Filters**: Modify data passing through the system

### 3. **Service Container Integration**
- Plugins can register services in the DI container
- Automatic dependency injection
- Service tagging for grouped functionality

### 4. **Event-Driven Architecture**
- PSR-14 compliant event system
- Asynchronous event processing
- Priority-based execution

## Plugin Structure

```
plugins/
├── my-plugin/
│   ├── plugin.json              # Plugin manifest (required)
│   ├── src/
│   │   ├── MyPlugin.php        # Main plugin class (required)
│   │   ├── Controllers/        # API controllers
│   │   ├── Services/           # Business logic
│   │   ├── Models/             # Data models
│   │   └── Events/             # Custom events
│   ├── migrations/             # Database migrations
│   ├── templates/              # View templates
│   ├── assets/                 # CSS, JS, images
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   ├── languages/              # Translation files
│   ├── tests/                  # Plugin tests
│   └── README.md               # Documentation
```

## Plugin Manifest (plugin.json)

The manifest defines plugin metadata, dependencies, and configuration:

```json
{
    "name": "My Awesome Plugin",
    "version": "1.0.0",
    "description": "Adds awesome functionality",
    "author": {
        "name": "Your Name",
        "email": "you@example.com",
        "url": "https://yoursite.com"
    },
    "requirements": {
        "php_version": ">=8.1",
        "core_version": ">=1.0.0",
        "dependencies": {
            "core-commerce": ">=1.0.0",
            "other-plugin": ">=2.0.0"
        }
    },
    "provides": [
        "AwesomeInterface",
        "CoolInterface"
    ],
    "config": {
        "main_class": "MyPlugin\\MyPlugin",
        "namespace": "MyPlugin"
    }
}
```

## Main Plugin Class

Every plugin must extend `AbstractPlugin` and implement `PluginInterface`:

```php
<?php
namespace MyPlugin;

use Core\Plugin\AbstractPlugin;
use Core\Plugin\PluginInterface;
use Core\Plugin\Hook;

class MyPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'my-plugin';
    protected string $version = '1.0.0';
    
    public function install(): bool
    {
        // Run on plugin installation
        $this->runMigrations();
        $this->setDefaultConfig();
        return true;
    }
    
    public function activate(): bool
    {
        // Run on plugin activation
        return true;
    }
    
    public function deactivate(): bool
    {
        // Run on plugin deactivation
        return true;
    }
    
    public function uninstall(): bool
    {
        // Run on plugin removal
        return true;
    }
    
    public function update(string $previousVersion): bool
    {
        // Run on plugin update
        return true;
    }
    
    public function boot(): void
    {
        // Called when plugin is loaded
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
    }
}
```

## Hook System

### Registering Hooks

```php
// Action hook (no return value)
Hook::addAction('order.created', [$this, 'handleOrderCreated'], 10);

// Filter hook (modifies value)
Hook::addFilter('product.price', [$this, 'modifyPrice'], 10);

// Conditional hook
Hook::addConditionalAction('user.login', function($user) {
    return $user->isVIP();
}, [$this, 'handleVIPLogin']);

// One-time hook
Hook::once('app.initialized', [$this, 'runOnce']);
```

### Available Core Hooks

#### **Actions**
- `app.initialized` - Application fully loaded
- `user.registered` - New user registration
- `user.logged_in` - User login
- `user.logged_out` - User logout
- `order.created` - New order created
- `order.placed` - Order placed (before payment)
- `order.completed` - Order completed (after payment)
- `order.cancelled` - Order cancelled
- `order.refunded` - Order refunded
- `product.created` - Product created
- `product.updated` - Product updated
- `product.deleted` - Product deleted
- `cart.item_added` - Item added to cart
- `cart.item_removed` - Item removed from cart
- `cart.updated` - Cart updated
- `page.head` - In HTML head section
- `page.header` - Page header area
- `page.footer` - Page footer area
- `admin.menu` - Admin menu creation

#### **Filters**
- `product.price` - Modify product price
- `product.name` - Modify product name
- `order.totals` - Modify order totals
- `cart.items` - Modify cart items
- `user.capabilities` - Modify user permissions
- `email.content` - Modify email content
- `api.response` - Modify API responses

## Service Registration

Register services in the container:

```php
protected function registerServices(): void
{
    // Singleton service
    $this->container->singleton(MyService::class, function ($container) {
        return new MyService(
            $container->get('db'),
            $container->get('cache')
        );
    });
    
    // Regular binding
    $this->container->bind(MyInterface::class, MyImplementation::class);
    
    // Tagged services
    $this->container->tag([
        EmailProvider::class,
        SmsProvider::class
    ], 'notification.providers');
}
```

## API Route Registration

```php
protected function registerRoutes(): void
{
    // Public endpoint
    $this->registerRoute('GET', '/api/v1/my-plugin/data', 
        'MyPlugin\Controllers\DataController@index');
    
    // Authenticated endpoint
    $this->registerRoute('POST', '/api/v1/my-plugin/action', 
        'MyPlugin\Controllers\ActionController@store', 
        ['auth' => true]);
    
    // With permission check
    $this->registerRoute('DELETE', '/api/v1/my-plugin/{id}', 
        'MyPlugin\Controllers\ActionController@destroy', 
        ['auth' => true, 'permission' => 'my-plugin.delete']);
}
```

## Database Migrations

Create migrations in `migrations/` directory:

```php
<?php
use Core\Database\Schema;
use Core\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_plugin_data', function ($table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('user_id')->index();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('my_plugin_data');
    }
};
```

## Configuration Schema

Define configuration options in plugin.json:

```json
"config_schema": {
    "api_key": {
        "type": "string",
        "label": "API Key",
        "description": "Your service API key",
        "required": true,
        "encrypted": true
    },
    "mode": {
        "type": "select",
        "label": "Mode",
        "default": "test",
        "options": {
            "test": "Test Mode",
            "live": "Live Mode"
        }
    },
    "features": {
        "type": "multiselect",
        "label": "Enabled Features",
        "options": {
            "feature1": "Feature One",
            "feature2": "Feature Two"
        }
    }
}
```

## Asset Management

Register CSS and JavaScript files:

```json
"assets": {
    "js": [
        {
            "src": "assets/js/main.js",
            "position": "footer",
            "pages": ["product", "category"],
            "defer": true,
            "priority": 10
        }
    ],
    "css": [
        {
            "src": "assets/css/styles.css",
            "pages": ["all"],
            "media": "all",
            "priority": 10
        }
    ]
}
```

## Template System

Use Twig-like syntax for templates:

```twig
{# templates/widget.twig #}
<div class="my-plugin-widget">
    <h3>{{ title }}</h3>
    {% for item in items %}
        <div class="item">
            {{ item.name }} - {{ item.price|currency }}
        </div>
    {% endfor %}
</div>
```

Render templates in plugin:

```php
public function renderWidget(): string
{
    return $this->render('widget', [
        'title' => 'My Widget',
        'items' => $this->getItems()
    ]);
}
```

## Plugin API Reference

The PluginAPI provides safe access to core functionality:

```php
// Configuration
$value = $this->getConfig('key', 'default');
$this->setConfig('key', 'value');

// Paths
$path = $this->getPath('templates/widget.twig');
$url = $this->getAssetUrl('images/logo.png');

// Services
$db = $this->api->getDatabase();
$cache = $this->api->getCache();
$events = $this->api->getEvents();

// Users
$user = $this->api->getCurrentUser();
$hasPermission = $this->api->userCan('permission.name');

// URLs
$url = $this->api->siteUrl('my-page');
$adminUrl = $this->api->adminUrl('plugins/my-plugin');

// Utilities
$this->api->log('info', 'Message', ['context' => 'data']);
$this->api->sendEmail('user@example.com', 'template', $data);
$this->api->scheduleTask('task.name', $data, $timestamp);
```

## Best Practices

### 1. **Security**
- Always validate and sanitize input
- Use permission checks for sensitive operations
- Escape output to prevent XSS
- Use prepared statements for database queries

### 2. **Performance**
- Cache expensive operations
- Use lazy loading for heavy resources
- Optimize database queries
- Minimize asset sizes

### 3. **Compatibility**
- Follow semantic versioning
- Maintain backwards compatibility
- Test with different PHP versions
- Document breaking changes

### 4. **Code Quality**
- Follow PSR standards
- Write comprehensive tests
- Document public APIs
- Use type declarations

### 5. **User Experience**
- Provide clear configuration options
- Include helpful descriptions
- Handle errors gracefully
- Provide feedback for actions

## Testing Plugins

Write tests for your plugin:

```php
<?php
namespace MyPlugin\Tests;

use Core\Testing\PluginTestCase;

class MyPluginTest extends PluginTestCase
{
    protected string $pluginName = 'my-plugin';
    
    public function testPluginActivation(): void
    {
        $this->activatePlugin();
        
        $this->assertTrue($this->isPluginActive());
        $this->assertDatabaseHas('my_plugin_data', [
            'name' => 'default'
        ]);
    }
    
    public function testHookExecution(): void
    {
        $this->activatePlugin();
        
        $result = apply_filters('my_plugin.filter', 'original');
        
        $this->assertEquals('modified', $result);
    }
}
```

## Debugging

Enable debug mode for detailed logging:

```php
// In plugin
$this->api->log('debug', 'Processing data', [
    'input' => $input,
    'result' => $result
]);

// Check logs
tail -f storage/logs/plugins/my-plugin.log
```

## Plugin Submission

To submit your plugin:

1. **Validate**: Run `php cli/plugin.php validate my-plugin`
2. **Package**: Create zip with `php cli/plugin.php package my-plugin`
3. **Document**: Include comprehensive README
4. **Test**: Provide test coverage report
5. **Submit**: Upload to marketplace

## Conclusion

The Shopologic plugin architecture provides a robust foundation for extending the platform. By following these guidelines and best practices, you can create powerful, maintainable plugins that integrate seamlessly with the core system.