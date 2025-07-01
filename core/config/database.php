<?php

return [
    'default' => $_ENV['DB_CONNECTION'] ?? 'pgsql',
    
    'connections' => [
        'mock' => [
            'driver' => 'mock',
            'database' => 'shopologic_mock',
        ],
        
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? 5432,
            'database' => $_ENV['DB_DATABASE'] ?? 'shopologic',
            'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8',
            'schema' => 'public',
            'sslmode' => 'prefer',
            'persistent' => $_ENV['DB_PERSISTENT'] ?? false,
        ],
        
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_DATABASE'] ?? 'shopologic',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
            'timezone' => '+00:00',
            'options' => [
                // MYSQLI_OPT_CONNECT_TIMEOUT => 10,
                // MYSQLI_OPT_READ_TIMEOUT => 30,
                // MYSQLI_OPT_NET_CMD_BUFFER_SIZE => 16384,
            ],
            'persistent' => $_ENV['DB_PERSISTENT'] ?? false,
        ],
        
        'mariadb' => [
            'driver' => 'mysql', // MariaDB uses the same driver
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_DATABASE'] ?? 'shopologic',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
            'timezone' => '+00:00',
            'persistent' => $_ENV['DB_PERSISTENT'] ?? false,
        ],
        
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $_ENV['DB_DATABASE'] ?? SHOPOLOGIC_ROOT . '/storage/database.sqlite',
            'prefix' => '',
        ],
        
        'pgsql_read' => [
            'driver' => 'pgsql',
            'host' => $_ENV['DB_READ_HOST'] ?? $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_READ_PORT'] ?? $_ENV['DB_PORT'] ?? 5432,
            'database' => $_ENV['DB_DATABASE'] ?? 'shopologic',
            'username' => $_ENV['DB_READ_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? 'postgres',
            'password' => $_ENV['DB_READ_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8',
            'schema' => 'public',
            'sslmode' => 'prefer',
            'persistent' => $_ENV['DB_PERSISTENT'] ?? false,
        ],
        
        'mysql_read' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_READ_HOST'] ?? $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_READ_PORT'] ?? $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_DATABASE'] ?? 'shopologic',
            'username' => $_ENV['DB_READ_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_READ_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'persistent' => $_ENV['DB_PERSISTENT'] ?? false,
        ],
    ],
    
    'migrations' => [
        'table' => 'migrations',
        'path' => 'database/migrations',
    ],
    
    'redis' => [
        'client' => 'predis',
        'cluster' => false,
        'default' => [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'database' => $_ENV['REDIS_DB'] ?? 0,
        ],
    ],
];