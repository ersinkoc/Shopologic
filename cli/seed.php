<?php

declare(strict_types=1);

/**
 * Shopologic Database Seeder
 * 
 * Populates database with sample data for development and testing
 */

// Define root path
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Register autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\Core\Database\DatabaseManager;

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
    // Initialize database
    $config = new ConfigurationManager();
    $db = new DatabaseManager($config);
    
    // Parse command line arguments
    $command = $argv[1] ?? 'help';
    $arguments = array_slice($argv, 2);
    
    switch ($command) {
        case 'run':
            $seeder = $arguments[0] ?? 'all';
            echo "Running database seeders...\n";
            
            if ($seeder === 'all') {
                runAllSeeders($db);
            } else {
                runSeeder($db, $seeder);
            }
            
            echo "Database seeding completed successfully.\n";
            break;
            
        case 'fresh':
            echo "Refreshing database with fresh seed data...\n";
            truncateAllTables($db);
            runAllSeeders($db);
            echo "Fresh seeding completed successfully.\n";
            break;
            
        case 'create':
            $name = $arguments[0] ?? null;
            if (!$name) {
                echo "Error: Seeder name is required.\n";
                echo "Usage: php cli/seed.php create UsersSeeder\n";
                exit(1);
            }
            
            createSeeder($name);
            echo "Created seeder: {$name}\n";
            break;
            
        case 'list':
            echo "Available Seeders:\n";
            echo "==================\n";
            listSeeders();
            break;
            
        default:
            echo "Shopologic Database Seeder\n";
            echo "=========================\n\n";
            echo "Available commands:\n";
            echo "  run [seeder]     Run seeders (default: all)\n";
            echo "  fresh            Truncate tables and run fresh seeders\n";
            echo "  create <name>    Create new seeder class\n";
            echo "  list             List available seeders\n";
            echo "  help             Show this help message\n";
            break;
    }
    
} catch (Exception $e) {
    echo "Seeder Error: " . $e->getMessage() . "\n";
    
    if (getenv('APP_DEBUG') === 'true') {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
    exit(1);
}

/**
 * Run all seeders
 */
function runAllSeeders(DatabaseManager $db): void
{
    $seeders = [
        'UsersSeeder',
        'StoresSeeder',
        'CategoriesSeeder',
        'ProductsSeeder',
        'CustomersSeeder',
        'OrdersSeeder'
    ];
    
    foreach ($seeders as $seeder) {
        echo "Running {$seeder}...\n";
        runSeeder($db, $seeder);
    }
}

/**
 * Run specific seeder
 */
function runSeeder(DatabaseManager $db, string $seeder): void
{
    switch ($seeder) {
        case 'UsersSeeder':
            seedUsers($db);
            break;
            
        case 'StoresSeeder':
            seedStores($db);
            break;
            
        case 'CategoriesSeeder':
            seedCategories($db);
            break;
            
        case 'ProductsSeeder':
            seedProducts($db);
            break;
            
        case 'CustomersSeeder':
            seedCustomers($db);
            break;
            
        case 'OrdersSeeder':
            seedOrders($db);
            break;
            
        default:
            echo "Unknown seeder: {$seeder}\n";
    }
}

/**
 * Seed admin users
 */
function seedUsers(DatabaseManager $db): void
{
    $users = [
        [
            'name' => 'Admin User',
            'email' => 'admin@shopologic.local',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'name' => 'Store Manager',
            'email' => 'manager@shopologic.local',
            'password' => password_hash('manager123', PASSWORD_BCRYPT),
            'role' => 'manager',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    foreach ($users as $user) {
        $db->table('users')->insert($user);
    }
    
    echo "  Created " . count($users) . " admin users\n";
}

/**
 * Seed stores
 */
function seedStores(DatabaseManager $db): void
{
    $stores = [
        [
            'name' => 'Main Store',
            'domain' => 'localhost:17000',
            'code' => 'main',
            'is_active' => true,
            'settings' => json_encode([
                'currency' => 'USD',
                'locale' => 'en',
                'timezone' => 'UTC'
            ]),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'name' => 'EU Store',
            'domain' => 'eu.shopologic.local',
            'code' => 'eu',
            'is_active' => true,
            'settings' => json_encode([
                'currency' => 'EUR',
                'locale' => 'en',
                'timezone' => 'Europe/London'
            ]),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    foreach ($stores as $store) {
        $db->table('stores')->insert($store);
    }
    
    echo "  Created " . count($stores) . " stores\n";
}

/**
 * Seed product categories
 */
function seedCategories(DatabaseManager $db): void
{
    $categories = [
        [
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic devices and accessories',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'description' => 'Mobile phones and accessories',
            'parent_id' => 1,
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'name' => 'Laptops',
            'slug' => 'laptops',
            'description' => 'Portable computers',
            'parent_id' => 1,
            'is_active' => true,
            'sort_order' => 2,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'name' => 'Clothing',
            'slug' => 'clothing',
            'description' => 'Fashion and apparel',
            'is_active' => true,
            'sort_order' => 2,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    foreach ($categories as $category) {
        $db->table('categories')->insert($category);
    }
    
    echo "  Created " . count($categories) . " categories\n";
}

/**
 * Seed products
 */
function seedProducts(DatabaseManager $db): void
{
    $products = [
        [
            'name' => 'iPhone 15 Pro',
            'slug' => 'iphone-15-pro',
            'description' => 'Latest iPhone with titanium design',
            'short_description' => 'Premium smartphone',
            'sku' => 'APPLE-IP15P-128',
            'price' => 999.00,
            'compare_price' => 1099.00,
            'cost_price' => 700.00,
            'track_quantity' => true,
            'quantity' => 50,
            'weight' => 187,
            'is_active' => true,
            'category_id' => 2,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'name' => 'MacBook Pro 14"',
            'slug' => 'macbook-pro-14',
            'description' => 'Professional laptop with M3 chip',
            'short_description' => 'High-performance laptop',
            'sku' => 'APPLE-MBP14-512',
            'price' => 1999.00,
            'compare_price' => 2199.00,
            'cost_price' => 1400.00,
            'track_quantity' => true,
            'quantity' => 25,
            'weight' => 1600,
            'is_active' => true,
            'category_id' => 3,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'name' => 'Classic T-Shirt',
            'slug' => 'classic-t-shirt',
            'description' => 'Comfortable cotton t-shirt',
            'short_description' => 'Basic cotton tee',
            'sku' => 'TSHIRT-CLASSIC-M',
            'price' => 29.99,
            'cost_price' => 15.00,
            'track_quantity' => true,
            'quantity' => 100,
            'weight' => 200,
            'is_active' => true,
            'category_id' => 4,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    foreach ($products as $product) {
        $db->table('products')->insert($product);
    }
    
    echo "  Created " . count($products) . " products\n";
}

/**
 * Seed customers
 */
function seedCustomers(DatabaseManager $db): void
{
    $customers = [
        [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'date_of_birth' => '1990-01-15',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'phone' => '+1234567891',
            'date_of_birth' => '1985-06-20',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    foreach ($customers as $customer) {
        $db->table('customers')->insert($customer);
    }
    
    echo "  Created " . count($customers) . " customers\n";
}

/**
 * Seed orders
 */
function seedOrders(DatabaseManager $db): void
{
    $orders = [
        [
            'order_number' => 'ORD-001',
            'customer_id' => 1,
            'status' => 'completed',
            'subtotal' => 999.00,
            'tax_amount' => 79.92,
            'shipping_amount' => 9.99,
            'total_amount' => 1088.91,
            'currency' => 'USD',
            'billing_address' => json_encode([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address_1' => '123 Main St',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'US'
            ]),
            'shipping_address' => json_encode([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address_1' => '123 Main St',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'US'
            ]),
            'created_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-7 days'))
        ],
        [
            'order_number' => 'ORD-002',
            'customer_id' => 2,
            'status' => 'processing',
            'subtotal' => 1999.00,
            'tax_amount' => 159.92,
            'shipping_amount' => 0.00,
            'total_amount' => 2158.92,
            'currency' => 'USD',
            'billing_address' => json_encode([
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'address_1' => '456 Oak Ave',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'postal_code' => '90210',
                'country' => 'US'
            ]),
            'shipping_address' => json_encode([
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'address_1' => '456 Oak Ave',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'postal_code' => '90210',
                'country' => 'US'
            ]),
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ]
    ];
    
    foreach ($orders as $order) {
        $db->table('orders')->insert($order);
    }
    
    echo "  Created " . count($orders) . " orders\n";
}

/**
 * Truncate all tables
 */
function truncateAllTables(DatabaseManager $db): void
{
    $tables = [
        'order_items',
        'orders',
        'customers',
        'products',
        'categories',
        'stores',
        'users'
    ];
    
    foreach ($tables as $table) {
        try {
            $db->statement("TRUNCATE TABLE {$table} RESTART IDENTITY CASCADE");
        } catch (Exception $e) {
            // Table might not exist, continue
        }
    }
    
    echo "  Truncated all tables\n";
}

/**
 * List available seeders
 */
function listSeeders(): void
{
    $seeders = [
        'UsersSeeder' => 'Seed admin users',
        'StoresSeeder' => 'Seed store configurations',
        'CategoriesSeeder' => 'Seed product categories',
        'ProductsSeeder' => 'Seed sample products',
        'CustomersSeeder' => 'Seed customer accounts',
        'OrdersSeeder' => 'Seed sample orders'
    ];
    
    foreach ($seeders as $name => $description) {
        echo "  {$name}: {$description}\n";
    }
}

/**
 * Create a new seeder
 */
function createSeeder(string $name): void
{
    $seedersDir = SHOPOLOGIC_ROOT . '/database/seeds';
    
    if (!is_dir($seedersDir)) {
        mkdir($seedersDir, 0755, true);
    }
    
    $template = <<<PHP
<?php

declare(strict_types=1);

/**
 * {$name} - Database Seeder
 */

class {$name}
{
    public function run(DatabaseManager \$db): void
    {
        // Add your seeding logic here
        
        echo "  Seeded data from {$name}\\n";
    }
}
PHP;
    
    file_put_contents($seedersDir . '/' . $name . '.php', $template);
}