<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

/**
 * MySQL query result wrapper
 */
class MySQLResult implements ResultInterface
{
    private ?\mysqli_result $result;
    private \mysqli $connection;
    private int $affectedRows;
    private int $currentRow = 0;

    public function __construct($result, \mysqli $connection, ?int $affectedRows = null)
    {
        $this->result = $result instanceof \mysqli_result ? $result : null;
        $this->connection = $connection;
        $this->affectedRows = $affectedRows ?? ($this->result ? $this->result->num_rows : 0);
    }

    public function fetch(): ?array
    {
        if (!$this->result) {
            return null;
        }

        $row = $this->result->fetch_assoc();
        
        if ($row !== null) {
            $this->currentRow++;
        }
        
        return $row;
    }

    public function fetchAll(): array
    {
        if (!$this->result) {
            return [];
        }

        return $this->result->fetch_all(MYSQLI_ASSOC);
    }

    public function fetchColumn(int $column = 0)
    {
        if (!$this->result) {
            return null;
        }

        $row = $this->result->fetch_row();
        
        if ($row === null) {
            return null;
        }
        
        $this->currentRow++;
        
        return $row[$column] ?? null;
    }

    public function fetchObject(?string $className = null, array $constructorArgs = []): ?object
    {
        if (!$this->result) {
            return null;
        }

        if ($className) {
            $object = $this->result->fetch_object($className, $constructorArgs);
        } else {
            $object = $this->result->fetch_object();
        }
        
        if ($object !== null) {
            $this->currentRow++;
        }
        
        return $object;
    }

    public function rowCount(): int
    {
        if ($this->result) {
            return $this->result->num_rows;
        }
        
        return $this->affectedRows;
    }

    public function columnCount(): int
    {
        if (!$this->result) {
            return 0;
        }

        return $this->result->field_count;
    }

    public function getColumnMeta(int $column): ?array
    {
        if (!$this->result) {
            return null;
        }

        $field = $this->result->fetch_field_direct($column);
        
        if (!$field) {
            return null;
        }

        return [
            'name' => $field->name,
            'table' => $field->table,
            'type' => $this->mapFieldType($field->type),
            'length' => $field->length,
            'precision' => $field->decimals,
            'unsigned' => ($field->flags & MYSQLI_UNSIGNED_FLAG) !== 0,
            'nullable' => ($field->flags & MYSQLI_NOT_NULL_FLAG) === 0,
            'auto_increment' => ($field->flags & MYSQLI_AUTO_INCREMENT_FLAG) !== 0,
            'primary_key' => ($field->flags & MYSQLI_PRI_KEY_FLAG) !== 0,
            'unique' => ($field->flags & MYSQLI_UNIQUE_KEY_FLAG) !== 0,
            'multiple_key' => ($field->flags & MYSQLI_MULTIPLE_KEY_FLAG) !== 0,
        ];
    }

    public function free(): void
    {
        if ($this->result) {
            $this->result->free();
            $this->result = null;
        }
    }

    /**
     * Map MySQL field types to generic types
     */
    private function mapFieldType(int $type): string
    {
        $typeMap = [
            MYSQLI_TYPE_DECIMAL => 'decimal',
            MYSQLI_TYPE_TINY => 'int',
            MYSQLI_TYPE_SHORT => 'int',
            MYSQLI_TYPE_LONG => 'int',
            MYSQLI_TYPE_FLOAT => 'float',
            MYSQLI_TYPE_DOUBLE => 'double',
            MYSQLI_TYPE_NULL => 'null',
            MYSQLI_TYPE_TIMESTAMP => 'timestamp',
            MYSQLI_TYPE_LONGLONG => 'bigint',
            MYSQLI_TYPE_INT24 => 'int',
            MYSQLI_TYPE_DATE => 'date',
            MYSQLI_TYPE_TIME => 'time',
            MYSQLI_TYPE_DATETIME => 'datetime',
            MYSQLI_TYPE_YEAR => 'year',
            MYSQLI_TYPE_NEWDATE => 'date',
            MYSQLI_TYPE_VARCHAR => 'varchar',
            MYSQLI_TYPE_BIT => 'bit',
            MYSQLI_TYPE_TIMESTAMP2 => 'timestamp',
            MYSQLI_TYPE_DATETIME2 => 'datetime',
            MYSQLI_TYPE_TIME2 => 'time',
            MYSQLI_TYPE_JSON => 'json',
            MYSQLI_TYPE_NEWDECIMAL => 'decimal',
            MYSQLI_TYPE_ENUM => 'enum',
            MYSQLI_TYPE_SET => 'set',
            MYSQLI_TYPE_TINY_BLOB => 'blob',
            MYSQLI_TYPE_MEDIUM_BLOB => 'blob',
            MYSQLI_TYPE_LONG_BLOB => 'blob',
            MYSQLI_TYPE_BLOB => 'blob',
            MYSQLI_TYPE_VAR_STRING => 'varchar',
            MYSQLI_TYPE_STRING => 'char',
            MYSQLI_TYPE_GEOMETRY => 'geometry',
        ];

        return $typeMap[$type] ?? 'unknown';
    }
}