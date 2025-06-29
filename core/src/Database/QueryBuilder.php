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
        $sql = "SELECT " . implode(', ', $this->columns) . " FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= " " . $this->buildJoins();
        }

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWheres();
        }

        if (!empty($this->groups)) {
            $sql .= " GROUP BY " . implode(', ', $this->groups);
        }

        if (!empty($this->havings)) {
            $sql .= " HAVING " . $this->buildHavings();
        }

        if (!empty($this->orders)) {
            $sql .= " ORDER BY " . $this->buildOrders();
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    protected function buildWheres(): string
    {
        $sql = [];

        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : strtoupper($where['boolean']) . ' ';

            switch ($where['type']) {
                case 'basic':
                    $sql[] = $boolean . "{$where['column']} {$where['operator']} ?";
                    break;
                case 'in':
                    $placeholders = str_repeat('?,', count($where['values']) - 1) . '?';
                    $sql[] = $boolean . "{$where['column']} IN ({$placeholders})";
                    break;
                case 'not_in':
                    $placeholders = str_repeat('?,', count($where['values']) - 1) . '?';
                    $sql[] = $boolean . "{$where['column']} NOT IN ({$placeholders})";
                    break;
                case 'between':
                    $sql[] = $boolean . "{$where['column']} BETWEEN ? AND ?";
                    break;
                case 'null':
                    $sql[] = $boolean . "{$where['column']} IS NULL";
                    break;
                case 'not_null':
                    $sql[] = $boolean . "{$where['column']} IS NOT NULL";
                    break;
            }
        }

        return implode(' ', $sql);
    }

    protected function buildJoins(): string
    {
        $sql = [];

        foreach ($this->joins as $join) {
            $type = strtoupper($join['type']);
            $sql[] = "{$type} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        return implode(' ', $sql);
    }

    protected function buildOrders(): string
    {
        $sql = [];

        foreach ($this->orders as $order) {
            $sql[] = "{$order['column']} " . strtoupper($order['direction']);
        }

        return implode(', ', $sql);
    }

    protected function buildHavings(): string
    {
        $sql = [];

        foreach ($this->havings as $having) {
            $sql[] = "{$having['column']} {$having['operator']} ?";
        }

        return implode(' AND ', $sql);
    }
}