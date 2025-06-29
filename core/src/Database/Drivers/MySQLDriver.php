<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Drivers;

use Shopologic\Core\Database\DatabaseException;
use Shopologic\Core\Database\MySQLResult;
use Shopologic\Core\Database\ResultInterface;

/**
 * MySQL/MariaDB database driver using native mysqli functions
 */
class MySQLDriver implements DatabaseDriverInterface
{
    private ?\mysqli $connection = null;
    private array $config;
    private bool $inTransaction = false;
    private int $transactionLevel = 0;

    public function connect(array $config): void
    {
        if ($this->isConnected()) {
            return;
        }

        $this->config = $config;
        
        // Initialize mysqli
        $this->connection = new \mysqli();
        
        // Set connection options
        if (!empty($config['options'])) {
            foreach ($config['options'] as $option => $value) {
                $this->connection->options($option, $value);
            }
        }
        
        // Support persistent connections
        $host = $config['host'] ?? '127.0.0.1';
        if (!empty($config['persistent'])) {
            $host = 'p:' . $host;
        }
        
        // Connect
        $result = @$this->connection->real_connect(
            $host,
            $config['username'] ?? 'root',
            $config['password'] ?? '',
            $config['database'] ?? '',
            (int)($config['port'] ?? 3306),
            $config['socket'] ?? null
        );
        
        if (!$result) {
            $error = $this->connection->connect_error ?: 'Unknown connection error';
            $this->connection = null;
            throw new DatabaseException('Failed to connect to MySQL database: ' . $error);
        }
        
        // Set charset
        if (isset($config['charset'])) {
            $this->connection->set_charset($config['charset']);
        }
        
        // Set SQL mode for consistency
        if (isset($config['strict']) && $config['strict']) {
            $this->connection->query("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }
        
        // Set timezone if specified
        if (isset($config['timezone'])) {
            $this->connection->query("SET time_zone = '{$config['timezone']}'");
        }
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
            $this->inTransaction = false;
            $this->transactionLevel = 0;
        }
    }

    public function isConnected(): bool
    {
        return $this->connection && $this->connection->ping();
    }

    public function query(string $query, array $params = []): ResultInterface
    {
        $this->ensureConnected();
        
        if (empty($params)) {
            $result = $this->connection->query($query);
            
            if ($result === false) {
                throw new DatabaseException($this->errorMessage() ?? 'Query failed');
            }
            
            return new MySQLResult($result, $this->connection);
        }
        
        // Use prepared statements for parameterized queries
        $stmt = $this->connection->prepare($query);
        
        if (!$stmt) {
            throw new DatabaseException($this->errorMessage() ?? 'Failed to prepare statement');
        }
        
        // Bind parameters if any
        if (!empty($params)) {
            $types = '';
            $values = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_bool($param)) {
                    $types .= 'i';
                    $param = $param ? 1 : 0;
                } else {
                    $types .= 's';
                    $param = (string)$param;
                }
                $values[] = $param;
            }
            
            // Create references for bind_param
            $refs = [];
            $refs[] = $types;
            foreach ($values as $key => $value) {
                $refs[] = &$values[$key];
            }
            
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new DatabaseException($error);
        }
        
        $result = $stmt->get_result();
        
        if ($result === false) {
            // For non-SELECT queries
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            return new MySQLResult(null, $this->connection, $affectedRows);
        }
        
        $stmt->close();
        return new MySQLResult($result, $this->connection);
    }

    public function execute(string $query, array $params = []): int
    {
        $result = $this->query($query, $params);
        return $result->rowCount();
    }

    public function prepare(string $query)
    {
        $this->ensureConnected();
        
        $stmt = $this->connection->prepare($query);
        
        if (!$stmt) {
            throw new DatabaseException($this->errorMessage() ?? 'Failed to prepare statement');
        }
        
        return $stmt;
    }

    public function beginTransaction(): bool
    {
        $this->ensureConnected();
        
        if ($this->transactionLevel === 0) {
            $result = $this->connection->begin_transaction();
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
            $result = $this->connection->commit();
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
            $result = $this->connection->rollback();
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
        $result = $this->connection->query("SAVEPOINT `{$name}`");
        return $result !== false;
    }

    public function releaseSavepoint(string $name): bool
    {
        $result = $this->connection->query("RELEASE SAVEPOINT `{$name}`");
        return $result !== false;
    }

    public function rollbackToSavepoint(string $name): bool
    {
        $result = $this->connection->query("ROLLBACK TO SAVEPOINT `{$name}`");
        return $result !== false;
    }

    public function lastInsertId(?string $sequence = null)
    {
        // MySQL doesn't use sequences, ignore the parameter
        return $this->connection->insert_id;
    }

    public function quote(string $value, int $type = 0): string
    {
        $this->ensureConnected();
        return "'" . $this->connection->real_escape_string($value) . "'";
    }

    public function getDriverName(): string
    {
        return 'mysql';
    }

    public function getVersion(): string
    {
        $this->ensureConnected();
        return $this->connection->server_info;
    }

    public function getInfo(): array
    {
        $this->ensureConnected();
        return [
            'client_info' => $this->connection->client_info,
            'client_version' => $this->connection->client_version,
            'server_info' => $this->connection->server_info,
            'server_version' => $this->connection->server_version,
            'host_info' => $this->connection->host_info,
            'protocol_version' => $this->connection->protocol_version,
            'thread_id' => $this->connection->thread_id,
            'stat' => $this->connection->stat(),
        ];
    }

    public function ping(): bool
    {
        return $this->connection && $this->connection->ping();
    }

    public function reconnect(): bool
    {
        if (!$this->connection) {
            return false;
        }
        
        // Close existing connection
        $this->connection->close();
        
        // Reconnect
        try {
            $this->connection = null;
            $this->connect($this->config);
            return true;
        } catch (\Exception $e) {
            return false;
        }
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
                case 'charset':
                    $this->connection->set_charset($value);
                    break;
                case 'autocommit':
                    $this->connection->autocommit((bool)$value);
                    break;
            }
        }
    }

    public function errorInfo(): array
    {
        if (!$this->connection) {
            return ['', 'No connection'];
        }
        
        return [
            $this->connection->errno,
            $this->connection->error
        ];
    }

    public function errorMessage(): ?string
    {
        return $this->connection ? $this->connection->error : null;
    }

    public function errorCode()
    {
        return $this->connection ? $this->connection->errno : null;
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