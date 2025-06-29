<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema\Grammars;

use Shopologic\Core\Database\Schema\Blueprint;
use Shopologic\Core\Database\Expression;

/**
 * Base schema grammar for DDL operations
 */
abstract class SchemaGrammar
{
    /**
     * The possible column modifiers
     */
    protected array $modifiers = [];

    /**
     * The possible column serial types
     */
    protected array $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * Compile the query to determine if a table exists
     */
    abstract public function compileTableExists(): string;

    /**
     * Compile the query to determine the list of tables
     */
    abstract public function compileGetAllTables(): string;

    /**
     * Compile the query to determine the list of columns
     */
    abstract public function compileColumnListing(string $table): string;

    /**
     * Compile a create table command
     */
    abstract public function compileCreate(Blueprint $blueprint): string;

    /**
     * Compile a drop table command
     */
    abstract public function compileDrop(Blueprint $blueprint): string;

    /**
     * Compile a drop table (if exists) command
     */
    abstract public function compileDropIfExists(Blueprint $blueprint): string;

    /**
     * Compile a rename table command
     */
    abstract public function compileRename(Blueprint $blueprint, string $to): string;

    /**
     * Compile the SQL to enable foreign key constraints
     */
    abstract public function compileEnableForeignKeyConstraints(): string;

    /**
     * Compile the SQL to disable foreign key constraints
     */
    abstract public function compileDisableForeignKeyConstraints(): string;

    /**
     * Compile a drop column command
     */
    abstract public function compileDropColumn(Blueprint $blueprint, array $columns): string;

    /**
     * Compile a drop primary key command
     */
    abstract public function compileDropPrimary(Blueprint $blueprint, string $index): string;

    /**
     * Compile a drop unique key command
     */
    abstract public function compileDropUnique(Blueprint $blueprint, string $index): string;

    /**
     * Compile a drop index command
     */
    abstract public function compileDropIndex(Blueprint $blueprint, string $index): string;

    /**
     * Compile a drop foreign key command
     */
    abstract public function compileDropForeign(Blueprint $blueprint, string $index): string;

    /**
     * Compile a rename column command
     */
    abstract public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string;

    /**
     * Compile the SQL needed to create a new index
     */
    public function compileCreateIndex(Blueprint $blueprint, string $name, string $type, array $columns): string
    {
        $columns = $this->columnize($columns);
        $table = $this->wrapTable($blueprint->getTable());

        return "create {$type} index {$this->wrap($name)} on {$table} ({$columns})";
    }

    /**
     * Compile an add column command
     */
    public function compileAdd(Blueprint $blueprint): string
    {
        $columns = $this->prefixArray('add column', $this->getColumns($blueprint));

        return 'alter table ' . $this->wrapTable($blueprint->getTable()) . ' ' . implode(', ', $columns);
    }

    /**
     * Compile a primary key command
     */
    public function compilePrimary(Blueprint $blueprint, string $name, array $columns): string
    {
        return $this->compileKey($blueprint, $name, $columns, 'primary key');
    }

    /**
     * Compile a unique key command
     */
    public function compileUnique(Blueprint $blueprint, string $name, array $columns): string
    {
        return $this->compileKey($blueprint, $name, $columns, 'unique');
    }

    /**
     * Compile a plain index key command
     */
    public function compileIndex(Blueprint $blueprint, string $name, array $columns): string
    {
        return $this->compileCreateIndex($blueprint, $name, 'index', $columns);
    }

    /**
     * Compile a foreign key command
     */
    public function compileForeign(Blueprint $blueprint, string $name, array $columns, string $table, array $foreignColumns, string $onDelete, string $onUpdate): string
    {
        $sql = "alter table {$this->wrapTable($blueprint->getTable())} add constraint {$this->wrap($name)} ";
        $sql .= "foreign key ({$this->columnize($columns)}) references {$this->wrapTable($table)} ({$this->columnize($foreignColumns)})";

        if (!is_null($onDelete)) {
            $sql .= " on delete {$onDelete}";
        }

        if (!is_null($onUpdate)) {
            $sql .= " on update {$onUpdate}";
        }

        return $sql;
    }

    /**
     * Compile an index creation command
     */
    protected function compileKey(Blueprint $blueprint, string $name, array $columns, string $type): string
    {
        return "alter table {$this->wrapTable($blueprint->getTable())} add {$type} {$this->wrap($name)}({$this->columnize($columns)})";
    }

    /**
     * Get the columns from the blueprint
     */
    protected function getColumns(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getAddedColumns() as $column) {
            $sql = $this->wrap($column->name) . ' ' . $this->getType($column);
            $columns[] = $this->addModifiers($sql, $blueprint, $column);
        }

        return $columns;
    }

    /**
     * Get the SQL for the column data type
     */
    abstract protected function getType($column): string;

    /**
     * Add the column modifiers to the definition
     */
    protected function addModifiers(string $sql, Blueprint $blueprint, $column): string
    {
        foreach ($this->modifiers as $modifier) {
            $method = "modify{$modifier}";
            $sql .= $this->{$method}($blueprint, $column);
        }

        return $sql;
    }

    /**
     * Get the primary key command if it exists
     */
    protected function getCommandByName(Blueprint $blueprint, string $name): ?object
    {
        $commands = $this->getCommandsByName($blueprint, $name);

        return count($commands) > 0 ? reset($commands) : null;
    }

    /**
     * Get all of the commands with a given name
     */
    protected function getCommandsByName(Blueprint $blueprint, string $name): array
    {
        return array_filter($blueprint->getCommands(), function ($value) use ($name) {
            return $value->name == $name;
        });
    }

    /**
     * Prefix an array of values
     */
    protected function prefixArray(string $prefix, array $values): array
    {
        return array_map(function ($value) use ($prefix) {
            return $prefix . ' ' . $value;
        }, $values);
    }

    /**
     * Wrap a table in keyword identifiers
     */
    public function wrapTable($table): string
    {
        return $this->wrap($this->getTablePrefix() . $table);
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

        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        if (strpos($value, '.') !== false) {
            return $this->wrapSegments(explode('.', $value));
        }

        return $this->wrapValue($value);
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
     * Get the format for database stored dates
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get the grammar table prefix
     */
    public function getTablePrefix(): string
    {
        return '';
    }
}