<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema;

use Shopologic\Core\Database\ConnectionInterface;

abstract class SchemaBuilder
{
    protected ConnectionInterface $connection;
    protected Grammar $grammar;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->grammar = $this->createGrammar();
    }

    abstract protected function createGrammar(): Grammar;

    public function hasTable(string $table): bool
    {
        $table = $this->connection->getConfig()['schema'] . '.' . $table;
        
        $sql = $this->grammar->compileTableExists();
        
        $result = $this->connection->query($sql, [$table]);
        
        return $result->rowCount() > 0;
    }

    public function hasColumn(string $table, string $column): bool
    {
        return in_array(
            strtolower($column),
            array_map('strtolower', $this->getColumnListing($table))
        );
    }

    public function hasColumns(string $table, array $columns): bool
    {
        $tableColumns = array_map('strtolower', $this->getColumnListing($table));

        foreach ($columns as $column) {
            if (!in_array(strtolower($column), $tableColumns)) {
                return false;
            }
        }

        return true;
    }

    public function getColumnListing(string $table): array
    {
        $table = $this->connection->getConfig()['schema'] . '.' . $table;
        
        $sql = $this->grammar->compileColumnListing($table);
        
        $results = $this->connection->query($sql, [$table]);
        
        $columns = [];
        foreach ($results as $result) {
            $columns[] = $result['column_name'];
        }
        
        return $columns;
    }

    public function getColumnType(string $table, string $column): string
    {
        $table = $this->connection->getConfig()['schema'] . '.' . $table;
        
        $sql = $this->grammar->compileColumnType();
        
        $result = $this->connection->query($sql, [$table, $column])->fetch();
        
        return $result['data_type'] ?? '';
    }

    public function create(Blueprint $blueprint): void
    {
        $sql = $this->grammar->compileCreate($blueprint);
        
        $this->connection->execute($sql);
        
        $this->createIndexes($blueprint);
    }

    public function drop(string $table): void
    {
        $sql = $this->grammar->compileDrop($table);
        
        $this->connection->execute($sql);
    }

    public function dropIfExists(string $table): void
    {
        $sql = $this->grammar->compileDropIfExists($table);
        
        $this->connection->execute($sql);
    }

    public function rename(string $from, string $to): void
    {
        $sql = $this->grammar->compileRename($from, $to);
        
        $this->connection->execute($sql);
    }

    public function table(Blueprint $blueprint): void
    {
        $this->build($blueprint);
    }

    protected function build(Blueprint $blueprint): void
    {
        foreach ($blueprint->getCommands() as $command) {
            $method = 'compile' . ucfirst($command->name);
            
            if (method_exists($this->grammar, $method)) {
                $sql = $this->grammar->$method($blueprint, $command);
                
                if ($sql) {
                    $this->connection->execute($sql);
                }
            }
        }
    }

    protected function createIndexes(Blueprint $blueprint): void
    {
        foreach ($blueprint->getCommands() as $command) {
            if (in_array($command->name, ['index', 'unique', 'primary', 'foreign', 'fullText'])) {
                $method = 'compile' . ucfirst($command->name);
                
                if (method_exists($this->grammar, $method)) {
                    $sql = $this->grammar->$method($blueprint, $command);
                    
                    if ($sql) {
                        $this->connection->execute($sql);
                    }
                }
            }
        }
    }
}