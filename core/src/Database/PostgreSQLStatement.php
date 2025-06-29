<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

class PostgreSQLStatement implements StatementInterface
{
    private $connection;
    private string $sql;
    private string $statementName;
    private array $boundParams = [];
    private $result = null;
    private static int $statementCounter = 0;

    public function __construct($connection, string $sql)
    {
        $this->connection = $connection;
        $this->sql = $sql;
        $this->statementName = 'stmt_' . (++self::$statementCounter);
        
        // Prepare the statement
        $result = pg_prepare($this->connection, $this->statementName, $sql);
        
        if (!$result) {
            throw new DatabaseException('Failed to prepare statement: ' . pg_last_error($this->connection));
        }
    }

    public function bindValue(string|int $parameter, mixed $value, int $type = null): bool
    {
        if (is_int($parameter)) {
            $this->boundParams[$parameter - 1] = $value;
        } else {
            // For named parameters, we need to convert to positional
            $this->boundParams[] = $value;
        }
        
        return true;
    }

    public function bindParam(string|int $parameter, mixed &$variable, int $type = null): bool
    {
        if (is_int($parameter)) {
            $this->boundParams[$parameter - 1] = &$variable;
        } else {
            $this->boundParams[] = &$variable;
        }
        
        return true;
    }

    public function execute(array $params = []): bool
    {
        $executeParams = !empty($params) ? $params : array_values($this->boundParams);
        
        $this->result = pg_execute($this->connection, $this->statementName, $executeParams);
        
        if (!$this->result) {
            throw new DatabaseException('Statement execution failed: ' . pg_last_error($this->connection));
        }

        return true;
    }

    public function fetch(): ?array
    {
        if (!$this->result) {
            return null;
        }

        $row = pg_fetch_assoc($this->result);
        return $row ?: null;
    }

    public function fetchAll(): array
    {
        if (!$this->result) {
            return [];
        }

        $rows = [];
        while ($row = $this->fetch()) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if (!$this->result) {
            return null;
        }

        $row = pg_fetch_row($this->result);
        return $row ? $row[$column] : null;
    }

    public function rowCount(): int
    {
        if (!$this->result) {
            return 0;
        }

        return pg_affected_rows($this->result);
    }

    public function columnCount(): int
    {
        if (!$this->result) {
            return 0;
        }

        return pg_num_fields($this->result);
    }

    public function closeCursor(): void
    {
        if ($this->result) {
            pg_free_result($this->result);
            $this->result = null;
        }
    }

    public function __destruct()
    {
        $this->closeCursor();
    }
}