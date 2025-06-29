<?php

return [
    'name' => $_ENV['APP_NAME'] ?? 'Shopologic',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => $_ENV['APP_DEBUG'] ?? false,
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    'locale' => $_ENV['APP_LOCALE'] ?? 'en',
    'fallback_locale' => $_ENV['APP_FALLBACK_LOCALE'] ?? 'en',
    
    'key' => $_ENV['APP_KEY'] ?? '',
    'cipher' => 'AES-256-CBC',
    
    'providers' => [
        \Shopologic\Core\Http\HttpServiceProvider::class,
        \Shopologic\Core\Router\RouterServiceProvider::class,
        \Shopologic\Core\Database\DatabaseServiceProvider::class,
        \Shopologic\Core\Cache\CacheServiceProvider::class,
        \Shopologic\Core\Logging\LoggingServiceProvider::class,
        \Shopologic\Core\Security\SecurityServiceProvider::class,
    ],
];