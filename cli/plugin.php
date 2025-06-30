<?php

declare(strict_types=1);

/**
 * Shopologic Plugin Management Tool
 * 
 * Handles plugin installation, activation, and management
 */

// Define root path
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Register autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\Core\Plugin\PluginManager;
use Shopologic\Core\Plugin\PluginRepository;
use Shopologic\Core\Container\Container;
use Shopologic\Core\Events\EventDispatcher;

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->register();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');

// Load environment
if (file_exists(SHOPOLOGIC_ROOT . '/.env')) {
    $lines = file(SHOPOLOGIC_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

try {
    // Initialize dependencies
    $container = new Container();
    $events = new EventDispatcher();
    $config = new ConfigurationManager();
    
    $repository = new PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
    $pluginManager = new PluginManager($repository, $container, $events);
    
    // Parse command line arguments
    $command = $argv[1] ?? 'help';
    $arguments = array_slice($argv, 2);
    
    switch ($command) {
        case 'list':
            echo "Installed Plugins:\n";
            echo "==================\n";
            $plugins = $repository->getAll();
            
            if (empty($plugins)) {
                echo "No plugins found.\n";
                break;
            }
            
            foreach ($plugins as $plugin) {
                $status = $pluginManager->isActive($plugin['name']) ? 'Active' : 'Inactive';
                echo sprintf("%-25s %-10s %s\n", 
                    $plugin['name'], 
                    $status, 
                    $plugin['version'] ?? 'Unknown'
                );
            }
            break;
            
        case 'install':
            $name = $arguments[0] ?? null;
            if (!$name) {
                echo "Error: Plugin name is required.\n";
                echo "Usage: php cli/plugin.php install plugin-name\n";
                exit(1);
            }
            
            echo "Installing plugin: {$name}...\n";
            $pluginManager->install($name);
            echo "Plugin installed successfully.\n";
            break;
            
        case 'uninstall':
            $name = $arguments[0] ?? null;
            if (!$name) {
                echo "Error: Plugin name is required.\n";
                echo "Usage: php cli/plugin.php uninstall plugin-name\n";
                exit(1);
            }
            
            echo "Uninstalling plugin: {$name}...\n";
            $pluginManager->uninstall($name);
            echo "Plugin uninstalled successfully.\n";
            break;
            
        case 'activate':
            $name = $arguments[0] ?? null;
            if (!$name) {
                echo "Error: Plugin name is required.\n";
                echo "Usage: php cli/plugin.php activate plugin-name\n";
                exit(1);
            }
            
            echo "Activating plugin: {$name}...\n";
            $pluginManager->activate($name);
            echo "Plugin activated successfully.\n";
            break;
            
        case 'deactivate':
            $name = $arguments[0] ?? null;
            if (!$name) {
                echo "Error: Plugin name is required.\n";
                echo "Usage: php cli/plugin.php deactivate plugin-name\n";
                exit(1);
            }
            
            echo "Deactivating plugin: {$name}...\n";
            $pluginManager->deactivate($name);
            echo "Plugin deactivated successfully.\n";
            break;
            
        case 'update':
            $name = $arguments[0] ?? null;
            
            if ($name) {
                echo "Updating plugin: {$name}...\n";
                $pluginManager->update($name);
                echo "Plugin updated successfully.\n";
            } else {
                echo "Updating all plugins...\n";
                $plugins = $repository->getAll();
                foreach ($plugins as $plugin) {
                    echo "Updating {$plugin['name']}...\n";
                    $pluginManager->update($plugin['name']);
                }
                echo "All plugins updated successfully.\n";
            }
            break;
            
        case 'info':
            $name = $arguments[0] ?? null;
            if (!$name) {
                echo "Error: Plugin name is required.\n";
                echo "Usage: php cli/plugin.php info plugin-name\n";
                exit(1);
            }
            
            $plugin = $repository->get($name);
            if (!$plugin) {
                echo "Plugin not found: {$name}\n";
                exit(1);
            }
            
            echo "Plugin Information:\n";
            echo "===================\n";
            echo "Name: " . ($plugin['name'] ?? 'Unknown') . "\n";
            echo "Version: " . ($plugin['version'] ?? 'Unknown') . "\n";
            echo "Description: " . ($plugin['description'] ?? 'No description') . "\n";
            echo "Author: " . ($plugin['author'] ?? 'Unknown') . "\n";
            echo "Status: " . ($pluginManager->isActive($name) ? 'Active' : 'Inactive') . "\n";
            
            if (!empty($plugin['dependencies'])) {
                echo "Dependencies:\n";
                foreach ($plugin['dependencies'] as $dep) {
                    echo "  - {$dep}\n";
                }
            }
            break;
            
        case 'generate':
            // Redirect to the new generate-plugin.php script
            $args = array_merge(['php', __DIR__ . '/generate-plugin.php'], $arguments);
            $result = 0;
            passthru(implode(' ', array_map('escapeshellarg', $args)), $result);
            exit($result);
            
        case 'validate':
            // Redirect to the new validate-plugins.php script
            $result = 0;
            passthru('php ' . escapeshellarg(__DIR__ . '/validate-plugins.php'), $result);
            exit($result);
            
        default:
            echo "Shopologic Plugin Management Tool\n";
            echo "================================\n\n";
            echo "Available commands:\n";
            echo "  list                    List all installed plugins\n";
            echo "  install <name>          Install a plugin\n";
            echo "  uninstall <name>        Uninstall a plugin\n";
            echo "  activate <name>         Activate a plugin\n";
            echo "  deactivate <name>       Deactivate a plugin\n";
            echo "  update [name]           Update plugin(s)\n";
            echo "  info <name>             Show plugin information\n";
            echo "  generate <name>         Generate plugin scaffold\n";
            echo "  validate [name]         Validate plugin(s)\n";
            echo "  help                    Show this help message\n";
            break;
    }
    
} catch (Exception $e) {
    echo "Plugin Error: " . $e->getMessage() . "\n";
    
    if (getenv('APP_DEBUG') === 'true') {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
    exit(1);
}

/**
 * Generate a new plugin scaffold
 */
function generatePlugin(string $name): void
{
    $pluginDir = SHOPOLOGIC_ROOT . '/plugins/' . $name;
    
    if (is_dir($pluginDir)) {
        throw new Exception("Plugin directory already exists: {$pluginDir}");
    }
    
    // Create directory structure
    mkdir($pluginDir, 0755, true);
    mkdir($pluginDir . '/src', 0755, true);
    mkdir($pluginDir . '/templates', 0755, true);
    mkdir($pluginDir . '/assets', 0755, true);
    mkdir($pluginDir . '/migrations', 0755, true);
    
    // Generate plugin.json
    $manifest = [
        'name' => $name,
        'version' => '1.0.0',
        'description' => 'A new Shopologic plugin',
        'author' => 'Your Name',
        'main' => 'src/' . $name . 'Plugin.php',
        'dependencies' => [],
        'permissions' => [],
        'hooks' => []
    ];
    
    file_put_contents(
        $pluginDir . '/plugin.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    
    // Generate main plugin class
    $pluginClass = <<<PHP
<?php

declare(strict_types=1);

namespace Shopologic\\Plugins\\{$name};

use Shopologic\\Core\\Plugin\\AbstractPlugin;

class {$name}Plugin extends AbstractPlugin
{
    public function boot(): void
    {
        // Plugin initialization code here
    }
    
    protected function registerServices(): void
    {
        // Register plugin services
    }
    
    protected function registerHooks(): void
    {
        // Register plugin hooks
    }
    
    protected function registerRoutes(): void
    {
        // Register plugin routes
    }
}
PHP;
    
    file_put_contents($pluginDir . '/src/' . $name . 'Plugin.php', $pluginClass);
    
    // Generate README
    $readme = <<<MD
# {$name} Plugin

A Shopologic plugin.

## Installation

1. Copy this plugin to the `plugins/{$name}` directory
2. Run `php cli/plugin.php activate {$name}`

## Configuration

Add any configuration instructions here.

## Usage

Add usage instructions here.
MD;
    
    file_put_contents($pluginDir . '/README.md', $readme);
}

/**
 * Validate a plugin
 */
function validatePlugin(string $name, PluginRepository $repository): void
{
    $plugin = $repository->get($name);
    if (!$plugin) {
        echo "❌ Plugin not found: {$name}\n";
        return;
    }
    
    $errors = [];
    $warnings = [];
    
    // Check required fields
    $required = ['name', 'version', 'main'];
    foreach ($required as $field) {
        if (empty($plugin[$field])) {
            $errors[] = "Missing required field: {$field}";
        }
    }
    
    // Check main class exists
    if (!empty($plugin['main'])) {
        $mainFile = SHOPOLOGIC_ROOT . '/plugins/' . $name . '/' . $plugin['main'];
        if (!file_exists($mainFile)) {
            $errors[] = "Main plugin file not found: {$plugin['main']}";
        }
    }
    
    // Check dependencies
    if (!empty($plugin['dependencies'])) {
        foreach ($plugin['dependencies'] as $dep) {
            if (!$repository->get($dep)) {
                $warnings[] = "Dependency not found: {$dep}";
            }
        }
    }
    
    // Output results
    if (empty($errors) && empty($warnings)) {
        echo "✅ Plugin '{$name}' is valid\n";
    } else {
        echo "⚠️  Plugin '{$name}' has issues:\n";
        
        foreach ($errors as $error) {
            echo "  ❌ Error: {$error}\n";
        }
        
        foreach ($warnings as $warning) {
            echo "  ⚠️  Warning: {$warning}\n";
        }
    }
}