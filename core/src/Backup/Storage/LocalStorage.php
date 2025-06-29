<?php

namespace Shopologic\Core\Backup\Storage;

class LocalStorage implements StorageInterface
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        
        // Create storage directory if it doesn't exist
        if (!is_dir($this->config['path'])) {
            mkdir($this->config['path'], 0755, true);
        }
    }
    
    public function store(string $localPath, string $backupId): string
    {
        $filename = basename($localPath);
        $destinationPath = $this->config['path'] . '/' . $backupId . '/' . $filename;
        $destinationDir = dirname($destinationPath);
        
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }
        
        if (is_dir($localPath)) {
            $this->copyDirectory($localPath, $destinationPath);
        } else {
            copy($localPath, $destinationPath);
        }
        
        return $destinationPath;
    }
    
    public function retrieve(string $remotePath, string $backupId): string
    {
        // For local storage, remote path is already local
        if (!file_exists($remotePath)) {
            throw new \Exception("Backup file not found: $remotePath");
        }
        
        return $remotePath;
    }
    
    public function delete(string $remotePath): void
    {
        if (is_dir($remotePath)) {
            $this->removeDirectory($remotePath);
        } elseif (file_exists($remotePath)) {
            unlink($remotePath);
        }
        
        // Remove parent directory if empty
        $parentDir = dirname($remotePath);
        if (is_dir($parentDir) && count(scandir($parentDir)) === 2) {
            rmdir($parentDir);
        }
    }
    
    public function exists(string $remotePath): bool
    {
        return file_exists($remotePath);
    }
    
    public function getStatus(): array
    {
        $path = $this->config['path'];
        
        if (!is_dir($path)) {
            return [
                'available' => false,
                'error' => 'Storage directory does not exist'
            ];
        }
        
        $freeSpace = disk_free_space($path);
        $totalSpace = disk_total_space($path);
        
        return [
            'available' => true,
            'free_space' => $freeSpace,
            'total_space' => $totalSpace,
            'used_space' => $totalSpace - $freeSpace,
            'path' => $path
        ];
    }
    
    public function list(string $prefix = ''): array
    {
        $path = $this->config['path'];
        $backups = [];
        
        if (!is_dir($path)) {
            return $backups;
        }
        
        $iterator = new \DirectoryIterator($path);
        
        foreach ($iterator as $dir) {
            if ($dir->isDot() || !$dir->isDir()) {
                continue;
            }
            
            $backupId = $dir->getFilename();
            
            if ($prefix && !str_starts_with($backupId, $prefix)) {
                continue;
            }
            
            // Find backup file in directory
            $backupFiles = glob($dir->getPathname() . '/*');
            
            if (!empty($backupFiles)) {
                $backups[] = [
                    'id' => $backupId,
                    'path' => $backupFiles[0],
                    'size' => $this->getDirectorySize($dir->getPathname()),
                    'modified' => $dir->getMTime()
                ];
            }
        }
        
        return $backups;
    }
    
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $destPath = $destination . '/' . $iterator->getSubPathName();
            
            if ($file->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($file->getPathname(), $destPath);
            }
        }
    }
    
    private function removeDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        
        rmdir($dir);
    }
    
    private function getDirectorySize(string $dir): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
}