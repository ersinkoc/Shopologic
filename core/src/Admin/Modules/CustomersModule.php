<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin\Modules;

use Shopologic\Core\Admin\AdminPanel;
use Shopologic\Core\Admin\AdminModuleInterface;

/**
 * Customers management module for admin panel
 */
class CustomersModule implements AdminModuleInterface
{
    public function register(AdminPanel $admin): void
    {
        // Register menu items
        $menuBuilder = $admin->getMenu();
        
        $menuBuilder->addItem([
            'title' => 'Customers',
            'url' => '/admin/customers',
            'icon' => 'users',
            'permission' => 'admin.customers.view',
            'order' => 30,
            'group' => 'sales',
            'children' => [
                [
                    'title' => 'All Customers',
                    'url' => '/admin/customers',
                    'permission' => 'admin.customers.view'
                ],
                [
                    'title' => 'Customer Groups',
                    'url' => '/admin/customers/groups',
                    'permission' => 'admin.customers.groups'
                ],
                [
                    'title' => 'Reviews',
                    'url' => '/admin/customers/reviews',
                    'permission' => 'admin.customers.reviews'
                ],
                [
                    'title' => 'Import/Export',
                    'url' => '/admin/customers/import-export',
                    'permission' => 'admin.customers.import'
                ]
            ]
        ]);
    }
    
    public function getName(): string
    {
        return 'customers';
    }
    
    public function getRoutes(): array
    {
        return [
            // Customers
            ['GET', '/admin/customers', 'CustomersController@index'],
            ['GET', '/admin/customers/create', 'CustomersController@create'],
            ['POST', '/admin/customers', 'CustomersController@store'],
            ['GET', '/admin/customers/{id}', 'CustomersController@show'],
            ['GET', '/admin/customers/{id}/edit', 'CustomersController@edit'],
            ['PUT', '/admin/customers/{id}', 'CustomersController@update'],
            ['DELETE', '/admin/customers/{id}', 'CustomersController@destroy'],
            ['POST', '/admin/customers/{id}/disable', 'CustomersController@disable'],
            ['POST', '/admin/customers/{id}/enable', 'CustomersController@enable'],
            ['GET', '/admin/customers/{id}/orders', 'CustomersController@orders'],
            ['GET', '/admin/customers/{id}/addresses', 'CustomersController@addresses'],
            ['POST', '/admin/customers/{id}/send-email', 'CustomersController@sendEmail'],
            ['POST', '/admin/customers/{id}/add-note', 'CustomersController@addNote'],
            
            // Customer groups
            ['GET', '/admin/customers/groups', 'CustomerGroupsController@index'],
            ['GET', '/admin/customers/groups/create', 'CustomerGroupsController@create'],
            ['POST', '/admin/customers/groups', 'CustomerGroupsController@store'],
            ['GET', '/admin/customers/groups/{id}/edit', 'CustomerGroupsController@edit'],
            ['PUT', '/admin/customers/groups/{id}', 'CustomerGroupsController@update'],
            ['DELETE', '/admin/customers/groups/{id}', 'CustomerGroupsController@destroy'],
            
            // Reviews
            ['GET', '/admin/customers/reviews', 'CustomerReviewsController@index'],
            ['GET', '/admin/customers/reviews/{id}', 'CustomerReviewsController@show'],
            ['POST', '/admin/customers/reviews/{id}/approve', 'CustomerReviewsController@approve'],
            ['POST', '/admin/customers/reviews/{id}/reject', 'CustomerReviewsController@reject'],
            ['DELETE', '/admin/customers/reviews/{id}', 'CustomerReviewsController@destroy'],
            
            // Import/Export
            ['GET', '/admin/customers/import-export', 'CustomersImportExportController@index'],
            ['POST', '/admin/customers/import', 'CustomersImportExportController@import'],
            ['GET', '/admin/customers/export', 'CustomersImportExportController@export'],
            
            // Bulk actions
            ['POST', '/admin/customers/bulk-action', 'CustomersController@bulkAction']
        ];
    }
    
    public function getMenuItems(): array
    {
        return [
            [
                'title' => 'Customers',
                'url' => '/admin/customers',
                'icon' => 'users',
                'permission' => 'admin.customers.view',
                'order' => 30,
                'children' => [
                    [
                        'title' => 'All Customers',
                        'url' => '/admin/customers',
                        'permission' => 'admin.customers.view'
                    ],
                    [
                        'title' => 'Customer Groups',
                        'url' => '/admin/customers/groups',
                        'permission' => 'admin.customers.groups'
                    ],
                    [
                        'title' => 'Reviews',
                        'url' => '/admin/customers/reviews',
                        'permission' => 'admin.customers.reviews'
                    ],
                    [
                        'title' => 'Import/Export',
                        'url' => '/admin/customers/import-export',
                        'permission' => 'admin.customers.import'
                    ]
                ]
            ]
        ];
    }
    
    public function getPermissions(): array
    {
        return [
            // Customers
            'admin.customers.view' => 'View customers',
            'admin.customers.create' => 'Create customers',
            'admin.customers.update' => 'Update customers',
            'admin.customers.delete' => 'Delete customers',
            'admin.customers.disable' => 'Disable/Enable customers',
            'admin.customers.impersonate' => 'Impersonate customers',
            'admin.customers.bulk' => 'Bulk operations on customers',
            
            // Customer groups
            'admin.customers.groups' => 'Manage customer groups',
            
            // Reviews
            'admin.customers.reviews' => 'Manage customer reviews',
            
            // Import/Export
            'admin.customers.import' => 'Import customers',
            'admin.customers.export' => 'Export customers'
        ];
    }
}