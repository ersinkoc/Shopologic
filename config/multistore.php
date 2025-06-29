<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-Store Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how stores are detected and managed in your application.
    |
    */

    // Store detection order (domain, subdomain, path)
    'detection_order' => ['domain', 'subdomain', 'path'],
    
    // Cache TTL for store detection (in seconds)
    'cache_ttl' => 3600,
    
    // Fallback to default store if no store is detected
    'fallback_to_default' => true,
    
    // Store isolation settings
    'isolation' => [
        // Isolate products between stores
        'products' => true,
        
        // Isolate categories between stores
        'categories' => true,
        
        // Isolate customers between stores
        'customers' => true,
        
        // Isolate orders between stores
        'orders' => true,
        
        // Share user accounts across stores
        'share_users' => true,
    ],
    
    // Default store settings
    'defaults' => [
        'locale' => 'en',
        'currency' => 'USD',
        'timezone' => 'UTC',
        'theme' => 'default',
    ],
    
    // Store roles and permissions
    'roles' => [
        'owner' => [
            'label' => 'Store Owner',
            'permissions' => ['*'],
        ],
        'admin' => [
            'label' => 'Store Administrator',
            'permissions' => [
                'store.manage',
                'products.manage',
                'orders.manage',
                'customers.manage',
                'settings.manage',
            ],
        ],
        'manager' => [
            'label' => 'Store Manager',
            'permissions' => [
                'products.manage',
                'orders.manage',
                'customers.view',
                'reports.view',
            ],
        ],
        'editor' => [
            'label' => 'Content Editor',
            'permissions' => [
                'products.edit',
                'categories.edit',
                'content.manage',
            ],
        ],
        'viewer' => [
            'label' => 'Viewer',
            'permissions' => [
                'dashboard.view',
                'products.view',
                'orders.view',
                'reports.view',
            ],
        ],
    ],
    
    // Store-specific features
    'features' => [
        // Enable store-specific pricing
        'store_pricing' => true,
        
        // Enable store-specific inventory
        'store_inventory' => true,
        
        // Enable store-specific payment methods
        'store_payment_methods' => true,
        
        // Enable store-specific shipping methods
        'store_shipping_methods' => true,
        
        // Enable store-specific tax rates
        'store_tax_rates' => true,
    ],
];