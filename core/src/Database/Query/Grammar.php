<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Query;

class Grammar
{
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

    public function compileSelect(Builder $query): string
    {
        if ($query->unions && $query->aggregate) {
            return $this->compileUnionAggregate($query);
        }
        
        $sql = $this->concatenate($this->compileComponents($query));
        
        if ($query->unions) {
            $sql = $this->wrapUnion($sql) . ' ' . $this->compileUnions($query);
        }
        
        return $sql;
    }

    protected function compileComponents(Builder $query): array
    {
        $sql = [];
        
        foreach ($this->selectComponents as $component) {
            if ($component === 'aggregate' && empty($query->aggregate)) {
                continue;
            }
            
            if (isset($query->$component) && !is_null($query->$component)) {
                $method = 'compile' . ucfirst($component);
                $sql[$component] = $this->$method($query, $query->$component);
            }
        }
        
        return $sql;
    }

    protected function compileAggregate(Builder $query, array $aggregate): string
    {
        $column = $this->columnize($aggregate['columns']);
        
        if ($query->distinct && $column !== '*') {
            $column = 'distinct ' . $column;
        }
        
        return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
    }

    protected function compileColumns(Builder $query, array $columns): string
    {
        if (!is_null($query->aggregate)) {
            return '';
        }
        
        $select = $query->distinct ? 'select distinct ' : 'select ';
        
        // Default to * if no columns specified
        if (empty($columns)) {
            $columns = ['*'];
        }
        
        return $select . $this->columnize($columns);
    }

    protected function compileFrom(Builder $query, string $table): string
    {
        return 'from ' . $this->wrapTable($table);
    }

    protected function compileJoins(Builder $query, array $joins): string
    {
        return implode(' ', array_map(function ($join) {
            $table = $this->wrapTable($join->table);
            $clauses = [];
            
            foreach ($join->clauses as $clause) {
                $clauses[] = $this->compileJoinConstraint($clause);
            }
            
            $clauses = implode(' ', $clauses);
            
            return "{$join->type} join {$table} {$clauses}";
        }, $joins));
    }

    protected function compileJoinConstraint(array $clause): string
    {
        $first = $this->wrap($clause['first']);
        $second = $clause['where'] ? '?' : $this->wrap($clause['second']);
        
        return "{$clause['boolean']} {$first} {$clause['operator']} {$second}";
    }

    protected function compileWheres(Builder $query): string
    {
        if (empty($query->wheres)) {
            return '';
        }
        
        $sql = implode(' ', array_map(function ($where) use ($query) {
            return $where['boolean'] . ' ' . $this->{'where' . $where['type']}($query, $where);
        }, $query->wheres));
        
        return 'where ' . $this->removeLeadingBoolean($sql);
    }

    protected function whereBasic(Builder $query, array $where): string
    {
        $value = $where['value'];
        $operator = $where['operator'];
        
        return $this->wrap($where['column']) . ' ' . $operator . ' ' . $this->parameter($value);
    }

    protected function whereIn(Builder $query, array $where): string
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }
        
        $values = $this->parameterize($where['values']);
        
        return $this->wrap($where['column']) . ' in (' . $values . ')';
    }

    protected function whereNotIn(Builder $query, array $where): string
    {
        if (empty($where['values'])) {
            return '1 = 1';
        }
        
        $values = $this->parameterize($where['values']);
        
        return $this->wrap($where['column']) . ' not in (' . $values . ')';
    }

    protected function whereNull(Builder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' is null';
    }

    protected function whereNotNull(Builder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' is not null';
    }

    protected function whereBetween(Builder $query, array $where): string
    {
        $between = ' between ? and ?';
        
        return $this->wrap($where['column']) . $between;
    }

    protected function whereNotBetween(Builder $query, array $where): string
    {
        $between = ' not between ? and ?';
        
        return $this->wrap($where['column']) . $between;
    }

    protected function whereNested(Builder $query, array $where): string
    {
        $nested = $where['query'];
        
        $sql = substr($this->compileWheres($nested), 6);
        
        return '(' . $sql . ')';
    }

    protected function compileGroups(Builder $query, array $groups): string
    {
        return 'group by ' . $this->columnize($groups);
    }

    protected function compileHavings(Builder $query, array $havings): string
    {
        $sql = implode(' ', array_map(function ($having) {
            return $having['boolean'] . ' ' . $this->compileHaving($having);
        }, $havings));
        
        return 'having ' . $this->removeLeadingBoolean($sql);
    }

    protected function compileHaving(array $having): string
    {
        if ($having['type'] === 'Basic') {
            return $this->wrap($having['column']) . ' ' . $having['operator'] . ' ' . $this->parameter($having['value']);
        }
        
        return '';
    }

    protected function compileOrders(Builder $query, array $orders): string
    {
        return 'order by ' . implode(', ', array_map(function ($order) {
            return $this->wrap($order['column']) . ' ' . $order['direction'];
        }, $orders));
    }

    protected function compileLimit(Builder $query, int $limit): string
    {
        return 'limit ' . $limit;
    }

    protected function compileOffset(Builder $query, int $offset): string
    {
        return 'offset ' . $offset;
    }

    protected function compileLock(Builder $query, $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        
        return $value ? 'for update' : '';
    }

    protected function compileUnions(Builder $query): string
    {
        $sql = '';
        
        foreach ($query->unions as $union) {
            $sql .= $this->compileUnion($union);
        }
        
        if ($query->unionOrders) {
            $sql .= ' ' . $this->compileOrders($query, $query->unionOrders);
        }
        
        if ($query->unionLimit) {
            $sql .= ' ' . $this->compileLimit($query, $query->unionLimit);
        }
        
        if ($query->unionOffset) {
            $sql .= ' ' . $this->compileOffset($query, $query->unionOffset);
        }
        
        return ltrim($sql);
    }

    protected function compileUnion(array $union): string
    {
        $conjuction = $union['all'] ? ' union all ' : ' union ';
        
        return $conjuction . '(' . $union['query']->toSql() . ')';
    }

    protected function wrapUnion(string $sql): string
    {
        return '(' . $sql . ')';
    }

    protected function compileUnionAggregate(Builder $query): string
    {
        $query->aggregate = null;
        
        $sql = $this->compileAggregate($query, $query->aggregate) . " from ({$this->compileSelect($query)}) as temp_table";
        
        return $sql;
    }

    protected function concatenate(array $segments): string
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    protected function removeLeadingBoolean(string $value): string
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    public function wrap($value): string
    {
        if (strpos(strtolower($value), ' as ') !== false) {
            return $this->wrapAliasedValue($value);
        }
        
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }
        
        if (strpos($value, '.') !== false) {
            return $this->wrapSegments(explode('.', $value));
        }
        
        return $this->wrapValue($value);
    }

    protected function wrapAliasedValue(string $value): string
    {
        $segments = preg_split('/\s+as\s+/i', $value);
        
        return $this->wrap($segments[0]) . ' as ' . $this->wrapValue($segments[1]);
    }

    protected function wrapSegments(array $segments): string
    {
        return implode('.', array_map(function ($segment) {
            return $this->wrapValue($segment);
        }, $segments));
    }

    protected function wrapValue(string $value): string
    {
        if ($value !== '*') {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        
        return $value;
    }

    public function wrapTable($table): string
    {
        return $this->wrap($table);
    }

    protected function isExpression($value): bool
    {
        return $value instanceof Expression;
    }

    protected function getValue($expression): string
    {
        return $expression->getValue();
    }

    public function columnize(array $columns): string
    {
        return implode(', ', array_map(function ($column) {
            return $this->wrap($column);
        }, $columns));
    }

    public function parameterize(array $values): string
    {
        return implode(', ', array_map(function () {
            return '?';
        }, $values));
    }

    public function parameter($value): string
    {
        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }
}