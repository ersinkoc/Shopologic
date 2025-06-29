<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Query\Grammars;

use Shopologic\Core\Database\Query\Builder;

/**
 * PostgreSQL-specific SQL grammar
 */
class PostgreSQLGrammar extends Grammar
{
    /**
     * All of the available clause operators
     */
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'ilike', 'not ilike',
        '~', '&', '|', '#', '<<', '>>', '<<=', '>>=',
        '&&', '@>', '<@', '?', '?|', '?&', '||', '-', '@?', '@@', '#-',
        'is distinct from', 'is not distinct from',
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
        return 'offset ' . $offset;
    }

    /**
     * Compile an insert and get ID statement into SQL
     */
    public function compileInsertGetId(Builder $query, array $values, ?string $sequence = null): string
    {
        if (!$sequence) {
            $sequence = 'id';
        }

        return $this->compileInsert($query, $values) . ' returning ' . $this->wrap($sequence);
    }

    /**
     * Compile an update statement into SQL with joins
     */
    public function compileUpdate(Builder $query, array $values): string
    {
        if (!isset($query->joins)) {
            return parent::compileUpdate($query, $values);
        }

        $table = $this->wrapTable($query->from);
        $columns = $this->compileUpdateColumns($values);
        $from = $this->compileUpdateFrom($query);
        $where = $this->compileUpdateWheres($query);

        return trim("update {$table} set {$columns} {$from} {$where}");
    }

    /**
     * Compile the "from" clause for an update with joins
     */
    protected function compileUpdateFrom(Builder $query): string
    {
        $froms = [];

        foreach ($query->joins as $join) {
            $froms[] = $this->wrapTable($join->table);
        }

        if (count($froms) > 0) {
            return 'from ' . implode(', ', $froms);
        }

        return '';
    }

    /**
     * Compile the "where" clause for an update with joins
     */
    protected function compileUpdateWheres(Builder $query): string
    {
        $baseWheres = $this->compileWheres($query);

        if (!isset($query->joins)) {
            return $baseWheres;
        }

        $joinWheres = $this->compileUpdateJoinWheres($query);

        if (trim($baseWheres) == '') {
            return 'where ' . $this->removeLeadingBoolean($joinWheres);
        }

        return $baseWheres . ' ' . $joinWheres;
    }

    /**
     * Compile the join clause for an update
     */
    protected function compileUpdateJoinWheres(Builder $query): string
    {
        $joinWheres = [];

        foreach ($query->joins as $join) {
            foreach ($join->wheres as $where) {
                $method = "where{$where['type']}";
                $joinWheres[] = $where['boolean'] . ' ' . $this->$method($query, $where);
            }
        }

        return implode(' ', $joinWheres);
    }

    /**
     * Compile a delete statement with joins into SQL
     */
    public function compileDelete(Builder $query): string
    {
        if (!isset($query->joins)) {
            return parent::compileDelete($query);
        }

        $table = $this->wrapTable($query->from);
        $using = $this->compileDeleteUsing($query);
        $where = $this->compileDeleteWheres($query);

        return trim("delete from {$table} {$using} {$where}");
    }

    /**
     * Compile the "using" clause for a delete with joins
     */
    protected function compileDeleteUsing(Builder $query): string
    {
        $tables = [];

        foreach ($query->joins as $join) {
            $tables[] = $this->wrapTable($join->table);
        }

        if (count($tables) > 0) {
            return 'using ' . implode(', ', $tables);
        }

        return '';
    }

    /**
     * Compile the "where" clause for a delete with joins
     */
    protected function compileDeleteWheres(Builder $query): string
    {
        $baseWheres = $this->compileWheres($query);

        if (!isset($query->joins)) {
            return $baseWheres;
        }

        $joinWheres = $this->compileDeleteJoinWheres($query);

        if (trim($baseWheres) == '') {
            return 'where ' . $this->removeLeadingBoolean($joinWheres);
        }

        return $baseWheres . ' ' . $joinWheres;
    }

    /**
     * Compile the join clause for a delete
     */
    protected function compileDeleteJoinWheres(Builder $query): string
    {
        $joinWheres = [];

        foreach ($query->joins as $join) {
            foreach ($join->wheres as $where) {
                $method = "where{$where['type']}";
                $joinWheres[] = $where['boolean'] . ' ' . $this->$method($query, $where);
            }
        }

        return implode(' ', $joinWheres);
    }

    /**
     * Compile a truncate table statement into SQL
     */
    public function compileTruncate(Builder $query): array
    {
        return ['truncate ' . $this->wrapTable($query->from) . ' restart identity cascade' => []];
    }

    /**
     * Wrap a single string in keyword identifiers
     */
    protected function wrapValue(string $value): string
    {
        if ($value !== '*') {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /**
     * Compile the lock into SQL
     */
    protected function compileLock(Builder $query, $value): string
    {
        if (!is_string($value)) {
            return $value ? 'for update' : 'for share';
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
                return $this->wrap($where['column']) . '::date ' . $where['operator'] . ' ' . $value;
            case 'time':
                return $this->wrap($where['column']) . '::time ' . $where['operator'] . ' ' . $value;
            case 'day':
                return 'extract(day from ' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
            case 'month':
                return 'extract(month from ' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
            case 'year':
                return 'extract(year from ' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
        }
    }

    /**
     * Compile a "JSON contains" statement into SQL
     */
    public function compileJsonContains(string $column, string $value): string
    {
        $column = $this->wrap($column);
        return "{$column} @> {$value}";
    }

    /**
     * Compile a "JSON length" statement into SQL
     */
    public function compileJsonLength(string $column, string $operator, string $value): string
    {
        $column = $this->wrap($column);
        return "jsonb_array_length({$column}) {$operator} {$value}";
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
}