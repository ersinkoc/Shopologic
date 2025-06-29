<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Drivers;

use Shopologic\Core\Database\ResultInterface;
use Shopologic\Core\Database\Result;
use SQLite3;
use SQLite3Result;

class SQLiteDriver implements DatabaseDriverInterface
{
    protected ?SQLite3 $connection = null;
    protected array $config = [];
    protected bool $inTransaction = false;

    public function connect(array $config): void
    {
        $this->config = $config;
        
        $database = $config['database'] ?? ':memory:';
        
        // Create directory if it doesn't exist
        if ($database !== ':memory:') {
            $dir = dirname($database);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        $flags = \SQLITE3_OPEN_READWRITE | \SQLITE3_OPEN_CREATE;
        
        $this->connection = new SQLite3($database, $flags);
        $this->connection->enableExceptions(true);
        
        // Set pragmas for better performance
        $this->connection->exec('PRAGMA foreign_keys = ON');
        $this->connection->exec('PRAGMA journal_mode = WAL');
        $this->connection->exec('PRAGMA synchronous = NORMAL');
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    public function query(string $query, array $params = []): ResultInterface
    {
        $this->ensureConnected();
        
        if (empty($params)) {
            $result = $this->connection->query($query);
        } else {
            $stmt = $this->connection->prepare($query);
            
            foreach ($params as $index => $value) {
                $param = is_int($index) ? $index + 1 : $index;
                
                if (is_bool($value)) {
                    $stmt->bindValue($param, $value ? 1 : 0, \SQLITE3_INTEGER);
                } elseif (is_int($value)) {
                    $stmt->bindValue($param, $value, \SQLITE3_INTEGER);
                } elseif (is_float($value)) {
                    $stmt->bindValue($param, $value, \SQLITE3_FLOAT);
                } elseif (is_null($value)) {
                    $stmt->bindValue($param, null, \SQLITE3_NULL);
                } else {
                    $stmt->bindValue($param, (string) $value, \SQLITE3_TEXT);
                }
            }
            
            $result = $stmt->execute();
        }
        
        return new Result($this->fetchAll($result));
    }

    public function select(string $query, array $params = []): array
    {
        $result = $this->query($query, $params);
        return $result->fetchAll();
    }

    public function insert(string $query, array $params = []): bool
    {
        $result = $this->query($query, $params);
        return $this->connection->changes() > 0;
    }

    public function update(string $query, array $params = []): int
    {
        $this->query($query, $params);
        return $this->connection->changes();
    }

    public function delete(string $query, array $params = []): int
    {
        $this->query($query, $params);
        return $this->connection->changes();
    }

    public function statement(string $query): bool
    {
        $this->ensureConnected();
        return $this->connection->exec($query);
    }

    public function lastInsertId(?string $sequence = null): int
    {
        $this->ensureConnected();
        return $this->connection->lastInsertRowID();
    }

    public function beginTransaction(): bool
    {
        $this->ensureConnected();
        
        if ($this->inTransaction) {
            return false;
        }
        
        $result = $this->connection->exec('BEGIN TRANSACTION');
        if ($result) {
            $this->inTransaction = true;
        }
        
        return $result;
    }

    public function commit(): bool
    {
        $this->ensureConnected();
        
        if (!$this->inTransaction) {
            return false;
        }
        
        $result = $this->connection->exec('COMMIT');
        if ($result) {
            $this->inTransaction = false;
        }
        
        return $result;
    }

    public function rollback(): bool
    {
        $this->ensureConnected();
        
        if (!$this->inTransaction) {
            return false;
        }
        
        $result = $this->connection->exec('ROLLBACK');
        if ($result) {
            $this->inTransaction = false;
        }
        
        return $result;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function getDriverName(): string
    {
        return 'sqlite';
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    protected function ensureConnected(): void
    {
        if (!$this->connection) {
            throw new \RuntimeException('No SQLite connection established');
        }
    }

    protected function fetchAll(SQLite3Result $result): array
    {
        $rows = [];
        
        while ($row = $result->fetchArray(\SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        
        return $rows;
    }

    public function escape(string $value): string
    {
        $this->ensureConnected();
        return $this->connection->escapeString($value);
    }

    public function getServerVersion(): string
    {
        $this->ensureConnected();
        $result = $this->connection->query('SELECT sqlite_version()');
        $row = $result->fetchArray(\SQLITE3_NUM);
        return $row[0] ?? 'unknown';
    }

    // Additional required methods from DatabaseDriverInterface

    public function execute(string $query, array $params = []): int
    {
        $this->query($query, $params);
        return $this->connection->changes();
    }

    public function prepare(string $query)
    {
        $this->ensureConnected();
        return $this->connection->prepare($query);
    }

    public function savepoint(string $name): bool
    {
        return $this->statement("SAVEPOINT {$name}");
    }

    public function releaseSavepoint(string $name): bool
    {
        return $this->statement("RELEASE SAVEPOINT {$name}");
    }

    public function rollbackToSavepoint(string $name): bool
    {
        return $this->statement("ROLLBACK TO SAVEPOINT {$name}");
    }

    public function quote(string $value, int $type = 0): string
    {
        return "'" . $this->escape($value) . "'";
    }

    public function getVersion(): string
    {
        return $this->getServerVersion();
    }

    public function getInfo(): array
    {
        return [
            'driver' => 'sqlite',
            'version' => $this->getVersion(),
            'database' => $this->config['database'] ?? ':memory:',
        ];
    }

    public function ping(): bool
    {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function reconnect(): bool
    {
        $this->disconnect();
        try {
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
        // SQLite doesn't support connection attributes like PDO
        // This is a no-op for SQLite
    }

    public function errorInfo(): array
    {
        $this->ensureConnected();
        $code = $this->connection->lastErrorCode();
        $message = $this->connection->lastErrorMsg();
        return [$code, $message];
    }

    public function errorMessage(): ?string
    {
        $this->ensureConnected();
        return $this->connection->lastErrorMsg();
    }

    public function errorCode()
    {
        $this->ensureConnected();
        return $this->connection->lastErrorCode();
    }
}