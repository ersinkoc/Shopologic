<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Migrations;

use Shopologic\Core\Database\ConnectionInterface;
use Shopologic\Core\Database\DatabaseManager;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class Migrator
{
    protected DatabaseManager $db;
    protected ConnectionInterface $connection;
    protected string $table = 'migrations';
    protected array $paths = [];

    public function __construct(DatabaseManager $db, string $migrationsPath = null)
    {
        $this->db = $db;
        $this->connection = $db->connection();
        if ($migrationsPath) {
            $this->paths[] = $migrationsPath;
        }
    }

    public function install(): void
    {
        if ($this->migrationTableExists()) {
            return;
        }

        Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
            $table->timestamps();
        });
    }

    public function up(): array
    {
        $this->ensureMigrationTableExists();
        
        $migrations = $this->getPendingMigrations();
        
        if (empty($migrations)) {
            return [];
        }

        $batch = $this->getNextBatchNumber();
        
        foreach ($migrations as $migration) {
            $this->runMigration($migration, $batch);
        }
        
        return $migrations;
    }

    public function down(int $steps = 1): array
    {
        $this->ensureMigrationTableExists();
        
        $migrations = $this->getLastMigrations($steps);
        
        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration);
        }
        
        return $migrations;
    }

    public function reset(): array
    {
        $this->ensureMigrationTableExists();
        
        $migrations = $this->getAllMigrations();
        
        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration);
        }
        
        return $migrations;
    }

    public function fresh(): array
    {
        $this->dropAllTables();
        
        return $this->up();
    }

    public function refresh(?int $steps = null): array
    {
        if ($steps) {
            $this->down($steps);
        } else {
            $this->reset();
        }
        
        return $this->up();
    }

    public function status(): array
    {
        $this->ensureMigrationTableExists();
        
        $allMigrations = $this->getAllMigrationFiles();
        $ranMigrations = $this->getRanMigrations();
        
        $status = [];
        foreach ($allMigrations as $migration) {
            $status[$migration] = in_array($migration, $ranMigrations);
        }
        
        return $status;
    }

    public function create(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        $className = 'Create' . ucfirst(camel_case($name)) . 'Table';
        $filename = $timestamp . '_' . snake_case($name) . '.php';
        $path = $this->paths[0] . '/' . $filename;
        
        $stub = $this->getStub($className);
        
        file_put_contents($path, $stub);
        
        return $filename;
    }

    protected function migrationTableExists(): bool
    {
        try {
            $this->connection->select("SELECT 1 FROM {$this->table} LIMIT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function ensureMigrationTableExists(): void
    {
        if (!$this->migrationTableExists()) {
            $this->install();
        }
    }

    protected function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $ranMigrations = $this->getRanMigrations();
        
        return array_diff($allMigrations, $ranMigrations);
    }

    protected function getAllMigrationFiles(): array
    {
        $migrations = [];
        
        foreach ($this->paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            
            $files = glob($path . '/*.php');
            foreach ($files as $file) {
                $migrations[] = basename($file, '.php');
            }
        }
        
        sort($migrations);
        return $migrations;
    }

    protected function getRanMigrations(): array
    {
        $results = $this->connection->select("SELECT migration FROM {$this->table} ORDER BY migration");
        
        return array_column($results, 'migration');
    }

    protected function getAllMigrations(): array
    {
        $results = $this->connection->select("SELECT migration FROM {$this->table} ORDER BY batch DESC, migration DESC");
        
        return array_column($results, 'migration');
    }

    protected function getLastMigrations(int $steps): array
    {
        $results = $this->connection->select("SELECT migration FROM {$this->table} ORDER BY batch DESC, migration DESC LIMIT ?", [$steps]);
        
        return array_column($results, 'migration');
    }

    protected function getNextBatchNumber(): int
    {
        $result = $this->connection->select("SELECT MAX(batch) as max_batch FROM {$this->table}");
        
        return ((int) ($result[0]['max_batch'] ?? 0)) + 1;
    }

    protected function runMigration(string $migration, int $batch): void
    {
        $instance = $this->loadMigration($migration);
        
        if (method_exists($instance, 'up')) {
            $instance->up();
        }
        
        $this->connection->insert("INSERT INTO {$this->table} (migration, batch) VALUES (?, ?)", [$migration, $batch]);
    }

    protected function rollbackMigration(string $migration): void
    {
        $instance = $this->loadMigration($migration);
        
        if (method_exists($instance, 'down')) {
            $instance->down();
        }
        
        $this->connection->delete("DELETE FROM {$this->table} WHERE migration = ?", [$migration]);
    }

    protected function loadMigration(string $migration): object
    {
        foreach ($this->paths as $path) {
            $file = $path . '/' . $migration . '.php';
            if (file_exists($file)) {
                require_once $file;
                
                // Extract class name from filename
                $parts = explode('_', $migration);
                $className = '';
                for ($i = 4; $i < count($parts); $i++) {
                    $className .= ucfirst($parts[$i]);
                }
                
                if (class_exists($className)) {
                    return new $className();
                }
            }
        }
        
        throw new \Exception("Migration class not found for: {$migration}");
    }

    protected function dropAllTables(): void
    {
        $driver = $this->connection->getDriverName();
        
        if ($driver === 'mysql') {
            $this->connection->statement('SET FOREIGN_KEY_CHECKS = 0');
            $tables = $this->connection->select('SHOW TABLES');
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                $this->connection->statement("DROP TABLE IF EXISTS `{$tableName}`");
            }
            $this->connection->statement('SET FOREIGN_KEY_CHECKS = 1');
        } elseif ($driver === 'pgsql') {
            $tables = $this->connection->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
            foreach ($tables as $table) {
                $this->connection->statement("DROP TABLE IF EXISTS \"{$table['tablename']}\" CASCADE");
            }
        }
    }

    protected function getStub(string $className): string
    {
        return "<?php

declare(strict_types=1);

use Shopologic\\Core\\Database\\Migrations\\Migration;
use Shopologic\\Core\\Database\\Schema\\Schema;
use Shopologic\\Core\\Database\\Schema\\Blueprint;

class {$className} extends Migration
{
    public function up(): void
    {
        Schema::create('table_name', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
";
    }
}

// Helper functions
if (!function_exists('camel_case')) {
    function camel_case(string $value): string {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value))));
    }
}

if (!function_exists('snake_case')) {
    function snake_case(string $value): string {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}