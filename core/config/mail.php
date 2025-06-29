<?php

return [
    'default' => $_ENV['MAIL_MAILER'] ?? 'smtp',
    
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
            'port' => $_ENV['MAIL_PORT'] ?? 587,
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'username' => $_ENV['MAIL_USERNAME'] ?? '',
            'password' => $_ENV['MAIL_PASSWORD'] ?? '',
            'timeout' => null,
        ],
        
        'sendmail' => [
            'transport' => 'sendmail',
            'path' => '/usr/sbin/sendmail -bs',
        ],
        
        'log' => [
            'transport' => 'log',
            'channel' => $_ENV['MAIL_LOG_CHANNEL'] ?? 'single',
        ],
        
        'array' => [
            'transport' => 'array',
        ],
    ],
    
    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'hello@shopologic.com',
        'name' => $_ENV['MAIL_FROM_NAME'] ?? 'Shopologic',
    ],
];