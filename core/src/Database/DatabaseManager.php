<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\Core\Database\Drivers\DatabaseDriverInterface;
use Shopologic\Core\Database\Drivers\PostgreSQLDriver;
use Shopologic\Core\Database\Drivers\MySQLDriver;
use Shopologic\Core\Database\Drivers\SQLiteDriver;
use Shopologic\Core\Database\Drivers\MockDriver;
use Shopologic\Core\Database\Connections\EnhancedPostgreSQLConnection;
use Shopologic\Core\Database\Connections\PostgreSQLConnectionPool;
use Shopologic\Core\Database\Connections\MasterSlaveConnection;

class DatabaseManager
{
    private ConfigurationManager $config;
    private array $connections = [];
    private array $drivers = [];
    private array $reconnectors = [];

    public function __construct(ConfigurationManager $config)
    {
        $this->config = $config;
    }

    public function connection(?string $name = null): DatabaseConnection
    {
        $name = $name ?: $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    public function getDefaultConnection(): string
    {
        return $this->config->get('database.default', 'pgsql');
    }

    public function setDefaultConnection(string $name): void
    {
        $this->config->set('database.default', $name);
    }

    public function reconnect(?string $name = null): DatabaseConnection
    {
        $name = $name ?: $this->getDefaultConnection();
        
        $this->disconnect($name);
        
        if (isset($this->reconnectors[$name])) {
            return $this->reconnectors[$name]();
        }

        return $this->connection($name);
    }

    public function disconnect(?string $name = null): void
    {
        $name = $name ?: $this->getDefaultConnection();
        
        if (isset($this->connections[$name])) {
            $this->connections[$name]->disconnect();
            unset($this->connections[$name]);
        }
        
        if (isset($this->drivers[$name])) {
            unset($this->drivers[$name]);
        }
    }

    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        return $this->connection($connection)->table($table);
    }

    public function raw(string $value): Expression
    {
        return new Expression($value);
    }

    public function beginTransaction(?string $connection = null): bool
    {
        return $this->connection($connection)->beginTransaction();
    }

    public function commit(?string $connection = null): bool
    {
        return $this->connection($connection)->commit();
    }

    public function rollback(?string $connection = null): bool
    {
        return $this->connection($connection)->rollback();
    }

    /**
     * Execute callback in database transaction
     * BUG-ERR-010 FIX: Now catches \Throwable instead of just \Exception
     */
    public function transaction(callable $callback, ?string $connection = null)
    {
        $conn = $this->connection($connection);

        $conn->beginTransaction();

        try {
            $result = $callback($conn);
            $conn->commit();
            return $result;
        } catch (\Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    public function getDriverName(?string $connection = null): string
    {
        return $this->connection($connection)->getDriverName();
    }

    protected function makeConnection(string $name): DatabaseConnection
    {
        $config = $this->getConnectionConfig($name);
        
        // Check for master-slave configuration
        if (isset($config['read']) || isset($config['write'])) {
            return $this->createMasterSlaveConnection($name, $config);
        }
        
        // Check for connection pooling
        if (($config['use_pool'] ?? false) && $config['driver'] === 'pgsql') {
            return $this->createPooledConnection($config);
        }
        
        $driver = $this->createDriver($config);
        
        // Use enhanced PostgreSQL connection if available
        if ($config['driver'] === 'pgsql' && ($config['enhanced'] ?? true)) {
            $connection = new EnhancedPostgreSQLConnection($config);
            return new DatabaseConnection($connection, $config);
        }
        
        return new DatabaseConnection($driver, $config);
    }

    protected function createDriver(array $config): DatabaseDriverInterface
    {
        switch ($config['driver']) {
            case 'mock':
                return new MockDriver();
                
            case 'pgsql':
            case 'postgresql':
                return new PostgreSQLDriver();
                
            case 'mysql':
            case 'mariadb':
                return new MySQLDriver();
                
            case 'sqlite':
                return new SQLiteDriver();
                
            default:
                throw new DatabaseException("Unsupported database driver: {$config['driver']}");
        }
    }

    protected function getConnectionConfig(string $name): array
    {
        $connections = $this->config->get('database.connections', []);
        
        if (!isset($connections[$name])) {
            throw new DatabaseException("Database connection [{$name}] not configured.");
        }

        $config = $connections[$name];
        
        // Handle read/write configuration
        if (isset($config['read']) || isset($config['write'])) {
            $config = $this->mergeReadWriteConfig($config);
        }
        
        return $config;
    }

    protected function mergeReadWriteConfig(array $config): array
    {
        // For now, return write config as default
        // In future, can implement read/write splitting
        if (isset($config['write'])) {
            return array_merge($config, $config['write']);
        }
        
        return $config;
    }
    
    /**
     * Create a master-slave connection
     */
    protected function createMasterSlaveConnection(string $name, array $config): DatabaseConnection
    {
        // Create master connection
        $masterConfig = array_merge($config, $config['write'] ?? []);
        unset($masterConfig['read'], $masterConfig['write']);
        $master = $this->createSingleConnection($masterConfig);
        
        // Create slave connections
        $slaves = [];
        $readConfigs = isset($config['read']['host']) ? [$config['read']] : $config['read'];
        
        foreach ($readConfigs as $readConfig) {
            $slaveConfig = array_merge($config, $readConfig);
            unset($slaveConfig['read'], $slaveConfig['write']);
            $slaves[] = $this->createSingleConnection($slaveConfig);
        }
        
        // Create master-slave connection
        $connection = new MasterSlaveConnection($master, $slaves, $config);
        
        return new DatabaseConnection($connection, $config);
    }
    
    /**
     * Create a pooled connection
     */
    protected function createPooledConnection(array $config): DatabaseConnection
    {
        $pool = new PostgreSQLConnectionPool($config);
        $pool->initialize();
        
        // Create a connection that uses the pool
        $connection = new EnhancedPostgreSQLConnection($config, $pool);
        
        return new DatabaseConnection($connection, $config);
    }
    
    /**
     * Create a single connection instance
     */
    protected function createSingleConnection(array $config): ConnectionInterface
    {
        if ($config['driver'] === 'pgsql' && ($config['enhanced'] ?? true)) {
            return new EnhancedPostgreSQLConnection($config);
        }
        
        // Fall back to driver-based connection
        $driver = $this->createDriver($config);
        return $driver->connect($config);
    }

    public function extend(string $name, callable $resolver): void
    {
        $this->reconnectors[$name] = $resolver;
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    public function purge(?string $name = null): void
    {
        $name = $name ?: $this->getDefaultConnection();
        
        $this->disconnect($name);
        unset($this->reconnectors[$name]);
    }

    /**
     * Enable query logging
     */
    public function enableQueryLog(?string $connection = null): void
    {
        $this->connection($connection)->enableQueryLog();
    }

    /**
     * Disable query logging
     */
    public function disableQueryLog(?string $connection = null): void
    {
        $this->connection($connection)->disableQueryLog();
    }

    /**
     * Get query log
     */
    public function getQueryLog(?string $connection = null): array
    {
        return $this->connection($connection)->getQueryLog();
    }
}