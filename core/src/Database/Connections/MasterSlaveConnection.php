<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Connections;

use Shopologic\Core\Database\ConnectionInterface;
use Shopologic\Core\Database\DatabaseException;
use Shopologic\Core\Database\ResultInterface;
use Shopologic\Core\Database\StatementInterface;
use Shopologic\Core\Database\QueryBuilder;

/**
 * Master-Slave Database Connection Manager
 * 
 * Automatically routes read queries to slave databases and
 * write queries to the master database for load balancing
 */
class MasterSlaveConnection implements ConnectionInterface
{
    private ConnectionInterface $master;
    private array $slaves = [];
    private ?ConnectionInterface $currentSlave = null;
    private bool $forceMaster = false;
    private array $config;
    private string $slaveSelectionStrategy = 'round-robin';
    private int $currentSlaveIndex = 0;
    private array $slaveWeights = [];
    private array $failedSlaves = [];
    private float $slaveRetryAfter = 300.0; // 5 minutes
    private bool $stickyReads = false;
    private ?string $stickySlaveId = null;
    
    public function __construct(
        ConnectionInterface $master,
        array $slaves,
        array $config = []
    ) {
        $this->master = $master;
        $this->slaves = $slaves;
        $this->config = $config;
        
        // Configure options
        $this->slaveSelectionStrategy = $config['slave_selection'] ?? 'round-robin';
        $this->slaveRetryAfter = $config['slave_retry_after'] ?? 300.0;
        $this->stickyReads = $config['sticky_reads'] ?? false;
        
        // Initialize slave weights for weighted selection
        if ($this->slaveSelectionStrategy === 'weighted') {
            $this->initializeSlaveWeights();
        }
    }
    
    /**
     * Connect to the database
     */
    public function connect(): void
    {
        $this->master->connect();
        
        // Optionally pre-connect to slaves
        if ($this->config['pre_connect_slaves'] ?? false) {
            foreach ($this->slaves as $slave) {
                try {
                    $slave->connect();
                } catch (\Exception $e) {
                    // Log but don't fail if slave connection fails
                    $this->markSlaveFailed($slave);
                }
            }
        }
    }
    
    /**
     * Disconnect from the database
     */
    public function disconnect(): void
    {
        $this->master->disconnect();
        
        foreach ($this->slaves as $slave) {
            $slave->disconnect();
        }
        
        $this->currentSlave = null;
        $this->stickySlaveId = null;
    }
    
    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->master->isConnected();
    }
    
    /**
     * Execute a query
     */
    public function query(string $sql, array $bindings = []): ResultInterface
    {
        $connection = $this->selectConnection($sql);
        
        try {
            return $connection->query($sql, $bindings);
        } catch (DatabaseException $e) {
            // If slave query failed, retry on master
            if ($connection !== $this->master && $this->shouldRetryOnMaster($e)) {
                $this->markSlaveFailed($connection);
                return $this->master->query($sql, $bindings);
            }
            
            throw $e;
        }
    }
    
    /**
     * Execute a statement
     */
    public function execute(string $sql, array $bindings = []): int
    {
        // Always use master for execute operations
        return $this->master->execute($sql, $bindings);
    }
    
    /**
     * Prepare a statement
     */
    public function prepare(string $sql): StatementInterface
    {
        $connection = $this->selectConnection($sql);
        return $connection->prepare($sql);
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool
    {
        // Force all subsequent queries to master during transaction
        $this->forceMaster = true;
        return $this->master->beginTransaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        $result = $this->master->commit();
        $this->forceMaster = false;
        return $result;
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback(): bool
    {
        $result = $this->master->rollback();
        $this->forceMaster = false;
        return $result;
    }
    
    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->master->inTransaction();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId(?string $sequence = null): string
    {
        return $this->master->lastInsertId($sequence);
    }
    
    /**
     * Quote a value
     */
    public function quote(string $value): string
    {
        return $this->master->quote($value);
    }
    
    /**
     * Get configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Create a query builder
     */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }
    
    /**
     * Force next query to use master
     */
    public function forceMaster(): void
    {
        $this->forceMaster = true;
    }
    
    /**
     * Select the appropriate connection for a query
     */
    private function selectConnection(string $sql): ConnectionInterface
    {
        // Always use master if forced or in transaction
        if ($this->forceMaster || $this->inTransaction()) {
            return $this->master;
        }
        
        // Check if query is a write operation
        if ($this->isWriteQuery($sql)) {
            // After a write, optionally stick to master for subsequent reads
            if ($this->config['read_after_write_timeout'] ?? 0 > 0) {
                $this->forceMaster = true;
                // TODO: Implement timeout mechanism
            }
            return $this->master;
        }
        
        // Select a slave for read operations
        return $this->selectSlave();
    }
    
    /**
     * Determine if a query is a write operation
     */
    private function isWriteQuery(string $sql): bool
    {
        $sql = trim($sql);
        $firstWord = strtoupper(strtok($sql, ' '));
        
        $writeOperations = [
            'INSERT', 'UPDATE', 'DELETE', 'REPLACE',
            'CREATE', 'ALTER', 'DROP', 'TRUNCATE',
            'LOAD', 'COPY', 'LOCK', 'UNLOCK',
            'GRANT', 'REVOKE', 'SET', 'RESET',
            'START', 'BEGIN', 'COMMIT', 'ROLLBACK',
            'SAVEPOINT', 'RELEASE', 'PREPARE', 'EXECUTE',
            'DEALLOCATE', 'CALL', 'DO', 'HANDLER',
            'LOAD', 'REPLACE', 'START', 'STOP'
        ];
        
        return in_array($firstWord, $writeOperations, true);
    }
    
    /**
     * Select a slave connection
     */
    private function selectSlave(): ConnectionInterface
    {
        // Check for available slaves
        $availableSlaves = $this->getAvailableSlaves();
        
        if (empty($availableSlaves)) {
            // No slaves available, fall back to master
            return $this->master;
        }
        
        // Use sticky slave if enabled and available
        if ($this->stickyReads && $this->stickySlaveId !== null) {
            foreach ($availableSlaves as $slave) {
                if ((string) spl_object_id($slave) === $this->stickySlaveId) {
                    return $slave;
                }
            }
        }
        
        // Select slave based on strategy
        $slave = match ($this->slaveSelectionStrategy) {
            'random' => $this->selectRandomSlave($availableSlaves),
            'weighted' => $this->selectWeightedSlave($availableSlaves),
            'least-connections' => $this->selectLeastConnectionsSlave($availableSlaves),
            default => $this->selectRoundRobinSlave($availableSlaves),
        };
        
        // Remember slave for sticky reads
        if ($this->stickyReads) {
            $this->stickySlaveId = (string) spl_object_id($slave);
        }
        
        return $slave;
    }
    
    /**
     * Get available slave connections
     */
    private function getAvailableSlaves(): array
    {
        $available = [];
        $now = microtime(true);
        
        foreach ($this->slaves as $key => $slave) {
            // Check if slave was marked as failed
            if (isset($this->failedSlaves[$key])) {
                // Check if retry time has passed
                if ($now - $this->failedSlaves[$key] < $this->slaveRetryAfter) {
                    continue;
                }
                // Remove from failed list to retry
                unset($this->failedSlaves[$key]);
            }
            
            $available[$key] = $slave;
        }
        
        return $available;
    }
    
    /**
     * Select slave using round-robin strategy
     */
    private function selectRoundRobinSlave(array $slaves): ConnectionInterface
    {
        $keys = array_keys($slaves);
        $key = $keys[$this->currentSlaveIndex % count($keys)];
        $this->currentSlaveIndex++;
        
        return $slaves[$key];
    }
    
    /**
     * Select slave randomly
     */
    private function selectRandomSlave(array $slaves): ConnectionInterface
    {
        $key = array_rand($slaves);
        return $slaves[$key];
    }
    
    /**
     * Select slave based on weights
     */
    private function selectWeightedSlave(array $slaves): ConnectionInterface
    {
        $totalWeight = 0;
        $weights = [];
        
        foreach ($slaves as $key => $slave) {
            $weight = $this->slaveWeights[$key] ?? 1;
            $weights[$key] = $weight;
            $totalWeight += $weight;
        }
        
        $random = mt_rand(1, $totalWeight);
        $current = 0;
        
        foreach ($weights as $key => $weight) {
            $current += $weight;
            if ($random <= $current) {
                return $slaves[$key];
            }
        }
        
        // Fallback to first slave
        return reset($slaves);
    }
    
    /**
     * Select slave with least connections (placeholder)
     */
    private function selectLeastConnectionsSlave(array $slaves): ConnectionInterface
    {
        // This would require tracking connection counts
        // For now, fall back to round-robin
        return $this->selectRoundRobinSlave($slaves);
    }
    
    /**
     * Initialize slave weights from configuration
     */
    private function initializeSlaveWeights(): void
    {
        if (isset($this->config['slave_weights'])) {
            $this->slaveWeights = $this->config['slave_weights'];
        } else {
            // Default equal weights
            foreach ($this->slaves as $key => $slave) {
                $this->slaveWeights[$key] = 1;
            }
        }
    }
    
    /**
     * Mark a slave as failed
     */
    private function markSlaveFailed(ConnectionInterface $slave): void
    {
        foreach ($this->slaves as $key => $s) {
            if ($s === $slave) {
                $this->failedSlaves[$key] = microtime(true);
                break;
            }
        }
    }
    
    /**
     * Check if error should trigger retry on master
     */
    private function shouldRetryOnMaster(DatabaseException $e): bool
    {
        $message = strtolower($e->getMessage());
        
        $retryPatterns = [
            'connection',
            'gone away',
            'lost connection',
            'timeout',
            'refused',
            'reset',
        ];
        
        foreach ($retryPatterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get connection statistics
     */
    public function getStats(): array
    {
        return [
            'master' => $this->master instanceof EnhancedPostgreSQLConnection 
                ? $this->master->getStats() 
                : ['connected' => $this->master->isConnected()],
            'slaves' => array_map(function($slave, $key) {
                $stats = $slave instanceof EnhancedPostgreSQLConnection 
                    ? $slave->getStats() 
                    : ['connected' => $slave->isConnected()];
                    
                $stats['failed'] = isset($this->failedSlaves[$key]);
                return $stats;
            }, $this->slaves, array_keys($this->slaves)),
            'strategy' => $this->slaveSelectionStrategy,
            'sticky_reads' => $this->stickyReads,
            'force_master' => $this->forceMaster,
        ];
    }
}