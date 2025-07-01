<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

/**
 * Generic database result wrapper
 */
class Result implements ResultInterface
{
    private array $data;
    private int $currentRow = 0;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function fetch(): ?array
    {
        if (!isset($this->data[$this->currentRow])) {
            return null;
        }

        $row = $this->data[$this->currentRow];
        $this->currentRow++;
        
        return $row;
    }

    public function fetchAll(): array
    {
        return $this->data;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if (!isset($this->data[$this->currentRow])) {
            return null;
        }

        $row = array_values($this->data[$this->currentRow]);
        $value = $row[$column] ?? null;
        $this->currentRow++;
        
        return $value;
    }

    public function fetchObject(?string $class = null): ?object
    {
        $row = $this->fetch();
        
        if ($row === null) {
            return null;
        }

        if ($class) {
            $object = new $class();
            foreach ($row as $key => $value) {
                if (property_exists($object, $key)) {
                    $object->$key = $value;
                }
            }
            return $object;
        }

        return (object) $row;
    }

    public function rowCount(): int
    {
        return count($this->data);
    }

    public function columnCount(): int
    {
        if (empty($this->data)) {
            return 0;
        }

        return count($this->data[0]);
    }

    public function getColumnMeta(int $column): array
    {
        if (empty($this->data) || !isset($this->data[0])) {
            return [];
        }

        $keys = array_keys($this->data[0]);
        
        if (!isset($keys[$column])) {
            return [];
        }

        $columnName = $keys[$column];
        $value = $this->data[0][$columnName];

        return [
            'name' => $columnName,
            'table' => '',
            'type' => $this->getValueType($value),
            'length' => is_string($value) ? strlen($value) : 0,
            'precision' => 0,
            'unsigned' => false,
            'nullable' => $value === null,
            'auto_increment' => false,
            'primary_key' => $columnName === 'id',
            'unique' => false,
            'multiple_key' => false,
        ];
    }

    public function free(): void
    {
        $this->data = [];
        $this->currentRow = 0;
    }

    // Iterator interface methods
    public function current(): mixed
    {
        return $this->data[$this->currentRow] ?? null;
    }

    public function key(): mixed
    {
        return $this->currentRow;
    }

    public function next(): void
    {
        $this->currentRow++;
    }

    public function rewind(): void
    {
        $this->currentRow = 0;
    }

    public function valid(): bool
    {
        return isset($this->data[$this->currentRow]);
    }

    // Countable interface method
    public function count(): int
    {
        return count($this->data);
    }

    private function getValueType($value): string
    {
        if ($value === null) {
            return 'null';
        }
        
        if (is_int($value)) {
            return 'int';
        }
        
        if (is_float($value)) {
            return 'float';
        }
        
        if (is_bool($value)) {
            return 'boolean';
        }
        
        if (is_string($value)) {
            return 'varchar';
        }
        
        if (is_array($value)) {
            return 'json';
        }
        
        return 'unknown';
    }
}