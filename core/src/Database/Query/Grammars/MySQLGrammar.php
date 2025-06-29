<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Query\Grammars;

use Shopologic\Core\Database\Query\Builder;

/**
 * MySQL-specific SQL grammar
 */
class MySQLGrammar extends Grammar
{
    /**
     * The grammar specific operators
     */
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>', '&~',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];

    /**
     * Compile the "limit" portions of the query
     */
    protected function compileLimit(Builder $query, int $limit): string
    {
        return 'limit ' . $limit;
    }

    /**
     * Compile the "offset" portions of the query
     */
    protected function compileOffset(Builder $query, int $offset): string
    {
        // MySQL requires LIMIT when using OFFSET
        // If no limit is set, use a very large number
        if (is_null($query->limit)) {
            return 'limit 18446744073709551615 offset ' . $offset;
        }
        
        return 'offset ' . $offset;
    }

    /**
     * Compile an insert and get ID statement into SQL
     */
    public function compileInsertGetId(Builder $query, array $values, ?string $sequence = null): string
    {
        // MySQL doesn't need the sequence parameter
        return $this->compileInsert($query, $values);
    }

    /**
     * Compile the lock into SQL
     */
    protected function compileLock(Builder $query, $value): string
    {
        if (!is_string($value)) {
            return $value ? 'for update' : 'lock in share mode';
        }

        return $value;
    }

    /**
     * Compile an update statement with joins into SQL
     */
    public function compileUpdate(Builder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        if (!isset($query->joins)) {
            return parent::compileUpdate($query, $values);
        }

        // Build the update statement with joins
        $joins = $this->compileJoins($query, $query->joins);
        $columns = $this->compileUpdateColumns($values);
        $where = $this->compileWheres($query);

        return trim("update {$table} {$joins} set {$columns} {$where}");
    }

    /**
     * Compile a delete statement with joins into SQL
     */
    public function compileDelete(Builder $query): string
    {
        $table = $this->wrapTable($query->from);

        if (!isset($query->joins)) {
            return parent::compileDelete($query);
        }

        $alias = stripos($table, ' as ') !== false 
            ? explode(' as ', $table)[1] 
            : $table;

        $joins = $this->compileJoins($query, $query->joins);
        $where = $this->compileWheres($query);

        return trim("delete {$alias} from {$table} {$joins} {$where}");
    }

    /**
     * Wrap a single string in keyword identifiers
     */
    protected function wrapValue(string $value): string
    {
        if ($value !== '*') {
            return '`' . str_replace('`', '``', $value) . '`';
        }

        return $value;
    }

    /**
     * Compile a "where date" clause
     */
    public function whereDate(Builder $query, array $where): string
    {
        return $this->dateBasedWhere('date', $query, $where);
    }

    /**
     * Compile a "where time" clause
     */
    public function whereTime(Builder $query, array $where): string
    {
        return $this->dateBasedWhere('time', $query, $where);
    }

    /**
     * Compile a "where day" clause
     */
    public function whereDay(Builder $query, array $where): string
    {
        return $this->dateBasedWhere('day', $query, $where);
    }

    /**
     * Compile a "where month" clause
     */
    public function whereMonth(Builder $query, array $where): string
    {
        return $this->dateBasedWhere('month', $query, $where);
    }

    /**
     * Compile a "where year" clause
     */
    public function whereYear(Builder $query, array $where): string
    {
        return $this->dateBasedWhere('year', $query, $where);
    }

    /**
     * Compile a date based where clause
     */
    protected function dateBasedWhere(string $type, Builder $query, array $where): string
    {
        $value = $this->parameter($where['value']);

        switch ($type) {
            case 'date':
                return 'date(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
            case 'time':
                return 'time(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
            case 'day':
                return 'day(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
            case 'month':
                return 'month(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
            case 'year':
                return 'year(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
        }
    }

    /**
     * Compile a "JSON contains" statement into SQL
     */
    public function compileJsonContains(string $column, string $value): string
    {
        $column = $this->wrap($column);
        return "json_contains({$column}, {$value})";
    }

    /**
     * Compile a "JSON length" statement into SQL
     */
    public function compileJsonLength(string $column, string $operator, string $value): string
    {
        $column = $this->wrap($column);
        return "json_length({$column}) {$operator} {$value}";
    }

    /**
     * Compile the random statement into SQL
     */
    public function compileRandom(string $seed = ''): string
    {
        return 'RAND(' . $seed . ')';
    }

    /**
     * Compile the SQL statement to define a savepoint
     */
    public function compileSavepoint(string $name): string
    {
        return 'SAVEPOINT ' . $name;
    }

    /**
     * Compile the SQL statement to execute a savepoint rollback
     */
    public function compileSavepointRollBack(string $name): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }

    /**
     * Compile an "upsert" statement into SQL
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update): string
    {
        $sql = $this->compileInsert($query, $values);

        $sql .= ' on duplicate key update ';

        $columns = implode(', ', array_map(function ($value, $key) {
            return is_numeric($key)
                ? $this->wrap($value) . ' = values(' . $this->wrap($value) . ')'
                : $this->wrap($key) . ' = ' . $this->parameter($value);
        }, $update, array_keys($update)));

        return $sql . $columns;
    }

    /**
     * Prepare the bindings for an update statement
     */
    public function prepareBindingsForUpdate(array $bindings, array $values): array
    {
        // MySQL needs values first, then where clause bindings
        return array_merge(
            array_values($values),
            $bindings['join'] ?? [],
            $bindings['where'] ?? []
        );
    }
}