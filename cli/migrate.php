<?php

declare(strict_types=1);

/**
 * Shopologic Database Migration Tool
 * 
 * Handles database migrations for schema management
 */

// Define root path
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Register autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\Core\Database\DatabaseManager;
use Shopologic\Core\Database\Migrations\Migrator;

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->register();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');

// Load helper functions
require_once SHOPOLOGIC_ROOT . '/core/src/helpers.php';

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
    // Initialize configuration and database
    $config = new ConfigurationManager();
    $db = new DatabaseManager($config);
    $migrator = new Migrator($db, SHOPOLOGIC_ROOT . '/database/migrations');
    
    // Parse command line arguments
    $command = $argv[1] ?? 'help';
    $arguments = array_slice($argv, 2);
    
    switch ($command) {
        case 'up':
            echo "Running migrations...\n";
            $migrator->up();
            echo "Migrations completed successfully.\n";
            break;
            
        case 'down':
            $steps = (int)($arguments[0] ?? 1);
            echo "Rolling back {$steps} migration(s)...\n";
            $migrator->down($steps);
            echo "Rollback completed successfully.\n";
            break;
            
        case 'reset':
            echo "Resetting all migrations...\n";
            $migrator->reset();
            echo "Reset completed successfully.\n";
            break;
            
        case 'fresh':
            echo "Dropping all tables and running migrations...\n";
            $migrator->fresh();
            echo "Fresh migration completed successfully.\n";
            break;
            
        case 'refresh':
            $steps = (int)($arguments[0] ?? null);
            echo "Refreshing migrations...\n";
            $migrator->refresh($steps);
            echo "Refresh completed successfully.\n";
            break;
            
        case 'status':
            echo "Migration Status:\n";
            echo "================\n";
            $status = $migrator->status();
            foreach ($status as $migration => $ran) {
                $status = $ran ? 'Ran' : 'Pending';
                echo sprintf("%-50s %s\n", $migration, $status);
            }
            break;
            
        case 'create':
            $name = $arguments[0] ?? null;
            if (!$name) {
                echo "Error: Migration name is required.\n";
                echo "Usage: php cli/migrate.php create CreateUsersTable\n";
                exit(1);
            }
            
            $filename = $migrator->create($name);
            echo "Created migration: {$filename}\n";
            break;
            
        case 'install':
            echo "Installing migration system...\n";
            $migrator->install();
            echo "Migration system installed successfully.\n";
            break;
            
        default:
            echo "Shopologic Migration Tool\n";
            echo "=======================\n\n";
            echo "Available commands:\n";
            echo "  up              Run pending migrations\n";
            echo "  down [steps]    Rollback migrations (default: 1)\n";
            echo "  reset           Rollback all migrations\n";
            echo "  fresh           Drop all tables and run migrations\n";
            echo "  refresh [steps] Rollback and re-run migrations\n";
            echo "  status          Show migration status\n";
            echo "  create <name>   Create new migration file\n";
            echo "  install         Install migration system\n";
            echo "  help            Show this help message\n";
            break;
    }
    
} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
    
    if (getenv('APP_DEBUG') === 'true') {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
    exit(1);
}