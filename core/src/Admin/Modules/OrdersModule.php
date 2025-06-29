<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin\Modules;

use Shopologic\Core\Admin\AdminPanel;
use Shopologic\Core\Admin\AdminModuleInterface;

/**
 * Orders management module for admin panel
 */
class OrdersModule implements AdminModuleInterface
{
    public function register(AdminPanel $admin): void
    {
        // Register menu items
        $menuBuilder = $admin->getMenu();
        
        $menuBuilder->addItem([
            'title' => 'Orders',
            'url' => '/admin/orders',
            'icon' => 'shopping-cart',
            'permission' => 'admin.orders.view',
            'order' => 20,
            'group' => 'sales',
            'badge' => function() {
                return app('db')->table('orders')->where('status', 'pending')->count();
            },
            'children' => [
                [
                    'title' => 'All Orders',
                    'url' => '/admin/orders',
                    'permission' => 'admin.orders.view'
                ],
                [
                    'title' => 'Create Order',
                    'url' => '/admin/orders/create',
                    'permission' => 'admin.orders.create'
                ],
                [
                    'title' => 'Abandoned Carts',
                    'url' => '/admin/orders/abandoned',
                    'permission' => 'admin.orders.abandoned'
                ],
                [
                    'title' => 'Refunds',
                    'url' => '/admin/orders/refunds',
                    'permission' => 'admin.orders.refunds'
                ]
            ]
        ]);
    }
    
    public function getName(): string
    {
        return 'orders';
    }
    
    public function getRoutes(): array
    {
        return [
            // Orders
            ['GET', '/admin/orders', 'OrdersController@index'],
            ['GET', '/admin/orders/create', 'OrdersController@create'],
            ['POST', '/admin/orders', 'OrdersController@store'],
            ['GET', '/admin/orders/{id}', 'OrdersController@show'],
            ['GET', '/admin/orders/{id}/edit', 'OrdersController@edit'],
            ['PUT', '/admin/orders/{id}', 'OrdersController@update'],
            ['DELETE', '/admin/orders/{id}', 'OrdersController@destroy'],
            ['POST', '/admin/orders/{id}/status', 'OrdersController@updateStatus'],
            ['POST', '/admin/orders/{id}/cancel', 'OrdersController@cancel'],
            ['POST', '/admin/orders/{id}/refund', 'OrdersController@refund'],
            ['GET', '/admin/orders/{id}/invoice', 'OrdersController@invoice'],
            ['POST', '/admin/orders/{id}/send-email', 'OrdersController@sendEmail'],
            ['POST', '/admin/orders/{id}/add-note', 'OrdersController@addNote'],
            
            // Shipping
            ['POST', '/admin/orders/{id}/ship', 'OrdersController@ship'],
            ['GET', '/admin/orders/{id}/shipping-label', 'OrdersController@shippingLabel'],
            ['POST', '/admin/orders/{id}/tracking', 'OrdersController@updateTracking'],
            
            // Abandoned carts
            ['GET', '/admin/orders/abandoned', 'AbandonedCartsController@index'],
            ['POST', '/admin/orders/abandoned/{id}/recover', 'AbandonedCartsController@recover'],
            
            // Refunds
            ['GET', '/admin/orders/refunds', 'RefundsController@index'],
            ['GET', '/admin/orders/refunds/{id}', 'RefundsController@show'],
            ['POST', '/admin/orders/refunds/{id}/approve', 'RefundsController@approve'],
            ['POST', '/admin/orders/refunds/{id}/reject', 'RefundsController@reject'],
            
            // Bulk actions
            ['POST', '/admin/orders/bulk-action', 'OrdersController@bulkAction'],
            ['GET', '/admin/orders/export', 'OrdersController@export']
        ];
    }
    
    public function getMenuItems(): array
    {
        return [
            [
                'title' => 'Orders',
                'url' => '/admin/orders',
                'icon' => 'shopping-cart',
                'permission' => 'admin.orders.view',
                'order' => 20,
                'children' => [
                    [
                        'title' => 'All Orders',
                        'url' => '/admin/orders',
                        'permission' => 'admin.orders.view'
                    ],
                    [
                        'title' => 'Create Order',
                        'url' => '/admin/orders/create',
                        'permission' => 'admin.orders.create'
                    ],
                    [
                        'title' => 'Abandoned Carts',
                        'url' => '/admin/orders/abandoned',
                        'permission' => 'admin.orders.abandoned'
                    ],
                    [
                        'title' => 'Refunds',
                        'url' => '/admin/orders/refunds',
                        'permission' => 'admin.orders.refunds'
                    ]
                ]
            ]
        ];
    }
    
    public function getPermissions(): array
    {
        return [
            // Orders
            'admin.orders.view' => 'View orders',
            'admin.orders.create' => 'Create orders',
            'admin.orders.update' => 'Update orders',
            'admin.orders.delete' => 'Delete orders',
            'admin.orders.status' => 'Change order status',
            'admin.orders.cancel' => 'Cancel orders',
            'admin.orders.refund' => 'Refund orders',
            'admin.orders.invoice' => 'View/Generate invoices',
            'admin.orders.shipping' => 'Manage shipping',
            'admin.orders.export' => 'Export orders',
            'admin.orders.bulk' => 'Bulk operations on orders',
            
            // Abandoned carts
            'admin.orders.abandoned' => 'View abandoned carts',
            'admin.orders.abandoned.recover' => 'Recover abandoned carts',
            
            // Refunds
            'admin.orders.refunds' => 'View refunds',
            'admin.orders.refunds.approve' => 'Approve refunds',
            'admin.orders.refunds.reject' => 'Reject refunds'
        ];
    }
}