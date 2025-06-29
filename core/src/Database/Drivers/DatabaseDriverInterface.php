<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Drivers;

use Shopologic\Core\Database\ResultInterface;

/**
 * Database driver interface for abstracting database operations
 */
interface DatabaseDriverInterface
{
    /**
     * Connect to the database
     *
     * @param array $config Connection configuration
     * @return void
     * @throws \Exception
     */
    public function connect(array $config): void;

    /**
     * Disconnect from the database
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Check if connected to the database
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Execute a query with optional parameters
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return ResultInterface
     * @throws \Exception
     */
    public function query(string $query, array $params = []): ResultInterface;

    /**
     * Execute a statement without returning results
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return int Affected rows
     * @throws \Exception
     */
    public function execute(string $query, array $params = []): int;

    /**
     * Prepare a statement for execution
     *
     * @param string $query SQL query
     * @return mixed Prepared statement
     * @throws \Exception
     */
    public function prepare(string $query);

    /**
     * Begin a transaction
     *
     * @return bool
     */
    public function beginTransaction(): bool;

    /**
     * Commit a transaction
     *
     * @return bool
     */
    public function commit(): bool;

    /**
     * Rollback a transaction
     *
     * @return bool
     */
    public function rollback(): bool;

    /**
     * Check if in a transaction
     *
     * @return bool
     */
    public function inTransaction(): bool;

    /**
     * Create a savepoint
     *
     * @param string $name Savepoint name
     * @return bool
     */
    public function savepoint(string $name): bool;

    /**
     * Release a savepoint
     *
     * @param string $name Savepoint name
     * @return bool
     */
    public function releaseSavepoint(string $name): bool;

    /**
     * Rollback to a savepoint
     *
     * @param string $name Savepoint name
     * @return bool
     */
    public function rollbackToSavepoint(string $name): bool;

    /**
     * Get the last insert ID
     *
     * @param string|null $sequence Sequence name (for PostgreSQL)
     * @return int|string
     */
    public function lastInsertId(?string $sequence = null);

    /**
     * Quote a string for safe use in queries
     *
     * @param string $value Value to quote
     * @param int $type Parameter type
     * @return string
     */
    public function quote(string $value, int $type = 0): string;

    /**
     * Get the database driver name
     *
     * @return string
     */
    public function getDriverName(): string;

    /**
     * Get the database version
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Get database-specific information
     *
     * @return array
     */
    public function getInfo(): array;

    /**
     * Ping the database connection
     *
     * @return bool
     */
    public function ping(): bool;

    /**
     * Reconnect to the database if connection is lost
     *
     * @return bool
     */
    public function reconnect(): bool;

    /**
     * Get the connection resource
     *
     * @return mixed
     */
    public function getConnection();

    /**
     * Set connection attributes
     *
     * @param array $attributes
     * @return void
     */
    public function setAttributes(array $attributes): void;

    /**
     * Get error information
     *
     * @return array [code, message]
     */
    public function errorInfo(): array;

    /**
     * Get the last error message
     *
     * @return string|null
     */
    public function errorMessage(): ?string;

    /**
     * Get the last error code
     *
     * @return int|string|null
     */
    public function errorCode();
}