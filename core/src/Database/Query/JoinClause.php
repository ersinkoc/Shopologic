<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Query;

class JoinClause
{
    public Builder $query;
    public string $type;
    public string $table;
    public array $clauses = [];
    public array $bindings = [];

    public function __construct(Builder $query, string $type, string $table)
    {
        $this->query = $query;
        $this->type = $type;
        $this->table = $table;
    }

    public function on($first, ?string $operator = null, $second = null, string $boolean = 'and'): self
    {
        if ($first instanceof \Closure) {
            return $this->whereNested($first, $boolean);
        }
        
        return $this->whereColumn($first, $operator, $second, $boolean);
    }

    public function orOn($first, ?string $operator = null, $second = null): self
    {
        return $this->on($first, $operator, $second, 'or');
    }

    public function where($first, ?string $operator = null, $second = null, string $boolean = 'and'): self
    {
        if ($first instanceof \Closure) {
            return $this->whereNested($first, $boolean);
        }
        
        $this->clauses[] = [
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean,
            'where' => true,
        ];
        
        $this->bindings[] = $second;
        
        return $this;
    }

    public function orWhere($first, ?string $operator = null, $second = null): self
    {
        return $this->where($first, $operator, $second, 'or');
    }

    public function whereColumn($first, ?string $operator = null, $second = null, string $boolean = 'and'): self
    {
        $this->clauses[] = [
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean,
            'where' => false,
        ];
        
        return $this;
    }

    public function orWhereColumn($first, ?string $operator = null, $second = null): self
    {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    protected function whereNested(\Closure $callback, string $boolean = 'and'): self
    {
        $join = new static($this->query, $this->type, $this->table);
        
        $callback($join);
        
        if (count($join->clauses)) {
            $this->clauses[] = [
                'type' => 'Nested',
                'clauses' => $join->clauses,
                'boolean' => $boolean,
            ];
            
            $this->bindings = array_merge($this->bindings, $join->bindings);
        }
        
        return $this;
    }
}