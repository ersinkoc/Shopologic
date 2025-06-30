#!/usr/bin/env php
<?php

declare(strict_types=1);

// Autoloader not needed for this script

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

class PluginManifestMigrator
{
    private string $pluginPath;
    private array $backups = [];
    private array $migrated = [];
    private array $errors = [];
    private bool $dryRun = false;
    
    public function __construct(string $pluginPath, bool $dryRun = false)
    {
        $this->pluginPath = $pluginPath;
        $this->dryRun = $dryRun;
    }
    
    public function migrate(): void
    {
        printHeader("Plugin Manifest Migration" . ($this->dryRun ? " (DRY RUN)" : ""));
        
        $plugins = $this->scanPlugins();
        
        echo "Found " . count($plugins) . " plugins to process\n\n";
        
        foreach ($plugins as $plugin) {
            $this->migratePlugin($plugin);
        }
        
        $this->printSummary();
    }
    
    private function scanPlugins(): array
    {
        $plugins = [];
        
        if (!is_dir($this->pluginPath)) {
            throw new Exception("Plugin directory not found: {$this->pluginPath}");
        }
        
        $directories = scandir($this->pluginPath);
        
        foreach ($directories as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            
            $pluginDir = $this->pluginPath . '/' . $dir;
            
            if (!is_dir($pluginDir)) {
                continue;
            }
            
            $manifestFile = $pluginDir . '/plugin.json';
            
            if (file_exists($manifestFile)) {
                $plugins[] = [
                    'name' => $dir,
                    'path' => $pluginDir,
                    'manifest' => $manifestFile
                ];
            }
        }
        
        return $plugins;
    }
    
    private function migratePlugin(array $plugin): void
    {
        echo "Processing: {$plugin['name']}... ";
        
        try {
            // Read current manifest
            $content = file_get_contents($plugin['manifest']);
            $data = json_decode($content, true);
            
            if (!$data) {
                throw new Exception("Invalid JSON: " . json_last_error_msg());
            }
            
            // Create backup
            if (!$this->dryRun) {
                $backupFile = $plugin['manifest'] . '.backup.' . date('YmdHis');
                copy($plugin['manifest'], $backupFile);
                $this->backups[] = $backupFile;
            }
            
            // Migrate the structure
            $migrated = $this->migrateStructure($data, $plugin['path']);
            
            // Validate against schema
            $this->validateSchema($migrated);
            
            // Save migrated manifest
            if (!$this->dryRun) {
                file_put_contents(
                    $plugin['manifest'],
                    json_encode($migrated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                );
            }
            
            $this->migrated[] = $plugin['name'];
            printColored("✓ Migrated\n", COLOR_GREEN);
            
        } catch (Exception $e) {
            $this->errors[] = ['plugin' => $plugin['name'], 'error' => $e->getMessage()];
            printColored("✗ Error: {$e->getMessage()}\n", COLOR_RED);
        }
    }
    
    private function migrateStructure(array $data, string $pluginPath): array
    {
        $migrated = [];
        
        // Required fields
        $migrated['name'] = $data['name'] ?? throw new Exception("Missing 'name' field");
        $migrated['version'] = $data['version'] ?? throw new Exception("Missing 'version' field");
        $migrated['description'] = $data['description'] ?? throw new Exception("Missing 'description' field");
        
        // Migrate author
        $migrated['author'] = $this->migrateAuthor($data);
        
        // Optional fields
        if (isset($data['license'])) {
            $migrated['license'] = $data['license'];
        }
        
        if (isset($data['homepage'])) {
            $migrated['homepage'] = $data['homepage'];
        }
        
        if (isset($data['keywords'])) {
            $migrated['keywords'] = $data['keywords'];
        }
        
        // Migrate requirements
        $migrated['requirements'] = $this->migrateRequirements($data);
        
        // Migrate autoload
        if (isset($data['autoload'])) {
            $migrated['autoload'] = $data['autoload'];
        } elseif (isset($data['namespace'])) {
            // Create autoload from namespace
            $migrated['autoload'] = [
                'psr-4' => [
                    $data['namespace'] . '\\' => 'src/'
                ]
            ];
        }
        
        // Migrate bootstrap (main class definition)
        $migrated['bootstrap'] = $this->migrateBootstrap($data, $pluginPath);
        
        // Migrate provides
        if (isset($data['provides'])) {
            $migrated['provides'] = $data['provides'];
        }
        
        // Migrate permissions
        if (isset($data['permissions'])) {
            $migrated['permissions'] = $data['permissions'];
        }
        
        // Migrate hooks
        if (isset($data['hooks'])) {
            $migrated['hooks'] = $this->migrateHooks($data['hooks']);
        }
        
        // Migrate API
        if (isset($data['api_endpoints']) || isset($data['api'])) {
            $migrated['api'] = $this->migrateApi($data);
        }
        
        // Migrate database
        if (isset($data['database_tables']) || isset($data['database'])) {
            $migrated['database'] = $this->migrateDatabase($data);
        }
        
        // Migrate assets
        if (isset($data['assets'])) {
            $migrated['assets'] = $this->migrateAssets($data['assets']);
        }
        
        // Migrate config
        if (isset($data['config_schema']) || isset($data['config']) || isset($data['settings'])) {
            $migrated['config'] = $this->migrateConfig($data);
        }
        
        // Migrate widgets
        if (isset($data['widgets'])) {
            $migrated['widgets'] = $data['widgets'];
        }
        
        // Migrate scheduled tasks
        if (isset($data['cron_jobs']) || isset($data['scheduled_tasks'])) {
            $migrated['scheduled_tasks'] = $data['cron_jobs'] ?? $data['scheduled_tasks'];
        }
        
        // Migrate templates
        if (isset($data['templates'])) {
            $migrated['templates'] = $data['templates'];
        }
        
        // Migrate changelog
        if (isset($data['changelog'])) {
            $migrated['changelog'] = $data['changelog'];
        }
        
        return $migrated;
    }
    
    private function migrateAuthor(array $data): array
    {
        $author = $data['author'] ?? throw new Exception("Missing 'author' field");
        
        // If already an object with name, return as is
        if (is_array($author) && isset($author['name'])) {
            return $author;
        }
        
        // If string, convert to object
        if (is_string($author)) {
            return ['name' => $author];
        }
        
        // If object without name, try to extract
        if (is_array($author)) {
            return [
                'name' => $author['name'] ?? 'Unknown',
                'email' => $author['email'] ?? null,
                'url' => $author['url'] ?? null
            ];
        }
        
        throw new Exception("Invalid author format");
    }
    
    private function migrateRequirements(array $data): array
    {
        $requirements = [];
        
        // PHP version
        if (isset($data['php_version'])) {
            $requirements['php'] = $data['php_version'];
        } elseif (isset($data['requirements']['php'])) {
            $requirements['php'] = $data['requirements']['php'];
        } elseif (isset($data['requires']['php'])) {
            $requirements['php'] = $data['requires']['php'];
        } else {
            $requirements['php'] = '>=8.3';
        }
        
        // Core version
        if (isset($data['core_version'])) {
            $requirements['core'] = $data['core_version'];
        } elseif (isset($data['requirements']['core'])) {
            $requirements['core'] = $data['requirements']['core'];
        } elseif (isset($data['requirements']['shopologic'])) {
            $requirements['core'] = $data['requirements']['shopologic'];
        } else {
            $requirements['core'] = '>=1.0.0';
        }
        
        // Extensions
        if (isset($data['requirements']['extensions'])) {
            $requirements['extensions'] = $data['requirements']['extensions'];
        }
        
        // Dependencies
        $dependencies = [];
        
        if (isset($data['dependencies'])) {
            $dependencies = $data['dependencies'];
        } elseif (isset($data['requirements']['dependencies'])) {
            $dependencies = $data['requirements']['dependencies'];
        } elseif (isset($data['requires'])) {
            // Extract plugin dependencies from requires
            foreach ($data['requires'] as $key => $value) {
                if ($key !== 'php' && $key !== 'core' && $key !== 'shopologic') {
                    $dependencies[$key] = $value;
                }
            }
        }
        
        if (!empty($dependencies)) {
            $requirements['dependencies'] = $dependencies;
        }
        
        return $requirements;
    }
    
    private function migrateBootstrap(array $data, string $pluginPath): array
    {
        $bootstrap = [];
        
        // Try different fields for class definition
        if (isset($data['main'])) {
            // 'main' field points to file
            $bootstrap['file'] = $data['main'];
            $bootstrap['class'] = $this->extractClassFromFile($pluginPath . '/' . $data['main']);
        } elseif (isset($data['class']) && isset($data['file'])) {
            // Both class and file specified
            $bootstrap['class'] = $data['class'];
            $bootstrap['file'] = $data['file'];
        } elseif (isset($data['class'])) {
            // Only class specified, try to find file
            $bootstrap['class'] = $data['class'];
            $bootstrap['file'] = $this->findClassFile($pluginPath, $data['class']);
        } elseif (isset($data['main_class'])) {
            // main_class field
            $bootstrap['class'] = $data['main_class'];
            $bootstrap['file'] = $this->findClassFile($pluginPath, $data['main_class']);
        } elseif (isset($data['config']['main_class'])) {
            // Nested in config
            $bootstrap['class'] = $data['config']['main_class'];
            $bootstrap['file'] = $this->findClassFile($pluginPath, $data['config']['main_class']);
        } else {
            throw new Exception("No class definition found");
        }
        
        return $bootstrap;
    }
    
    private function migrateHooks(array $hooks): array
    {
        $migrated = [];
        
        // If already in correct format
        if (isset($hooks['actions']) || isset($hooks['filters'])) {
            return $hooks;
        }
        
        // Convert from flat array to categorized
        foreach ($hooks as $hook) {
            if (is_string($hook)) {
                // Simple string hook - guess type
                if (strpos($hook, 'filter') !== false || strpos($hook, 'modify') !== false) {
                    $migrated['filters'][] = $hook;
                } else {
                    $migrated['actions'][] = $hook;
                }
            } elseif (is_array($hook)) {
                // Object hook
                $type = $hook['type'] ?? 'action';
                unset($hook['type']);
                
                if ($type === 'filter') {
                    $migrated['filters'][] = $hook;
                } else {
                    $migrated['actions'][] = $hook;
                }
            }
        }
        
        return $migrated;
    }
    
    private function migrateApi(array $data): array
    {
        $api = [];
        
        if (isset($data['api_endpoints'])) {
            $endpoints = [];
            
            foreach ($data['api_endpoints'] as $endpoint) {
                if (is_string($endpoint)) {
                    // Parse string format "METHOD /path"
                    if (preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s+(.+)$/', $endpoint, $matches)) {
                        $endpoints[] = [
                            'method' => $matches[1],
                            'path' => $matches[2],
                            'handler' => 'handle' . ucfirst(strtolower($matches[1]))
                        ];
                    }
                } else {
                    $endpoints[] = $endpoint;
                }
            }
            
            $api['endpoints'] = $endpoints;
        } elseif (isset($data['api'])) {
            $api = $data['api'];
        }
        
        return $api;
    }
    
    private function migrateDatabase(array $data): array
    {
        $database = [];
        
        if (isset($data['database_tables'])) {
            $database['tables'] = $data['database_tables'];
        } elseif (isset($data['database']['tables'])) {
            $database = $data['database'];
        }
        
        if (!isset($database['migrations'])) {
            $database['migrations'] = 'migrations/';
        }
        
        return $database;
    }
    
    private function migrateAssets(array $assets): array
    {
        // Assets are already in a good format generally
        return $assets;
    }
    
    private function migrateConfig(array $data): array
    {
        $config = [];
        
        if (isset($data['config_schema'])) {
            $config['schema'] = $data['config_schema'];
        } elseif (isset($data['settings'])) {
            // Convert settings to schema
            $schema = [];
            foreach ($data['settings'] as $key => $setting) {
                if (is_array($setting)) {
                    $schema[$key] = $setting;
                } else {
                    $schema[$key] = [
                        'type' => gettype($setting),
                        'default' => $setting
                    ];
                }
            }
            $config['schema'] = $schema;
        } elseif (isset($data['config']['schema'])) {
            $config = $data['config'];
        }
        
        return $config;
    }
    
    private function extractClassFromFile(string $file): string
    {
        if (!file_exists($file)) {
            throw new Exception("File not found: $file");
        }
        
        $content = file_get_contents($file);
        
        // Extract namespace
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1] . '\\';
        }
        
        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $namespace . $matches[1];
        }
        
        throw new Exception("Could not extract class from file: $file");
    }
    
    private function findClassFile(string $pluginPath, string $className): string
    {
        // Extract simple class name
        $parts = explode('\\', $className);
        $simpleClassName = end($parts);
        
        // Common locations
        $locations = [
            $simpleClassName . '.php',
            'src/' . $simpleClassName . '.php',
            lcfirst($simpleClassName) . '.php',
            'src/' . lcfirst($simpleClassName) . '.php',
        ];
        
        foreach ($locations as $location) {
            if (file_exists($pluginPath . '/' . $location)) {
                return $location;
            }
        }
        
        // If not found, scan directory
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pluginPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                if (preg_match('/class\s+' . preg_quote($simpleClassName) . '\s/', $content)) {
                    return str_replace($pluginPath . '/', '', $file->getPathname());
                }
            }
        }
        
        throw new Exception("Could not find file for class: $className");
    }
    
    private function validateSchema(array $data): void
    {
        // Basic validation - in real implementation would use JSON Schema validator
        $required = ['name', 'version', 'description', 'author', 'bootstrap'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        if (!isset($data['bootstrap']['class'])) {
            throw new Exception("Missing bootstrap.class field");
        }
    }
    
    private function printSummary(): void
    {
        printHeader("Migration Summary");
        
        echo "Total plugins: " . (count($this->migrated) + count($this->errors)) . "\n";
        
        if (!empty($this->migrated)) {
            printColored("✓ Successfully migrated: " . count($this->migrated) . "\n", COLOR_GREEN);
            if (!$this->dryRun) {
                echo "  Backups created in original locations with .backup.* extension\n";
            }
        }
        
        if (!empty($this->errors)) {
            printColored("✗ Failed: " . count($this->errors) . "\n", COLOR_RED);
            echo "\nErrors:\n";
            foreach ($this->errors as $error) {
                echo "  - {$error['plugin']}: {$error['error']}\n";
            }
        }
        
        if ($this->dryRun) {
            echo "\n";
            printColored("This was a dry run. No files were modified.\n", COLOR_YELLOW);
            echo "Run without --dry-run to apply changes.\n";
        }
    }
}

// Parse command line arguments
$options = getopt('', ['dry-run', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Plugin Manifest Migration Tool

Usage: php cli/migrate-plugin-manifests.php [options]

Options:
    --dry-run    Show what would be changed without modifying files
    --help       Show this help message

This tool migrates all plugin.json files to the standardized format.

HELP;
    exit(0);
}

$pluginPath = dirname(__DIR__) . '/plugins';
$dryRun = isset($options['dry-run']);

try {
    $migrator = new PluginManifestMigrator($pluginPath, $dryRun);
    $migrator->migrate();
} catch (Exception $e) {
    printColored("Error: " . $e->getMessage() . "\n", COLOR_RED);
    exit(1);
}