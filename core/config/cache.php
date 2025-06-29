<?php

return [
    'default' => $_ENV['CACHE_DRIVER'] ?? 'file',
    
    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
        
        'file' => [
            'driver' => 'file',
            'path' => 'storage/cache',
        ],
        
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],
    ],
    
    'prefix' => $_ENV['CACHE_PREFIX'] ?? 'shopologic_cache',
    
    'ttl' => [
        'default' => 3600, // 1 hour
        'long' => 86400,   // 24 hours
        'short' => 300,    // 5 minutes
    ],
];