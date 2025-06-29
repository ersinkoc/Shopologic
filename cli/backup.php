#!/usr/bin/env php
<?php

/**
 * Shopologic Backup CLI Tool
 * 
 * Manages database and file backups with support for:
 * - Full and incremental backups
 * - Multiple storage backends (local, S3, FTP)
 * - Encryption and compression
 * - Retention policies
 * - Backup verification
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Shopologic\Core\Application;
use Shopologic\Core\Backup\BackupManager;
use Shopologic\Core\Backup\Storage\LocalStorage;
use Shopologic\Core\Backup\Storage\S3Storage;
use Shopologic\Core\Backup\Storage\FtpStorage;

$app = new Application(dirname(__DIR__));
$container = $app->getContainer();

// Initialize backup manager
$backupManager = new BackupManager($container);

// Register storage backends
$backupManager->registerStorage('local', new LocalStorage($container->get('config')['backup.local']));
if ($container->get('config')['backup.s3.enabled']) {
    $backupManager->registerStorage('s3', new S3Storage($container->get('config')['backup.s3']));
}
if ($container->get('config')['backup.ftp.enabled']) {
    $backupManager->registerStorage('ftp', new FtpStorage($container->get('config')['backup.ftp']));
}

$command = $argv[1] ?? 'help';
$options = array_slice($argv, 2);

switch ($command) {
    case 'create':
        createBackup($backupManager, $options);
        break;
        
    case 'restore':
        restoreBackup($backupManager, $options);
        break;
        
    case 'list':
        listBackups($backupManager, $options);
        break;
        
    case 'verify':
        verifyBackup($backupManager, $options);
        break;
        
    case 'clean':
        cleanBackups($backupManager, $options);
        break;
        
    case 'schedule':
        scheduleBackup($backupManager, $options);
        break;
        
    case 'status':
        showStatus($backupManager, $options);
        break;
        
    case 'test':
        testRestore($backupManager, $options);
        break;
        
    case 'export':
        exportBackup($backupManager, $options);
        break;
        
    case 'import':
        importBackup($backupManager, $options);
        break;
        
    default:
        showHelp();
}

function createBackup(BackupManager $manager, array $options): void
{
    $type = 'full';
    $storage = 'local';
    $encrypt = false;
    $compress = true;
    $description = '';
    
    foreach ($options as $option) {
        if (str_starts_with($option, '--type=')) {
            $type = substr($option, 7);
        } elseif (str_starts_with($option, '--storage=')) {
            $storage = substr($option, 10);
        } elseif ($option === '--encrypt') {
            $encrypt = true;
        } elseif ($option === '--no-compress') {
            $compress = false;
        } elseif (str_starts_with($option, '--description=')) {
            $description = substr($option, 14);
        }
    }
    
    echo "Creating $type backup...\n";
    
    try {
        $backup = $manager->create([
            'type' => $type,
            'storage' => $storage,
            'encrypt' => $encrypt,
            'compress' => $compress,
            'description' => $description,
            'include' => [
                'database' => true,
                'files' => true,
                'config' => true,
                'plugins' => true,
                'themes' => true,
                'uploads' => true
            ]
        ]);
        
        echo "Backup created successfully!\n";
        echo "Backup ID: {$backup->getId()}\n";
        echo "Size: " . formatBytes($backup->getSize()) . "\n";
        echo "Location: {$backup->getPath()}\n";
        
        if ($backup->isEncrypted()) {
            echo "Encryption: AES-256-GCM\n";
            echo "Encryption key saved to: storage/backups/.keys/{$backup->getId()}.key\n";
        }
        
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function restoreBackup(BackupManager $manager, array $options): void
{
    $backupId = null;
    $targetEnv = 'current';
    $skipVerification = false;
    $restoreDatabase = true;
    $restoreFiles = true;
    
    foreach ($options as $option) {
        if (!str_starts_with($option, '--')) {
            $backupId = $option;
        } elseif (str_starts_with($option, '--target=')) {
            $targetEnv = substr($option, 9);
        } elseif ($option === '--skip-verification') {
            $skipVerification = true;
        } elseif ($option === '--database-only') {
            $restoreFiles = false;
        } elseif ($option === '--files-only') {
            $restoreDatabase = false;
        }
    }
    
    if (!$backupId) {
        echo "Error: Backup ID required\n";
        echo "Usage: php backup.php restore <backup-id> [options]\n";
        exit(1);
    }
    
    echo "Restoring backup $backupId...\n";
    
    // Verify backup first
    if (!$skipVerification) {
        echo "Verifying backup integrity...\n";
        if (!$manager->verify($backupId)) {
            echo "Error: Backup verification failed\n";
            exit(1);
        }
    }
    
    // Create restore point
    echo "Creating restore point...\n";
    $restorePoint = $manager->createRestorePoint();
    echo "Restore point created: {$restorePoint->getId()}\n";
    
    try {
        $result = $manager->restore($backupId, [
            'target' => $targetEnv,
            'database' => $restoreDatabase,
            'files' => $restoreFiles,
            'pre_restore_hooks' => true,
            'post_restore_hooks' => true
        ]);
        
        echo "Restore completed successfully!\n";
        echo "Restored items:\n";
        foreach ($result->getRestoredItems() as $item => $status) {
            echo "  - $item: $status\n";
        }
        
        // Clear caches
        echo "Clearing caches...\n";
        exec('php cli/cache.php clear');
        
    } catch (\Exception $e) {
        echo "Error during restore: " . $e->getMessage() . "\n";
        echo "Rolling back to restore point...\n";
        
        try {
            $manager->rollback($restorePoint->getId());
            echo "Rollback completed\n";
        } catch (\Exception $rollbackError) {
            echo "Rollback failed: " . $rollbackError->getMessage() . "\n";
            echo "Manual intervention required!\n";
        }
        
        exit(1);
    }
}

function listBackups(BackupManager $manager, array $options): void
{
    $storage = 'all';
    $limit = 20;
    $showDetails = false;
    
    foreach ($options as $option) {
        if (str_starts_with($option, '--storage=')) {
            $storage = substr($option, 10);
        } elseif (str_starts_with($option, '--limit=')) {
            $limit = (int)substr($option, 8);
        } elseif ($option === '--details') {
            $showDetails = true;
        }
    }
    
    $backups = $manager->list($storage, $limit);
    
    if (empty($backups)) {
        echo "No backups found\n";
        return;
    }
    
    echo "Available backups:\n\n";
    
    if ($showDetails) {
        foreach ($backups as $backup) {
            echo "ID: {$backup->getId()}\n";
            echo "Created: {$backup->getCreatedAt()->format('Y-m-d H:i:s')}\n";
            echo "Type: {$backup->getType()}\n";
            echo "Size: " . formatBytes($backup->getSize()) . "\n";
            echo "Storage: {$backup->getStorage()}\n";
            echo "Encrypted: " . ($backup->isEncrypted() ? 'Yes' : 'No') . "\n";
            echo "Compressed: " . ($backup->isCompressed() ? 'Yes' : 'No') . "\n";
            echo "Description: {$backup->getDescription()}\n";
            echo "Status: {$backup->getStatus()}\n";
            echo "---\n\n";
        }
    } else {
        $headers = ['ID', 'Created', 'Type', 'Size', 'Storage', 'Status'];
        $rows = [];
        
        foreach ($backups as $backup) {
            $rows[] = [
                $backup->getId(),
                $backup->getCreatedAt()->format('Y-m-d H:i:s'),
                $backup->getType(),
                formatBytes($backup->getSize()),
                $backup->getStorage(),
                $backup->getStatus()
            ];
        }
        
        printTable($headers, $rows);
    }
}

function verifyBackup(BackupManager $manager, array $options): void
{
    $backupId = $options[0] ?? null;
    
    if (!$backupId) {
        echo "Error: Backup ID required\n";
        echo "Usage: php backup.php verify <backup-id>\n";
        exit(1);
    }
    
    echo "Verifying backup $backupId...\n\n";
    
    $result = $manager->verify($backupId);
    
    if ($result->isValid()) {
        echo "✓ Backup is valid\n\n";
    } else {
        echo "✗ Backup verification failed\n\n";
    }
    
    echo "Verification details:\n";
    echo "- Checksum: " . ($result->checksumValid() ? '✓ Valid' : '✗ Invalid') . "\n";
    echo "- Structure: " . ($result->structureValid() ? '✓ Valid' : '✗ Invalid') . "\n";
    echo "- Metadata: " . ($result->metadataValid() ? '✓ Valid' : '✗ Invalid') . "\n";
    
    if ($result->hasFiles()) {
        echo "- Files: {$result->getFileCount()} files, " . formatBytes($result->getTotalSize()) . "\n";
    }
    
    if ($result->hasDatabase()) {
        echo "- Database: {$result->getTableCount()} tables\n";
    }
    
    if (!$result->isValid()) {
        echo "\nErrors:\n";
        foreach ($result->getErrors() as $error) {
            echo "- $error\n";
        }
        exit(1);
    }
}

function cleanBackups(BackupManager $manager, array $options): void
{
    $dryRun = in_array('--dry-run', $options);
    $force = in_array('--force', $options);
    $keepDays = null;
    $keepCount = null;
    
    foreach ($options as $option) {
        if (str_starts_with($option, '--keep-days=')) {
            $keepDays = (int)substr($option, 12);
        } elseif (str_starts_with($option, '--keep-count=')) {
            $keepCount = (int)substr($option, 13);
        }
    }
    
    if (!$keepDays && !$keepCount) {
        // Use default retention policy from config
        $config = $manager->getRetentionPolicy();
        $keepDays = $config['days'] ?? 30;
        $keepCount = $config['count'] ?? 10;
    }
    
    echo "Cleaning old backups...\n";
    echo "Retention policy: Keep last $keepCount backups or backups from last $keepDays days\n\n";
    
    $toDelete = $manager->getBackupsToClean($keepDays, $keepCount);
    
    if (empty($toDelete)) {
        echo "No backups to clean\n";
        return;
    }
    
    echo "Backups to be deleted:\n";
    foreach ($toDelete as $backup) {
        echo "- {$backup->getId()} (Created: {$backup->getCreatedAt()->format('Y-m-d H:i:s')}, Size: " . formatBytes($backup->getSize()) . ")\n";
    }
    
    $totalSize = array_sum(array_map(fn($b) => $b->getSize(), $toDelete));
    echo "\nTotal space to be freed: " . formatBytes($totalSize) . "\n";
    
    if ($dryRun) {
        echo "\nDry run mode - no backups deleted\n";
        return;
    }
    
    if (!$force) {
        echo "\nDelete these backups? (y/N): ";
        $confirm = trim(fgets(STDIN));
        if (strtolower($confirm) !== 'y') {
            echo "Cancelled\n";
            return;
        }
    }
    
    foreach ($toDelete as $backup) {
        echo "Deleting {$backup->getId()}...";
        try {
            $manager->delete($backup->getId());
            echo " Done\n";
        } catch (\Exception $e) {
            echo " Failed: {$e->getMessage()}\n";
        }
    }
    
    echo "\nCleanup completed\n";
}

function scheduleBackup(BackupManager $manager, array $options): void
{
    $action = $options[0] ?? 'list';
    
    switch ($action) {
        case 'add':
            $schedule = $options[1] ?? null;
            $type = 'full';
            $storage = 'local';
            
            foreach ($options as $option) {
                if (str_starts_with($option, '--type=')) {
                    $type = substr($option, 7);
                } elseif (str_starts_with($option, '--storage=')) {
                    $storage = substr($option, 10);
                }
            }
            
            if (!$schedule) {
                echo "Error: Schedule expression required\n";
                echo "Usage: php backup.php schedule add <cron-expression> [options]\n";
                exit(1);
            }
            
            $job = $manager->schedule($schedule, $type, $storage);
            echo "Backup scheduled\n";
            echo "Job ID: {$job->getId()}\n";
            echo "Schedule: $schedule\n";
            echo "Next run: {$job->getNextRun()->format('Y-m-d H:i:s')}\n";
            break;
            
        case 'remove':
            $jobId = $options[1] ?? null;
            if (!$jobId) {
                echo "Error: Job ID required\n";
                exit(1);
            }
            
            $manager->unschedule($jobId);
            echo "Schedule removed\n";
            break;
            
        case 'list':
        default:
            $schedules = $manager->getSchedules();
            
            if (empty($schedules)) {
                echo "No scheduled backups\n";
                return;
            }
            
            echo "Scheduled backups:\n\n";
            foreach ($schedules as $schedule) {
                echo "ID: {$schedule->getId()}\n";
                echo "Schedule: {$schedule->getCronExpression()}\n";
                echo "Type: {$schedule->getType()}\n";
                echo "Storage: {$schedule->getStorage()}\n";
                echo "Next run: {$schedule->getNextRun()->format('Y-m-d H:i:s')}\n";
                echo "Last run: " . ($schedule->getLastRun() ? $schedule->getLastRun()->format('Y-m-d H:i:s') : 'Never') . "\n";
                echo "Status: {$schedule->getStatus()}\n";
                echo "---\n\n";
            }
    }
}

function showStatus(BackupManager $manager, array $options): void
{
    $status = $manager->getStatus();
    
    echo "Backup System Status\n";
    echo "===================\n\n";
    
    echo "Storage Backends:\n";
    foreach ($status->getStorageBackends() as $name => $backend) {
        echo "- $name: " . ($backend['available'] ? '✓ Available' : '✗ Unavailable') . "\n";
        if ($backend['available']) {
            echo "  Space: " . formatBytes($backend['free_space']) . " free of " . formatBytes($backend['total_space']) . "\n";
        }
    }
    
    echo "\nLast Backup:\n";
    if ($lastBackup = $status->getLastBackup()) {
        echo "- ID: {$lastBackup->getId()}\n";
        echo "- Created: {$lastBackup->getCreatedAt()->format('Y-m-d H:i:s')}\n";
        echo "- Type: {$lastBackup->getType()}\n";
        echo "- Status: {$lastBackup->getStatus()}\n";
    } else {
        echo "- No backups found\n";
    }
    
    echo "\nBackup Statistics:\n";
    $stats = $status->getStatistics();
    echo "- Total backups: {$stats['total_count']}\n";
    echo "- Total size: " . formatBytes($stats['total_size']) . "\n";
    echo "- Success rate: {$stats['success_rate']}%\n";
    echo "- Average backup time: {$stats['avg_duration']}s\n";
    
    echo "\nActive Jobs:\n";
    $activeJobs = $status->getActiveJobs();
    if (empty($activeJobs)) {
        echo "- No active backup jobs\n";
    } else {
        foreach ($activeJobs as $job) {
            echo "- {$job->getId()}: {$job->getProgress()}% complete\n";
        }
    }
    
    echo "\nHealth Check:\n";
    $health = $status->getHealthCheck();
    foreach ($health as $check => $result) {
        echo "- $check: " . ($result['passed'] ? '✓ OK' : '✗ Failed') . "\n";
        if (!$result['passed']) {
            echo "  Error: {$result['message']}\n";
        }
    }
}

function testRestore(BackupManager $manager, array $options): void
{
    $backupId = $options[0] ?? null;
    
    if (!$backupId) {
        // Use latest backup
        $backups = $manager->list('all', 1);
        if (empty($backups)) {
            echo "Error: No backups available\n";
            exit(1);
        }
        $backupId = $backups[0]->getId();
    }
    
    echo "Testing restore of backup $backupId...\n\n";
    
    // Create test environment
    echo "Creating test environment...\n";
    $testEnv = $manager->createTestEnvironment();
    
    try {
        // Perform test restore
        echo "Performing test restore...\n";
        $result = $manager->testRestore($backupId, $testEnv);
        
        if ($result->isSuccessful()) {
            echo "✓ Test restore successful\n\n";
            
            echo "Validation results:\n";
            echo "- Database integrity: " . ($result->isDatabaseValid() ? '✓ Pass' : '✗ Fail') . "\n";
            echo "- File integrity: " . ($result->areFilesValid() ? '✓ Pass' : '✗ Fail') . "\n";
            echo "- Application health: " . ($result->isApplicationHealthy() ? '✓ Pass' : '✗ Fail') . "\n";
            
            if ($result->hasWarnings()) {
                echo "\nWarnings:\n";
                foreach ($result->getWarnings() as $warning) {
                    echo "- $warning\n";
                }
            }
        } else {
            echo "✗ Test restore failed\n\n";
            echo "Errors:\n";
            foreach ($result->getErrors() as $error) {
                echo "- $error\n";
            }
            exit(1);
        }
        
    } finally {
        // Clean up test environment
        echo "\nCleaning up test environment...\n";
        $manager->destroyTestEnvironment($testEnv);
    }
}

function exportBackup(BackupManager $manager, array $options): void
{
    $backupId = $options[0] ?? null;
    $outputPath = null;
    $format = 'tar';
    
    foreach ($options as $option) {
        if (str_starts_with($option, '--output=')) {
            $outputPath = substr($option, 9);
        } elseif (str_starts_with($option, '--format=')) {
            $format = substr($option, 9);
        }
    }
    
    if (!$backupId) {
        echo "Error: Backup ID required\n";
        echo "Usage: php backup.php export <backup-id> --output=<path>\n";
        exit(1);
    }
    
    if (!$outputPath) {
        $outputPath = "./backup-export-{$backupId}.{$format}";
    }
    
    echo "Exporting backup $backupId...\n";
    
    try {
        $exportFile = $manager->export($backupId, $outputPath, $format);
        
        echo "Export completed successfully!\n";
        echo "File: $exportFile\n";
        echo "Size: " . formatBytes(filesize($exportFile)) . "\n";
        
        // Generate manifest
        $manifest = $manager->generateManifest($backupId);
        $manifestFile = str_replace(".{$format}", '-manifest.json', $exportFile);
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));
        echo "Manifest: $manifestFile\n";
        
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function importBackup(BackupManager $manager, array $options): void
{
    $inputPath = $options[0] ?? null;
    $storage = 'local';
    
    foreach ($options as $option) {
        if (str_starts_with($option, '--storage=')) {
            $storage = substr($option, 10);
        }
    }
    
    if (!$inputPath || !file_exists($inputPath)) {
        echo "Error: Valid backup file path required\n";
        echo "Usage: php backup.php import <backup-file> [--storage=<storage>]\n";
        exit(1);
    }
    
    echo "Importing backup from $inputPath...\n";
    
    // Check for manifest
    $manifestPath = str_replace(['.tar', '.zip'], '-manifest.json', $inputPath);
    if (file_exists($manifestPath)) {
        echo "Found manifest file\n";
        $manifest = json_decode(file_get_contents($manifestPath), true);
        echo "Original backup ID: {$manifest['id']}\n";
        echo "Created: {$manifest['created_at']}\n";
    }
    
    try {
        $backup = $manager->import($inputPath, $storage);
        
        echo "Import completed successfully!\n";
        echo "New backup ID: {$backup->getId()}\n";
        echo "Storage: {$backup->getStorage()}\n";
        
        // Verify imported backup
        echo "Verifying imported backup...\n";
        if ($manager->verify($backup->getId())) {
            echo "✓ Verification passed\n";
        } else {
            echo "✗ Verification failed\n";
            echo "Warning: Imported backup may be corrupted\n";
        }
        
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

function printTable(array $headers, array $rows): void
{
    $widths = [];
    foreach ($headers as $i => $header) {
        $widths[$i] = strlen($header);
        foreach ($rows as $row) {
            $widths[$i] = max($widths[$i], strlen($row[$i] ?? ''));
        }
    }
    
    // Print headers
    foreach ($headers as $i => $header) {
        printf("%-{$widths[$i]}s  ", $header);
    }
    echo "\n";
    
    // Print separator
    foreach ($widths as $width) {
        echo str_repeat('-', $width) . '  ';
    }
    echo "\n";
    
    // Print rows
    foreach ($rows as $row) {
        foreach ($row as $i => $cell) {
            printf("%-{$widths[$i]}s  ", $cell ?? '');
        }
        echo "\n";
    }
}

function showHelp(): void
{
    echo <<<HELP
Shopologic Backup Management

Usage: php backup.php <command> [options]

Commands:
  create              Create a new backup
  restore             Restore from a backup
  list                List available backups
  verify              Verify backup integrity
  clean               Clean old backups
  schedule            Manage backup schedules
  status              Show backup system status
  test                Test restore process
  export              Export backup to file
  import              Import backup from file

Create Options:
  --type=<type>       Backup type (full, incremental, differential)
  --storage=<name>    Storage backend (local, s3, ftp)
  --encrypt           Encrypt backup
  --no-compress       Disable compression
  --description=<desc> Add description

Restore Options:
  --target=<env>      Target environment
  --skip-verification Skip backup verification
  --database-only     Restore database only
  --files-only        Restore files only

List Options:
  --storage=<name>    Filter by storage backend
  --limit=<n>         Limit results
  --details           Show detailed information

Clean Options:
  --keep-days=<n>     Keep backups newer than n days
  --keep-count=<n>    Keep last n backups
  --dry-run           Show what would be deleted
  --force             Skip confirmation

Schedule Options:
  add <cron>          Add backup schedule
  remove <id>         Remove backup schedule
  list                List schedules

Examples:
  php backup.php create --type=full --encrypt
  php backup.php restore backup-20240115-123456
  php backup.php list --storage=s3 --limit=10
  php backup.php clean --keep-days=30 --dry-run
  php backup.php schedule add "0 2 * * *" --type=incremental

HELP;
}