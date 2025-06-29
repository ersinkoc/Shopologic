<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

class PostgreSQLResult implements ResultInterface
{
    private $result;
    private int $position = 0;
    private ?array $currentRow = null;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function fetch(): ?array
    {
        $row = pg_fetch_assoc($this->result);
        return $row ?: null;
    }

    public function fetchAll(): array
    {
        $rows = [];
        while ($row = $this->fetch()) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = pg_fetch_row($this->result);
        return $row ? $row[$column] : null;
    }

    public function fetchObject(?string $class = null): ?object
    {
        if ($class) {
            $row = pg_fetch_object($this->result, null, $class);
        } else {
            $row = pg_fetch_object($this->result);
        }
        
        return $row ?: null;
    }

    public function rowCount(): int
    {
        return pg_num_rows($this->result);
    }

    public function columnCount(): int
    {
        return pg_num_fields($this->result);
    }

    public function getColumnMeta(int $column): array
    {
        return [
            'name' => pg_field_name($this->result, $column),
            'type' => pg_field_type($this->result, $column),
            'size' => pg_field_size($this->result, $column),
            'is_null' => pg_field_is_null($this->result, $this->position, $column),
        ];
    }

    // Iterator implementation
    public function current(): mixed
    {
        return $this->currentRow;
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->currentRow = pg_fetch_assoc($this->result);
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
        pg_result_seek($this->result, 0);
        $this->currentRow = pg_fetch_assoc($this->result);
    }

    public function valid(): bool
    {
        return $this->currentRow !== false && $this->currentRow !== null;
    }

    // Countable implementation
    public function count(): int
    {
        return $this->rowCount();
    }

    public function __destruct()
    {
        if ($this->result) {
            pg_free_result($this->result);
        }
    }
}