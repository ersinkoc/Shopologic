# Getting Started with Shopologic Development

This guide will help you set up your development environment and get started with Shopologic development.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

Shopologic now features a comprehensive plugin ecosystem with 47 advanced models, cross-plugin integration, real-time events, performance monitoring, and automated testing. This guide covers the enhanced development workflow.

## 🚀 Quick Start with Enhanced Ecosystem

```bash
# Initialize complete plugin ecosystem
php bootstrap_plugins.php

# Run with integration demonstration
php bootstrap_plugins.php --demo
```

## 🎯 Prerequisites

### Required Software
- **PHP 8.3+** with required extensions
- **PostgreSQL 13+** database server
- **Redis** (optional, but recommended for caching)
- **Node.js 18+** (for frontend asset compilation)
- **Git** for version control

### Required PHP Extensions
```bash
# Check if extensions are installed
php -m | grep -E "(pdo|pdo_pgsql|json|mbstring|openssl|curl|gd|intl|zip)"
```

Required extensions:
- `pdo` and `pdo_pgsql`
- `json`
- `mbstring`
- `openssl`
- `curl`
- `gd` or `imagick`
- `intl`
- `zip`

## 📥 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/shopologic/shopologic.git
cd shopologic
```

### 2. Install Dependencies
Shopologic uses zero external dependencies by design, but you may want development tools:
```bash
# Install development tools (optional)
composer install --dev
```

### 3. Environment Configuration
```bash
# Copy environment template
cp .env.example .env

# Edit configuration
nano .env
```

Configure your `.env` file:
```env
# Application
APP_NAME="Shopologic Development"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=shopologic_dev
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Cache (Redis recommended)
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=1440

# Security (will be generated during installation)
ENCRYPTION_KEY=
JWT_SECRET=
```

### 4. Run Installation
```bash
php cli/install.php
```

This will:
- Check system requirements
- Generate encryption keys
- Create necessary directories
- Set file permissions

### 5. Database Setup
```bash
# Run database migrations
php cli/migrate.php up

# Seed with sample data
php cli/seed.php run
```

### 6. Start Development Server
```bash
php -S localhost:8000 -t public/
```

Visit `http://localhost:8000` to see your Shopologic installation.

## 🏗️ Development Workflow

### Directory Structure
```
Shopologic/
├── core/src/           # Core framework code
├── plugins/            # Plugin system
├── themes/            # Theme system
├── public/            # Web entry points
├── storage/           # Runtime data
├── database/          # Migrations and seeds
├── cli/              # Command-line tools
├── tests/            # Test suites
└── docs/             # Documentation
```

### Key Development Commands
```bash
# Database operations
php cli/migrate.php up              # Run migrations
php cli/migrate.php create TableName # Create migration
php cli/seed.php run               # Seed database

# Plugin management
php cli/plugin.php list            # List plugins
php cli/plugin.php activate name   # Activate plugin
php cli/plugin.php generate Name   # Generate plugin

# Cache management
php cli/cache.php clear            # Clear cache
php cli/cache.php warm             # Warm cache

# Testing
php cli/test.php                   # Run tests
php cli/test.php --coverage        # With coverage

# Security scanning
php cli/security.php scan          # Security scan
php cli/security.php harden        # Apply hardening
```

## 🔧 Development Tools

### IDE Configuration

#### VS Code Extensions
```json
{
  "recommendations": [
    "bmewburn.vscode-intelephense-client",
    "ms-vscode.vscode-json",
    "redhat.vscode-yaml",
    "bradlc.vscode-tailwindcss"
  ]
}
```

#### PHPStorm Configuration
1. Enable PHP 8.3 language level
2. Configure PSR-12 code style
3. Set up xdebug for debugging
4. Configure database connection

### Debugging Setup

#### Xdebug Configuration
Add to your `php.ini`:
```ini
[xdebug]
zend_extension=xdebug
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=localhost
xdebug.client_port=9003
```

### Code Quality Tools

#### PHP CS Fixer
```bash
# Fix code style
composer cs-fix

# Check code style
composer cs-check
```

#### PHPStan
```bash
# Analyze code
composer analyse
```

## 🧪 Testing

### Running Tests
```bash
# Run all tests
php cli/test.php

# Run specific test suite
php cli/test.php --suite=Unit

# Run with coverage
php cli/test.php --coverage
```

### Writing Tests
Create test files in `tests/` directory:

```php
<?php
// tests/Unit/ExampleTest.php

TestFramework::describe('Example Component', function() {
    TestFramework::it('should work correctly', function() {
        $result = someFunction();
        TestFramework::expect($result)->toBe('expected');
    });
});
```

## 🔌 Plugin Development

### Generate Plugin Scaffold
```bash
php cli/plugin.php generate MyPlugin
```

This creates:
```
plugins/MyPlugin/
├── plugin.json        # Plugin manifest
├── src/
│   └── MyPluginPlugin.php
├── templates/         # Plugin templates
├── assets/           # CSS/JS assets
└── migrations/       # Database migrations
```

### Basic Plugin Structure
```php
<?php
// plugins/MyPlugin/src/MyPluginPlugin.php

namespace Shopologic\Plugins\MyPlugin;

use Shopologic\Core\Plugin\AbstractPlugin;

class MyPluginPlugin extends AbstractPlugin
{
    public function boot(): void
    {
        // Plugin initialization
    }
    
    protected function registerServices(): void
    {
        // Register plugin services
    }
    
    protected function registerHooks(): void
    {
        // Register event hooks
        HookSystem::addAction('order_created', [$this, 'handleOrderCreated']);
    }
    
    protected function registerRoutes(): void
    {
        // Register plugin routes
        $this->registerRoute('GET', '/api/my-plugin/test', [$this, 'testEndpoint']);
    }
}
```

## 🎨 Theme Development

### Theme Structure
```
themes/my-theme/
├── theme.json         # Theme configuration
├── templates/         # Template files
│   ├── layouts/
│   ├── pages/
│   └── partials/
├── components/        # Reusable components
├── assets/           # CSS, JS, images
└── config/           # Theme settings
```

### Basic Template
```twig
{# templates/pages/product.twig #}
{% extends "layouts/base.twig" %}

{% block content %}
    <div class="product">
        <h1>{{ product.name }}</h1>
        <p>{{ product.description }}</p>
        <div class="price">${{ product.price }}</div>
        
        {{ hook('product_details_after') }}
    </div>
{% endblock %}
```

## 🔧 Configuration

### Application Configuration
Configuration files in `core/config/`:
- `app.php` - Application settings
- `database.php` - Database configuration
- `cache.php` - Cache settings
- `security.php` - Security configuration

### Environment Variables
Key environment variables:
```env
# Application
APP_ENV=development|production
APP_DEBUG=true|false
APP_URL=http://localhost:8000

# Database
DB_HOST=localhost
DB_DATABASE=shopologic
DB_USERNAME=username
DB_PASSWORD=password

# Cache
CACHE_DRIVER=file|redis|array
REDIS_HOST=127.0.0.1

# Security
ENCRYPTION_KEY=base64:...
JWT_SECRET=...
```

## 📊 Monitoring Development

### Debug Information
Enable debug mode for detailed error information:
```env
APP_DEBUG=true
```

### Logging
Logs are stored in `storage/logs/`:
```php
// Log debug information
log_debug('Debug message', ['context' => $data]);

// Log errors
log_error('Error occurred', ['exception' => $e]);
```

### Performance Monitoring
```bash
# Check performance
php cli/cache.php stats

# Monitor queries (in debug mode)
tail -f storage/logs/queries.log
```

## 🚀 Next Steps

1. **Explore the Architecture**: Read [Architecture Overview](./architecture.md)
2. **Build Your First Plugin**: Follow [Plugin Development](./plugin-development.md)
3. **Create a Custom Theme**: See [Theme Development](./theme-development.md)
4. **Learn the API**: Check [API Reference](./api-reference.md)
5. **Security Best Practices**: Review [Security Guidelines](./security.md)

## 🆘 Getting Help

- **Documentation**: Browse the [docs directory](../)
- **Issues**: [Report bugs or request features](https://github.com/shopologic/shopologic/issues)
- **Community**: [Join our community forum](https://community.shopologic.com)
- **Discord**: [Chat with developers](https://discord.gg/shopologic)

## 📝 Common Issues

### Database Connection Errors
```bash
# Check PostgreSQL is running
sudo systemctl status postgresql

# Test connection
psql -h localhost -U username -d shopologic_dev
```

### Permission Issues
```bash
# Fix permissions
sudo chown -R $USER:$USER storage/
chmod -R 755 storage/
```

### Cache Issues
```bash
# Clear all caches
php cli/cache.php clear

# Check Redis connection
redis-cli ping
```

---

You're now ready to start developing with Shopologic! 🎉