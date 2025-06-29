<?php

namespace Shopologic\Core\Backup;

use Shopologic\Core\Container\ServiceContainer;
use Shopologic\Core\Database\DatabaseManager;

class DatabaseBackup
{
    private ServiceContainer $container;
    private DatabaseManager $db;
    
    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
        $this->db = $container->get(DatabaseManager::class);
    }
    
    public function backup(string $outputDir): array
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $config = $this->container->get('config')['database'];
        $backed = [];
        
        // Get all tables
        $tables = $this->getTables();
        
        // Create schema dump
        $schemaFile = $outputDir . '/schema.sql';
        $this->dumpSchema($tables, $schemaFile);
        $backed['schema'] = 'schema.sql';
        
        // Create data dumps
        foreach ($tables as $table) {
            $dataFile = $outputDir . "/data_{$table}.sql";
            $rowCount = $this->dumpTableData($table, $dataFile);
            
            if ($rowCount > 0) {
                $backed['data'][$table] = [
                    'file' => "data_{$table}.sql",
                    'rows' => $rowCount
                ];
            }
        }
        
        // Create metadata file
        $metadata = [
            'database' => $config['database'],
            'tables' => count($tables),
            'created_at' => date('c'),
            'pg_version' => $this->getPostgresVersion()
        ];
        
        file_put_contents($outputDir . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
        $backed['metadata'] = 'metadata.json';
        
        return $backed;
    }
    
    private function getTables(): array
    {
        $query = "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename";
        $result = $this->db->query($query);
        
        $tables = [];
        while ($row = pg_fetch_assoc($result)) {
            $tables[] = $row['tablename'];
        }
        
        return $tables;
    }
    
    private function dumpSchema(array $tables, string $outputFile): void
    {
        $sql = "-- Shopologic Database Schema Dump\n";
        $sql .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Dump table schemas
        foreach ($tables as $table) {
            $sql .= $this->getTableSchema($table);
            $sql .= "\n";
        }
        
        // Dump indexes
        foreach ($tables as $table) {
            $sql .= $this->getTableIndexes($table);
        }
        
        // Dump constraints
        foreach ($tables as $table) {
            $sql .= $this->getTableConstraints($table);
        }
        
        // Dump sequences
        $sql .= $this->getSequences();
        
        file_put_contents($outputFile, $sql);
    }
    
    private function getTableSchema(string $table): string
    {
        $sql = "-- Table: $table\n";
        $sql .= "DROP TABLE IF EXISTS \"$table\" CASCADE;\n";
        $sql .= "CREATE TABLE \"$table\" (\n";
        
        // Get column definitions
        $query = "
            SELECT column_name, data_type, character_maximum_length,
                   numeric_precision, numeric_scale, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = $1 AND table_schema = 'public'
            ORDER BY ordinal_position
        ";
        
        $result = $this->db->query($query, [$table]);
        $columns = [];
        
        while ($col = pg_fetch_assoc($result)) {
            $def = "    \"{$col['column_name']}\" ";
            
            // Data type
            $def .= $this->formatDataType($col);
            
            // Nullable
            if ($col['is_nullable'] === 'NO') {
                $def .= ' NOT NULL';
            }
            
            // Default value
            if ($col['column_default'] !== null) {
                $def .= ' DEFAULT ' . $col['column_default'];
            }
            
            $columns[] = $def;
        }
        
        $sql .= implode(",\n", $columns);
        $sql .= "\n);\n\n";
        
        return $sql;
    }
    
    private function formatDataType(array $column): string
    {
        $type = strtoupper($column['data_type']);
        
        switch ($type) {
            case 'CHARACTER VARYING':
                $length = $column['character_maximum_length'] ?? 255;
                return "VARCHAR($length)";
                
            case 'CHARACTER':
                $length = $column['character_maximum_length'] ?? 1;
                return "CHAR($length)";
                
            case 'NUMERIC':
                if ($column['numeric_precision'] && $column['numeric_scale']) {
                    return "NUMERIC({$column['numeric_precision']}, {$column['numeric_scale']})";
                }
                return 'NUMERIC';
                
            case 'TIMESTAMP WITHOUT TIME ZONE':
                return 'TIMESTAMP';
                
            case 'TIMESTAMP WITH TIME ZONE':
                return 'TIMESTAMPTZ';
                
            default:
                return $type;
        }
    }
    
    private function getTableIndexes(string $table): string
    {
        $sql = "";
        
        $query = "
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = $1 AND schemaname = 'public'
            AND indexname NOT LIKE '%_pkey'
        ";
        
        $result = $this->db->query($query, [$table]);
        
        while ($index = pg_fetch_assoc($result)) {
            $sql .= "-- Index: {$index['indexname']}\n";
            $sql .= $index['indexdef'] . ";\n\n";
        }
        
        return $sql;
    }
    
    private function getTableConstraints(string $table): string
    {
        $sql = "";
        
        // Primary keys
        $query = "
            SELECT conname, pg_get_constraintdef(oid) as condef
            FROM pg_constraint
            WHERE conrelid = $1::regclass
            AND contype = 'p'
        ";
        
        $result = $this->db->query($query, [$table]);
        
        while ($constraint = pg_fetch_assoc($result)) {
            $sql .= "ALTER TABLE \"$table\" ADD CONSTRAINT \"{$constraint['conname']}\" {$constraint['condef']};\n";
        }
        
        // Foreign keys
        $query = "
            SELECT conname, pg_get_constraintdef(oid) as condef
            FROM pg_constraint
            WHERE conrelid = $1::regclass
            AND contype = 'f'
        ";
        
        $result = $this->db->query($query, [$table]);
        
        while ($constraint = pg_fetch_assoc($result)) {
            $sql .= "ALTER TABLE \"$table\" ADD CONSTRAINT \"{$constraint['conname']}\" {$constraint['condef']};\n";
        }
        
        if ($sql) {
            $sql .= "\n";
        }
        
        return $sql;
    }
    
    private function getSequences(): string
    {
        $sql = "";
        
        $query = "
            SELECT sequence_name, start_value, increment_by, max_value, min_value, cache_value
            FROM information_schema.sequences
            WHERE sequence_schema = 'public'
        ";
        
        $result = $this->db->query($query);
        
        while ($seq = pg_fetch_assoc($result)) {
            $sql .= "-- Sequence: {$seq['sequence_name']}\n";
            $sql .= "DROP SEQUENCE IF EXISTS \"{$seq['sequence_name']}\" CASCADE;\n";
            $sql .= "CREATE SEQUENCE \"{$seq['sequence_name']}\"\n";
            $sql .= "    START WITH {$seq['start_value']}\n";
            $sql .= "    INCREMENT BY {$seq['increment_by']}\n";
            $sql .= "    MINVALUE {$seq['min_value']}\n";
            $sql .= "    MAXVALUE {$seq['max_value']}\n";
            $sql .= "    CACHE {$seq['cache_value']};\n\n";
        }
        
        return $sql;
    }
    
    private function dumpTableData(string $table, string $outputFile): int
    {
        $handle = fopen($outputFile, 'w');
        
        // Write header
        fwrite($handle, "-- Data for table: $table\n");
        fwrite($handle, "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n");
        
        // Get total row count
        $countResult = $this->db->query("SELECT COUNT(*) as count FROM \"$table\"");
        $totalRows = pg_fetch_assoc($countResult)['count'];
        
        if ($totalRows == 0) {
            fclose($handle);
            unlink($outputFile);
            return 0;
        }
        
        // Disable triggers and constraints for faster restore
        fwrite($handle, "ALTER TABLE \"$table\" DISABLE TRIGGER ALL;\n\n");
        
        // Get column names
        $columnsResult = $this->db->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = $1 AND table_schema = 'public'
            ORDER BY ordinal_position
        ", [$table]);
        
        $columns = [];
        while ($col = pg_fetch_assoc($columnsResult)) {
            $columns[] = $col['column_name'];
        }
        
        // Dump data in batches
        $batchSize = 1000;
        $offset = 0;
        
        while ($offset < $totalRows) {
            $query = "SELECT * FROM \"$table\" ORDER BY 1 LIMIT $batchSize OFFSET $offset";
            $result = $this->db->query($query);
            
            while ($row = pg_fetch_assoc($result)) {
                $values = [];
                
                foreach ($columns as $column) {
                    $value = $row[$column];
                    
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_bool($value)) {
                        $values[] = $value ? 'TRUE' : 'FALSE';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        // Escape single quotes and wrap in quotes
                        $escaped = str_replace("'", "''", $value);
                        $values[] = "'" . $escaped . "'";
                    }
                }
                
                $columnList = '"' . implode('", "', $columns) . '"';
                $valueList = implode(', ', $values);
                
                fwrite($handle, "INSERT INTO \"$table\" ($columnList) VALUES ($valueList);\n");
            }
            
            $offset += $batchSize;
        }
        
        // Re-enable triggers
        fwrite($handle, "\nALTER TABLE \"$table\" ENABLE TRIGGER ALL;\n");
        
        fclose($handle);
        
        return $totalRows;
    }
    
    private function getPostgresVersion(): string
    {
        $result = $this->db->query("SELECT version()");
        $version = pg_fetch_assoc($result)['version'];
        
        // Extract version number
        if (preg_match('/PostgreSQL (\d+\.\d+)/', $version, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }
}