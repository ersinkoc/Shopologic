<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Query;

/**
 * Window Clause Builder
 * 
 * Represents a WINDOW clause for window functions
 */
class WindowClause
{
    private string $name;
    private array $partitions = [];
    private array $orders = [];
    private ?string $frameMode = null;
    private ?string $frameStart = null;
    private ?string $frameEnd = null;
    
    public function __construct(string $name)
    {
        $this->name = $name;
    }
    
    /**
     * Get the window name
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Add a PARTITION BY clause
     */
    public function partitionBy(string|array $columns): self
    {
        $this->partitions = is_array($columns) ? $columns : func_get_args();
        return $this;
    }
    
    /**
     * Add an ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction)
        ];
        
        return $this;
    }
    
    /**
     * Add an ORDER BY DESC clause
     */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'desc');
    }
    
    /**
     * Define window frame as ROWS
     */
    public function rows(?string $start = null, ?string $end = null): self
    {
        $this->frameMode = 'ROWS';
        $this->frameStart = $start;
        $this->frameEnd = $end;
        
        return $this;
    }
    
    /**
     * Define window frame as RANGE
     */
    public function range(?string $start = null, ?string $end = null): self
    {
        $this->frameMode = 'RANGE';
        $this->frameStart = $start;
        $this->frameEnd = $end;
        
        return $this;
    }
    
    /**
     * Define window frame as GROUPS
     */
    public function groups(?string $start = null, ?string $end = null): self
    {
        $this->frameMode = 'GROUPS';
        $this->frameStart = $start;
        $this->frameEnd = $end;
        
        return $this;
    }
    
    /**
     * Set frame to unbounded preceding
     */
    public function unboundedPreceding(): self
    {
        $this->frameStart = 'UNBOUNDED PRECEDING';
        return $this;
    }
    
    /**
     * Set frame to current row
     */
    public function currentRow(): self
    {
        if ($this->frameStart === null) {
            $this->frameStart = 'CURRENT ROW';
        } else {
            $this->frameEnd = 'CURRENT ROW';
        }
        return $this;
    }
    
    /**
     * Set frame to unbounded following
     */
    public function unboundedFollowing(): self
    {
        $this->frameEnd = 'UNBOUNDED FOLLOWING';
        return $this;
    }
    
    /**
     * Set frame to N preceding
     */
    public function preceding(int $n): self
    {
        if ($this->frameStart === null) {
            $this->frameStart = "{$n} PRECEDING";
        } else {
            $this->frameEnd = "{$n} PRECEDING";
        }
        return $this;
    }
    
    /**
     * Set frame to N following
     */
    public function following(int $n): self
    {
        if ($this->frameStart === null) {
            $this->frameStart = "{$n} FOLLOWING";
        } else {
            $this->frameEnd = "{$n} FOLLOWING";
        }
        return $this;
    }
    
    /**
     * Convert to SQL string
     */
    public function toSql(): string
    {
        $sql = $this->name . ' AS (';
        
        if (!empty($this->partitions)) {
            $sql .= 'PARTITION BY ' . implode(', ', $this->partitions) . ' ';
        }
        
        if (!empty($this->orders)) {
            $orderClauses = array_map(function($order) {
                return $order['column'] . ' ' . strtoupper($order['direction']);
            }, $this->orders);
            $sql .= 'ORDER BY ' . implode(', ', $orderClauses) . ' ';
        }
        
        if ($this->frameMode !== null) {
            $sql .= $this->frameMode . ' ';
            
            if ($this->frameStart !== null && $this->frameEnd !== null) {
                $sql .= 'BETWEEN ' . $this->frameStart . ' AND ' . $this->frameEnd;
            } elseif ($this->frameStart !== null) {
                $sql .= $this->frameStart;
            }
        }
        
        $sql = rtrim($sql) . ')';
        
        return $sql;
    }
}