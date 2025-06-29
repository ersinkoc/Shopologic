<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\Core\Database\Drivers\DatabaseDriverInterface;
use Shopologic\Core\Database\Drivers\PostgreSQLDriver;
use Shopologic\Core\Database\Drivers\MySQLDriver;
use Shopologic\Core\Database\Drivers\SQLiteDriver;

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

    public function transaction(callable $callback, ?string $connection = null)
    {
        $conn = $this->connection($connection);
        
        $conn->beginTransaction();
        
        try {
            $result = $callback($conn);
            $conn->commit();
            return $result;
        } catch (\Exception $e) {
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
        $driver = $this->createDriver($config);
        
        return new DatabaseConnection($driver, $config);
    }

    protected function createDriver(array $config): DatabaseDriverInterface
    {
        switch ($config['driver']) {
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