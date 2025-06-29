<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema\Grammars;

use Shopologic\Core\Database\Schema\Blueprint;

/**
 * MySQL-specific schema grammar
 */
class MySQLSchemaGrammar extends SchemaGrammar
{
    /**
     * The possible column modifiers
     */
    protected array $modifiers = ['Unsigned', 'Charset', 'Collate', 'Nullable', 'Default', 'Increment', 'Comment', 'After', 'First'];

    /**
     * Compile the query to determine if a table exists
     */
    public function compileTableExists(): string
    {
        return "select * from information_schema.tables where table_schema = ? and table_name = ? and table_type = 'BASE TABLE'";
    }

    /**
     * Compile the query to determine the list of tables
     */
    public function compileGetAllTables(): string
    {
        return "select table_name from information_schema.tables where table_schema = ? and table_type = 'BASE TABLE'";
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
        $sql = $this->compileCreateTable($blueprint);

        $sql = $this->compileCreateEncoding($sql, $blueprint);

        return $this->compileCreateEngine($sql, $blueprint);
    }

    /**
     * Create the main create table clause
     */
    protected function compileCreateTable(Blueprint $blueprint): string
    {
        return sprintf('create table %s (%s)',
            $this->wrapTable($blueprint->getTable()),
            implode(', ', $this->getColumns($blueprint))
        );
    }

    /**
     * Append the character set specifications to a command
     */
    protected function compileCreateEncoding(string $sql, Blueprint $blueprint): string
    {
        if (isset($blueprint->charset)) {
            $sql .= ' default character set ' . $blueprint->charset;

            if (isset($blueprint->collation)) {
                $sql .= ' collate ' . $blueprint->collation;
            }
        } elseif (isset($blueprint->collation)) {
            $sql .= ' collate ' . $blueprint->collation;
        }

        return $sql;
    }

    /**
     * Append the engine specifications to a command
     */
    protected function compileCreateEngine(string $sql, Blueprint $blueprint): string
    {
        if (isset($blueprint->engine)) {
            return $sql . ' engine = ' . $blueprint->engine;
        }

        return $sql;
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

        return "rename table {$from} to " . $this->wrapTable($to);
    }

    /**
     * Compile the SQL to enable foreign key constraints
     */
    public function compileEnableForeignKeyConstraints(): string
    {
        return 'SET FOREIGN_KEY_CHECKS=1;';
    }

    /**
     * Compile the SQL to disable foreign key constraints
     */
    public function compileDisableForeignKeyConstraints(): string
    {
        return 'SET FOREIGN_KEY_CHECKS=0;';
    }

    /**
     * Compile a drop column command
     */
    public function compileDropColumn(Blueprint $blueprint, array $columns): string
    {
        $columns = $this->prefixArray('drop', $columns);

        return 'alter table ' . $this->wrapTable($blueprint->getTable()) . ' ' . implode(', ', $columns);
    }

    /**
     * Compile a drop primary key command
     */
    public function compileDropPrimary(Blueprint $blueprint, string $index): string
    {
        return 'alter table ' . $this->wrapTable($blueprint->getTable()) . ' drop primary key';
    }

    /**
     * Compile a drop unique key command
     */
    public function compileDropUnique(Blueprint $blueprint, string $index): string
    {
        return 'alter table ' . $this->wrapTable($blueprint->getTable()) . ' drop index ' . $this->wrap($index);
    }

    /**
     * Compile a drop index command
     */
    public function compileDropIndex(Blueprint $blueprint, string $index): string
    {
        return 'alter table ' . $this->wrapTable($blueprint->getTable()) . ' drop index ' . $this->wrap($index);
    }

    /**
     * Compile a drop foreign key command
     */
    public function compileDropForeign(Blueprint $blueprint, string $index): string
    {
        return 'alter table ' . $this->wrapTable($blueprint->getTable()) . ' drop foreign key ' . $this->wrap($index);
    }

    /**
     * Compile a rename column command
     */
    public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        
        // MySQL requires the full column definition when renaming
        // This is a simplified version - in production, you'd need to get the current column definition
        return "alter table {$table} change " . $this->wrap($from) . ' ' . $this->wrap($to) . ' varchar(255)';
    }

    /**
     * Get the SQL for the column data type
     */
    protected function getType($column): string
    {
        switch ($column->type) {
            case 'bigInteger':
                return 'bigint';
            case 'integer':
                return 'int';
            case 'mediumInteger':
                return 'mediumint';
            case 'smallInteger':
                return 'smallint';
            case 'tinyInteger':
                return 'tinyint';
            case 'float':
                return 'float';
            case 'double':
                return 'double';
            case 'decimal':
                return "decimal({$column->total}, {$column->places})";
            case 'boolean':
                return 'tinyint(1)';
            case 'enum':
                return 'enum(' . $this->quoteString(implode("', '", $column->allowed)) . ')';
            case 'json':
                return 'json';
            case 'jsonb':
                return 'json'; // MySQL doesn't have jsonb, use json
            case 'date':
                return 'date';
            case 'dateTime':
                return $column->precision ? "datetime($column->precision)" : 'datetime';
            case 'dateTimeTz':
                return $column->precision ? "datetime($column->precision)" : 'datetime';
            case 'time':
                return $column->precision ? "time($column->precision)" : 'time';
            case 'timeTz':
                return $column->precision ? "time($column->precision)" : 'time';
            case 'timestamp':
                return $column->precision ? "timestamp($column->precision)" : 'timestamp';
            case 'timestampTz':
                return $column->precision ? "timestamp($column->precision)" : 'timestamp';
            case 'binary':
                return 'blob';
            case 'uuid':
                return 'char(36)';
            case 'ipAddress':
                return 'varchar(45)';
            case 'macAddress':
                return 'varchar(17)';
            case 'char':
                return $column->length ? "char({$column->length})" : 'char';
            case 'string':
                return $column->length ? "varchar({$column->length})" : 'varchar(255)';
            case 'text':
                return 'text';
            case 'mediumText':
                return 'mediumtext';
            case 'longText':
                return 'longtext';
        }
    }

    /**
     * Create the column definition for an unsigned modifier
     */
    protected function modifyUnsigned(Blueprint $blueprint, $column): string
    {
        if ($column->unsigned) {
            return ' unsigned';
        }

        return '';
    }

    /**
     * Create the column definition for a character set modifier
     */
    protected function modifyCharset(Blueprint $blueprint, $column): string
    {
        if (!is_null($column->charset)) {
            return ' character set ' . $column->charset;
        }

        return '';
    }

    /**
     * Create the column definition for a collation modifier
     */
    protected function modifyCollate(Blueprint $blueprint, $column): string
    {
        if (!is_null($column->collation)) {
            return ' collate ' . $column->collation;
        }

        return '';
    }

    /**
     * Create the column definition for a nullable modifier
     */
    protected function modifyNullable(Blueprint $blueprint, $column): string
    {
        if (is_null($column->virtualAs) && is_null($column->storedAs)) {
            return $column->nullable ? ' null' : ' not null';
        }

        return '';
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
     * Create the column definition for an auto-increment modifier
     */
    protected function modifyIncrement(Blueprint $blueprint, $column): string
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' auto_increment primary key';
        }

        return '';
    }

    /**
     * Create the column definition for a comment modifier
     */
    protected function modifyComment(Blueprint $blueprint, $column): string
    {
        if (!is_null($column->comment)) {
            return ' comment ' . $this->quoteString($column->comment);
        }

        return '';
    }

    /**
     * Create the column definition for an after modifier
     */
    protected function modifyAfter(Blueprint $blueprint, $column): string
    {
        if (!is_null($column->after)) {
            return ' after ' . $this->wrap($column->after);
        }

        return '';
    }

    /**
     * Create the column definition for a first modifier
     */
    protected function modifyFirst(Blueprint $blueprint, $column): string
    {
        if ($column->first) {
            return ' first';
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
            return $value ? '1' : '0';
        }

        return $this->quoteString((string) $value);
    }

    /**
     * Wrap a single string in keyword identifiers
     */
    protected function wrapValue(string $value): string
    {
        if ($value !== '*') {
            return '`' . str_replace('`', '``', $value) . '`';
        }

        return $value;
    }
}