<?php

/**
 * Shopologic Plugin Project Cleanup Analyzer
 * Identifies and removes unnecessary files from the project
 */

declare(strict_types=1);

class ProjectCleanupAnalyzer
{
    private string $pluginsDir;
    private array $unnecessaryFiles = [];
    private array $duplicateFiles = [];
    private array $temporaryFiles = [];
    private array $obsoleteFiles = [];
    private int $totalFilesScanned = 0;
    private int $totalSizeRecovered = 0;
    
    public function __construct()
    {
        $this->pluginsDir = __DIR__;
    }
    
    public function executeCleanup(): void
    {
        echo "🧹 Shopologic Plugin Project Cleanup Analyzer\n";
        echo "=============================================\n\n";
        
        $this->scanForUnnecessaryFiles();
        $this->identifyDuplicateFiles();
        $this->findTemporaryFiles();
        $this->findObsoleteFiles();
        $this->generateCleanupReport();
        $this->executeCleanupOperations();
    }
    
    private function scanForUnnecessaryFiles(): void
    {
        echo "🔍 SCANNING FOR UNNECESSARY FILES\n";
        echo "=================================\n\n";
        
        $this->findSystemFiles();
        $this->findLogFiles();
        $this->findCacheFiles();
        $this->findBackupFiles();
        $this->findDebugFiles();
        
        echo "📊 Found " . count($this->unnecessaryFiles) . " unnecessary files\n\n";
    }
    
    private function findSystemFiles(): void
    {
        $systemPatterns = [
            '.DS_Store',
            'Thumbs.db',
            'desktop.ini',
            '.git',
            '.svn',
            '.hg',
            '.bzr'
        ];
        
        foreach ($systemPatterns as $pattern) {
            $files = $this->findFilesByPattern($pattern);
            foreach ($files as $file) {
                $this->unnecessaryFiles[] = [
                    'file' => $file,
                    'type' => 'system',
                    'reason' => 'System-generated file',
                    'size' => file_exists($file) ? filesize($file) : 0
                ];
            }
        }
    }
    
    private function findLogFiles(): void
    {
        $logPatterns = [
            '*.log',
            '*.log.*',
            'error_log',
            'access_log',
            'debug.log'
        ];
        
        foreach ($logPatterns as $pattern) {
            $files = glob($this->pluginsDir . '/' . $pattern);
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $this->unnecessaryFiles[] = [
                        'file' => $file,
                        'type' => 'log',
                        'reason' => 'Log file - not needed in repository',
                        'size' => filesize($file)
                    ];
                }
            }
        }
    }
    
    private function findCacheFiles(): void
    {
        $cachePatterns = [
            'cache/*',
            '*.cache',
            'tmp/*',
            'temp/*',
            '.phpunit.result.cache'
        ];
        
        foreach ($cachePatterns as $pattern) {
            $files = glob($this->pluginsDir . '/' . $pattern);
            foreach ($files as $file) {
                if (file_exists($file) && is_file($file)) {
                    $this->unnecessaryFiles[] = [
                        'file' => $file,
                        'type' => 'cache',
                        'reason' => 'Cache file - regeneratable',
                        'size' => filesize($file)
                    ];
                }
            }
        }
    }
    
    private function findBackupFiles(): void
    {
        $backupPatterns = [
            '*.bak',
            '*.backup',
            '*.old',
            '*~',
            '*.orig',
            '*.swp',
            '*.swo',
            '*.tmp'
        ];
        
        foreach ($backupPatterns as $pattern) {
            $files = glob($this->pluginsDir . '/' . $pattern);
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $this->unnecessaryFiles[] = [
                        'file' => $file,
                        'type' => 'backup',
                        'reason' => 'Backup file - not needed',
                        'size' => filesize($file)
                    ];
                }
            }
        }
    }
    
    private function findDebugFiles(): void
    {
        $debugPatterns = [
            'debug.php',
            'test.php',
            'temp.php',
            'debug_*',
            'test_*'
        ];
        
        foreach ($debugPatterns as $pattern) {
            $files = glob($this->pluginsDir . '/' . $pattern);
            foreach ($files as $file) {
                if (file_exists($file) && $this->isDebugFile($file)) {
                    $this->unnecessaryFiles[] = [
                        'file' => $file,
                        'type' => 'debug',
                        'reason' => 'Debug/test file - development only',
                        'size' => filesize($file)
                    ];
                }
            }
        }
    }
    
    private function identifyDuplicateFiles(): void
    {
        echo "🔄 IDENTIFYING DUPLICATE FILES\n";
        echo "==============================\n\n";
        
        $fileHashes = [];
        $allFiles = $this->getAllProjectFiles();
        
        foreach ($allFiles as $file) {
            if (!file_exists($file) || !is_file($file)) continue;
            
            $size = filesize($file);
            if ($size < 100) continue; // Skip very small files
            
            $hash = md5_file($file);
            
            if (isset($fileHashes[$hash])) {
                $this->duplicateFiles[] = [
                    'original' => $fileHashes[$hash],
                    'duplicate' => $file,
                    'size' => $size,
                    'reason' => 'Duplicate content detected'
                ];
            } else {
                $fileHashes[$hash] = $file;
            }
        }
        
        echo "📊 Found " . count($this->duplicateFiles) . " duplicate files\n\n";
    }
    
    private function findTemporaryFiles(): void
    {
        echo "⏰ FINDING TEMPORARY FILES\n";
        echo "==========================\n\n";
        
        $tempPatterns = [
            '/tmp/*',
            '/temp/*',
            '*.tmp',
            '*.temp',
            'coverage/*',
            'build/*'
        ];
        
        foreach ($tempPatterns as $pattern) {
            $files = glob($this->pluginsDir . $pattern);
            foreach ($files as $file) {
                if (file_exists($file) && is_file($file)) {
                    $this->temporaryFiles[] = [
                        'file' => $file,
                        'type' => 'temporary',
                        'reason' => 'Temporary file - safe to remove',
                        'size' => filesize($file)
                    ];
                }
            }
        }
        
        echo "📊 Found " . count($this->temporaryFiles) . " temporary files\n\n";
    }
    
    private function findObsoleteFiles(): void
    {
        echo "📜 FINDING OBSOLETE FILES\n";
        echo "=========================\n\n";
        
        // Check for files that might be obsolete based on content analysis
        $obsoletePatterns = [
            'old_*',
            'deprecated_*',
            'unused_*',
            'legacy_*'
        ];
        
        foreach ($obsoletePatterns as $pattern) {
            $files = glob($this->pluginsDir . '/' . $pattern);
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $this->obsoleteFiles[] = [
                        'file' => $file,
                        'type' => 'obsolete',
                        'reason' => 'Obsolete/deprecated file',
                        'size' => filesize($file)
                    ];
                }
            }
        }
        
        // Check for empty directories
        $this->findEmptyDirectories();
        
        echo "📊 Found " . count($this->obsoleteFiles) . " obsolete files/directories\n\n";
    }
    
    private function findEmptyDirectories(): void
    {
        $directories = $this->getAllDirectories();
        
        foreach ($directories as $dir) {
            if ($this->isEmptyDirectory($dir) && !$this->isProtectedDirectory($dir)) {
                $this->obsoleteFiles[] = [
                    'file' => $dir,
                    'type' => 'empty_directory',
                    'reason' => 'Empty directory - no content',
                    'size' => 0
                ];
            }
        }
    }
    
    private function generateCleanupReport(): void
    {
        echo "📋 CLEANUP ANALYSIS REPORT\n";
        echo "===========================\n\n";
        
        $totalFiles = count($this->unnecessaryFiles) + count($this->duplicateFiles) + 
                     count($this->temporaryFiles) + count($this->obsoleteFiles);
        
        $totalSize = 0;
        
        // Calculate total size
        foreach ($this->unnecessaryFiles as $file) {
            $totalSize += $file['size'];
        }
        foreach ($this->duplicateFiles as $file) {
            $totalSize += $file['size'];
        }
        foreach ($this->temporaryFiles as $file) {
            $totalSize += $file['size'];
        }
        foreach ($this->obsoleteFiles as $file) {
            $totalSize += $file['size'];
        }
        
        echo "📊 CLEANUP SUMMARY:\n";
        echo "- Unnecessary files: " . count($this->unnecessaryFiles) . "\n";
        echo "- Duplicate files: " . count($this->duplicateFiles) . "\n";
        echo "- Temporary files: " . count($this->temporaryFiles) . "\n";
        echo "- Obsolete files: " . count($this->obsoleteFiles) . "\n";
        echo "- Total files to clean: $totalFiles\n";
        echo "- Total size to recover: " . $this->formatFileSize($totalSize) . "\n\n";
        
        // Show details by category
        if (!empty($this->unnecessaryFiles)) {
            echo "🗑️ UNNECESSARY FILES:\n";
            foreach (array_slice($this->unnecessaryFiles, 0, 10) as $file) {
                echo "  - " . basename($file['file']) . " ({$file['type']}) - " . $this->formatFileSize($file['size']) . "\n";
            }
            if (count($this->unnecessaryFiles) > 10) {
                echo "  ... and " . (count($this->unnecessaryFiles) - 10) . " more\n";
            }
            echo "\n";
        }
        
        if (!empty($this->duplicateFiles)) {
            echo "🔄 DUPLICATE FILES:\n";
            foreach (array_slice($this->duplicateFiles, 0, 5) as $file) {
                echo "  - " . basename($file['duplicate']) . " (duplicate of " . basename($file['original']) . ") - " . $this->formatFileSize($file['size']) . "\n";
            }
            if (count($this->duplicateFiles) > 5) {
                echo "  ... and " . (count($this->duplicateFiles) - 5) . " more\n";
            }
            echo "\n";
        }
        
        if (!empty($this->temporaryFiles)) {
            echo "⏰ TEMPORARY FILES:\n";
            foreach (array_slice($this->temporaryFiles, 0, 10) as $file) {
                echo "  - " . basename($file['file']) . " - " . $this->formatFileSize($file['size']) . "\n";
            }
            if (count($this->temporaryFiles) > 10) {
                echo "  ... and " . (count($this->temporaryFiles) - 10) . " more\n";
            }
            echo "\n";
        }
        
        if (!empty($this->obsoleteFiles)) {
            echo "📜 OBSOLETE FILES/DIRECTORIES:\n";
            foreach (array_slice($this->obsoleteFiles, 0, 10) as $file) {
                echo "  - " . basename($file['file']) . " ({$file['type']})\n";
            }
            if (count($this->obsoleteFiles) > 10) {
                echo "  ... and " . (count($this->obsoleteFiles) - 10) . " more\n";
            }
            echo "\n";
        }
        
        $this->totalSizeRecovered = $totalSize;
    }
    
    private function executeCleanupOperations(): void
    {
        echo "🧹 EXECUTING CLEANUP OPERATIONS\n";
        echo "================================\n\n";
        
        $totalCleaned = 0;
        $totalSizeCleaned = 0;
        
        // Clean unnecessary files
        foreach ($this->unnecessaryFiles as $file) {
            if ($this->safeToDelete($file['file'])) {
                if (unlink($file['file'])) {
                    $totalCleaned++;
                    $totalSizeCleaned += $file['size'];
                    echo "🗑️  Removed: " . basename($file['file']) . " ({$file['type']})\n";
                }
            }
        }
        
        // Clean duplicate files (keep original)
        foreach ($this->duplicateFiles as $file) {
            if ($this->safeToDelete($file['duplicate'])) {
                if (unlink($file['duplicate'])) {
                    $totalCleaned++;
                    $totalSizeCleaned += $file['size'];
                    echo "🔄 Removed duplicate: " . basename($file['duplicate']) . "\n";
                }
            }
        }
        
        // Clean temporary files
        foreach ($this->temporaryFiles as $file) {
            if ($this->safeToDelete($file['file'])) {
                if (unlink($file['file'])) {
                    $totalCleaned++;
                    $totalSizeCleaned += $file['size'];
                    echo "⏰ Removed temporary: " . basename($file['file']) . "\n";
                }
            }
        }
        
        // Clean obsolete files and empty directories
        foreach ($this->obsoleteFiles as $file) {
            if ($file['type'] === 'empty_directory') {
                if (is_dir($file['file']) && $this->isEmptyDirectory($file['file'])) {
                    if (rmdir($file['file'])) {
                        $totalCleaned++;
                        echo "📁 Removed empty directory: " . basename($file['file']) . "\n";
                    }
                }
            } else {
                if ($this->safeToDelete($file['file'])) {
                    if (unlink($file['file'])) {
                        $totalCleaned++;
                        $totalSizeCleaned += $file['size'];
                        echo "📜 Removed obsolete: " . basename($file['file']) . "\n";
                    }
                }
            }
        }
        
        echo "\n✅ CLEANUP COMPLETED!\n";
        echo "======================\n\n";
        echo "📊 CLEANUP RESULTS:\n";
        echo "- Files cleaned: $totalCleaned\n";
        echo "- Space recovered: " . $this->formatFileSize($totalSizeCleaned) . "\n";
        echo "- Project is now optimized and clean!\n\n";
        
        // Generate cleanup summary report
        $this->generateCleanupSummaryReport($totalCleaned, $totalSizeCleaned);
    }
    
    private function generateCleanupSummaryReport(int $totalCleaned, int $totalSizeCleaned): void
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'cleanup_summary' => [
                'files_cleaned' => $totalCleaned,
                'size_recovered' => $totalSizeCleaned,
                'size_recovered_formatted' => $this->formatFileSize($totalSizeCleaned)
            ],
            'categories_cleaned' => [
                'unnecessary_files' => count($this->unnecessaryFiles),
                'duplicate_files' => count($this->duplicateFiles),
                'temporary_files' => count($this->temporaryFiles),
                'obsolete_files' => count($this->obsoleteFiles)
            ],
            'project_status' => 'cleaned_and_optimized'
        ];
        
        file_put_contents($this->pluginsDir . '/CLEANUP_REPORT.json', json_encode($report, JSON_PRETTY_PRINT));
        echo "💾 Cleanup report saved: CLEANUP_REPORT.json\n";
    }
    
    // Helper methods
    private function findFilesByPattern(string $pattern): array
    {
        $files = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->pluginsDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    private function getAllProjectFiles(): array
    {
        $files = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->pluginsDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
                $this->totalFilesScanned++;
            }
        }
        
        return $files;
    }
    
    private function getAllDirectories(): array
    {
        $directories = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->pluginsDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $dir) {
            if ($dir->isDir()) {
                $directories[] = $dir->getPathname();
            }
        }
        
        return $directories;
    }
    
    private function isEmptyDirectory(string $dir): bool
    {
        if (!is_dir($dir)) return false;
        
        $files = scandir($dir);
        return count($files) <= 2; // Only . and ..
    }
    
    private function isProtectedDirectory(string $dir): bool
    {
        $protectedDirs = [
            'src',
            'tests',
            'migrations',
            'templates',
            'assets',
            'marketplace-assets',
            'marketplace-packages'
        ];
        
        $dirName = basename($dir);
        return in_array($dirName, $protectedDirs);
    }
    
    private function isDebugFile(string $file): bool
    {
        if (!file_exists($file)) return false;
        
        $content = file_get_contents($file);
        $debugIndicators = [
            'var_dump',
            'print_r',
            'debug',
            'test',
            'TODO',
            'FIXME'
        ];
        
        foreach ($debugIndicators as $indicator) {
            if (strpos($content, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function safeToDelete(string $file): bool
    {
        // Don't delete if file doesn't exist
        if (!file_exists($file)) return false;
        
        // Don't delete critical project files
        $criticalFiles = [
            'plugin.json',
            'bootstrap.php',
            'README.md',
            'phpunit.xml',
            '.htaccess',
            'composer.json',
            'package.json'
        ];
        
        $fileName = basename($file);
        if (in_array($fileName, $criticalFiles)) {
            return false;
        }
        
        // Don't delete files in protected directories
        $protectedPaths = [
            '/src/',
            '/core/',
            '/public/'
        ];
        
        foreach ($protectedPaths as $path) {
            if (strpos($file, $path) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        } elseif ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}

// Execute project cleanup
$cleanup = new ProjectCleanupAnalyzer();
$cleanup->executeCleanup();