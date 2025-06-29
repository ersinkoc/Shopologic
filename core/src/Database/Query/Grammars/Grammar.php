<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Query\Grammars;

use Shopologic\Core\Database\Query\Builder;
use Shopologic\Core\Database\Query\JoinClause;
use Shopologic\Core\Database\Expression;

/**
 * Base SQL grammar for query building
 */
abstract class Grammar
{
    /**
     * The grammar table prefix
     */
    protected string $tablePrefix = '';

    /**
     * The components that make up a select clause
     */
    protected array $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'lock',
    ];

    /**
     * Compile a select query into SQL
     */
    public function compileSelect(Builder $query): string
    {
        if ($query->unions && $query->aggregate) {
            return $this->compileUnionAggregate($query);
        }

        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        $sql = trim($this->concatenate($this->compileComponents($query)));

        $query->columns = $original;

        if ($query->unions) {
            $sql = $this->compileUnions($query, $sql);
        }

        return $sql;
    }

    /**
     * Compile the components necessary for a select clause
     */
    protected function compileComponents(Builder $query): array
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            if (isset($query->$component)) {
                $method = 'compile' . ucfirst($component);
                $sql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $sql;
    }

    /**
     * Compile an aggregated select clause
     */
    protected function compileAggregate(Builder $query, array $aggregate): string
    {
        $column = $this->columnize($aggregate['columns']);

        if (is_array($query->distinct)) {
            $column = 'distinct ' . $this->columnize($query->distinct);
        } elseif ($query->distinct && $column !== '*') {
            $column = 'distinct ' . $column;
        }

        return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
    }

    /**
     * Compile the "select *" portion of the query
     */
    protected function compileColumns(Builder $query, array $columns): string
    {
        if (!is_null($query->aggregate)) {
            return '';
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';

        return $select . $this->columnize($columns);
    }

    /**
     * Compile the "from" portion of the query
     */
    protected function compileFrom(Builder $query, string $table): string
    {
        return 'from ' . $this->wrapTable($table);
    }

    /**
     * Compile the "join" portions of the query
     */
    protected function compileJoins(Builder $query, array $joins): string
    {
        $sql = [];

        foreach ($joins as $join) {
            $table = $this->wrapTable($join->table);
            $type = $join->type;

            $sql[] = trim("{$type} join {$table} {$this->compileWheres($join)}");
        }

        return implode(' ', $sql);
    }

    /**
     * Compile the "where" portions of the query
     */
    protected function compileWheres(Builder $query): string
    {
        if (is_null($query->wheres)) {
            return '';
        }

        if (count($sql = $this->compileWheresToArray($query)) > 0) {
            return $this->concatenateWhereClauses($query, $sql);
        }

        return '';
    }

    /**
     * Get an array of all the where clauses for the query
     */
    protected function compileWheresToArray(Builder $query): array
    {
        $sql = [];

        foreach ($query->wheres as $where) {
            $method = "where{$where['type']}";
            $sql[] = $where['boolean'] . ' ' . $this->$method($query, $where);
        }

        return $sql;
    }

    /**
     * Format the where clause statements into one string
     */
    protected function concatenateWhereClauses(Builder $query, array $sql): string
    {
        $conjunction = $query instanceof JoinClause ? 'on' : 'where';

        return $conjunction . ' ' . $this->removeLeadingBoolean(implode(' ', $sql));
    }

    /**
     * Compile a basic where clause
     */
    protected function whereBasic(Builder $query, array $where): string
    {
        $value = $this->parameter($where['value']);
        $operator = str_replace('?', '??', $where['operator']);

        return $this->wrap($where['column']) . ' ' . $operator . ' ' . $value;
    }

    /**
     * Compile a "where in" clause
     */
    protected function whereIn(Builder $query, array $where): string
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' in (' . $this->parameterize($where['values']) . ')';
        }

        return '0 = 1';
    }

    /**
     * Compile a "where not in" clause
     */
    protected function whereNotIn(Builder $query, array $where): string
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' not in (' . $this->parameterize($where['values']) . ')';
        }

        return '1 = 1';
    }

    /**
     * Compile a "where null" clause
     */
    protected function whereNull(Builder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' is null';
    }

    /**
     * Compile a "where not null" clause
     */
    protected function whereNotNull(Builder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' is not null';
    }

    /**
     * Compile a "where between" clause
     */
    protected function whereBetween(Builder $query, array $where): string
    {
        $between = $where['not'] ? 'not between' : 'between';
        $min = $this->parameter($where['values'][0]);
        $max = $this->parameter($where['values'][1]);

        return $this->wrap($where['column']) . ' ' . $between . ' ' . $min . ' and ' . $max;
    }

    /**
     * Compile a "where exists" clause
     */
    protected function whereExists(Builder $query, array $where): string
    {
        return 'exists (' . $this->compileSelect($where['query']) . ')';
    }

    /**
     * Compile a "where not exists" clause
     */
    protected function whereNotExists(Builder $query, array $where): string
    {
        return 'not exists (' . $this->compileSelect($where['query']) . ')';
    }

    /**
     * Compile a raw where clause
     */
    protected function whereRaw(Builder $query, array $where): string
    {
        return $where['sql'];
    }

    /**
     * Compile the "group by" portions of the query
     */
    protected function compileGroups(Builder $query, array $groups): string
    {
        return 'group by ' . $this->columnize($groups);
    }

    /**
     * Compile the "having" portions of the query
     */
    protected function compileHavings(Builder $query, array $havings): string
    {
        $sql = implode(' ', array_map([$this, 'compileHaving'], $havings));

        return 'having ' . $this->removeLeadingBoolean($sql);
    }

    /**
     * Compile a single having clause
     */
    protected function compileHaving(array $having): string
    {
        if ($having['type'] === 'Raw') {
            return $having['boolean'] . ' ' . $having['sql'];
        }

        return $this->compileBasicHaving($having);
    }

    /**
     * Compile a basic having clause
     */
    protected function compileBasicHaving(array $having): string
    {
        $column = $this->wrap($having['column']);
        $parameter = $this->parameter($having['value']);

        return $having['boolean'] . ' ' . $column . ' ' . $having['operator'] . ' ' . $parameter;
    }

    /**
     * Compile the "order by" portions of the query
     */
    protected function compileOrders(Builder $query, array $orders): string
    {
        if (!empty($orders)) {
            return 'order by ' . implode(', ', $this->compileOrdersToArray($query, $orders));
        }

        return '';
    }

    /**
     * Compile the query orders to an array
     */
    protected function compileOrdersToArray(Builder $query, array $orders): array
    {
        return array_map(function ($order) {
            return $this->wrap($order['column']) . ' ' . $order['direction'];
        }, $orders);
    }

    /**
     * Compile the "limit" portions of the query
     */
    abstract protected function compileLimit(Builder $query, int $limit): string;

    /**
     * Compile the "offset" portions of the query
     */
    abstract protected function compileOffset(Builder $query, int $offset): string;

    /**
     * Compile the lock into SQL
     */
    protected function compileLock(Builder $query, $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $value ? 'for update' : 'lock in share mode';
    }

    /**
     * Compile an insert statement into SQL
     */
    public function compileInsert(Builder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        if (empty($values)) {
            return "insert into {$table} default values";
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));
        $parameters = $this->parameterize(reset($values));

        $sql = "insert into {$table} ({$columns}) values ({$parameters})";

        if (count($values) > 1) {
            $sql .= ', ' . implode(', ', array_map(function ($record) {
                return '(' . $this->parameterize($record) . ')';
            }, array_slice($values, 1)));
        }

        return $sql;
    }

    /**
     * Compile an insert and get ID statement into SQL
     */
    abstract public function compileInsertGetId(Builder $query, array $values, ?string $sequence = null): string;

    /**
     * Compile an update statement into SQL
     */
    public function compileUpdate(Builder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        $columns = $this->compileUpdateColumns($values);

        $where = $this->compileWheres($query);

        return trim("update {$table} set {$columns} {$where}");
    }

    /**
     * Compile the columns for an update statement
     */
    protected function compileUpdateColumns(array $values): string
    {
        return implode(', ', array_map(function ($value, $key) {
            return $this->wrap($key) . ' = ' . $this->parameter($value);
        }, $values, array_keys($values)));
    }

    /**
     * Compile a delete statement into SQL
     */
    public function compileDelete(Builder $query): string
    {
        $table = $this->wrapTable($query->from);

        $where = $this->compileWheres($query);

        return trim("delete from {$table} {$where}");
    }

    /**
     * Compile a truncate table statement into SQL
     */
    public function compileTruncate(Builder $query): array
    {
        return ['truncate table ' . $this->wrapTable($query->from) => []];
    }

    /**
     * Wrap a value in keyword identifiers
     */
    public function wrap($value): string
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        if (strpos(strtolower($value), ' as ') !== false) {
            return $this->wrapAliasedValue($value);
        }

        return $this->wrapSegments(explode('.', $value));
    }

    /**
     * Wrap a value that has an alias
     */
    protected function wrapAliasedValue(string $value): string
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        return $this->wrap($segments[0]) . ' as ' . $this->wrapValue($segments[1]);
    }

    /**
     * Wrap the given value segments
     */
    protected function wrapSegments(array $segments): string
    {
        return implode('.', array_map([$this, 'wrapValue'], $segments));
    }

    /**
     * Wrap a single string in keyword identifiers
     */
    abstract protected function wrapValue(string $value): string;

    /**
     * Wrap a table in keyword identifiers
     */
    public function wrapTable($table): string
    {
        if (!$this->isExpression($table)) {
            return $this->wrap($this->tablePrefix . $table);
        }

        return $this->getValue($table);
    }

    /**
     * Convert an array of column names into a delimited string
     */
    public function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /**
     * Create query parameter place-holders for an array
     */
    public function parameterize(array $values): string
    {
        return implode(', ', array_map([$this, 'parameter'], $values));
    }

    /**
     * Get the appropriate query parameter place-holder for a value
     */
    public function parameter($value): string
    {
        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }

    /**
     * Quote the given string literal
     */
    public function quoteString(string $value): string
    {
        return "'$value'";
    }

    /**
     * Determine if the given value is a raw expression
     */
    public function isExpression($value): bool
    {
        return $value instanceof Expression;
    }

    /**
     * Get the value of a raw expression
     */
    public function getValue($expression): string
    {
        return $expression->getValue();
    }

    /**
     * Remove the leading boolean from a statement
     */
    protected function removeLeadingBoolean(string $value): string
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    /**
     * Concatenate an array of segments, removing empties
     */
    protected function concatenate(array $segments): string
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    /**
     * Set the grammar's table prefix
     */
    public function setTablePrefix(string $prefix): void
    {
        $this->tablePrefix = $prefix;
    }

    /**
     * Get the grammar's table prefix
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }
    
    /**
     * Prepare the bindings for an update statement
     */
    public function prepareBindingsForUpdate(array $bindings, array $values): array
    {
        return array_merge(
            array_values($values),
            $bindings['where'] ?? []
        );
    }
}