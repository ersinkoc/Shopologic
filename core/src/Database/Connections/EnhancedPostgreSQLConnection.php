<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Connections;

use Shopologic\Core\Database\PostgreSQLConnection;
use Shopologic\Core\Database\DatabaseException;
use Shopologic\Core\Database\ResultInterface;
use Shopologic\Core\Database\StatementInterface;

/**
 * Enhanced PostgreSQL Connection
 * 
 * Extends the base PostgreSQL connection with advanced features:
 * - Savepoint support for nested transactions
 * - Connection retry logic with exponential backoff
 * - Query performance monitoring
 * - Health checking and failover
 * - Prepared statement caching
 */
class EnhancedPostgreSQLConnection extends PostgreSQLConnection
{
    private ?PostgreSQLConnectionPool $pool = null;
    private array $savepoints = [];
    private int $savepointLevel = 0;
    private array $preparedStatements = [];
    private bool $queryLogging = false;
    private array $queryLog = [];
    private array $performanceMetrics = [];
    private int $retryAttempts = 3;
    private float $retryDelay = 0.1; // 100ms
    private float $retryMultiplier = 2.0;
    private ?callable $healthCheck = null;
    private ?string $connectionId;
    
    public function __construct(array $config, ?PostgreSQLConnectionPool $pool = null)
    {
        parent::__construct($config);
        $this->pool = $pool;
        $this->connectionId = uniqid('conn_', true);
        
        // Configure retry settings
        if (isset($config['retry_attempts'])) {
            $this->retryAttempts = (int) $config['retry_attempts'];
        }
        if (isset($config['retry_delay'])) {
            $this->retryDelay = (float) $config['retry_delay'];
        }
    }
    
    /**
     * Execute a query with retry logic
     */
    public function query(string $sql, array $bindings = []): ResultInterface
    {
        return $this->executeWithRetry(function() use ($sql, $bindings) {
            $startTime = microtime(true);
            
            try {
                $result = parent::query($sql, $bindings);
                
                // Log query if enabled
                if ($this->queryLogging) {
                    $this->logQuery($sql, $bindings, microtime(true) - $startTime);
                }
                
                // Track performance metrics
                $this->trackPerformance($sql, microtime(true) - $startTime);
                
                return $result;
                
            } catch (DatabaseException $e) {
                // Log failed query
                if ($this->queryLogging) {
                    $this->logQuery($sql, $bindings, microtime(true) - $startTime, $e);
                }
                
                throw $e;
            }
        });
    }
    
    /**
     * Begin a transaction with savepoint support
     */
    public function beginTransaction(): bool
    {
        $this->connect();
        
        if ($this->savepointLevel === 0) {
            // Start a real transaction
            $result = parent::beginTransaction();
            if ($result) {
                $this->savepointLevel = 1;
            }
            return $result;
        } else {
            // Create a savepoint
            $savepoint = 'sp_' . $this->savepointLevel;
            $this->query("SAVEPOINT {$savepoint}");
            $this->savepoints[] = $savepoint;
            $this->savepointLevel++;
            return true;
        }
    }
    
    /**
     * Commit a transaction or release a savepoint
     */
    public function commit(): bool
    {
        if ($this->savepointLevel === 0) {
            return false;
        }
        
        if ($this->savepointLevel === 1) {
            // Commit the real transaction
            $result = parent::commit();
            if ($result) {
                $this->savepointLevel = 0;
                $this->savepoints = [];
            }
            return $result;
        } else {
            // Release the savepoint
            $savepoint = array_pop($this->savepoints);
            $this->query("RELEASE SAVEPOINT {$savepoint}");
            $this->savepointLevel--;
            return true;
        }
    }
    
    /**
     * Rollback a transaction or to a savepoint
     */
    public function rollback(): bool
    {
        if ($this->savepointLevel === 0) {
            return false;
        }
        
        if ($this->savepointLevel === 1) {
            // Rollback the real transaction
            $result = parent::rollback();
            if ($result) {
                $this->savepointLevel = 0;
                $this->savepoints = [];
            }
            return $result;
        } else {
            // Rollback to the savepoint
            $savepoint = array_pop($this->savepoints);
            $this->query("ROLLBACK TO SAVEPOINT {$savepoint}");
            $this->savepointLevel--;
            return true;
        }
    }
    
    /**
     * Get the current transaction nesting level
     */
    public function getTransactionLevel(): int
    {
        return $this->savepointLevel;
    }
    
    /**
     * Prepare a statement with caching
     */
    public function prepare(string $sql): StatementInterface
    {
        $hash = md5($sql);
        
        if (!isset($this->preparedStatements[$hash])) {
            $this->preparedStatements[$hash] = parent::prepare($sql);
        }
        
        return $this->preparedStatements[$hash];
    }
    
    /**
     * Execute with retry logic
     */
    private function executeWithRetry(callable $callback)
    {
        $lastException = null;
        $delay = $this->retryDelay;
        
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                // Check connection health before executing
                if ($this->healthCheck && !call_user_func($this->healthCheck, $this)) {
                    throw new DatabaseException("Connection health check failed");
                }
                
                return $callback();
                
            } catch (DatabaseException $e) {
                $lastException = $e;
                
                // Check if error is retryable
                if (!$this->isRetryableError($e)) {
                    throw $e;
                }
                
                // Don't retry on last attempt
                if ($attempt === $this->retryAttempts) {
                    break;
                }
                
                // Wait before retrying with exponential backoff
                usleep((int)($delay * 1000000));
                $delay *= $this->retryMultiplier;
                
                // Try to reconnect
                try {
                    $this->disconnect();
                    $this->connect();
                } catch (\Exception $reconnectException) {
                    // Continue with next retry attempt
                }
            }
        }
        
        throw new DatabaseException(
            "Query failed after {$this->retryAttempts} attempts: " . $lastException->getMessage(),
            0,
            $lastException
        );
    }
    
    /**
     * Check if an error is retryable
     */
    private function isRetryableError(DatabaseException $e): bool
    {
        $message = strtolower($e->getMessage());
        
        // List of retryable error patterns
        $retryablePatterns = [
            'connection',
            'timeout',
            'terminated',
            'reset',
            'broken pipe',
            'gone away',
            'lost connection',
            'server has gone away',
            'deadlock',
            'lock wait timeout',
        ];
        
        foreach ($retryablePatterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Enable query logging
     */
    public function enableQueryLog(): void
    {
        $this->queryLogging = true;
    }
    
    /**
     * Disable query logging
     */
    public function disableQueryLog(): void
    {
        $this->queryLogging = false;
    }
    
    /**
     * Get the query log
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }
    
    /**
     * Clear the query log
     */
    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }
    
    /**
     * Log a query
     */
    private function logQuery(string $sql, array $bindings, float $time, ?\Exception $error = null): void
    {
        $this->queryLog[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => $time,
            'error' => $error ? $error->getMessage() : null,
            'timestamp' => microtime(true),
            'connection_id' => $this->connectionId,
        ];
        
        // Limit query log size
        if (count($this->queryLog) > 1000) {
            array_shift($this->queryLog);
        }
    }
    
    /**
     * Track performance metrics
     */
    private function trackPerformance(string $sql, float $time): void
    {
        // Extract query type (SELECT, INSERT, UPDATE, DELETE, etc.)
        $type = strtoupper(strtok($sql, ' '));
        
        if (!isset($this->performanceMetrics[$type])) {
            $this->performanceMetrics[$type] = [
                'count' => 0,
                'total_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0,
                'avg_time' => 0,
            ];
        }
        
        $metrics = &$this->performanceMetrics[$type];
        $metrics['count']++;
        $metrics['total_time'] += $time;
        $metrics['min_time'] = min($metrics['min_time'], $time);
        $metrics['max_time'] = max($metrics['max_time'], $time);
        $metrics['avg_time'] = $metrics['total_time'] / $metrics['count'];
    }
    
    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }
    
    /**
     * Set a health check callback
     */
    public function setHealthCheck(callable $callback): void
    {
        $this->healthCheck = $callback;
    }
    
    /**
     * Perform a health check
     */
    public function healthCheck(): bool
    {
        try {
            // Basic connectivity check
            if (!$this->isConnected()) {
                return false;
            }
            
            // Execute a simple query
            $result = $this->query("SELECT 1");
            
            // Custom health check if set
            if ($this->healthCheck) {
                return call_user_func($this->healthCheck, $this);
            }
            
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get connection statistics
     */
    public function getStats(): array
    {
        return [
            'connection_id' => $this->connectionId,
            'transaction_level' => $this->savepointLevel,
            'prepared_statements' => count($this->preparedStatements),
            'query_log_size' => count($this->queryLog),
            'performance_metrics' => $this->performanceMetrics,
            'is_connected' => $this->isConnected(),
        ];
    }
    
    /**
     * Clean up resources
     */
    public function __destruct()
    {
        // Clear prepared statements
        $this->preparedStatements = [];
        
        // Ensure all savepoints are cleaned up
        if ($this->savepointLevel > 0) {
            try {
                while ($this->savepointLevel > 0) {
                    $this->rollback();
                }
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }
        
        parent::disconnect();
    }
}