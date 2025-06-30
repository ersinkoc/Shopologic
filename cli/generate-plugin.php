#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// ANSI color codes
const COLOR_RED = "\033[31m";
const COLOR_GREEN = "\033[32m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_RESET = "\033[0m";

function printColored(string $text, string $color): void {
    echo $color . $text . COLOR_RESET;
}

function printHeader(string $text): void {
    echo "\n";
    printColored("=== {$text} ===\n", COLOR_BLUE);
}

function kebabToPascal(string $str): string {
    return str_replace(' ', '', ucwords(str_replace('-', ' ', $str)));
}

function pascalToWords(string $str): string {
    return ucfirst(strtolower(preg_replace('/([A-Z])/', ' $1', $str)));
}

function generatePlugin(string $name, string $type = 'standard', array $options = []): void {
    $pluginPath = __DIR__ . '/../plugins';
    $pluginDir = $pluginPath . '/' . $name;
    
    // Convert kebab-case to PascalCase for class names
    $className = kebabToPascal($name);
    $namespace = $className;
    $displayName = pascalToWords($className);
    
    // Check if plugin already exists
    if (is_dir($pluginDir)) {
        printColored("Error: Plugin '{$name}' already exists!\n", COLOR_RED);
        exit(1);
    }
    
    printHeader("Generating Plugin: {$name}");
    echo "Type: {$type}\n";
    echo "Class: {$className}Plugin\n";
    echo "Namespace: {$namespace}\n";
    
    // Create plugin directory structure based on type
    $directories = [''];
    
    switch ($type) {
        case 'full':
            $directories = array_merge($directories, ['src', 'migrations', 'templates', 'assets', 'assets/css', 'assets/js', 'tests']);
            break;
        case 'api':
            $directories = array_merge($directories, ['src', 'src/Controllers', 'src/Services', 'migrations', 'tests']);
            break;
        case 'theme':
            $directories = array_merge($directories, ['templates', 'assets', 'assets/css', 'assets/js', 'assets/images']);
            break;
        case 'minimal':
            // Just the root directory
            break;
        case 'standard':
        default:
            $directories = array_merge($directories, ['src', 'migrations', 'templates']);
            break;
    }
    
    // Create directories
    foreach ($directories as $dir) {
        $path = $pluginDir . ($dir ? '/' . $dir : '');
        if (!mkdir($path, 0755, true)) {
            printColored("Error: Failed to create directory {$path}\n", COLOR_RED);
            exit(1);
        }
    }
    
    // Generate plugin.json with standardized format
    $manifest = [
        'name' => $name,
        'version' => '1.0.0',
        'description' => $options['description'] ?? "A {$displayName} plugin for Shopologic",
        'author' => [
            'name' => $options['author'] ?? 'Shopologic Team'
        ],
        'requirements' => [
            'php' => '>=8.3',
            'core' => '>=1.0.0',
            'dependencies' => [
                'shopologic/core' => '^1.0'
            ]
        ],
        'autoload' => [
            'psr-4' => [
                "{$namespace}\\" => ($type === 'minimal') ? '' : 'src/'
            ]
        ],
        'bootstrap' => [
            'class' => "{$namespace}\\{$className}Plugin",
            'file' => ($type === 'minimal') ? "{$className}Plugin.php" : "src/{$className}Plugin.php"
        ],
        'permissions' => []
    ];
    
    // Add type-specific manifest fields
    switch ($type) {
        case 'full':
        case 'api':
            $manifest['api'] = [
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => "/api/v1/{$name}",
                        'handler' => 'listItems'
                    ],
                    [
                        'method' => 'POST',
                        'path' => "/api/v1/{$name}",
                        'handler' => 'createItem'
                    ],
                    [
                        'method' => 'GET',
                        'path' => "/api/v1/{$name}/{id}",
                        'handler' => 'getItem'
                    ],
                    [
                        'method' => 'PUT',
                        'path' => "/api/v1/{$name}/{id}",
                        'handler' => 'updateItem'
                    ],
                    [
                        'method' => 'DELETE',
                        'path' => "/api/v1/{$name}/{id}",
                        'handler' => 'deleteItem'
                    ]
                ]
            ];
            $manifest['permissions'][] = "{$name}.view";
            $manifest['permissions'][] = "{$name}.create";
            $manifest['permissions'][] = "{$name}.update";
            $manifest['permissions'][] = "{$name}.delete";
            
            // Add hooks for API plugins
            $manifest['hooks'] = [
                'actions' => [
                    "{$name}.created",
                    "{$name}.updated",
                    "{$name}.deleted"
                ]
            ];
            break;
            
        case 'theme':
            $manifest['assets'] = [
                'css' => [
                    [
                        'src' => 'assets/css/style.css',
                        'pages' => ['all']
                    ]
                ],
                'js' => [
                    [
                        'src' => 'assets/js/theme.js',
                        'position' => 'footer',
                        'pages' => ['all']
                    ]
                ]
            ];
            
            $manifest['hooks'] = [
                'filters' => [
                    'template_paths',
                    'theme_assets'
                ]
            ];
            break;
            
        case 'standard':
            // Add basic hooks for standard plugins
            $manifest['hooks'] = [
                'actions' => [
                    'init'
                ],
                'filters' => [
                    'menu_items'
                ]
            ];
            break;
    }
    
    file_put_contents($pluginDir . '/plugin.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    // Generate main plugin class
    $classPath = ($type === 'minimal') ? $pluginDir : $pluginDir . '/src';
    $classContent = generatePluginClass($className, $namespace, $type, $displayName);
    file_put_contents($classPath . "/{$className}Plugin.php", $classContent);
    
    // Generate additional files based on type
    switch ($type) {
        case 'full':
        case 'standard':
            // Generate sample migration
            $migrationContent = generateMigration($className, $name);
            file_put_contents($pluginDir . "/migrations/001_create_{$name}_table.php", $migrationContent);
            
            // Generate sample template
            $templateContent = generateTemplate($displayName);
            file_put_contents($pluginDir . "/templates/index.twig", $templateContent);
            break;
            
        case 'api':
            // Generate controller
            $controllerContent = generateController($className, $namespace);
            file_put_contents($pluginDir . "/src/Controllers/{$className}Controller.php", $controllerContent);
            
            // Generate service
            $serviceContent = generateService($className, $namespace);
            file_put_contents($pluginDir . "/src/Services/{$className}Service.php", $serviceContent);
            break;
            
        case 'theme':
            // Generate base template
            $themeTemplateContent = generateThemeTemplate($displayName);
            file_put_contents($pluginDir . "/templates/base.twig", $themeTemplateContent);
            
            // Generate CSS
            file_put_contents($pluginDir . "/assets/css/style.css", "/* {$displayName} Theme Styles */\n");
            
            // Generate JS
            file_put_contents($pluginDir . "/assets/js/theme.js", "// {$displayName} Theme JavaScript\n");
            break;
    }
    
    // Generate README.md
    $readmeContent = generateReadme($name, $displayName, $type, $className);
    file_put_contents($pluginDir . "/README.md", $readmeContent);
    
    // Generate .gitignore
    $gitignoreContent = "/vendor/\n/node_modules/\n.env\n.DS_Store\n*.log\n";
    file_put_contents($pluginDir . "/.gitignore", $gitignoreContent);
    
    printColored("\n✓ Plugin '{$name}' generated successfully!\n", COLOR_GREEN);
    
    echo "\nNext steps:\n";
    echo "1. cd plugins/{$name}\n";
    echo "2. Implement your plugin logic in " . (($type === 'minimal') ? '' : 'src/') . "{$className}Plugin.php\n";
    echo "3. Run validation: php cli/validate-plugins.php\n";
    echo "4. Activate plugin: php cli/plugin.php activate {$name}\n";
}

function generatePluginClass(string $className, string $namespace, string $type, string $displayName): string {
    $hooks = '';
    $methods = '';
    
    switch ($type) {
        case 'full':
        case 'api':
            $hooks = <<<PHP
        // Register API endpoints
        \$this->registerApiEndpoints();
        
        // Register permissions
        HookSystem::addFilter('permissions_list', [\$this, 'registerPermissions']);
PHP;
            
            $methods = <<<PHP

    /**
     * Register API endpoints
     */
    protected function registerApiEndpoints(): void
    {
        \$this->registerRoute('GET', '/api/v1/{$namespace}', [\$this, 'listItems']);
        \$this->registerRoute('POST', '/api/v1/{$namespace}', [\$this, 'createItem']);
        \$this->registerRoute('GET', '/api/v1/{$namespace}/{id}', [\$this, 'getItem']);
        \$this->registerRoute('PUT', '/api/v1/{$namespace}/{id}', [\$this, 'updateItem']);
        \$this->registerRoute('DELETE', '/api/v1/{$namespace}/{id}', [\$this, 'deleteItem']);
    }

    /**
     * Register plugin permissions
     */
    public function registerPermissions(array \$permissions): array
    {
        \$permissions['{$namespace}'] = [
            'view' => 'View {$displayName}',
            'create' => 'Create {$displayName}',
            'update' => 'Update {$displayName}',
            'delete' => 'Delete {$displayName}'
        ];
        
        return \$permissions;
    }

    /**
     * List items endpoint
     */
    public function listItems(Request \$request, Response \$response): Response
    {
        // TODO: Implement list logic
        return \$response->json(['items' => []]);
    }

    /**
     * Create item endpoint
     */
    public function createItem(Request \$request, Response \$response): Response
    {
        // TODO: Implement create logic
        return \$response->json(['success' => true]);
    }

    /**
     * Get single item endpoint
     */
    public function getItem(Request \$request, Response \$response, array \$params): Response
    {
        // TODO: Implement get logic
        return \$response->json(['item' => null]);
    }

    /**
     * Update item endpoint
     */
    public function updateItem(Request \$request, Response \$response, array \$params): Response
    {
        // TODO: Implement update logic
        return \$response->json(['success' => true]);
    }

    /**
     * Delete item endpoint
     */
    public function deleteItem(Request \$request, Response \$response, array \$params): Response
    {
        // TODO: Implement delete logic
        return \$response->json(['success' => true]);
    }
PHP;
            break;
            
        case 'theme':
            $hooks = <<<PHP
        // Register theme templates
        HookSystem::addFilter('template_paths', [\$this, 'registerTemplatePaths']);
        
        // Register theme assets
        HookSystem::addAction('wp_enqueue_scripts', [\$this, 'enqueueAssets']);
PHP;
            
            $methods = <<<PHP

    /**
     * Register template paths
     */
    public function registerTemplatePaths(array \$paths): array
    {
        \$paths[] = \$this->getPluginPath() . '/templates';
        return \$paths;
    }

    /**
     * Enqueue theme assets
     */
    public function enqueueAssets(): void
    {
        \$this->enqueueStyle('{$namespace}-style', 'assets/css/style.css');
        \$this->enqueueScript('{$namespace}-script', 'assets/js/theme.js', ['jquery']);
    }
PHP;
            break;
            
        case 'standard':
        default:
            $hooks = <<<PHP
        // Register hooks
        HookSystem::addAction('init', [\$this, 'init']);
        HookSystem::addFilter('menu_items', [\$this, 'addMenuItem']);
PHP;
            
            $methods = <<<PHP

    /**
     * Initialize plugin
     */
    public function init(): void
    {
        // TODO: Add initialization logic
    }

    /**
     * Add menu item
     */
    public function addMenuItem(array \$items): array
    {
        \$items[] = [
            'title' => '{$displayName}',
            'slug' => '{$namespace}',
            'icon' => 'dashicons-admin-generic',
            'capability' => '{$namespace}.view'
        ];
        
        return \$items;
    }
PHP;
            break;
    }
    
    return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;

class {$className}Plugin extends AbstractPlugin
{
    /**
     * Get plugin information
     */
    public function getInfo(): array
    {
        return [
            'name' => '{$displayName}',
            'version' => '1.0.0',
            'author' => 'Shopologic Team',
            'description' => 'A {$displayName} plugin for Shopologic'
        ];
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        \$this->runMigrations();
        
        // Set default options
        \$this->setDefaultOptions();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Clean up temporary data if needed
    }

    /**
     * Plugin uninstall
     */
    public function uninstall(): void
    {
        // Remove plugin data
        \$this->removeTables();
        \$this->removeOptions();
    }

    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
{$hooks}
    }

    /**
     * Set default options
     */
    protected function setDefaultOptions(): void
    {
        \$defaults = [
            '{$namespace}_enabled' => true,
            '{$namespace}_version' => '1.0.0'
        ];
        
        foreach (\$defaults as \$key => \$value) {
            if (!\$this->getOption(\$key)) {
                \$this->setOption(\$key, \$value);
            }
        }
    }

    /**
     * Remove plugin tables
     */
    protected function removeTables(): void
    {
        // TODO: Implement table removal if needed
    }

    /**
     * Remove plugin options
     */
    protected function removeOptions(): void
    {
        \$options = [
            '{$namespace}_enabled',
            '{$namespace}_version'
        ];
        
        foreach (\$options as \$option) {
            \$this->deleteOption(\$option);
        }
    }
{$methods}
}
PHP;
}

function generateMigration(string $className, string $name): string {
    $tableName = str_replace('-', '_', $name);
    return <<<PHP
<?php

declare(strict_types=1);

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Blueprint;
use Shopologic\Core\Database\Schema\Schema;

return new class extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
            \$table->text('description')->nullable();
            \$table->boolean('active')->default(true);
            \$table->timestamps();
            
            \$table->index('name');
            \$table->index('active');
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;
}

function generateTemplate(string $displayName): string {
    return <<<TWIG
{% extends "base.twig" %}

{% block title %}{$displayName}{% endblock %}

{% block content %}
<div class="container">
    <h1>{$displayName}</h1>
    
    <div class="card">
        <div class="card-body">
            <p>Welcome to the {$displayName} plugin!</p>
            
            {% if items is defined %}
            <ul>
                {% for item in items %}
                <li>{{ item.name }}</li>
                {% endfor %}
            </ul>
            {% endif %}
        </div>
    </div>
</div>
{% endblock %}
TWIG;
}

function generateController(string $className, string $namespace): string {
    return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\Controllers;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use {$namespace}\Services\\{$className}Service;

class {$className}Controller
{
    protected {$className}Service \$service;
    
    public function __construct({$className}Service \$service)
    {
        \$this->service = \$service;
    }
    
    /**
     * List all items
     */
    public function index(Request \$request, Response \$response): Response
    {
        \$items = \$this->service->getAll();
        
        return \$response->json([
            'success' => true,
            'data' => \$items
        ]);
    }
    
    /**
     * Get single item
     */
    public function show(Request \$request, Response \$response, array \$params): Response
    {
        \$item = \$this->service->find(\$params['id']);
        
        if (!\$item) {
            return \$response->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }
        
        return \$response->json([
            'success' => true,
            'data' => \$item
        ]);
    }
    
    /**
     * Create new item
     */
    public function store(Request \$request, Response \$response): Response
    {
        \$data = \$request->getParsedBody();
        
        try {
            \$item = \$this->service->create(\$data);
            
            return \$response->json([
                'success' => true,
                'data' => \$item
            ], 201);
        } catch (\Exception \$e) {
            return \$response->json([
                'success' => false,
                'message' => \$e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Update item
     */
    public function update(Request \$request, Response \$response, array \$params): Response
    {
        \$data = \$request->getParsedBody();
        
        try {
            \$item = \$this->service->update(\$params['id'], \$data);
            
            return \$response->json([
                'success' => true,
                'data' => \$item
            ]);
        } catch (\Exception \$e) {
            return \$response->json([
                'success' => false,
                'message' => \$e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Delete item
     */
    public function destroy(Request \$request, Response \$response, array \$params): Response
    {
        try {
            \$this->service->delete(\$params['id']);
            
            return \$response->json([
                'success' => true,
                'message' => 'Item deleted successfully'
            ]);
        } catch (\Exception \$e) {
            return \$response->json([
                'success' => false,
                'message' => \$e->getMessage()
            ], 400);
        }
    }
}
PHP;
}

function generateService(string $className, string $namespace): string {
    return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\Services;

use Shopologic\Core\Database\DB;

class {$className}Service
{
    protected string \$table;
    
    public function __construct()
    {
        \$this->table = '{$namespace}';
    }
    
    /**
     * Get all items
     */
    public function getAll(): array
    {
        return DB::table(\$this->table)
            ->where('active', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Find item by ID
     */
    public function find(int \$id): ?array
    {
        return DB::table(\$this->table)
            ->where('id', \$id)
            ->first();
    }
    
    /**
     * Create new item
     */
    public function create(array \$data): array
    {
        \$id = DB::table(\$this->table)->insertGetId([
            'name' => \$data['name'],
            'description' => \$data['description'] ?? null,
            'active' => \$data['active'] ?? true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return \$this->find(\$id);
    }
    
    /**
     * Update item
     */
    public function update(int \$id, array \$data): array
    {
        DB::table(\$this->table)
            ->where('id', \$id)
            ->update([
                'name' => \$data['name'],
                'description' => \$data['description'] ?? null,
                'active' => \$data['active'] ?? true,
                'updated_at' => now()
            ]);
        
        return \$this->find(\$id);
    }
    
    /**
     * Delete item
     */
    public function delete(int \$id): bool
    {
        return DB::table(\$this->table)
            ->where('id', \$id)
            ->delete() > 0;
    }
}
PHP;
}

function generateThemeTemplate(string $displayName): string {
    return <<<TWIG
<!DOCTYPE html>
<html lang="{{ app.locale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}{$displayName} Theme{% endblock %} - {{ site.name }}</title>
    
    {% block styles %}
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    {% endblock %}
</head>
<body class="{{ body_class() }}">
    <header class="site-header">
        <div class="container">
            <h1 class="site-title">{{ site.name }}</h1>
            <nav class="site-nav">
                {{ menu('primary') }}
            </nav>
        </div>
    </header>
    
    <main class="site-main">
        {% block content %}
        <div class="container">
            <h1>Welcome to {$displayName} Theme</h1>
            <p>Start customizing your theme!</p>
        </div>
        {% endblock %}
    </main>
    
    <footer class="site-footer">
        <div class="container">
            <p>&copy; {{ "now"|date("Y") }} {{ site.name }}. All rights reserved.</p>
        </div>
    </footer>
    
    {% block scripts %}
    <script src="{{ asset('js/theme.js') }}"></script>
    {% endblock %}
</body>
</html>
TWIG;
}

function generateReadme(string $name, string $displayName, string $type, string $className): string {
    $typeDescription = match($type) {
        'full' => 'full-featured plugin with API endpoints, migrations, and templates',
        'api' => 'API-focused plugin with controllers and services',
        'theme' => 'theme plugin with templates and assets',
        'minimal' => 'minimal plugin with basic structure',
        default => 'standard plugin with common features'
    };
    
    return <<<MD
# {$displayName} Plugin

A {$typeDescription} for Shopologic e-commerce platform.

## Features

- TODO: List your plugin features here

## Installation

1. Copy this plugin to the `plugins/{$name}` directory
2. Run migrations (if any): `php cli/migrate.php`
3. Activate the plugin: `php cli/plugin.php activate {$name}`

## Configuration

TODO: Document any configuration options

## Usage

TODO: Add usage instructions

## API Endpoints

TODO: Document API endpoints (if applicable)

## Hooks

This plugin uses the following hooks:
- TODO: List hooks used

This plugin provides the following hooks:
- TODO: List hooks provided

## Development

### Directory Structure

```
{$name}/
├── plugin.json          # Plugin manifest
├── README.md           # This file
├── {$className}Plugin.php  # Main plugin class
├── migrations/         # Database migrations
├── templates/          # Twig templates
└── assets/            # CSS, JS, images
```

### Running Tests

```bash
php vendor/bin/phpunit plugins/{$name}/tests
```

## License

TODO: Add license information
MD;
}

// Parse command line arguments
$options = getopt('', ['type:', 'author:', 'description:', 'help']);

if (isset($options['help']) || $argc < 2) {
    echo <<<HELP
Shopologic Plugin Generator

Usage: php cli/generate-plugin.php <plugin-name> [options]

Options:
    --type=TYPE         Plugin type: standard (default), full, api, theme, minimal
    --author=AUTHOR     Plugin author name
    --description=DESC  Plugin description
    --help             Show this help message

Plugin Types:
    standard  - Basic plugin with migrations and templates
    full      - Full-featured plugin with API, migrations, templates, and assets
    api       - API-focused plugin with controllers and services
    theme     - Theme plugin with templates and assets
    minimal   - Minimal plugin with just the basics

Examples:
    php cli/generate-plugin.php my-awesome-plugin
    php cli/generate-plugin.php payment-gateway --type=full
    php cli/generate-plugin.php custom-theme --type=theme --author="John Doe"

HELP;
    exit(0);
}

$pluginName = $argv[1];
$type = $options['type'] ?? 'standard';
$validTypes = ['standard', 'full', 'api', 'theme', 'minimal'];

if (!preg_match('/^[a-z0-9-]+$/', $pluginName)) {
    printColored("Error: Plugin name must contain only lowercase letters, numbers, and hyphens\n", COLOR_RED);
    exit(1);
}

if (!in_array($type, $validTypes)) {
    printColored("Error: Invalid plugin type. Must be one of: " . implode(', ', $validTypes) . "\n", COLOR_RED);
    exit(1);
}

// Generate the plugin
try {
    generatePlugin($pluginName, $type, $options);
} catch (Exception $e) {
    printColored("Error: " . $e->getMessage() . "\n", COLOR_RED);
    exit(1);
}