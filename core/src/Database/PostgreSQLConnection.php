<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

class PostgreSQLConnection implements ConnectionInterface
{
    private $connection = null;
    private array $config;
    private bool $inTransaction = false;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        $dsn = $this->buildDsn();
        
        $this->connection = pg_connect($dsn);
        
        if (!$this->connection) {
            throw new DatabaseException('Failed to connect to PostgreSQL database');
        }

        // Set connection encoding
        if (isset($this->config['charset'])) {
            pg_set_client_encoding($this->connection, $this->config['charset']);
        }
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            pg_close($this->connection);
            $this->connection = null;
            $this->inTransaction = false;
        }
    }

    public function isConnected(): bool
    {
        return $this->connection && pg_connection_status($this->connection) === PGSQL_CONNECTION_OK;
    }

    public function query(string $sql, array $bindings = []): ResultInterface
    {
        $this->connect();
        
        if (empty($bindings)) {
            $result = pg_query($this->connection, $sql);
        } else {
            $result = pg_query_params($this->connection, $sql, $bindings);
        }

        if (!$result) {
            throw new DatabaseException('Query failed: ' . pg_last_error($this->connection));
        }

        return new PostgreSQLResult($result);
    }

    public function execute(string $sql, array $bindings = []): int
    {
        $result = $this->query($sql, $bindings);
        return $result->rowCount();
    }

    public function prepare(string $sql): StatementInterface
    {
        $this->connect();
        return new PostgreSQLStatement($this->connection, $sql);
    }

    public function beginTransaction(): bool
    {
        $this->connect();
        
        if ($this->inTransaction) {
            return false;
        }

        $result = pg_query($this->connection, 'BEGIN');
        
        if ($result) {
            $this->inTransaction = true;
            return true;
        }

        return false;
    }

    public function commit(): bool
    {
        if (!$this->inTransaction) {
            return false;
        }

        $result = pg_query($this->connection, 'COMMIT');
        
        if ($result) {
            $this->inTransaction = false;
            return true;
        }

        return false;
    }

    public function rollback(): bool
    {
        if (!$this->inTransaction) {
            return false;
        }

        $result = pg_query($this->connection, 'ROLLBACK');
        
        if ($result) {
            $this->inTransaction = false;
            return true;
        }

        return false;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function lastInsertId(?string $sequence = null): string
    {
        if ($sequence) {
            $result = $this->query("SELECT currval(?)", [$sequence]);
        } else {
            $result = $this->query("SELECT lastval()");
        }
        
        return (string) $result->fetchColumn();
    }

    public function quote(string $value): string
    {
        $this->connect();
        return "'" . pg_escape_string($this->connection, $value) . "'";
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

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

        return implode(' ', $parts);
    }
}