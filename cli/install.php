<?php

declare(strict_types=1);

/**
 * Shopologic Installation Script
 * 
 * Sets up the platform for first-time use
 */

// Define root path
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Register autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->register();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\PSR', SHOPOLOGIC_ROOT . '/core/src/PSR');

echo "Shopologic Installation\n";
echo "======================\n\n";

try {
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.3.0', '<')) {
        throw new Exception("PHP 8.3.0 or higher is required. Current version: " . PHP_VERSION);
    }
    echo "✓ PHP version check passed (" . PHP_VERSION . ")\n";
    
    // Check for database extensions
    $availableDrivers = [];
    if (extension_loaded('pgsql')) {
        $availableDrivers[] = 'pgsql';
    }
    if (extension_loaded('mysqli')) {
        $availableDrivers[] = 'mysql';
    }
    
    if (empty($availableDrivers)) {
        throw new Exception("No database drivers found. Please install either pgsql or mysqli PHP extension.");
    }
    
    echo "✓ Available database drivers: " . implode(', ', $availableDrivers) . "\n";
    
    // Check other required extensions
    $requiredExtensions = ['json', 'mbstring', 'openssl', 'curl'];
    $missingExtensions = [];
    
    foreach ($requiredExtensions as $extension) {
        if (!extension_loaded($extension)) {
            $missingExtensions[] = $extension;
        }
    }
    
    if (!empty($missingExtensions)) {
        throw new Exception("Missing required PHP extensions: " . implode(', ', $missingExtensions));
    }
    echo "✓ Required PHP extensions check passed\n";
    
    // Create directories
    $directories = [
        'storage',
        'storage/cache',
        'storage/logs',
        'storage/sessions',
        'storage/uploads',
        'storage/temp',
        'storage/plugins',
        'database/migrations',
        'database/seeds',
        'database/schemas'
    ];
    
    foreach ($directories as $dir) {
        $path = SHOPOLOGIC_ROOT . '/' . $dir;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
    echo "✓ Directory structure created\n";
    
    // Set permissions
    $writableDirectories = [
        'storage',
        'storage/cache',
        'storage/logs',
        'storage/sessions',
        'storage/uploads',
        'storage/temp'
    ];
    
    foreach ($writableDirectories as $dir) {
        $path = SHOPOLOGIC_ROOT . '/' . $dir;
        if (is_dir($path)) {
            chmod($path, 0755);
        }
    }
    echo "✓ Permissions set correctly\n";
    
    // Check environment file
    $envExample = SHOPOLOGIC_ROOT . '/.env.example';
    $envFile = SHOPOLOGIC_ROOT . '/.env';
    
    if (!file_exists($envFile) && file_exists($envExample)) {
        copy($envExample, $envFile);
        echo "✓ Environment file created from example\n";
    } else if (file_exists($envFile)) {
        echo "✓ Environment file exists\n";
    } else {
        echo "⚠ No environment file found. Please create .env file manually\n";
    }
    
    // Load environment variables
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
                putenv(trim($key) . '=' . trim($value));
            }
        }
    }
    
    // Database configuration
    echo "\nDatabase Configuration\n";
    echo "---------------------\n";
    
    $dbConnection = getenv('DB_CONNECTION') ?: '';
    
    // If no connection specified, ask user
    if (!$dbConnection || !in_array($dbConnection, ['pgsql', 'mysql'])) {
        if (count($availableDrivers) === 1) {
            $dbConnection = $availableDrivers[0];
            echo "Using available database driver: $dbConnection\n";
        } else {
            echo "\nMultiple database drivers available. Please choose:\n";
            echo "1. PostgreSQL (pgsql)\n";
            echo "2. MySQL/MariaDB (mysql)\n";
            echo "\nEnter your choice (1 or 2): ";
            
            $choice = trim(fgets(STDIN));
            $dbConnection = $choice === '2' ? 'mysql' : 'pgsql';
        }
        
        updateEnvFile('DB_CONNECTION', $dbConnection);
    }
    
    // Test database connection
    if (getenv('DB_HOST') && getenv('DB_DATABASE')) {
        try {
            if ($dbConnection === 'pgsql') {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    getenv('DB_HOST'),
                    getenv('DB_PORT') ?: '5432',
                    getenv('DB_DATABASE')
                );
                
                $conn = pg_connect(sprintf(
                    "host=%s port=%s dbname=%s user=%s password=%s",
                    getenv('DB_HOST'),
                    getenv('DB_PORT') ?: '5432',
                    getenv('DB_DATABASE'),
                    getenv('DB_USERNAME'),
                    getenv('DB_PASSWORD')
                ));
                
                if ($conn) {
                    pg_close($conn);
                    echo "✓ PostgreSQL connection successful\n";
                } else {
                    throw new Exception("PostgreSQL connection failed");
                }
            } else {
                $mysqli = new mysqli(
                    getenv('DB_HOST') ?: 'localhost',
                    getenv('DB_USERNAME') ?: 'root',
                    getenv('DB_PASSWORD') ?: '',
                    getenv('DB_DATABASE') ?: '',
                    (int)(getenv('DB_PORT') ?: 3306)
                );
                
                if ($mysqli->connect_error) {
                    throw new Exception($mysqli->connect_error);
                }
                
                $mysqli->close();
                echo "✓ MySQL/MariaDB connection successful\n";
            }
        } catch (Exception $e) {
            echo "⚠ Database connection failed: " . $e->getMessage() . "\n";
            echo "  Please check your database configuration in .env file\n";
            
            // Provide database-specific help
            if ($dbConnection === 'pgsql') {
                echo "\n  PostgreSQL configuration example:\n";
                echo "    DB_CONNECTION=pgsql\n";
                echo "    DB_HOST=localhost\n";
                echo "    DB_PORT=5432\n";
                echo "    DB_DATABASE=shopologic\n";
                echo "    DB_USERNAME=postgres\n";
                echo "    DB_PASSWORD=your_password\n";
            } else {
                echo "\n  MySQL/MariaDB configuration example:\n";
                echo "    DB_CONNECTION=mysql\n";
                echo "    DB_HOST=127.0.0.1\n";
                echo "    DB_PORT=3306\n";
                echo "    DB_DATABASE=shopologic\n";
                echo "    DB_USERNAME=root\n";
                echo "    DB_PASSWORD=your_password\n";
            }
        }
    } else {
        echo "⚠ Database configuration not found in .env file\n";
    }
    
    // Generate application key if not exists
    $appKey = getenv('ENCRYPTION_KEY');
    if (!$appKey || $appKey === 'base64:your-32-byte-key') {
        $key = base64_encode(random_bytes(32));
        updateEnvFile('ENCRYPTION_KEY', 'base64:' . $key);
        echo "✓ Application encryption key generated\n";
    } else {
        echo "✓ Application encryption key exists\n";
    }
    
    // Generate JWT secret if not exists
    $jwtSecret = getenv('JWT_SECRET');
    if (!$jwtSecret || $jwtSecret === 'your-256-bit-secret') {
        $secret = bin2hex(random_bytes(32));
        updateEnvFile('JWT_SECRET', $secret);
        echo "✓ JWT secret generated\n";
    } else {
        echo "✓ JWT secret exists\n";
    }
    
    // Create initial plugin registry
    $pluginRegistry = SHOPOLOGIC_ROOT . '/storage/plugins/plugins.json';
    if (!file_exists($pluginRegistry)) {
        $initialPlugins = [
            'installed' => [],
            'active' => []
        ];
        file_put_contents($pluginRegistry, json_encode($initialPlugins, JSON_PRETTY_PRINT));
        echo "✓ Plugin registry initialized\n";
    }
    
    // Run migrations if database is available
    if (getenv('DB_HOST') && getenv('DB_DATABASE')) {
        echo "\nRunning database migrations...\n";
        $output = [];
        $returnCode = 0;
        exec('php ' . SHOPOLOGIC_ROOT . '/cli/migrate.php install', $output, $returnCode);
        
        if ($returnCode === 0) {
            exec('php ' . SHOPOLOGIC_ROOT . '/cli/migrate.php up', $output, $returnCode);
            if ($returnCode === 0) {
                echo "✓ Database migrations completed\n";
            } else {
                echo "⚠ Migration failed: " . implode("\n", $output) . "\n";
            }
        } else {
            echo "⚠ Migration system installation failed\n";
        }
    }
    
    // Installation complete
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "Installation Complete!\n";
    echo str_repeat('=', 50) . "\n";
    echo "Shopologic has been successfully installed.\n";
    echo "Database: " . strtoupper($dbConnection) . "\n\n";
    
    echo "Next steps:\n";
    echo "1. Configure your .env file with your database and service credentials\n";
    echo "2. Run 'php cli/migrate.php up' to set up the database schema\n";
    echo "3. Run 'php cli/seed.php run' to populate with sample data\n";
    echo "4. Start the development server: 'php -S localhost:8000 -t public/'\n";
    echo "5. Access your store at http://localhost:8000\n";
    echo "6. Access the admin panel at http://localhost:8000/admin\n\n";
    
    echo "For production deployment:\n";
    echo "1. Set APP_ENV=production in .env\n";
    echo "2. Set APP_DEBUG=false in .env\n";
    echo "3. Configure your web server to point to the public/ directory\n";
    echo "4. Set up SSL certificates for HTTPS\n";
    echo "5. Configure your cache and session drivers\n";
    echo "6. Set up cron jobs for scheduled tasks\n\n";
    
    // Database-specific recommendations
    if ($dbConnection === 'pgsql') {
        echo "PostgreSQL recommendations:\n";
        echo "- Enable query logging for development: log_statement = 'all'\n";
        echo "- Configure connection pooling for production\n";
        echo "- Set up streaming replication for high availability\n\n";
    } else {
        echo "MySQL/MariaDB recommendations:\n";
        echo "- Enable query logging for development: SET GLOBAL general_log = 1\n";
        echo "- Configure query cache for better performance\n";
        echo "- Set up master-slave replication for high availability\n";
        echo "- Ensure innodb_buffer_pool_size is properly configured\n\n";
    }
    
    echo "Documentation: https://docs.shopologic.com\n";
    echo "Support: https://github.com/shopologic/shopologic/issues\n";
    
} catch (Exception $e) {
    echo "\n❌ Installation failed: " . $e->getMessage() . "\n";
    
    if (getenv('APP_DEBUG') === 'true') {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
    exit(1);
}

/**
 * Update environment file with new value
 */
function updateEnvFile(string $key, string $value): void
{
    $envFile = SHOPOLOGIC_ROOT . '/.env';
    
    if (!file_exists($envFile)) {
        return;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES);
    $updated = false;
    
    foreach ($lines as $i => $line) {
        if (strpos($line, $key . '=') === 0) {
            $lines[$i] = $key . '=' . $value;
            $updated = true;
            break;
        }
    }
    
    if (!$updated) {
        $lines[] = $key . '=' . $value;
    }
    
    file_put_contents($envFile, implode("\n", $lines) . "\n");
}

/**
 * Check if running in CLI mode
 */
function isCliMode(): bool
{
    return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
}

// Ensure script is run from CLI
if (!isCliMode()) {
    echo "This script must be run from the command line.\n";
    exit(1);
}