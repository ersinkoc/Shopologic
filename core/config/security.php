<?php

return [
    'password' => [
        'driver' => 'bcrypt',
        'bcrypt' => [
            'rounds' => $_ENV['BCRYPT_ROUNDS'] ?? 10,
        ],
        'argon' => [
            'memory' => 1024,
            'threads' => 2,
            'time' => 2,
        ],
    ],
    
    'session' => [
        'driver' => $_ENV['SESSION_DRIVER'] ?? 'file',
        'lifetime' => $_ENV['SESSION_LIFETIME'] ?? 120,
        'expire_on_close' => false,
        'encrypt' => false,
        'files' => 'storage/sessions',
        'connection' => null,
        'table' => 'sessions',
        'store' => null,
        'lottery' => [2, 100],
        'cookie' => $_ENV['SESSION_COOKIE'] ?? 'shopologic_session',
        'path' => '/',
        'domain' => $_ENV['SESSION_DOMAIN'] ?? null,
        'secure' => $_ENV['SESSION_SECURE_COOKIE'] ?? false,
        'http_only' => true,
        'same_site' => 'lax',
    ],
    
    'csrf' => [
        'token_name' => '_token',
        'header_name' => 'X-CSRF-TOKEN',
        'expire' => 3600,
    ],
    
    'rate_limit' => [
        'default' => [
            'requests' => 60,
            'per_minute' => 1,
        ],
        'api' => [
            'requests' => 1000,
            'per_minute' => 1,
        ],
    ],
    
    'cors' => [
        'paths' => ['api/*'],
        'allowed_methods' => ['*'],
        'allowed_origins' => ['*'],
        'allowed_origins_patterns' => [],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 0,
        'supports_credentials' => false,
    ],
];