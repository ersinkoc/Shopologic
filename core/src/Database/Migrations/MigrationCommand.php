<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Migrations;

class MigrationCommand
{
    protected Migrator $migrator;
    protected array $paths;

    public function __construct(Migrator $migrator, array $paths = [])
    {
        $this->migrator = $migrator;
        $this->paths = $paths ?: ['database/migrations'];
    }

    public function migrate(array $options = []): void
    {
        if (!$this->migrator->repositoryExists()) {
            $this->migrator->createRepository();
            echo "Migration table created successfully.\n";
        }
        
        $migrations = $this->migrator->run($this->paths, $options);
        
        if (count($migrations) === 0) {
            echo "Nothing to migrate.\n";
            return;
        }
        
        foreach ($migrations as $migration) {
            echo "Migrated: {$migration}\n";
        }
    }

    public function rollback(array $options = []): void
    {
        $migrations = $this->migrator->rollback($this->paths, $options);
        
        if (count($migrations) === 0) {
            echo "Nothing to rollback.\n";
            return;
        }
        
        foreach ($migrations as $migration) {
            echo "Rolled back: {$migration}\n";
        }
    }

    public function reset(): void
    {
        if (!$this->migrator->repositoryExists()) {
            echo "Migration table not found.\n";
            return;
        }
        
        $migrations = [];
        
        while (true) {
            $rolled = $this->migrator->rollback($this->paths, ['step' => 1000]);
            
            if (count($rolled) === 0) {
                break;
            }
            
            $migrations = array_merge($migrations, $rolled);
        }
        
        if (count($migrations) === 0) {
            echo "Nothing to rollback.\n";
            return;
        }
        
        foreach ($migrations as $migration) {
            echo "Rolled back: {$migration}\n";
        }
    }

    public function refresh(array $options = []): void
    {
        $this->reset();
        $this->migrate($options);
    }

    public function status(): void
    {
        if (!$this->migrator->repositoryExists()) {
            echo "Migration table not found.\n";
            return;
        }
        
        $ran = $this->migrator->getRan();
        $migrations = $this->migrator->getMigrationFiles($this->paths);
        
        if (count($migrations) === 0) {
            echo "No migrations found.\n";
            return;
        }
        
        $pending = array_diff(array_keys($migrations), $ran);
        
        echo "Migration Status:\n";
        echo "================\n\n";
        
        foreach ($migrations as $migration => $path) {
            $status = in_array($migration, $ran) ? 'Ran' : 'Pending';
            echo sprintf("%-50s %s\n", $migration, $status);
        }
        
        echo "\n";
        echo "Total: " . count($migrations) . "\n";
        echo "Ran: " . count($ran) . "\n";
        echo "Pending: " . count($pending) . "\n";
    }

    public function install(): void
    {
        $this->migrator->createRepository();
        echo "Migration table created successfully.\n";
    }
}