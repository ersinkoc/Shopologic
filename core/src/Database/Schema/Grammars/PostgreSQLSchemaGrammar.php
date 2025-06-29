<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema\Grammars;

use Shopologic\Core\Database\Schema\Blueprint;

/**
 * PostgreSQL-specific schema grammar
 */
class PostgreSQLSchemaGrammar extends SchemaGrammar
{
    /**
     * The possible column modifiers
     */
    protected array $modifiers = ['Increment', 'Nullable', 'Default'];

    /**
     * Compile the query to determine if a table exists
     */
    public function compileTableExists(): string
    {
        return "select * from information_schema.tables where table_schema = ? and table_name = ?";
    }

    /**
     * Compile the query to determine the list of tables
     */
    public function compileGetAllTables(): string
    {
        return "select tablename from pg_catalog.pg_tables where schemaname = ?";
    }

    /**
     * Compile the query to determine the list of columns
     */
    public function compileColumnListing(string $table): string
    {
        return "select column_name from information_schema.columns where table_schema = ? and table_name = ?";
    }

    /**
     * Compile a create table command
     */
    public function compileCreate(Blueprint $blueprint): string
    {
        return sprintf('create table %s (%s)',
            $this->wrapTable($blueprint->getTable()),
            implode(', ', $this->getColumns($blueprint))
        );
    }

    /**
     * Compile a drop table command
     */
    public function compileDrop(Blueprint $blueprint): string
    {
        return 'drop table ' . $this->wrapTable($blueprint->getTable());
    }

    /**
     * Compile a drop table (if exists) command
     */
    public function compileDropIfExists(Blueprint $blueprint): string
    {
        return 'drop table if exists ' . $this->wrapTable($blueprint->getTable());
    }

    /**
     * Compile a rename table command
     */
    public function compileRename(Blueprint $blueprint, string $to): string
    {
        $from = $this->wrapTable($blueprint->getTable());

        return "alter table {$from} rename to " . $this->wrapTable($to);
    }

    /**
     * Compile the SQL to enable foreign key constraints
     */
    public function compileEnableForeignKeyConstraints(): string
    {
        return 'SET CONSTRAINTS ALL IMMEDIATE';
    }

    /**
     * Compile the SQL to disable foreign key constraints
     */
    public function compileDisableForeignKeyConstraints(): string
    {
        return 'SET CONSTRAINTS ALL DEFERRED';
    }

    /**
     * Compile a drop column command
     */
    public function compileDropColumn(Blueprint $blueprint, array $columns): string
    {
        $columns = $this->prefixArray('drop column', $columns);

        return 'alter table ' . $this->wrapTable($blueprint->getTable()) . ' ' . implode(', ', $columns);
    }

    /**
     * Compile a drop primary key command
     */
    public function compileDropPrimary(Blueprint $blueprint, string $index): string
    {
        $table = $blueprint->getTable();

        return 'alter table ' . $this->wrapTable($table) . ' drop constraint ' . $this->wrap("{$table}_pkey");
    }

    /**
     * Compile a drop unique key command
     */
    public function compileDropUnique(Blueprint $blueprint, string $index): string
    {
        return 'drop index ' . $this->wrap($index);
    }

    /**
     * Compile a drop index command
     */
    public function compileDropIndex(Blueprint $blueprint, string $index): string
    {
        return 'drop index ' . $this->wrap($index);
    }

    /**
     * Compile a drop foreign key command
     */
    public function compileDropForeign(Blueprint $blueprint, string $index): string
    {
        return 'alter table ' . $this->wrapTable($blueprint->getTable()) . ' drop constraint ' . $this->wrap($index);
    }

    /**
     * Compile a rename column command
     */
    public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string
    {
        $table = $this->wrapTable($blueprint->getTable());

        return "alter table {$table} rename column " . $this->wrap($from) . ' to ' . $this->wrap($to);
    }

    /**
     * Get the SQL for the column data type
     */
    protected function getType($column): string
    {
        switch ($column->type) {
            case 'bigInteger':
                return $column->autoIncrement ? 'bigserial' : 'bigint';
            case 'integer':
                return $column->autoIncrement ? 'serial' : 'integer';
            case 'mediumInteger':
                return 'integer';
            case 'smallInteger':
                return 'smallint';
            case 'tinyInteger':
                return 'smallint';
            case 'float':
                return "real";
            case 'double':
                return 'double precision';
            case 'decimal':
                return "decimal({$column->total}, {$column->places})";
            case 'boolean':
                return 'boolean';
            case 'enum':
                return "varchar(255) check ({$this->wrap($column->name)} in (" . $this->quoteString(implode("', '", $column->allowed)) . '))';
            case 'json':
                return 'json';
            case 'jsonb':
                return 'jsonb';
            case 'date':
                return 'date';
            case 'dateTime':
                return $column->precision ? "timestamp($column->precision) without time zone" : 'timestamp without time zone';
            case 'dateTimeTz':
                return $column->precision ? "timestamp($column->precision) with time zone" : 'timestamp with time zone';
            case 'time':
                return $column->precision ? "time($column->precision) without time zone" : 'time without time zone';
            case 'timeTz':
                return $column->precision ? "time($column->precision) with time zone" : 'time with time zone';
            case 'timestamp':
                return $column->precision ? "timestamp($column->precision) without time zone" : 'timestamp without time zone';
            case 'timestampTz':
                return $column->precision ? "timestamp($column->precision) with time zone" : 'timestamp with time zone';
            case 'binary':
                return 'bytea';
            case 'uuid':
                return 'uuid';
            case 'ipAddress':
                return 'inet';
            case 'macAddress':
                return 'macaddr';
            case 'char':
                return $column->length ? "char({$column->length})" : 'char';
            case 'string':
                return $column->length ? "varchar({$column->length})" : 'varchar';
            case 'text':
                return 'text';
            case 'mediumText':
                return 'text';
            case 'longText':
                return 'text';
        }
    }

    /**
     * Create the column definition for an auto-increment modifier
     */
    protected function modifyIncrement(Blueprint $blueprint, $column): string
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' primary key';
        }

        return '';
    }

    /**
     * Create the column definition for a nullable modifier
     */
    protected function modifyNullable(Blueprint $blueprint, $column): string
    {
        return $column->nullable ? ' null' : ' not null';
    }

    /**
     * Create the column definition for a default modifier
     */
    protected function modifyDefault(Blueprint $blueprint, $column): string
    {
        if (!is_null($column->default)) {
            return ' default ' . $this->getDefaultValue($column->default);
        }

        return '';
    }

    /**
     * Get the SQL for a default column value
     */
    protected function getDefaultValue($value): string
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $this->quoteString((string) $value);
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
}