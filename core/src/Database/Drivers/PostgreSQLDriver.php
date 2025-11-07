<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Drivers;

use Shopologic\Core\Database\DatabaseException;
use Shopologic\Core\Database\PostgreSQLResult;
use Shopologic\Core\Database\ResultInterface;

/**
 * PostgreSQL database driver using native PHP functions
 */
class PostgreSQLDriver implements DatabaseDriverInterface
{
    private $connection = null;
    private array $config;
    private bool $inTransaction = false;
    private int $transactionLevel = 0;

    public function connect(array $config): void
    {
        if ($this->isConnected()) {
            return;
        }

        $this->config = $config;
        $dsn = $this->buildDsn($config);
        
        // Support persistent connections
        if (!empty($config['persistent'])) {
            $this->connection = pg_pconnect($dsn);
        } else {
            $this->connection = pg_connect($dsn);
        }
        
        if (!$this->connection) {
            throw new DatabaseException('Failed to connect to PostgreSQL database');
        }

        // Set connection encoding
        if (isset($config['charset'])) {
            pg_set_client_encoding($this->connection, $config['charset']);
        }

        // Set schema if specified
        if (isset($config['schema'])) {
            // Escape schema name to prevent SQL injection
            $escapedSchema = pg_escape_identifier($this->connection, $config['schema']);
            pg_query($this->connection, "SET search_path TO {$escapedSchema}");
        }
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            pg_close($this->connection);
            $this->connection = null;
            $this->inTransaction = false;
            $this->transactionLevel = 0;
        }
    }

    public function isConnected(): bool
    {
        return $this->connection && pg_connection_status($this->connection) === PGSQL_CONNECTION_OK;
    }

    public function query(string $query, array $params = []): ResultInterface
    {
        $this->ensureConnected();
        
        if (empty($params)) {
            $result = pg_query($this->connection, $query);
        } else {
            // Convert boolean values for PostgreSQL
            $params = array_map(function ($param) {
                if (is_bool($param)) {
                    return $param ? 'true' : 'false';
                }
                return $param;
            }, $params);
            
            $result = pg_query_params($this->connection, $query, $params);
        }

        if (!$result) {
            throw new DatabaseException($this->errorMessage() ?? 'Query failed');
        }

        return new PostgreSQLResult($result);
    }

    public function execute(string $query, array $params = []): int
    {
        $result = $this->query($query, $params);
        return $result->rowCount();
    }

    public function prepare(string $query)
    {
        $this->ensureConnected();
        
        // PostgreSQL doesn't have true prepared statements like MySQL
        // Return a closure that will execute the query with parameters
        return function(array $params = []) use ($query) {
            return $this->query($query, $params);
        };
    }

    public function beginTransaction(): bool
    {
        $this->ensureConnected();
        
        if ($this->transactionLevel === 0) {
            $result = pg_query($this->connection, 'BEGIN');
            if ($result) {
                $this->inTransaction = true;
                $this->transactionLevel = 1;
                return true;
            }
            return false;
        }
        
        // Nested transaction - use savepoint
        $this->transactionLevel++;
        return $this->savepoint('sp' . $this->transactionLevel);
    }

    public function commit(): bool
    {
        if (!$this->inTransaction || $this->transactionLevel === 0) {
            return false;
        }

        if ($this->transactionLevel === 1) {
            $result = pg_query($this->connection, 'COMMIT');
            if ($result) {
                $this->inTransaction = false;
                $this->transactionLevel = 0;
                return true;
            }
            return false;
        }

        // Nested transaction - release savepoint
        $result = $this->releaseSavepoint('sp' . $this->transactionLevel);
        if ($result) {
            $this->transactionLevel--;
        }
        return $result;
    }

    public function rollback(): bool
    {
        if (!$this->inTransaction || $this->transactionLevel === 0) {
            return false;
        }

        if ($this->transactionLevel === 1) {
            $result = pg_query($this->connection, 'ROLLBACK');
            if ($result) {
                $this->inTransaction = false;
                $this->transactionLevel = 0;
                return true;
            }
            return false;
        }

        // Nested transaction - rollback to savepoint
        $result = $this->rollbackToSavepoint('sp' . $this->transactionLevel);
        if ($result) {
            $this->transactionLevel--;
        }
        return $result;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function savepoint(string $name): bool
    {
        $result = pg_query($this->connection, "SAVEPOINT {$name}");
        return $result !== false;
    }

    public function releaseSavepoint(string $name): bool
    {
        $result = pg_query($this->connection, "RELEASE SAVEPOINT {$name}");
        return $result !== false;
    }

    public function rollbackToSavepoint(string $name): bool
    {
        $result = pg_query($this->connection, "ROLLBACK TO SAVEPOINT {$name}");
        return $result !== false;
    }

    public function lastInsertId(?string $sequence = null)
    {
        if ($sequence) {
            $result = $this->query("SELECT currval(?)", [$sequence]);
        } else {
            $result = $this->query("SELECT lastval()");
        }
        
        return $result->fetchColumn();
    }

    public function quote(string $value, int $type = 0): string
    {
        $this->ensureConnected();
        return "'" . pg_escape_string($this->connection, $value) . "'";
    }

    public function getDriverName(): string
    {
        return 'pgsql';
    }

    public function getVersion(): string
    {
        $this->ensureConnected();
        $result = pg_version($this->connection);
        return $result['server'] ?? 'unknown';
    }

    public function getInfo(): array
    {
        $this->ensureConnected();
        return [
            'version' => pg_version($this->connection),
            'host' => pg_host($this->connection),
            'port' => pg_port($this->connection),
            'dbname' => pg_dbname($this->connection),
            'options' => pg_options($this->connection),
            'status' => pg_connection_status($this->connection),
        ];
    }

    public function ping(): bool
    {
        if (!$this->connection) {
            return false;
        }
        
        return pg_ping($this->connection);
    }

    public function reconnect(): bool
    {
        if ($this->connection && !$this->ping()) {
            pg_connection_reset($this->connection);
            return $this->ping();
        }
        
        return false;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function setAttributes(array $attributes): void
    {
        $this->ensureConnected();
        
        foreach ($attributes as $key => $value) {
            switch ($key) {
                case 'client_encoding':
                    pg_set_client_encoding($this->connection, $value);
                    break;
                case 'application_name':
                    // Escape value to prevent SQL injection
                    $escapedValue = pg_escape_string($this->connection, $value);
                    pg_query($this->connection, "SET application_name = '{$escapedValue}'");
                    break;
            }
        }
    }

    public function errorInfo(): array
    {
        if (!$this->connection) {
            return ['', 'No connection'];
        }
        
        $message = pg_last_error($this->connection);
        
        // Try to extract error code from message
        $code = '';
        if (preg_match('/ERROR:\s+(\w+):/', $message, $matches)) {
            $code = $matches[1];
        }
        
        return [$code, $message];
    }

    public function errorMessage(): ?string
    {
        return $this->connection ? pg_last_error($this->connection) : null;
    }

    public function errorCode()
    {
        // PostgreSQL doesn't provide error codes through pg_* functions
        // Would need to parse from error message
        $info = $this->errorInfo();
        return $info[0] ?: null;
    }

    private function buildDsn(array $config): string
    {
        $parts = [];
        
        if (isset($config['host'])) {
            $parts[] = 'host=' . $config['host'];
        }
        
        if (isset($config['port'])) {
            $parts[] = 'port=' . $config['port'];
        }
        
        if (isset($config['database'])) {
            $parts[] = 'dbname=' . $config['database'];
        }
        
        if (isset($config['username'])) {
            $parts[] = 'user=' . $config['username'];
        }
        
        if (isset($config['password'])) {
            $parts[] = 'password=' . $config['password'];
        }
        
        if (isset($config['sslmode'])) {
            $parts[] = 'sslmode=' . $config['sslmode'];
        }
        
        if (isset($config['connect_timeout'])) {
            $parts[] = 'connect_timeout=' . $config['connect_timeout'];
        }

        return implode(' ', $parts);
    }

    private function ensureConnected(): void
    {
        if (!$this->isConnected()) {
            if ($this->config) {
                $this->connect($this->config);
            } else {
                throw new DatabaseException('Database connection not established');
            }
        }
    }
}