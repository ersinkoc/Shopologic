<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema;

class PostgreSQLGrammar extends Grammar
{
    protected array $modifiers = ['Increment', 'Nullable', 'Default'];
    
    protected array $serialTypes = [
        'bigInteger' => 'bigserial',
        'integer' => 'serial',
        'mediumInteger' => 'serial',
        'smallInteger' => 'smallserial',
    ];

    public function compileTableExists(): string
    {
        return "select * from information_schema.tables where table_schema = current_schema() and table_name = ?";
    }

    public function compileColumnListing(string $table): string
    {
        return "select column_name from information_schema.columns where table_schema = current_schema() and table_name = ?";
    }

    public function compileColumnType(): string
    {
        return "select data_type from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?";
    }

    public function compileCreate(Blueprint $blueprint): string
    {
        $sql = 'create table ' . $this->wrapTable($blueprint->getTable()) . ' (';

        $columns = [];

        foreach ($blueprint->getAddedColumns() as $column) {
            $columns[] = $this->getColumn($column);
        }

        $sql .= implode(', ', $columns);

        $sql .= ')';

        return $sql;
    }

    protected function getColumn(ColumnDefinition $column): string
    {
        $sql = $this->wrap($column->name) . ' ' . $this->getType($column);

        foreach ($this->modifiers as $modifier) {
            $method = "modify{$modifier}";
            
            if (method_exists($this, $method)) {
                $sql .= $this->$method($column);
            }
        }

        return $sql;
    }

    protected function getType(ColumnDefinition $column): string
    {
        switch ($column->type) {
            case 'bigInteger':
                return $column->autoIncrement && isset($this->serialTypes['bigInteger']) 
                    ? $this->serialTypes['bigInteger'] 
                    : 'bigint';
            case 'binary':
                return 'bytea';
            case 'boolean':
                return 'boolean';
            case 'char':
                return "char({$column->length})";
            case 'date':
                return 'date';
            case 'dateTime':
                return $column->precision ? "timestamp({$column->precision}) without time zone" : 'timestamp without time zone';
            case 'dateTimeTz':
                return $column->precision ? "timestamp({$column->precision}) with time zone" : 'timestamp with time zone';
            case 'decimal':
                return "decimal({$column->total}, {$column->places})";
            case 'double':
                return 'double precision';
            case 'enum':
                return "varchar(255) check ({$this->wrap($column->name)} in (" . $this->quoteString(implode("', '", $column->allowed)) . '))';
            case 'float':
                return 'real';
            case 'integer':
                return $column->autoIncrement && isset($this->serialTypes['integer']) 
                    ? $this->serialTypes['integer'] 
                    : 'integer';
            case 'json':
                return 'json';
            case 'jsonb':
                return 'jsonb';
            case 'longText':
            case 'mediumText':
            case 'text':
            case 'tinyText':
                return 'text';
            case 'mediumInteger':
                return $column->autoIncrement && isset($this->serialTypes['mediumInteger']) 
                    ? $this->serialTypes['mediumInteger'] 
                    : 'integer';
            case 'smallInteger':
                return $column->autoIncrement && isset($this->serialTypes['smallInteger']) 
                    ? $this->serialTypes['smallInteger'] 
                    : 'smallint';
            case 'string':
                return "varchar({$column->length})";
            case 'time':
                return $column->precision ? "time({$column->precision}) without time zone" : 'time without time zone';
            case 'timeTz':
                return $column->precision ? "time({$column->precision}) with time zone" : 'time with time zone';
            case 'timestamp':
                return $column->precision ? "timestamp({$column->precision}) without time zone" : 'timestamp without time zone';
            case 'timestampTz':
                return $column->precision ? "timestamp({$column->precision}) with time zone" : 'timestamp with time zone';
            case 'tinyInteger':
                return 'smallint';
            case 'uuid':
                return 'uuid';
            case 'year':
                return 'smallint';
            default:
                throw new \RuntimeException("Unknown column type: {$column->type}");
        }
    }

    protected function modifyNullable(ColumnDefinition $column): string
    {
        return $column->nullable ? ' null' : ' not null';
    }

    protected function modifyDefault(ColumnDefinition $column): string
    {
        if ($column->default !== null) {
            return ' default ' . $this->getDefaultValue($column->default);
        }

        if ($column->useCurrent) {
            return ' default CURRENT_TIMESTAMP';
        }

        return '';
    }

    protected function modifyIncrement(ColumnDefinition $column): string
    {
        if ($column->type === 'bigInteger' && $column->autoIncrement && !isset($this->serialTypes['bigInteger'])) {
            return ' primary key';
        }

        if ($column->type === 'integer' && $column->autoIncrement && !isset($this->serialTypes['integer'])) {
            return ' primary key';
        }

        return '';
    }

    protected function getDefaultValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $this->quoteString($value);
        }

        return (string) $value;
    }

    public function compileDrop(string $table): string
    {
        return 'drop table ' . $this->wrapTable($table);
    }

    public function compileDropIfExists(string $table): string
    {
        return 'drop table if exists ' . $this->wrapTable($table);
    }

    public function compileRename(string $from, string $to): string
    {
        return 'alter table ' . $this->wrapTable($from) . ' rename to ' . $this->wrapTable($to);
    }

    public function compileIndex(Blueprint $blueprint, Command $command): string
    {
        return sprintf(
            'create index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint->getTable()),
            $this->columnize($command->columns)
        );
    }

    public function compileUnique(Blueprint $blueprint, Command $command): string
    {
        return sprintf(
            'create unique index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint->getTable()),
            $this->columnize($command->columns)
        );
    }

    public function compilePrimary(Blueprint $blueprint, Command $command): string
    {
        return sprintf(
            'alter table %s add primary key (%s)',
            $this->wrapTable($blueprint->getTable()),
            $this->columnize($command->columns)
        );
    }

    public function compileForeign(Blueprint $blueprint, Command $command): string
    {
        $sql = sprintf(
            'alter table %s add constraint %s foreign key (%s) references %s (%s)',
            $this->wrapTable($blueprint->getTable()),
            $this->wrap($command->index),
            $this->columnize($command->columns),
            $this->wrapTable($command->on),
            $this->columnize((array) $command->references)
        );

        if ($command->onDelete) {
            $sql .= " on delete {$command->onDelete}";
        }

        if ($command->onUpdate) {
            $sql .= " on update {$command->onUpdate}";
        }

        return $sql;
    }

    public function compileFullText(Blueprint $blueprint, Command $command): string
    {
        return sprintf(
            'create index %s on %s using gin(to_tsvector(\'english\', %s))',
            $this->wrap($command->index),
            $this->wrapTable($blueprint->getTable()),
            implode(' || \' \' || ', array_map([$this, 'wrap'], $command->columns))
        );
    }

    public function compileDropColumn(Blueprint $blueprint, Command $command): string
    {
        $columns = array_map(function ($column) {
            return 'drop column ' . $this->wrap($column);
        }, $command->columns);

        return 'alter table ' . $this->wrapTable($blueprint->getTable()) . ' ' . implode(', ', $columns);
    }

    public function compileDropPrimary(Blueprint $blueprint, Command $command): string
    {
        $table = $blueprint->getTable();
        return 'alter table ' . $this->wrapTable($table) . ' drop constraint ' . $this->wrap($table . '_pkey');
    }

    public function compileDropUnique(Blueprint $blueprint, Command $command): string
    {
        return 'drop index ' . $this->wrap($command->index);
    }

    public function compileDropIndex(Blueprint $blueprint, Command $command): string
    {
        return 'drop index ' . $this->wrap($command->index);
    }

    public function compileDropForeign(Blueprint $blueprint, Command $command): string
    {
        return 'alter table ' . $this->wrapTable($blueprint->getTable()) . ' drop constraint ' . $this->wrap($command->index);
    }

    public function compileRenameColumn(Blueprint $blueprint, Command $command): string
    {
        return sprintf(
            'alter table %s rename column %s to %s',
            $this->wrapTable($blueprint->getTable()),
            $this->wrap($command->from),
            $this->wrap($command->to)
        );
    }

    public function compileEnableForeignKeyConstraints(): string
    {
        return 'SET CONSTRAINTS ALL IMMEDIATE';
    }

    public function compileDisableForeignKeyConstraints(): string
    {
        return 'SET CONSTRAINTS ALL DEFERRED';
    }

    public function compileDropAllTables(): string
    {
        return "select 'drop table if exists \"' || tablename || '\" cascade;' from pg_tables where schemaname = current_schema()";
    }

    public function compileDropAllViews(): string
    {
        return "select 'drop view if exists \"' || viewname || '\" cascade;' from pg_views where schemaname = current_schema()";
    }

    public function compileGetAllTables(): string
    {
        return "select tablename from pg_tables where schemaname = current_schema()";
    }

    public function compileGetAllViews(): string
    {
        return "select viewname from pg_views where schemaname = current_schema()";
    }
}