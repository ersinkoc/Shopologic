<?php

namespace Shopologic\Core\Backup\Storage;

interface StorageInterface
{
    /**
     * Store backup file
     */
    public function store(string $localPath, string $backupId): string;
    
    /**
     * Retrieve backup file
     */
    public function retrieve(string $remotePath, string $backupId): string;
    
    /**
     * Delete backup file
     */
    public function delete(string $remotePath): void;
    
    /**
     * Check if backup exists
     */
    public function exists(string $remotePath): bool;
    
    /**
     * Get storage status
     */
    public function getStatus(): array;
    
    /**
     * List backups in storage
     */
    public function list(string $prefix = ''): array;
}