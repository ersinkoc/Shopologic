<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

class QueryBuilder
{
    protected ConnectionInterface $connection;
    protected string $table;
    protected array $columns = ['*'];
    protected array $wheres = [];
    protected array $joins = [];
    protected array $orders = [];
    protected array $groups = [];
    protected array $havings = [];
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;
    protected array $bindings = [];

    public function __construct(ConnectionInterface $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn(string $column, array $values, string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'not_in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereBetween(string $column, array $values, string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereNull(string $column, string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean
        ];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => $boolean
        ];

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc'
        ];

        return $this;
    }

    public function groupBy(string ...$groups): self
    {
        $this->groups = array_merge($this->groups, $groups);
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }

    public function get(): ResultInterface
    {
        $sql = $this->toSql();
        return $this->connection->query($sql, $this->bindings);
    }

    public function first(): ?array
    {
        $result = $this->limit(1)->get();
        return $result->fetch();
    }

    public function find(mixed $id, string $column = 'id'): ?array
    {
        return $this->where($column, $id)->first();
    }

    public function count(string $column = '*'): int
    {
        $original = $this->columns;
        $this->columns = ["COUNT({$column}) as aggregate"];
        
        $result = $this->get();
        $this->columns = $original;
        
        $row = $result->fetch();
        return (int) $row['aggregate'];
    }

    public function insert(array $values): bool
    {
        if (empty($values)) {
            return false;
        }

        $columns = array_keys($values);
        $placeholders = array_fill(0, count($values), '?');

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        return $this->connection->execute($sql, array_values($values)) > 0;
    }

    public function update(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        $columns = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $columns[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $columns);

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWheres();
            $bindings = array_merge($bindings, $this->bindings);
        }

        return $this->connection->execute($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWheres();
        }

        return $this->connection->execute($sql, $this->bindings);
    }

    public function toSql(): string
    {
        // SECURITY: Sanitize all column and table names
        $safeColumns = array_map([$this, 'sanitizeColumnName'], $this->columns);
        $safeTable = $this->sanitizeColumnName($this->table);

        $sql = "SELECT " . implode(', ', $safeColumns) . " FROM {$safeTable}";

        if (!empty($this->joins)) {
            $sql .= " " . $this->buildJoins();
        }

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWheres();
        }

        if (!empty($this->groups)) {
            // SECURITY: Sanitize GROUP BY columns
            $safeGroups = array_map([$this, 'sanitizeColumnName'], $this->groups);
            $sql .= " GROUP BY " . implode(', ', $safeGroups);
        }

        if (!empty($this->havings)) {
            $sql .= " HAVING " . $this->buildHavings();
        }

        if (!empty($this->orders)) {
            $sql .= " ORDER BY " . $this->buildOrders();
        }

        if ($this->limitValue !== null) {
            // SECURITY: Ensure LIMIT is an integer
            $sql .= " LIMIT " . (int)$this->limitValue;
        }

        if ($this->offsetValue !== null) {
            // SECURITY: Ensure OFFSET is an integer
            $sql .= " OFFSET " . (int)$this->offsetValue;
        }

        return $sql;
    }

    protected function buildWheres(): string
    {
        $sql = [];

        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : strtoupper($where['boolean']) . ' ';

            // SECURITY: Sanitize column names to prevent SQL injection
            $safeColumn = $this->sanitizeColumnName($where['column']);

            switch ($where['type']) {
                case 'basic':
                    $sql[] = $boolean . "{$safeColumn} {$where['operator']} ?";
                    break;
                case 'in':
                    $placeholders = str_repeat('?,', count($where['values']) - 1) . '?';
                    $sql[] = $boolean . "{$safeColumn} IN ({$placeholders})";
                    break;
                case 'not_in':
                    $placeholders = str_repeat('?,', count($where['values']) - 1) . '?';
                    $sql[] = $boolean . "{$safeColumn} NOT IN ({$placeholders})";
                    break;
                case 'between':
                    $sql[] = $boolean . "{$safeColumn} BETWEEN ? AND ?";
                    break;
                case 'null':
                    $sql[] = $boolean . "{$safeColumn} IS NULL";
                    break;
                case 'not_null':
                    $sql[] = $boolean . "{$safeColumn} IS NOT NULL";
                    break;
            }
        }

        return implode(' ', $sql);
    }

    /**
     * Sanitize column name to prevent SQL injection
     * SECURITY: Only allow alphanumeric, underscores, dots (for table.column), and backticks/quotes
     */
    protected function sanitizeColumnName(string $column): string
    {
        // Allow table.column syntax and quoted identifiers
        // Match: word, word.word, `word`, `word`.`word`, "word", etc.
        if (!preg_match('/^[\w\.`"\[\]]+$/', $column)) {
            throw new \InvalidArgumentException(
                "Invalid column name: '{$column}'. Column names can only contain alphanumeric characters, " .
                "underscores, dots, and quotes."
            );
        }

        return $column;
    }

    protected function buildJoins(): string
    {
        $sql = [];

        foreach ($this->joins as $join) {
            $type = strtoupper($join['type']);
            // SECURITY: Sanitize table and column names
            $safeTable = $this->sanitizeColumnName($join['table']);
            $safeFirst = $this->sanitizeColumnName($join['first']);
            $safeSecond = $this->sanitizeColumnName($join['second']);
            $sql[] = "{$type} JOIN {$safeTable} ON {$safeFirst} {$join['operator']} {$safeSecond}";
        }

        return implode(' ', $sql);
    }

    protected function buildOrders(): string
    {
        $sql = [];

        foreach ($this->orders as $order) {
            // SECURITY: Sanitize column names
            $safeColumn = $this->sanitizeColumnName($order['column']);
            $safeDirection = strtoupper($order['direction']) === 'DESC' ? 'DESC' : 'ASC';
            $sql[] = "{$safeColumn} {$safeDirection}";
        }

        return implode(', ', $sql);
    }

    protected function buildHavings(): string
    {
        $sql = [];

        foreach ($this->havings as $having) {
            // SECURITY: Sanitize column names
            $safeColumn = $this->sanitizeColumnName($having['column']);
            $sql[] = "{$safeColumn} {$having['operator']} ?";
        }

        return implode(' AND ', $sql);
    }
}