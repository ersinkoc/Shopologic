<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Query;

use Shopologic\Core\Database\QueryBuilder;
use Shopologic\Core\Database\ConnectionInterface;
use Shopologic\Core\Database\Expression;
use Shopologic\Core\Database\Paginator;
use Closure;

/**
 * Enhanced Query Builder
 * 
 * Extends the base QueryBuilder with advanced features:
 * - Subqueries and CTEs (Common Table Expressions)
 * - Advanced joins with complex conditions
 * - Window functions
 * - JSON operations
 * - Full-text search
 * - Query caching
 * - Query explanation and optimization hints
 */
class EnhancedQueryBuilder extends QueryBuilder
{
    protected array $unions = [];
    protected array $ctes = [];
    protected array $windows = [];
    protected array $hints = [];
    protected ?string $lockMode = null;
    protected bool $distinct = false;
    protected array $distinctOn = [];
    protected ?array $returning = null;
    protected ?int $cacheTime = null;
    protected ?string $cacheKey = null;
    protected array $jsonOperations = [];
    
    /**
     * Set the table/from clause
     */
    public function from(string $table, ?string $alias = null): self
    {
        $this->table = $alias ? "{$table} AS {$alias}" : $table;
        return $this;
    }
    
    /**
     * Add a raw select expression
     */
    public function selectRaw(string $expression, array $bindings = []): self
    {
        $this->columns = [$expression];
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }
    
    /**
     * Add a DISTINCT clause
     */
    public function distinct($columns = true): self
    {
        if ($columns === true) {
            $this->distinct = true;
        } else {
            $this->distinct = true;
            $this->distinctOn = is_array($columns) ? $columns : func_get_args();
        }
        
        return $this;
    }
    
    /**
     * Add a Common Table Expression (CTE)
     */
    public function withCTE(string $name, $query, ?array $columns = null): self
    {
        if ($query instanceof Closure) {
            $subQuery = new self($this->connection, $this->table);
            $query($subQuery);
            $query = $subQuery;
        }
        
        $this->ctes[] = [
            'name' => $name,
            'query' => $query,
            'columns' => $columns,
            'recursive' => false
        ];
        
        return $this;
    }
    
    /**
     * Add a recursive CTE
     */
    public function withRecursiveCTE(string $name, $query, ?array $columns = null): self
    {
        if ($query instanceof Closure) {
            $subQuery = new self($this->connection, $this->table);
            $query($subQuery);
            $query = $subQuery;
        }
        
        $this->ctes[] = [
            'name' => $name,
            'query' => $query,
            'columns' => $columns,
            'recursive' => true
        ];
        
        return $this;
    }
    
    /**
     * Add an advanced JOIN with complex conditions
     */
    public function joinComplex(string $table, Closure $on, string $type = 'inner'): self
    {
        $join = new JoinClause($type, $table);
        $on($join);
        
        $this->joins[] = $join;
        
        return $this;
    }
    
    /**
     * Add a lateral join
     */
    public function joinLateral(string $table, $query, string $alias, string $type = 'inner'): self
    {
        if ($query instanceof Closure) {
            $subQuery = new self($this->connection, $this->table);
            $query($subQuery);
            $query = $subQuery;
        }
        
        $this->joins[] = [
            'type' => $type . ' join lateral',
            'table' => $table,
            'query' => $query,
            'alias' => $alias
        ];
        
        return $this;
    }
    
    /**
     * Add a WHERE EXISTS clause with subquery
     */
    public function whereExists(Closure $callback, string $boolean = 'and', bool $not = false): self
    {
        $subQuery = new self($this->connection, $this->table);
        $callback($subQuery);
        
        $this->wheres[] = [
            'type' => 'exists',
            'query' => $subQuery,
            'boolean' => $boolean,
            'not' => $not
        ];
        
        return $this;
    }
    
    /**
     * Add a WHERE NOT EXISTS clause
     */
    public function whereNotExists(Closure $callback, string $boolean = 'and'): self
    {
        return $this->whereExists($callback, $boolean, true);
    }
    
    /**
     * Add a subquery WHERE clause
     */
    public function whereSubquery(string $column, string $operator, Closure $callback, string $boolean = 'and'): self
    {
        $subQuery = new self($this->connection, $this->table);
        $callback($subQuery);
        
        $this->wheres[] = [
            'type' => 'subquery',
            'column' => $column,
            'operator' => $operator,
            'query' => $subQuery,
            'boolean' => $boolean
        ];
        
        return $this;
    }
    
    /**
     * Add a raw WHERE clause
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => $boolean
        ];
        
        $this->bindings = array_merge($this->bindings, $bindings);
        
        return $this;
    }
    
    /**
     * Add a JSON WHERE clause
     */
    public function whereJson(string $column, string $key, $operator = null, $value = null): self
    {
        if (func_num_args() === 3) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->wheres[] = [
            'type' => 'json',
            'column' => $column,
            'key' => $key,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'and'
        ];
        
        $this->bindings[] = $value;
        
        return $this;
    }
    
    /**
     * Add a JSON contains WHERE clause
     */
    public function whereJsonContains(string $column, $value, string $boolean = 'and', bool $not = false): self
    {
        $this->wheres[] = [
            'type' => 'json_contains',
            'column' => $column,
            'value' => $value,
            'boolean' => $boolean,
            'not' => $not
        ];
        
        $this->bindings[] = json_encode($value);
        
        return $this;
    }
    
    /**
     * Add a full-text search WHERE clause
     */
    public function whereFullText(string|array $columns, string $search, array $options = []): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        
        $this->wheres[] = [
            'type' => 'fulltext',
            'columns' => $columns,
            'search' => $search,
            'options' => $options,
            'boolean' => 'and'
        ];
        
        $this->bindings[] = $search;
        
        return $this;
    }
    
    /**
     * Add a window function
     */
    public function window(string $name, Closure $callback): self
    {
        $window = new WindowClause($name);
        $callback($window);
        
        $this->windows[] = $window;
        
        return $this;
    }
    
    /**
     * Add a UNION clause
     */
    public function union($query, bool $all = false): self
    {
        if ($query instanceof Closure) {
            $subQuery = new self($this->connection, $this->table);
            $query($subQuery);
            $query = $subQuery;
        }
        
        $this->unions[] = [
            'query' => $query,
            'all' => $all
        ];
        
        return $this;
    }
    
    /**
     * Add a UNION ALL clause
     */
    public function unionAll($query): self
    {
        return $this->union($query, true);
    }
    
    /**
     * Add a RETURNING clause for INSERT/UPDATE/DELETE
     */
    public function returning(array|string $columns = ['*']): self
    {
        $this->returning = is_array($columns) ? $columns : func_get_args();
        return $this;
    }
    
    /**
     * Add query hints for optimization
     */
    public function hint(string $hint): self
    {
        $this->hints[] = $hint;
        return $this;
    }
    
    /**
     * Add index hint
     */
    public function useIndex(string $index): self
    {
        return $this->hint("/*+ INDEX({$this->table} {$index}) */");
    }
    
    /**
     * Force index usage
     */
    public function forceIndex(string $index): self
    {
        return $this->hint("/*+ FORCE_INDEX({$this->table} {$index}) */");
    }
    
    /**
     * Lock rows for update
     */
    public function lockForUpdate(bool $skipLocked = false): self
    {
        $this->lockMode = 'FOR UPDATE' . ($skipLocked ? ' SKIP LOCKED' : '');
        return $this;
    }
    
    /**
     * Lock rows for share
     */
    public function lockForShare(bool $skipLocked = false): self
    {
        $this->lockMode = 'FOR SHARE' . ($skipLocked ? ' SKIP LOCKED' : '');
        return $this;
    }
    
    /**
     * Enable query caching
     */
    public function cache(int $seconds, ?string $key = null): self
    {
        $this->cacheTime = $seconds;
        $this->cacheKey = $key;
        return $this;
    }
    
    /**
     * Insert data and get ID
     */
    public function insertGetId(array $values, ?string $sequence = null): string
    {
        $this->returning(['*']);
        $result = $this->insert($values);
        
        if ($result && isset($result[0]['id'])) {
            return (string) $result[0]['id'];
        }
        
        return $this->connection->lastInsertId($sequence);
    }
    
    /**
     * Insert or update on duplicate key
     */
    public function upsert(array $values, array $uniqueBy, array $update = []): int
    {
        // PostgreSQL UPSERT using ON CONFLICT
        $columns = array_keys(reset($values));
        $updateColumns = empty($update) ? array_diff($columns, $uniqueBy) : $update;
        
        $sql = $this->buildUpsertQuery($values, $uniqueBy, $updateColumns);
        
        return $this->connection->execute($sql, array_values($values));
    }
    
    /**
     * Update or insert
     */
    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        if (!$this->where($attributes)->exists()) {
            return $this->insert(array_merge($attributes, $values));
        }
        
        if (!empty($values)) {
            return $this->where($attributes)->update($values) > 0;
        }
        
        return true;
    }
    
    /**
     * Increment a column value
     */
    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        $values = array_merge([$column => new Expression("{$column} + {$amount}")], $extra);
        return $this->update($values);
    }
    
    /**
     * Decrement a column value
     */
    public function decrement(string $column, int $amount = 1, array $extra = []): int
    {
        $values = array_merge([$column => new Expression("{$column} - {$amount}")], $extra);
        return $this->update($values);
    }
    
    /**
     * Execute the query and get the first result
     */
    public function first(array $columns = ['*']): ?array
    {
        return $this->limit(1)->get($columns)->first();
    }
    
    /**
     * Execute the query and get the first result or throw
     */
    public function firstOrFail(array $columns = ['*']): array
    {
        $result = $this->first($columns);
        
        if ($result === null) {
            throw new ModelNotFoundException("No results found");
        }
        
        return $result;
    }
    
    /**
     * Get a single column value
     */
    public function value(string $column)
    {
        $result = $this->first([$column]);
        return $result ? $result[$column] : null;
    }
    
    /**
     * Get an array of column values
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $results = $this->get(is_null($key) ? [$column] : [$column, $key]);
        
        return Collection::make($results)->pluck($column, $key)->all();
    }
    
    /**
     * Chunk results for processing
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;
        
        do {
            $results = $this->limit($count)->offset(($page - 1) * $count)->get();
            $countResults = count($results);
            
            if ($countResults === 0) {
                break;
            }
            
            if ($callback($results, $page) === false) {
                return false;
            }
            
            unset($results);
            $page++;
            
        } while ($countResults === $count);
        
        return true;
    }
    
    /**
     * Execute query and get cursor
     */
    public function cursor(): \Generator
    {
        $offset = 0;
        $limit = 1000;
        
        while (true) {
            $results = $this->limit($limit)->offset($offset)->get();
            
            if (empty($results)) {
                break;
            }
            
            foreach ($results as $result) {
                yield $result;
            }
            
            $offset += $limit;
        }
    }
    
    /**
     * Get the SQL representation of the query
     */
    public function toSql(): string
    {
        return $this->buildSelect();
    }
    
    /**
     * Explain the query execution plan
     */
    public function explain(bool $analyze = false, bool $verbose = false): array
    {
        $options = [];
        if ($analyze) $options[] = 'ANALYZE';
        if ($verbose) $options[] = 'VERBOSE';
        
        $sql = 'EXPLAIN';
        if (!empty($options)) {
            $sql .= ' (' . implode(', ', $options) . ')';
        }
        $sql .= ' ' . $this->toSql();
        
        return $this->connection->query($sql, $this->bindings)->fetchAll();
    }
    
    /**
     * Build UPSERT query
     */
    private function buildUpsertQuery(array $values, array $uniqueBy, array $update): string
    {
        // Implementation would depend on the database driver
        // This is a PostgreSQL example
        $table = $this->table;
        $columns = array_keys(reset($values));
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ";
        $sql .= "(" . implode(', ', array_fill(0, count($columns), '?')) . ")";
        $sql .= " ON CONFLICT (" . implode(', ', $uniqueBy) . ") DO UPDATE SET ";
        
        $updates = [];
        foreach ($update as $col) {
            $updates[] = "{$col} = EXCLUDED.{$col}";
        }
        $sql .= implode(', ', $updates);
        
        return $sql;
    }
}