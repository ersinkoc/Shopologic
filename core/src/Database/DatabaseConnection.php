<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

use Shopologic\Core\Database\Drivers\DatabaseDriverInterface;
use Shopologic\Core\Database\Query\Grammars\Grammar;
use Shopologic\Core\Database\Query\Grammars\MySQLGrammar;
use Shopologic\Core\Database\Query\Grammars\PostgreSQLGrammar;

/**
 * Database connection wrapper that provides a unified interface
 */
class DatabaseConnection implements ConnectionInterface
{
    private DatabaseDriverInterface $driver;
    private array $config;
    private ?Grammar $queryGrammar = null;
    private bool $loggingEnabled = false;
    private array $queryLog = [];

    public function __construct(DatabaseDriverInterface $driver, array $config)
    {
        $this->driver = $driver;
        $this->config = $config;
        $this->driver->connect($config);
    }

    public function connect(): void
    {
        $this->driver->connect($this->config);
    }

    public function disconnect(): void
    {
        $this->driver->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->driver->isConnected();
    }

    public function query(string $sql, array $bindings = []): ResultInterface
    {
        $start = microtime(true);
        
        try {
            $result = $this->driver->query($sql, $bindings);
            
            if ($this->loggingEnabled) {
                $this->queryLog[] = [
                    'query' => $sql,
                    'bindings' => $bindings,
                    'time' => round((microtime(true) - $start) * 1000, 2),
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->loggingEnabled) {
                $this->queryLog[] = [
                    'query' => $sql,
                    'bindings' => $bindings,
                    'time' => round((microtime(true) - $start) * 1000, 2),
                    'error' => $e->getMessage(),
                ];
            }
            
            throw $e;
        }
    }

    public function execute(string $sql, array $bindings = []): int
    {
        return $this->driver->execute($sql, $bindings);
    }

    public function prepare(string $sql): StatementInterface
    {
        $prepared = $this->driver->prepare($sql);
        
        // Wrap the prepared statement
        return new PreparedStatement($prepared, $this);
    }

    public function beginTransaction(): bool
    {
        return $this->driver->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->driver->commit();
    }

    public function rollback(): bool
    {
        return $this->driver->rollback();
    }

    public function inTransaction(): bool
    {
        return $this->driver->inTransaction();
    }

    public function lastInsertId(?string $sequence = null): string
    {
        return (string)$this->driver->lastInsertId($sequence);
    }

    public function quote(string $value): string
    {
        return $this->driver->quote($value);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function table(string $table): QueryBuilder
    {
        $builder = new QueryBuilder($this, $this->getQueryGrammar());
        return $builder->from($table);
    }

    public function getDriverName(): string
    {
        return $this->driver->getDriverName();
    }

    public function getQueryGrammar(): Grammar
    {
        if ($this->queryGrammar === null) {
            $this->queryGrammar = $this->createQueryGrammar();
        }
        
        return $this->queryGrammar;
    }

    public function setQueryGrammar(Grammar $grammar): void
    {
        $this->queryGrammar = $grammar;
    }

    protected function createQueryGrammar(): Grammar
    {
        switch ($this->driver->getDriverName()) {
            case 'mysql':
                return new MySQLGrammar();
            case 'pgsql':
                return new PostgreSQLGrammar();
            default:
                throw new DatabaseException('No query grammar available for driver: ' . $this->driver->getDriverName());
        }
    }

    public function enableQueryLog(): void
    {
        $this->loggingEnabled = true;
    }

    public function disableQueryLog(): void
    {
        $this->loggingEnabled = false;
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function flushQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * Run a SQL statement and get the number of rows affected
     */
    public function affectingStatement(string $query, array $bindings = []): int
    {
        return $this->execute($query, $bindings);
    }

    /**
     * Run a select statement against the database
     */
    public function select(string $query, array $bindings = []): array
    {
        $result = $this->query($query, $bindings);
        return $result->fetchAll();
    }

    /**
     * Run a select statement and return a single result
     */
    public function selectOne(string $query, array $bindings = []): ?array
    {
        $result = $this->query($query, $bindings);
        return $result->fetch();
    }

    /**
     * Run an insert statement against the database
     */
    public function insert(string $query, array $bindings = []): bool
    {
        return $this->execute($query, $bindings) > 0;
    }

    /**
     * Run an update statement against the database
     */
    public function update(string $query, array $bindings = []): int
    {
        return $this->execute($query, $bindings);
    }

    /**
     * Run a delete statement against the database
     */
    public function delete(string $query, array $bindings = []): int
    {
        return $this->execute($query, $bindings);
    }

    /**
     * Execute a closure within a transaction
     */
    public function transaction(callable $callback, int $attempts = 1)
    {
        for ($i = 0; $i < $attempts; $i++) {
            $this->beginTransaction();
            
            try {
                $result = $callback($this);
                $this->commit();
                return $result;
            } catch (\Exception $e) {
                $this->rollback();
                
                if ($i >= $attempts - 1) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Get the underlying driver
     */
    public function getDriver(): DatabaseDriverInterface
    {
        return $this->driver;
    }

    /**
     * Reconnect to the database
     */
    public function reconnect(): bool
    {
        return $this->driver->reconnect();
    }

    /**
     * Check if the connection is alive
     */
    public function ping(): bool
    {
        return $this->driver->ping();
    }
}