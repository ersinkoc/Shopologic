<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema;

class Blueprint
{
    protected string $table;
    protected array $columns = [];
    protected array $commands = [];
    protected string $engine = '';
    protected string $charset = '';
    protected string $collation = '';
    protected bool $temporary = false;

    public function __construct(string $table, ?\Closure $callback = null)
    {
        $this->table = $table;

        if ($callback) {
            $callback($this);
        }
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getAddedColumns(): array
    {
        return array_filter($this->columns, function ($column) {
            return !$column->change;
        });
    }

    public function getChangedColumns(): array
    {
        return array_filter($this->columns, function ($column) {
            return $column->change;
        });
    }

    protected function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $column = new ColumnDefinition([
            'type' => $type,
            'name' => $name,
        ] + $parameters);

        $this->columns[] = $column;

        return $column;
    }

    protected function addCommand(string $name, array $parameters = []): Command
    {
        $command = new Command(array_merge(compact('name'), $parameters));
        $this->commands[] = $command;
        return $command;
    }

    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->bigInteger($column, true, true);
    }

    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    public function char(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('char', $column, compact('length'));
    }

    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    public function dateTime(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('dateTime', $column, compact('precision'));
    }

    public function dateTimeTz(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('dateTimeTz', $column, compact('precision'));
    }

    public function decimal(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, compact('total', 'places'));
    }

    public function double(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn('double', $column, compact('total', 'places'));
    }

    public function enum(string $column, array $allowed): ColumnDefinition
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    public function float(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn('float', $column, compact('total', 'places'));
    }

    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($column);
    }
    
    public function foreignId(string $column): ColumnDefinition
    {
        return $this->unsignedBigInteger($column);
    }

    public function increments(string $column): ColumnDefinition
    {
        return $this->integer($column, true, true);
    }

    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    public function jsonb(string $column): ColumnDefinition
    {
        return $this->addColumn('jsonb', $column);
    }

    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    public function mediumInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumText', $column);
    }

    public function smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $column, compact('length'));
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    public function time(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('time', $column, compact('precision'));
    }

    public function timeTz(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('timeTz', $column, compact('precision'));
    }

    public function timestamp(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    public function timestampTz(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('timestampTz', $column, compact('precision'));
    }

    public function timestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    public function timestampsTz(int $precision = 0): void
    {
        $this->timestampTz('created_at', $precision)->nullable();
        $this->timestampTz('updated_at', $precision)->nullable();
    }

    public function tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function tinyText(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyText', $column);
    }

    public function unsignedBigInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    public function unsignedInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->integer($column, $autoIncrement, true);
    }

    public function unsignedMediumInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    public function unsignedSmallInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    public function unsignedTinyInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    public function uuid(string $column): ColumnDefinition
    {
        return $this->addColumn('uuid', $column);
    }

    public function year(string $column): ColumnDefinition
    {
        return $this->addColumn('year', $column);
    }

    public function softDeletes(string $column = 'deleted_at', int $precision = 0): ColumnDefinition
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    public function softDeletesTz(string $column = 'deleted_at', int $precision = 0): ColumnDefinition
    {
        return $this->timestampTz($column, $precision)->nullable();
    }

    public function index($columns, ?string $name = null, ?string $algorithm = null): Command
    {
        return $this->indexCommand('index', $columns, $name, $algorithm);
    }

    public function unique($columns, ?string $name = null, ?string $algorithm = null): Command
    {
        return $this->indexCommand('unique', $columns, $name, $algorithm);
    }

    public function primary($columns, ?string $name = null, ?string $algorithm = null): Command
    {
        return $this->indexCommand('primary', $columns, $name, $algorithm);
    }

    public function spatialIndex($columns, ?string $name = null): Command
    {
        return $this->indexCommand('spatialIndex', $columns, $name);
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $command = new ForeignKeyDefinition([
            'name' => 'foreign',
            'columns' => [$column],
            'table' => $this->table,
        ]);

        $this->commands[] = $command;

        return $command;
    }

    public function fullText($columns, ?string $name = null): Command
    {
        return $this->indexCommand('fullText', $columns, $name);
    }

    protected function indexCommand(string $type, $columns, ?string $index, ?string $algorithm = null): Command
    {
        $columns = (array) $columns;

        $index = $index ?: $this->createIndexName($type, $columns);

        return $this->addCommand(
            $type,
            compact('index', 'columns', 'algorithm')
        );
    }

    protected function createIndexName(string $type, array $columns): string
    {
        $index = strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);

        return str_replace(['-', '.'], '_', $index);
    }

    public function dropColumn($columns): Command
    {
        $columns = (array) $columns;
        return $this->addCommand('dropColumn', compact('columns'));
    }

    public function dropPrimary(?string $index = null): Command
    {
        return $this->dropIndexCommand('dropPrimary', 'primary', $index);
    }

    public function dropUnique($index): Command
    {
        return $this->dropIndexCommand('dropUnique', 'unique', $index);
    }

    public function dropIndex($index): Command
    {
        return $this->dropIndexCommand('dropIndex', 'index', $index);
    }

    public function dropSpatialIndex($index): Command
    {
        return $this->dropIndexCommand('dropSpatialIndex', 'spatialIndex', $index);
    }

    public function dropForeign($index): Command
    {
        return $this->dropIndexCommand('dropForeign', 'foreign', $index);
    }

    protected function dropIndexCommand(string $command, string $type, $index): Command
    {
        $columns = [];

        if (is_array($index)) {
            $index = $this->createIndexName($type, $columns = $index);
        }

        return $this->addCommand($command, compact('index', 'columns'));
    }

    public function renameColumn(string $from, string $to): Command
    {
        return $this->addCommand('renameColumn', compact('from', 'to'));
    }

    public function renameIndex(string $from, string $to): Command
    {
        return $this->addCommand('renameIndex', compact('from', 'to'));
    }

    public function dropTimestamps(): void
    {
        $this->dropColumn(['created_at', 'updated_at']);
    }

    public function dropTimestampsTz(): void
    {
        $this->dropTimestamps();
    }

    public function dropSoftDeletes(string $column = 'deleted_at'): void
    {
        $this->dropColumn($column);
    }

    public function dropSoftDeletesTz(string $column = 'deleted_at'): void
    {
        $this->dropSoftDeletes($column);
    }

    public function temporary(): void
    {
        $this->temporary = true;
    }

    public function isTemporary(): bool
    {
        return $this->temporary;
    }
}