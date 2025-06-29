<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema;

use Shopologic\Core\Database\ConnectionInterface;

class Schema
{
    protected static ?ConnectionInterface $connection = null;

    public static function connection(?ConnectionInterface $connection = null): void
    {
        static::$connection = $connection;
    }

    public static function create(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $builder = static::getBuilder();
        $builder->create($blueprint);
    }

    public static function drop(string $table): void
    {
        $builder = static::getBuilder();
        $builder->drop($table);
    }

    public static function dropIfExists(string $table): void
    {
        $builder = static::getBuilder();
        $builder->dropIfExists($table);
    }

    public static function table(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $builder = static::getBuilder();
        $builder->table($blueprint);
    }

    public static function rename(string $from, string $to): void
    {
        $builder = static::getBuilder();
        $builder->rename($from, $to);
    }

    public static function hasTable(string $table): bool
    {
        $builder = static::getBuilder();
        return $builder->hasTable($table);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $builder = static::getBuilder();
        return $builder->hasColumn($table, $column);
    }

    public static function hasColumns(string $table, array $columns): bool
    {
        $builder = static::getBuilder();
        return $builder->hasColumns($table, $columns);
    }

    public static function getColumnType(string $table, string $column): string
    {
        $builder = static::getBuilder();
        return $builder->getColumnType($table, $column);
    }

    public static function getColumnListing(string $table): array
    {
        $builder = static::getBuilder();
        return $builder->getColumnListing($table);
    }

    protected static function getBuilder(): SchemaBuilder
    {
        $connection = static::$connection ?? app('db')->connection();
        return new PostgreSQLSchemaBuilder($connection);
    }
}