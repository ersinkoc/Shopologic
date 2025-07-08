<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Query;

/**
 * Join Clause Builder
 * 
 * Represents a JOIN clause with support for complex conditions
 */
class JoinClause
{
    public string $type;
    public string $table;
    public array $clauses = [];
    
    public function __construct(string $type, string $table)
    {
        $this->type = $type;
        $this->table = $table;
    }
    
    /**
     * Add an ON condition
     */
    public function on(string $first, string $operator, string $second, string $boolean = 'and'): self
    {
        $this->clauses[] = [
            'type' => 'on',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean
        ];
        
        return $this;
    }
    
    /**
     * Add an OR ON condition
     */
    public function orOn(string $first, string $operator, string $second): self
    {
        return $this->on($first, $operator, $second, 'or');
    }
    
    /**
     * Add a WHERE condition to the join
     */
    public function where(string $column, string $operator, $value, string $boolean = 'and'): self
    {
        $this->clauses[] = [
            'type' => 'where',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean
        ];
        
        return $this;
    }
    
    /**
     * Add an OR WHERE condition
     */
    public function orWhere(string $column, string $operator, $value): self
    {
        return $this->where($column, $operator, $value, 'or');
    }
    
    /**
     * Add a WHERE IN condition
     */
    public function whereIn(string $column, array $values, string $boolean = 'and'): self
    {
        $this->clauses[] = [
            'type' => 'whereIn',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean
        ];
        
        return $this;
    }
    
    /**
     * Add a WHERE NULL condition
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): self
    {
        $this->clauses[] = [
            'type' => 'whereNull',
            'column' => $column,
            'boolean' => $boolean,
            'not' => $not
        ];
        
        return $this;
    }
    
    /**
     * Add a WHERE NOT NULL condition
     */
    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        return $this->whereNull($column, $boolean, true);
    }
    
    /**
     * Add a nested condition group
     */
    public function nested(\Closure $callback, string $boolean = 'and'): self
    {
        $join = new self($this->type, $this->table);
        $callback($join);
        
        $this->clauses[] = [
            'type' => 'nested',
            'join' => $join,
            'boolean' => $boolean
        ];
        
        return $this;
    }
}