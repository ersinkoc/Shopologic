<?php

namespace Shopologic\Core\Backup;

use Shopologic\Core\Container\ServiceContainer;
use Shopologic\Core\Database\DatabaseManager;
use Shopologic\Core\Event\EventDispatcher;
use Shopologic\Core\Backup\Storage\StorageInterface;
use Shopologic\Core\Backup\Compressor\CompressorInterface;
use Shopologic\Core\Backup\Encryptor\EncryptorInterface;

class BackupManager
{
    private ServiceContainer $container;
    private array $storageBackends = [];
    private array $schedules = [];
    private BackupRepository $repository;
    private EventDispatcher $events;
    
    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
        $this->repository = new BackupRepository($container->get(DatabaseManager::class));
        $this->events = $container->get(EventDispatcher::class);
    }
    
    public function registerStorage(string $name, StorageInterface $storage): void
    {
        $this->storageBackends[$name] = $storage;
    }
    
    public function create(array $options = []): Backup
    {
        $this->events->dispatch(new BackupStartedEvent($options));
        
        $backup = new Backup();
        $backup->setId($this->generateBackupId());
        $backup->setType($options['type'] ?? 'full');
        $backup->setStorage($options['storage'] ?? 'local');
        $backup->setDescription($options['description'] ?? '');
        $backup->setEncrypted($options['encrypt'] ?? false);
        $backup->setCompressed($options['compress'] ?? true);
        $backup->setCreatedAt(new \DateTime());
        $backup->setStatus('in_progress');
        
        $this->repository->save($backup);
        
        try {
            // Create backup directory
            $backupDir = $this->prepareBackupDirectory($backup);
            
            // Backup components
            $files = [];
            
            if ($options['include']['database'] ?? true) {
                $this->events->dispatch(new BackupComponentEvent('database', 'started'));
                $files['database'] = $this->backupDatabase($backupDir);
                $this->events->dispatch(new BackupComponentEvent('database', 'completed'));
            }
            
            if ($options['include']['files'] ?? true) {
                $this->events->dispatch(new BackupComponentEvent('files', 'started'));
                $files['files'] = $this->backupFiles($backupDir, $options);
                $this->events->dispatch(new BackupComponentEvent('files', 'completed'));
            }
            
            if ($options['include']['config'] ?? true) {
                $files['config'] = $this->backupConfig($backupDir);
            }
            
            // Create manifest
            $manifest = $this->createManifest($backup, $files, $options);
            file_put_contents($backupDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            
            // Compress if requested
            if ($backup->isCompressed()) {
                $compressor = $this->container->get(CompressorInterface::class);
                $archivePath = $compressor->compress($backupDir, $backup->getId());
                $backup->setPath($archivePath);
                
                // Remove uncompressed directory
                $this->removeDirectory($backupDir);
            } else {
                $backup->setPath($backupDir);
            }
            
            // Encrypt if requested
            if ($backup->isEncrypted()) {
                $encryptor = $this->container->get(EncryptorInterface::class);
                $encryptedPath = $encryptor->encrypt($backup->getPath(), $backup->getId());
                
                // Save encryption key
                $this->saveEncryptionKey($backup->getId(), $encryptor->getKey());
                
                // Remove unencrypted file
                if ($backup->getPath() !== $encryptedPath) {
                    unlink($backup->getPath());
                }
                
                $backup->setPath($encryptedPath);
            }
            
            // Calculate size
            $backup->setSize($this->calculateSize($backup->getPath()));
            
            // Store in selected backend
            $storage = $this->storageBackends[$backup->getStorage()];
            $storagePath = $storage->store($backup->getPath(), $backup->getId());
            $backup->setPath($storagePath);
            
            // Update status
            $backup->setStatus('completed');
            $backup->setCompletedAt(new \DateTime());
            
            $this->repository->save($backup);
            
            $this->events->dispatch(new BackupCompletedEvent($backup));
            
            return $backup;
            
        } catch (\Exception $e) {
            $backup->setStatus('failed');
            $backup->setError($e->getMessage());
            $this->repository->save($backup);
            
            $this->events->dispatch(new BackupFailedEvent($backup, $e));
            
            throw $e;
        }
    }
    
    public function restore(string $backupId, array $options = []): RestoreResult
    {
        $backup = $this->repository->find($backupId);
        if (!$backup) {
            throw new \Exception("Backup not found: $backupId");
        }
        
        $this->events->dispatch(new RestoreStartedEvent($backup, $options));
        
        $result = new RestoreResult();
        
        try {
            // Retrieve backup from storage
            $storage = $this->storageBackends[$backup->getStorage()];
            $localPath = $storage->retrieve($backup->getPath(), $backupId);
            
            // Decrypt if needed
            if ($backup->isEncrypted()) {
                $encryptor = $this->container->get(EncryptorInterface::class);
                $key = $this->loadEncryptionKey($backupId);
                $decryptedPath = $encryptor->decrypt($localPath, $key);
                
                if ($localPath !== $decryptedPath) {
                    unlink($localPath);
                }
                
                $localPath = $decryptedPath;
            }
            
            // Decompress if needed
            if ($backup->isCompressed()) {
                $compressor = $this->container->get(CompressorInterface::class);
                $extractPath = $compressor->decompress($localPath);
                
                if ($localPath !== $extractPath) {
                    unlink($localPath);
                }
                
                $localPath = $extractPath;
            }
            
            // Load manifest
            $manifestPath = is_dir($localPath) ? $localPath . '/manifest.json' : dirname($localPath) . '/manifest.json';
            $manifest = json_decode(file_get_contents($manifestPath), true);
            
            // Pre-restore hooks
            if ($options['pre_restore_hooks'] ?? true) {
                $this->events->dispatch(new PreRestoreEvent($backup, $manifest));
            }
            
            // Restore components
            if ($options['database'] ?? true) {
                $this->restoreDatabase($localPath, $manifest);
                $result->addRestoredItem('database', 'success');
            }
            
            if ($options['files'] ?? true) {
                $this->restoreFiles($localPath, $manifest, $options);
                $result->addRestoredItem('files', 'success');
            }
            
            // Post-restore hooks
            if ($options['post_restore_hooks'] ?? true) {
                $this->events->dispatch(new PostRestoreEvent($backup, $result));
            }
            
            // Clean up temporary files
            if (is_dir($localPath)) {
                $this->removeDirectory($localPath);
            } else {
                unlink($localPath);
            }
            
            $result->setSuccessful(true);
            
            $this->events->dispatch(new RestoreCompletedEvent($backup, $result));
            
            return $result;
            
        } catch (\Exception $e) {
            $result->setSuccessful(false);
            $result->addError($e->getMessage());
            
            $this->events->dispatch(new RestoreFailedEvent($backup, $e));
            
            throw $e;
        }
    }
    
    public function verify(string $backupId): VerificationResult
    {
        $backup = $this->repository->find($backupId);
        if (!$backup) {
            throw new \Exception("Backup not found: $backupId");
        }
        
        $verifier = new BackupVerifier($this->container);
        return $verifier->verify($backup, $this->storageBackends[$backup->getStorage()]);
    }
    
    public function list(string $storage = 'all', int $limit = 20): array
    {
        if ($storage === 'all') {
            return $this->repository->findAll($limit);
        }
        
        return $this->repository->findByStorage($storage, $limit);
    }
    
    public function delete(string $backupId): void
    {
        $backup = $this->repository->find($backupId);
        if (!$backup) {
            throw new \Exception("Backup not found: $backupId");
        }
        
        // Delete from storage
        $storage = $this->storageBackends[$backup->getStorage()];
        $storage->delete($backup->getPath());
        
        // Delete encryption key if exists
        $keyPath = $this->getEncryptionKeyPath($backupId);
        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
        
        // Delete from database
        $this->repository->delete($backupId);
        
        $this->events->dispatch(new BackupDeletedEvent($backup));
    }
    
    public function getBackupsToClean(int $keepDays, int $keepCount): array
    {
        $allBackups = $this->repository->findAll();
        
        // Sort by created date (newest first)
        usort($allBackups, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        
        $toDelete = [];
        $cutoffDate = new \DateTime("-{$keepDays} days");
        
        foreach ($allBackups as $index => $backup) {
            // Keep the most recent backups based on count
            if ($index < $keepCount) {
                continue;
            }
            
            // Keep backups newer than cutoff date
            if ($backup->getCreatedAt() > $cutoffDate) {
                continue;
            }
            
            $toDelete[] = $backup;
        }
        
        return $toDelete;
    }
    
    public function schedule(string $cronExpression, string $type, string $storage): ScheduledBackup
    {
        $schedule = new ScheduledBackup();
        $schedule->setId(uniqid('schedule_'));
        $schedule->setCronExpression($cronExpression);
        $schedule->setType($type);
        $schedule->setStorage($storage);
        $schedule->setCreatedAt(new \DateTime());
        $schedule->setStatus('active');
        
        $this->schedules[] = $schedule;
        $this->saveSchedules();
        
        return $schedule;
    }
    
    public function unschedule(string $jobId): void
    {
        $this->schedules = array_filter(
            $this->schedules,
            fn($s) => $s->getId() !== $jobId
        );
        
        $this->saveSchedules();
    }
    
    public function getSchedules(): array
    {
        $this->loadSchedules();
        return $this->schedules;
    }
    
    public function getStatus(): BackupStatus
    {
        $status = new BackupStatus();
        
        // Check storage backends
        foreach ($this->storageBackends as $name => $storage) {
            $status->addStorageBackend($name, $storage->getStatus());
        }
        
        // Get last backup
        $lastBackup = $this->repository->findLast();
        $status->setLastBackup($lastBackup);
        
        // Calculate statistics
        $stats = $this->repository->getStatistics();
        $status->setStatistics($stats);
        
        // Check active jobs
        $activeJobs = $this->repository->findByStatus('in_progress');
        $status->setActiveJobs($activeJobs);
        
        // Run health checks
        $healthChecker = new BackupHealthChecker($this->container);
        $health = $healthChecker->check();
        $status->setHealthCheck($health);
        
        return $status;
    }
    
    public function createRestorePoint(): RestorePoint
    {
        $restorePoint = new RestorePoint();
        $restorePoint->setId(uniqid('restore_point_'));
        $restorePoint->setCreatedAt(new \DateTime());
        
        // Create quick backup of current state
        $this->create([
            'type' => 'restore_point',
            'storage' => 'local',
            'compress' => true,
            'description' => 'Automatic restore point'
        ]);
        
        return $restorePoint;
    }
    
    public function rollback(string $restorePointId): void
    {
        $this->restore($restorePointId, [
            'target' => 'current',
            'database' => true,
            'files' => true
        ]);
    }
    
    public function createTestEnvironment(): TestEnvironment
    {
        $testEnv = new TestEnvironment();
        $testEnv->setId(uniqid('test_env_'));
        $testEnv->setDatabaseName('shopologic_test_' . time());
        $testEnv->setFilePath('/tmp/shopologic_test_' . time());
        
        // Create test database
        $db = $this->container->get(DatabaseManager::class);
        $db->createDatabase($testEnv->getDatabaseName());
        
        // Create test directory
        mkdir($testEnv->getFilePath(), 0755, true);
        
        return $testEnv;
    }
    
    public function destroyTestEnvironment(TestEnvironment $testEnv): void
    {
        // Drop test database
        $db = $this->container->get(DatabaseManager::class);
        $db->dropDatabase($testEnv->getDatabaseName());
        
        // Remove test directory
        if (is_dir($testEnv->getFilePath())) {
            $this->removeDirectory($testEnv->getFilePath());
        }
    }
    
    public function testRestore(string $backupId, TestEnvironment $testEnv): TestRestoreResult
    {
        $originalDb = $this->container->get('config')['database.database'];
        $originalPath = $this->container->get('config')['app.base_path'];
        
        try {
            // Switch to test environment
            $this->container->get('config')->set('database.database', $testEnv->getDatabaseName());
            $this->container->get('config')->set('app.base_path', $testEnv->getFilePath());
            
            // Perform restore
            $restoreResult = $this->restore($backupId, [
                'target' => 'test',
                'database' => true,
                'files' => true
            ]);
            
            // Run validation tests
            $validator = new RestoreValidator($this->container);
            $testResult = $validator->validate($testEnv);
            
            return $testResult;
            
        } finally {
            // Restore original configuration
            $this->container->get('config')->set('database.database', $originalDb);
            $this->container->get('config')->set('app.base_path', $originalPath);
        }
    }
    
    public function export(string $backupId, string $outputPath, string $format = 'tar'): string
    {
        $backup = $this->repository->find($backupId);
        if (!$backup) {
            throw new \Exception("Backup not found: $backupId");
        }
        
        $exporter = new BackupExporter($this->container);
        return $exporter->export($backup, $outputPath, $format, $this->storageBackends[$backup->getStorage()]);
    }
    
    public function import(string $inputPath, string $storage = 'local'): Backup
    {
        $importer = new BackupImporter($this->container);
        $backup = $importer->import($inputPath, $storage);
        
        // Store in selected backend
        $storageBackend = $this->storageBackends[$storage];
        $storagePath = $storageBackend->store($backup->getPath(), $backup->getId());
        $backup->setPath($storagePath);
        $backup->setStorage($storage);
        
        $this->repository->save($backup);
        
        return $backup;
    }
    
    public function generateManifest(string $backupId): array
    {
        $backup = $this->repository->find($backupId);
        if (!$backup) {
            throw new \Exception("Backup not found: $backupId");
        }
        
        return [
            'id' => $backup->getId(),
            'version' => '1.0',
            'created_at' => $backup->getCreatedAt()->format('c'),
            'type' => $backup->getType(),
            'encrypted' => $backup->isEncrypted(),
            'compressed' => $backup->isCompressed(),
            'size' => $backup->getSize(),
            'checksum' => $this->calculateChecksum($backup),
            'application' => [
                'name' => 'Shopologic',
                'version' => file_get_contents($this->container->get('config')['app.base_path'] . '/VERSION')
            ]
        ];
    }
    
    public function getRetentionPolicy(): array
    {
        return $this->container->get('config')['backup.retention'] ?? [
            'days' => 30,
            'count' => 10
        ];
    }
    
    private function generateBackupId(): string
    {
        return 'backup-' . date('Ymd-His') . '-' . substr(uniqid(), -6);
    }
    
    private function prepareBackupDirectory(Backup $backup): string
    {
        $dir = sys_get_temp_dir() . '/' . $backup->getId();
        mkdir($dir, 0755, true);
        return $dir;
    }
    
    private function backupDatabase(string $backupDir): array
    {
        $dbBackup = new DatabaseBackup($this->container);
        return $dbBackup->backup($backupDir . '/database');
    }
    
    private function backupFiles(string $backupDir, array $options): array
    {
        $fileBackup = new FileBackup($this->container);
        return $fileBackup->backup($backupDir . '/files', $options['include'] ?? []);
    }
    
    private function backupConfig(string $backupDir): array
    {
        $configDir = $backupDir . '/config';
        mkdir($configDir, 0755, true);
        
        // Copy configuration files
        $files = ['.env', '.env.example', 'composer.json', 'composer.lock'];
        $backed = [];
        
        foreach ($files as $file) {
            $source = $this->container->get('config')['app.base_path'] . '/' . $file;
            if (file_exists($source)) {
                copy($source, $configDir . '/' . $file);
                $backed[] = $file;
            }
        }
        
        return $backed;
    }
    
    private function createManifest(Backup $backup, array $files, array $options): array
    {
        return [
            'backup' => [
                'id' => $backup->getId(),
                'type' => $backup->getType(),
                'created_at' => $backup->getCreatedAt()->format('c'),
                'description' => $backup->getDescription()
            ],
            'application' => [
                'name' => 'Shopologic',
                'version' => file_get_contents($this->container->get('config')['app.base_path'] . '/VERSION'),
                'environment' => $this->container->get('config')['app.env']
            ],
            'components' => $files,
            'options' => $options,
            'system' => [
                'php_version' => PHP_VERSION,
                'os' => PHP_OS,
                'timestamp' => time()
            ]
        ];
    }
    
    private function restoreDatabase(string $backupPath, array $manifest): void
    {
        $dbRestore = new DatabaseRestore($this->container);
        $dbPath = is_dir($backupPath) ? $backupPath . '/database' : dirname($backupPath) . '/database';
        $dbRestore->restore($dbPath, $manifest['components']['database'] ?? []);
    }
    
    private function restoreFiles(string $backupPath, array $manifest, array $options): void
    {
        $fileRestore = new FileRestore($this->container);
        $filesPath = is_dir($backupPath) ? $backupPath . '/files' : dirname($backupPath) . '/files';
        $fileRestore->restore($filesPath, $manifest['components']['files'] ?? [], $options);
    }
    
    private function saveEncryptionKey(string $backupId, string $key): void
    {
        $keyPath = $this->getEncryptionKeyPath($backupId);
        $keyDir = dirname($keyPath);
        
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0700, true);
        }
        
        file_put_contents($keyPath, $key);
        chmod($keyPath, 0600);
    }
    
    private function loadEncryptionKey(string $backupId): string
    {
        $keyPath = $this->getEncryptionKeyPath($backupId);
        
        if (!file_exists($keyPath)) {
            throw new \Exception("Encryption key not found for backup: $backupId");
        }
        
        return file_get_contents($keyPath);
    }
    
    private function getEncryptionKeyPath(string $backupId): string
    {
        return $this->container->get('config')['app.base_path'] . '/storage/backups/.keys/' . $backupId . '.key';
    }
    
    private function calculateSize(string $path): int
    {
        if (is_file($path)) {
            return filesize($path);
        }
        
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    private function calculateChecksum(Backup $backup): string
    {
        $storage = $this->storageBackends[$backup->getStorage()];
        $localPath = $storage->retrieve($backup->getPath(), $backup->getId());
        
        $checksum = hash_file('sha256', $localPath);
        
        // Clean up if we downloaded the file
        if ($localPath !== $backup->getPath()) {
            unlink($localPath);
        }
        
        return $checksum;
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
    
    private function loadSchedules(): void
    {
        $schedulePath = $this->container->get('config')['app.base_path'] . '/storage/backups/schedules.json';
        
        if (file_exists($schedulePath)) {
            $data = json_decode(file_get_contents($schedulePath), true);
            
            $this->schedules = array_map(function($item) {
                $schedule = new ScheduledBackup();
                $schedule->setId($item['id']);
                $schedule->setCronExpression($item['cron_expression']);
                $schedule->setType($item['type']);
                $schedule->setStorage($item['storage']);
                $schedule->setStatus($item['status']);
                $schedule->setCreatedAt(new \DateTime($item['created_at']));
                
                if ($item['last_run']) {
                    $schedule->setLastRun(new \DateTime($item['last_run']));
                }
                
                return $schedule;
            }, $data);
        }
    }
    
    private function saveSchedules(): void
    {
        $schedulePath = $this->container->get('config')['app.base_path'] . '/storage/backups/schedules.json';
        $scheduleDir = dirname($schedulePath);
        
        if (!is_dir($scheduleDir)) {
            mkdir($scheduleDir, 0755, true);
        }
        
        $data = array_map(function($schedule) {
            return [
                'id' => $schedule->getId(),
                'cron_expression' => $schedule->getCronExpression(),
                'type' => $schedule->getType(),
                'storage' => $schedule->getStorage(),
                'status' => $schedule->getStatus(),
                'created_at' => $schedule->getCreatedAt()->format('c'),
                'last_run' => $schedule->getLastRun() ? $schedule->getLastRun()->format('c') : null
            ];
        }, $this->schedules);
        
        file_put_contents($schedulePath, json_encode($data, JSON_PRETTY_PRINT));
    }
}