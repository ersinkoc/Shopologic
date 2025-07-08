<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Connections;

use Shopologic\Core\Database\ConnectionInterface;
use Shopologic\Core\Database\DatabaseException;

/**
 * PostgreSQL Connection Pool
 * 
 * Manages a pool of PostgreSQL connections for improved performance
 * and resource management
 */
class PostgreSQLConnectionPool
{
    private array $connections = [];
    private array $activeConnections = [];
    private array $idleConnections = [];
    private array $config;
    private int $maxConnections = 10;
    private int $minConnections = 1;
    private int $maxIdleTime = 300; // 5 minutes
    private int $maxLifetime = 3600; // 1 hour
    private bool $initialized = false;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->maxConnections = $config['pool_size'] ?? 10;
        $this->minConnections = $config['min_connections'] ?? 1;
        $this->maxIdleTime = $config['max_idle_time'] ?? 300;
        $this->maxLifetime = $config['max_lifetime'] ?? 3600;
    }
    
    /**
     * Initialize the connection pool
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        
        // Create minimum number of connections
        for ($i = 0; $i < $this->minConnections; $i++) {
            $connection = $this->createConnection();
            $this->idleConnections[] = $connection;
        }
        
        $this->initialized = true;
    }
    
    /**
     * Get a connection from the pool
     */
    public function getConnection(): PooledConnection
    {
        $this->initialize();
        $this->cleanupConnections();
        
        // Try to get an idle connection
        if (!empty($this->idleConnections)) {
            $connection = array_shift($this->idleConnections);
            
            // Validate connection is still alive
            if ($connection->isAlive()) {
                $this->activeConnections[$connection->getId()] = $connection;
                return $connection;
            } else {
                // Connection is dead, remove it
                unset($this->connections[$connection->getId()]);
            }
        }
        
        // Create new connection if under limit
        if (count($this->connections) < $this->maxConnections) {
            $connection = $this->createConnection();
            $this->activeConnections[$connection->getId()] = $connection;
            return $connection;
        }
        
        // Wait for a connection to become available
        return $this->waitForConnection();
    }
    
    /**
     * Release a connection back to the pool
     */
    public function releaseConnection(PooledConnection $connection): void
    {
        $id = $connection->getId();
        
        if (!isset($this->activeConnections[$id])) {
            return;
        }
        
        unset($this->activeConnections[$id]);
        
        // Check if connection is still valid
        if ($connection->isAlive() && !$connection->hasExceededLifetime($this->maxLifetime)) {
            $connection->reset();
            $this->idleConnections[] = $connection;
        } else {
            // Remove expired connection
            $connection->close();
            unset($this->connections[$id]);
            
            // Maintain minimum connections
            if (count($this->connections) < $this->minConnections) {
                $newConnection = $this->createConnection();
                $this->idleConnections[] = $newConnection;
            }
        }
    }
    
    /**
     * Create a new pooled connection
     */
    private function createConnection(): PooledConnection
    {
        $connection = new PooledConnection($this->config);
        $connection->connect();
        
        $id = $connection->getId();
        $this->connections[$id] = $connection;
        
        return $connection;
    }
    
    /**
     * Wait for a connection to become available
     */
    private function waitForConnection(float $timeout = 5.0): PooledConnection
    {
        $startTime = microtime(true);
        
        while ((microtime(true) - $startTime) < $timeout) {
            // Check if any connections became idle
            $this->cleanupConnections();
            
            if (!empty($this->idleConnections)) {
                return $this->getConnection();
            }
            
            // Sleep for a short time before checking again
            usleep(10000); // 10ms
        }
        
        throw new DatabaseException("Connection pool exhausted - timeout waiting for available connection");
    }
    
    /**
     * Clean up expired connections
     */
    private function cleanupConnections(): void
    {
        $now = time();
        
        // Check idle connections
        foreach ($this->idleConnections as $key => $connection) {
            if ($connection->getIdleTime() > $this->maxIdleTime || 
                $connection->hasExceededLifetime($this->maxLifetime)) {
                unset($this->idleConnections[$key]);
                unset($this->connections[$connection->getId()]);
                $connection->close();
            }
        }
        
        // Reindex array
        $this->idleConnections = array_values($this->idleConnections);
    }
    
    /**
     * Close all connections in the pool
     */
    public function close(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
        
        $this->connections = [];
        $this->activeConnections = [];
        $this->idleConnections = [];
        $this->initialized = false;
    }
    
    /**
     * Get pool statistics
     */
    public function getStats(): array
    {
        return [
            'total' => count($this->connections),
            'active' => count($this->activeConnections),
            'idle' => count($this->idleConnections),
            'max_connections' => $this->maxConnections,
            'min_connections' => $this->minConnections,
        ];
    }
}

/**
 * Pooled PostgreSQL Connection
 * 
 * Wrapper around a PostgreSQL connection with pooling metadata
 */
class PooledConnection
{
    private $connection;
    private array $config;
    private string $id;
    private float $createdAt;
    private float $lastUsedAt;
    private int $useCount = 0;
    private array $preparedStatements = [];
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->id = uniqid('pg_', true);
        $this->createdAt = microtime(true);
        $this->lastUsedAt = microtime(true);
    }
    
    /**
     * Connect to PostgreSQL
     */
    public function connect(): void
    {
        $dsn = $this->buildDsn();
        
        // Use persistent connections if configured
        if ($this->config['persistent'] ?? false) {
            $this->connection = pg_pconnect($dsn);
        } else {
            $this->connection = pg_connect($dsn);
        }
        
        if (!$this->connection) {
            throw new DatabaseException('Failed to connect to PostgreSQL database');
        }
        
        // Set connection encoding
        if (isset($this->config['charset'])) {
            pg_set_client_encoding($this->connection, $this->config['charset']);
        }
        
        // Set search path if configured
        if (isset($this->config['search_path'])) {
            pg_query($this->connection, "SET search_path TO " . $this->config['search_path']);
        }
        
        // Set application name for monitoring
        if (isset($this->config['application_name'])) {
            pg_query($this->connection, "SET application_name = '" . pg_escape_string($this->connection, $this->config['application_name']) . "'");
        }
    }
    
    /**
     * Get the raw connection resource
     */
    public function getResource()
    {
        $this->lastUsedAt = microtime(true);
        $this->useCount++;
        return $this->connection;
    }
    
    /**
     * Check if connection is alive
     */
    public function isAlive(): bool
    {
        if (!$this->connection) {
            return false;
        }
        
        // Ping the connection
        $result = @pg_ping($this->connection);
        
        if (!$result) {
            // Try to reconnect once
            try {
                $this->reconnect();
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Reconnect to the database
     */
    private function reconnect(): void
    {
        if ($this->connection) {
            @pg_close($this->connection);
        }
        
        $this->preparedStatements = [];
        $this->connect();
    }
    
    /**
     * Reset connection state for reuse
     */
    public function reset(): void
    {
        if (!$this->connection) {
            return;
        }
        
        // Clear any pending results
        while ($result = pg_get_result($this->connection)) {
            pg_free_result($result);
        }
        
        // Reset any session settings if needed
        if (isset($this->config['reset_query'])) {
            pg_query($this->connection, $this->config['reset_query']);
        }
        
        $this->lastUsedAt = microtime(true);
    }
    
    /**
     * Close the connection
     */
    public function close(): void
    {
        if ($this->connection) {
            // Deallocate prepared statements
            foreach ($this->preparedStatements as $name => $sql) {
                @pg_query($this->connection, "DEALLOCATE " . $name);
            }
            
            @pg_close($this->connection);
            $this->connection = null;
            $this->preparedStatements = [];
        }
    }
    
    /**
     * Get connection ID
     */
    public function getId(): string
    {
        return $this->id;
    }
    
    /**
     * Get idle time in seconds
     */
    public function getIdleTime(): float
    {
        return microtime(true) - $this->lastUsedAt;
    }
    
    /**
     * Check if connection has exceeded its lifetime
     */
    public function hasExceededLifetime(int $maxLifetime): bool
    {
        return (microtime(true) - $this->createdAt) > $maxLifetime;
    }
    
    /**
     * Get connection statistics
     */
    public function getStats(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->createdAt,
            'last_used_at' => $this->lastUsedAt,
            'use_count' => $this->useCount,
            'idle_time' => $this->getIdleTime(),
            'age' => microtime(true) - $this->createdAt,
        ];
    }
    
    /**
     * Build DSN string
     */
    private function buildDsn(): string
    {
        $parts = [];
        
        if (isset($this->config['host'])) {
            $parts[] = 'host=' . $this->config['host'];
        }
        
        if (isset($this->config['port'])) {
            $parts[] = 'port=' . $this->config['port'];
        }
        
        if (isset($this->config['database'])) {
            $parts[] = 'dbname=' . $this->config['database'];
        }
        
        if (isset($this->config['username'])) {
            $parts[] = 'user=' . $this->config['username'];
        }
        
        if (isset($this->config['password'])) {
            $parts[] = 'password=' . $this->config['password'];
        }
        
        if (isset($this->config['sslmode'])) {
            $parts[] = 'sslmode=' . $this->config['sslmode'];
        }
        
        if (isset($this->config['connect_timeout'])) {
            $parts[] = 'connect_timeout=' . $this->config['connect_timeout'];
        }
        
        if (isset($this->config['options'])) {
            $parts[] = 'options=' . $this->config['options'];
        }
        
        return implode(' ', $parts);
    }
}