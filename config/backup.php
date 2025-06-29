<?php

return [
    /**
     * Default backup storage
     */
    'default' => env('BACKUP_STORAGE', 'local'),
    
    /**
     * Backup storage configurations
     */
    'local' => [
        'path' => storage_path('backups'),
    ],
    
    's3' => [
        'enabled' => env('BACKUP_S3_ENABLED', false),
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'bucket' => env('AWS_BACKUP_BUCKET'),
        'path' => env('AWS_BACKUP_PATH', 'shopologic-backups'),
    ],
    
    'ftp' => [
        'enabled' => env('BACKUP_FTP_ENABLED', false),
        'host' => env('BACKUP_FTP_HOST'),
        'username' => env('BACKUP_FTP_USERNAME'),
        'password' => env('BACKUP_FTP_PASSWORD'),
        'port' => env('BACKUP_FTP_PORT', 21),
        'ssl' => env('BACKUP_FTP_SSL', false),
        'path' => env('BACKUP_FTP_PATH', '/backups'),
    ],
    
    /**
     * Backup retention policy
     */
    'retention' => [
        'days' => env('BACKUP_RETENTION_DAYS', 30),
        'count' => env('BACKUP_RETENTION_COUNT', 10),
    ],
    
    /**
     * Backup schedules
     */
    'schedules' => [
        'daily' => [
            'enabled' => env('BACKUP_DAILY_ENABLED', true),
            'time' => env('BACKUP_DAILY_TIME', '02:00'),
            'type' => 'incremental',
            'storage' => env('BACKUP_DAILY_STORAGE', 'local'),
        ],
        
        'weekly' => [
            'enabled' => env('BACKUP_WEEKLY_ENABLED', true),
            'day' => env('BACKUP_WEEKLY_DAY', 'sunday'),
            'time' => env('BACKUP_WEEKLY_TIME', '03:00'),
            'type' => 'full',
            'storage' => env('BACKUP_WEEKLY_STORAGE', 'local'),
        ],
        
        'monthly' => [
            'enabled' => env('BACKUP_MONTHLY_ENABLED', false),
            'day' => env('BACKUP_MONTHLY_DAY', 1),
            'time' => env('BACKUP_MONTHLY_TIME', '04:00'),
            'type' => 'full',
            'storage' => env('BACKUP_MONTHLY_STORAGE', 's3'),
        ],
    ],
    
    /**
     * Backup encryption
     */
    'encryption' => [
        'enabled' => env('BACKUP_ENCRYPTION_ENABLED', true),
        'algorithm' => 'AES-256-GCM',
        'key_storage' => storage_path('backups/.keys'),
    ],
    
    /**
     * Backup compression
     */
    'compression' => [
        'enabled' => env('BACKUP_COMPRESSION_ENABLED', true),
        'format' => env('BACKUP_COMPRESSION_FORMAT', 'tar.gz'),
        'level' => env('BACKUP_COMPRESSION_LEVEL', 6),
    ],
    
    /**
     * Backup notifications
     */
    'notifications' => [
        'enabled' => env('BACKUP_NOTIFICATIONS_ENABLED', true),
        'channels' => ['mail', 'slack'],
        'on_success' => env('BACKUP_NOTIFY_SUCCESS', false),
        'on_failure' => env('BACKUP_NOTIFY_FAILURE', true),
    ],
    
    /**
     * File backup settings
     */
    'files' => [
        'include' => [
            'plugins',
            'themes',
            'public/uploads',
            'storage/uploads',
        ],
        
        'exclude' => [
            '*.log',
            '.git',
            'node_modules',
            'vendor',
            'storage/cache',
            'storage/sessions',
            'storage/logs',
            '.env',
        ],
        
        'follow_symlinks' => false,
        'max_file_size' => 100 * 1024 * 1024, // 100MB
    ],
    
    /**
     * Database backup settings
     */
    'database' => [
        'dump_options' => [
            'use_single_transaction' => true,
            'lock_tables' => false,
            'add_drop_table' => true,
            'add_locks' => false,
            'extended_insert' => true,
        ],
        
        'exclude_tables' => [
            'cache',
            'sessions',
            'jobs',
            'failed_jobs',
        ],
    ],
    
    /**
     * Performance settings
     */
    'performance' => [
        'batch_size' => 1000,
        'memory_limit' => '512M',
        'timeout' => 3600, // 1 hour
        'nice_level' => 19,
    ],
];