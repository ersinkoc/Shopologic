<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Drivers;

use Shopologic\Core\Database\ResultInterface;
use Shopologic\Core\Database\Result;

class MockDriver implements DatabaseDriverInterface
{
    protected bool $connected = false;
    protected array $config = [];
    protected bool $inTransaction = false;
    protected array $tables = [];
    protected int $lastInsertId = 0;

    public function connect(array $config): void
    {
        $this->config = $config;
        $this->connected = true;
        
        // Initialize some mock tables
        $this->tables = [
            'migrations' => [],
            'users' => [],
            'products' => [],
            'categories' => [],
            'orders' => [],
        ];
    }

    public function disconnect(): void
    {
        $this->connected = false;
        $this->tables = [];
    }

    public function query(string $query, array $params = []): ResultInterface
    {
        $this->ensureConnected();
        
        // Mock query execution - return empty result set
        $mockData = [];
        
        // Handle SELECT queries with some mock data
        if (stripos($query, 'SELECT') === 0) {
            if (stripos($query, 'migrations') !== false) {
                $mockData = $this->getMockMigrations();
            } elseif (stripos($query, 'users') !== false) {
                $mockData = $this->getMockUsers();
            } elseif (stripos($query, 'products') !== false) {
                $mockData = $this->getMockProducts();
            }
        }
        
        return new Result($mockData);
    }

    public function select(string $query, array $params = []): array
    {
        $result = $this->query($query, $params);
        return $result->fetchAll();
    }

    public function insert(string $query, array $params = []): bool
    {
        $this->ensureConnected();
        $this->lastInsertId++;
        
        // Mock insert - always successful
        return true;
    }

    public function update(string $query, array $params = []): int
    {
        $this->ensureConnected();
        // Mock update - return affected rows count
        return 1;
    }

    public function delete(string $query, array $params = []): int
    {
        $this->ensureConnected();
        // Mock delete - return affected rows count
        return 1;
    }

    public function statement(string $query): bool
    {
        $this->ensureConnected();
        
        // Mock DDL statements (CREATE, ALTER, DROP)
        if (stripos($query, 'CREATE TABLE') === 0) {
            // Extract table name and add to mock tables
            if (preg_match('/CREATE TABLE\s+(\w+)/i', $query, $matches)) {
                $tableName = $matches[1];
                $this->tables[$tableName] = [];
            }
        }
        
        return true;
    }

    public function lastInsertId(?string $sequence = null): int
    {
        return $this->lastInsertId;
    }

    public function beginTransaction(): bool
    {
        $this->ensureConnected();
        $this->inTransaction = true;
        return true;
    }

    public function commit(): bool
    {
        $this->ensureConnected();
        $this->inTransaction = false;
        return true;
    }

    public function rollback(): bool
    {
        $this->ensureConnected();
        $this->inTransaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function getDriverName(): string
    {
        return 'mock';
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function execute(string $query, array $params = []): int
    {
        $this->statement($query);
        return 1;
    }

    public function prepare(string $query)
    {
        $this->ensureConnected();
        // Return a mock prepared statement
        return new class {
            public function execute(array $params = []): bool {
                return true;
            }
        };
    }

    public function savepoint(string $name): bool
    {
        return true;
    }

    public function releaseSavepoint(string $name): bool
    {
        return true;
    }

    public function rollbackToSavepoint(string $name): bool
    {
        return true;
    }

    public function quote(string $value, int $type = 0): string
    {
        return "'" . addslashes($value) . "'";
    }

    public function getVersion(): string
    {
        return 'Mock Database 1.0.0';
    }

    public function getInfo(): array
    {
        return [
            'driver' => 'mock',
            'version' => $this->getVersion(),
            'database' => $this->config['database'] ?? 'mock',
        ];
    }

    public function ping(): bool
    {
        return $this->connected;
    }

    public function reconnect(): bool
    {
        $this->disconnect();
        $this->connect($this->config);
        return true;
    }

    public function getConnection()
    {
        return $this;
    }

    public function setAttributes(array $attributes): void
    {
        // No-op for mock driver
    }

    public function errorInfo(): array
    {
        return [0, 'No error'];
    }

    public function errorMessage(): ?string
    {
        return null;
    }

    public function errorCode()
    {
        return 0;
    }

    public function escape(string $value): string
    {
        return addslashes($value);
    }

    public function getServerVersion(): string
    {
        return $this->getVersion();
    }

    protected function ensureConnected(): void
    {
        if (!$this->connected) {
            throw new \RuntimeException('No mock database connection established');
        }
    }

    protected function getMockMigrations(): array
    {
        return [
            ['id' => 1, 'migration' => '2024_01_01_000001_create_users_table', 'batch' => 1],
            ['id' => 2, 'migration' => '2024_01_01_000002_create_categories_table', 'batch' => 1],
            ['id' => 3, 'migration' => '2024_01_01_000003_create_products_table', 'batch' => 1],
        ];
    }

    protected function getMockUsers(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Admin User',
                'email' => 'admin@shopologic.com',
                'email_verified_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 2,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'email_verified_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    protected function getMockProducts(): array
    {
        return [
            [
                'id' => 1,
                'sku' => 'LAPTOP-001',
                'name' => 'Gaming Laptop Pro',
                'slug' => 'gaming-laptop-pro',
                'description' => 'High-performance gaming laptop with RTX graphics',
                'price' => 1299.99,
                'cost' => 899.99,
                'stock_quantity' => 25,
                'is_active' => 1,
                'category_id' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 2,
                'sku' => 'PHONE-001',
                'name' => 'Smartphone X',
                'slug' => 'smartphone-x',
                'description' => 'Latest smartphone with advanced camera system',
                'price' => 899.99,
                'cost' => 599.99,
                'stock_quantity' => 50,
                'is_active' => 1,
                'category_id' => 2,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
}