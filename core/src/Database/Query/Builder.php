<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Query;

use Shopologic\Core\Database\ConnectionInterface;
use Shopologic\Core\Database\DatabaseConnection;
use Shopologic\Core\Database\Query\Grammars\Grammar;
use Shopologic\Core\Database\Collection;
use Shopologic\Core\Database\Expression;

class Builder
{
    protected ?ConnectionInterface $connection = null;
    protected ?Grammar $grammar = null;
    protected array $bindings = [
        'select' => [],
        'from' => [],
        'join' => [],
        'where' => [],
        'groupBy' => [],
        'having' => [],
        'order' => [],
        'union' => [],
        'unionOrder' => [],
    ];
    
    public array $aggregate = [];
    public array $columns = [];
    public bool $distinct = false;
    public ?string $from = null;
    public array $joins = [];
    public array $wheres = [];
    public array $groups = [];
    public array $havings = [];
    public array $orders = [];
    public ?int $limit = null;
    public ?int $offset = null;
    public array $unions = [];
    public ?int $unionLimit = null;
    public ?int $unionOffset = null;
    public array $unionOrders = [];
    public ?string $lock = null;

    public function __construct(?ConnectionInterface $connection = null, ?Grammar $grammar = null)
    {
        $this->connection = $connection;
        $this->grammar = $grammar;
    }

    public function select($columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        $this->bindings['select'] = [];
        
        return $this;
    }

    public function selectRaw(string $expression, array $bindings = []): self
    {
        $this->addSelect([$expression]);
        
        if ($bindings) {
            $this->addBinding($bindings, 'select');
        }
        
        return $this;
    }

    public function addSelect($column): self
    {
        $columns = is_array($column) ? $column : func_get_args();
        $this->columns = array_merge($this->columns, $columns);
        
        return $this;
    }

    public function distinct(): self
    {
        $this->distinct = true;
        
        return $this;
    }

    public function from(string $table, ?string $as = null): self
    {
        $this->from = $as ? "{$table} as {$as}" : $table;
        
        return $this;
    }

    public function join(string $table, $first, ?string $operator = null, $second = null, string $type = 'inner'): self
    {
        if ($first instanceof \Closure) {
            $join = new JoinClause($this, $type, $table);
            $first($join);
            $this->joins[] = $join;
            $this->addBinding($join->bindings, 'join');
        } else {
            $join = new JoinClause($this, $type, $table);
            $join->on($first, $operator, $second);
            $this->joins[] = $join;
        }
        
        return $this;
    }

    public function leftJoin(string $table, $first, ?string $operator = null, $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string $table, $first, ?string $operator = null, $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function where($column, $operator = null, $value = null, string $boolean = 'and'): self
    {
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }
        
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        
        if ($column instanceof \Closure) {
            return $this->whereNested($column, $boolean);
        }
        
        if (!in_array(strtolower($operator), $this->operators, true)) {
            $value = $operator;
            $operator = '=';
        }
        
        if ($value instanceof \Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }
        
        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator !== '=');
        }
        
        $type = 'Basic';
        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');
        
        if (!$value instanceof Expression) {
            $this->addBinding($value, 'where');
        }
        
        return $this;
    }

    public function orWhere($column, ?string $operator = null, $value = null): self
    {
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): self
    {
        $type = $not ? 'NotIn' : 'In';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');
        $this->addBinding($values, 'where');
        
        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'and'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): self
    {
        $type = $not ? 'NotNull' : 'Null';
        $this->wheres[] = compact('type', 'column', 'boolean');
        
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): self
    {
        $type = $not ? 'NotBetween' : 'Between';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');
        $this->addBinding($values, 'where');
        
        return $this;
    }

    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): self
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function whereNested(\Closure $callback, string $boolean = 'and'): self
    {
        $query = $this->newQuery();
        $callback($query);
        
        return $this->addNestedWhereQuery($query, $boolean);
    }

    public function groupBy(...$groups): self
    {
        foreach ($groups as $group) {
            $this->groups = array_merge(
                $this->groups,
                is_array($group) ? $group : [$group]
            );
        }
        
        return $this;
    }

    public function having(string $column, ?string $operator = null, $value = null, string $boolean = 'and'): self
    {
        $type = 'Basic';
        $this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');
        
        if (!$value instanceof Expression) {
            $this->addBinding($value, 'having');
        }
        
        return $this;
    }

    public function orHaving(string $column, ?string $operator = null, $value = null): self
    {
        return $this->having($column, $operator, $value, 'or');
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $this->orders[] = compact('column', 'direction');
        
        return $this;
    }

    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
    }

    public function limit(int $value): self
    {
        $this->limit = $value;
        
        return $this;
    }

    public function take(int $value): self
    {
        return $this->limit($value);
    }

    public function offset(int $value): self
    {
        $this->offset = $value;
        
        return $this;
    }

    public function skip(int $value): self
    {
        return $this->offset($value);
    }

    public function forPage(int $page, int $perPage = 15): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    public function union($query, bool $all = false): self
    {
        $this->unions[] = compact('query', 'all');
        $this->addBinding($query->getBindings(), 'union');
        
        return $this;
    }

    public function unionAll($query): self
    {
        return $this->union($query, true);
    }

    public function lock($value = true): self
    {
        $this->lock = $value;
        
        return $this;
    }

    public function lockForUpdate(): self
    {
        return $this->lock('for update');
    }

    public function sharedLock(): self
    {
        return $this->lock('for share');
    }

    public function toSql(): string
    {
        $grammar = new Grammar();
        return $grammar->compileSelect($this);
    }

    public function getBindings(): array
    {
        return array_merge(...array_values($this->bindings));
    }

    public function addBinding($value, string $type = 'where'): self
    {
        if (!array_key_exists($type, $this->bindings)) {
            throw new \InvalidArgumentException("Invalid binding type: {$type}");
        }
        
        if (is_array($value)) {
            $this->bindings[$type] = array_merge($this->bindings[$type], $value);
        } else {
            $this->bindings[$type][] = $value;
        }
        
        return $this;
    }

    public function newQuery(): self
    {
        return new static($this->connection);
    }

    protected function addArrayOfWheres(array $column, string $boolean): self
    {
        foreach ($column as $key => $value) {
            if (is_numeric($key) && is_array($value)) {
                $this->where(...$value);
            } else {
                $this->where($key, '=', $value, $boolean);
            }
        }
        
        return $this;
    }

    protected function addNestedWhereQuery($query, string $boolean = 'and'): self
    {
        if (count($query->wheres)) {
            $type = 'Nested';
            $this->wheres[] = compact('type', 'query', 'boolean');
            $this->addBinding($query->getBindings(), 'where');
        }
        
        return $this;
    }

    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];
    
    /**
     * Execute the query as a select statement
     */
    public function get(array $columns = ['*']): Collection
    {
        return $this->onceWithColumns($columns, function () {
            return $this->runSelect();
        });
    }
    
    /**
     * Execute a query for a single record by ID
     */
    public function find($id, array $columns = ['*'])
    {
        return $this->where('id', '=', $id)->first($columns);
    }
    
    /**
     * Get a single column's value from the first result of a query
     */
    public function value(string $column)
    {
        $result = $this->first([$column]);
        
        return $result[$column] ?? null;
    }
    
    /**
     * Execute the query and get the first result
     */
    public function first(array $columns = ['*'])
    {
        return $this->take(1)->get($columns)->first();
    }
    
    /**
     * Execute a query with a single column selected
     */
    protected function onceWithColumns(array $columns, callable $callback)
    {
        $original = $this->columns;
        
        if (is_null($original)) {
            $this->columns = $columns;
        }
        
        $result = $callback();
        
        $this->columns = $original;
        
        return $result;
    }
    
    /**
     * Run the query as a "select" statement against the connection
     */
    protected function runSelect(): Collection
    {
        return $this->connection->select(
            $this->toSql(),
            $this->getBindings()
        );
    }
    
    /**
     * Get the SQL representation of the query
     */
    public function toSql(): string
    {
        return $this->grammar->compileSelect($this);
    }
    
    /**
     * Get the count of the total records for pagination
     */
    public function getCountForPagination(array $columns = ['*']): int
    {
        $results = $this->runPaginationCountQuery($columns);
        
        if (isset($this->groups)) {
            return count($results);
        }
        
        return (int) $results[0]->aggregate;
    }
    
    /**
     * Run a pagination count query
     */
    protected function runPaginationCountQuery(array $columns = ['*'])
    {
        $without = $this->unions ? ['orders', 'limit', 'offset'] : ['columns', 'orders', 'limit', 'offset'];
        
        return $this->cloneWithout($without)
            ->cloneWithoutBindings($this->unions ? ['order'] : ['select', 'order'])
            ->setAggregate('count', $this->withoutSelectAliases($columns))
            ->get();
    }
    
    /**
     * Set the aggregate property without running the query
     */
    protected function setAggregate(string $function, array $columns = ['*']): self
    {
        $this->aggregate = compact('function', 'columns');
        
        if (empty($this->groups)) {
            $this->orders = [];
            $this->bindings['order'] = [];
        }
        
        return $this;
    }
    
    /**
     * Clone the query without specific properties
     */
    protected function cloneWithout(array $properties): self
    {
        $clone = clone $this;
        
        foreach ($properties as $property) {
            $clone->{$property} = null;
        }
        
        return $clone;
    }
    
    /**
     * Clone the query without specific bindings
     */
    protected function cloneWithoutBindings(array $except): self
    {
        $clone = clone $this;
        
        foreach ($except as $type) {
            $clone->bindings[$type] = [];
        }
        
        return $clone;
    }
    
    /**
     * Remove column aliases
     */
    protected function withoutSelectAliases(array $columns): array
    {
        return array_map(function ($column) {
            return is_string($column) && ($aliasPosition = stripos($column, ' as ')) !== false
                ? substr($column, 0, $aliasPosition)
                : $column;
        }, $columns);
    }
    
    /**
     * Insert new records into the database
     */
    public function insert(array $values): bool
    {
        if (empty($values)) {
            return true;
        }
        
        if (!is_array(reset($values))) {
            $values = [$values];
        }
        
        return $this->connection->insert(
            $this->grammar->compileInsert($this, $values),
            $this->cleanBindings($this->flattenArray($values))
        );
    }
    
    /**
     * Insert a new record and get the value of the primary key
     */
    public function insertGetId(array $values, ?string $sequence = null)
    {
        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);
        
        $this->connection->insert($sql, $this->cleanBindings(array_flatten($values)));
        
        return $this->connection->lastInsertId($sequence);
    }
    
    /**
     * Update records in the database
     */
    public function update(array $values): int
    {
        $sql = $this->grammar->compileUpdate($this, $values);
        
        return $this->connection->update(
            $sql,
            $this->cleanBindings(
                $this->grammar->prepareBindingsForUpdate($this->bindings, $values)
            )
        );
    }
    
    /**
     * Delete records from the database
     */
    public function delete($id = null): int
    {
        if (!is_null($id)) {
            $this->where($this->from . '.id', '=', $id);
        }
        
        return $this->connection->delete(
            $this->grammar->compileDelete($this),
            $this->cleanBindings($this->getBindings())
        );
    }
    
    /**
     * Remove all bindings
     */
    protected function cleanBindings(array $bindings): array
    {
        return array_values(array_filter($bindings, function ($binding) {
            return !$binding instanceof Expression;
        }));
    }
    
    /**
     * Flatten a multi-dimensional array into a single level
     */
    protected function flattenArray(array $array): array
    {
        $result = [];
        
        array_walk_recursive($array, function ($value) use (&$result) {
            $result[] = $value;
        });
        
        return $result;
    }
    
    /**
     * Create a new query instance
     */
    public function newQuery(): self
    {
        return new static($this->connection, $this->grammar);
    }
    
    /**
     * Get the database connection
     */
    public function getConnection(): ?ConnectionInterface
    {
        return $this->connection;
    }
    
    /**
     * Get the query grammar
     */
    public function getGrammar(): ?Grammar
    {
        return $this->grammar;
    }
    
    /**
     * Set the query grammar
     */
    public function setGrammar(Grammar $grammar): self
    {
        $this->grammar = $grammar;
        return $this;
    }
}