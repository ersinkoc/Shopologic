<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin\Modules;

use Shopologic\Core\Admin\AdminPanel;
use Shopologic\Core\Admin\AdminModuleInterface;

/**
 * Products management module for admin panel
 */
class ProductsModule implements AdminModuleInterface
{
    public function register(AdminPanel $admin): void
    {
        // Register menu items
        $menuBuilder = $admin->getMenu();
        
        $menuBuilder->addItem([
            'title' => 'Products',
            'url' => '/admin/products',
            'icon' => 'box',
            'permission' => 'admin.products.view',
            'order' => 10,
            'group' => 'catalog',
            'children' => [
                [
                    'title' => 'All Products',
                    'url' => '/admin/products',
                    'permission' => 'admin.products.view'
                ],
                [
                    'title' => 'Add Product',
                    'url' => '/admin/products/create',
                    'permission' => 'admin.products.create'
                ],
                [
                    'title' => 'Categories',
                    'url' => '/admin/categories',
                    'permission' => 'admin.categories.view'
                ],
                [
                    'title' => 'Attributes',
                    'url' => '/admin/products/attributes',
                    'permission' => 'admin.products.attributes'
                ],
                [
                    'title' => 'Reviews',
                    'url' => '/admin/products/reviews',
                    'permission' => 'admin.products.reviews'
                ]
            ]
        ]);
    }
    
    public function getName(): string
    {
        return 'products';
    }
    
    public function getRoutes(): array
    {
        return [
            // Products
            ['GET', '/admin/products', 'ProductsController@index'],
            ['GET', '/admin/products/create', 'ProductsController@create'],
            ['POST', '/admin/products', 'ProductsController@store'],
            ['GET', '/admin/products/{id}/edit', 'ProductsController@edit'],
            ['PUT', '/admin/products/{id}', 'ProductsController@update'],
            ['DELETE', '/admin/products/{id}', 'ProductsController@destroy'],
            ['POST', '/admin/products/bulk-action', 'ProductsController@bulkAction'],
            ['POST', '/admin/products/{id}/duplicate', 'ProductsController@duplicate'],
            ['POST', '/admin/products/import', 'ProductsController@import'],
            ['GET', '/admin/products/export', 'ProductsController@export'],
            
            // Product variants
            ['GET', '/admin/products/{id}/variants', 'ProductVariantsController@index'],
            ['POST', '/admin/products/{id}/variants', 'ProductVariantsController@store'],
            ['PUT', '/admin/products/{id}/variants/{variantId}', 'ProductVariantsController@update'],
            ['DELETE', '/admin/products/{id}/variants/{variantId}', 'ProductVariantsController@destroy'],
            
            // Product images
            ['POST', '/admin/products/{id}/images', 'ProductImagesController@upload'],
            ['DELETE', '/admin/products/{id}/images/{imageId}', 'ProductImagesController@destroy'],
            ['POST', '/admin/products/{id}/images/reorder', 'ProductImagesController@reorder'],
            
            // Attributes
            ['GET', '/admin/products/attributes', 'AttributesController@index'],
            ['GET', '/admin/products/attributes/create', 'AttributesController@create'],
            ['POST', '/admin/products/attributes', 'AttributesController@store'],
            ['GET', '/admin/products/attributes/{id}/edit', 'AttributesController@edit'],
            ['PUT', '/admin/products/attributes/{id}', 'AttributesController@update'],
            ['DELETE', '/admin/products/attributes/{id}', 'AttributesController@destroy'],
            
            // Reviews
            ['GET', '/admin/products/reviews', 'ReviewsController@index'],
            ['GET', '/admin/products/reviews/{id}', 'ReviewsController@show'],
            ['PUT', '/admin/products/reviews/{id}', 'ReviewsController@update'],
            ['DELETE', '/admin/products/reviews/{id}', 'ReviewsController@destroy'],
            ['POST', '/admin/products/reviews/{id}/approve', 'ReviewsController@approve'],
            ['POST', '/admin/products/reviews/{id}/reject', 'ReviewsController@reject']
        ];
    }
    
    public function getMenuItems(): array
    {
        return [
            [
                'title' => 'Products',
                'url' => '/admin/products',
                'icon' => 'box',
                'permission' => 'admin.products.view',
                'order' => 10,
                'children' => [
                    [
                        'title' => 'All Products',
                        'url' => '/admin/products',
                        'permission' => 'admin.products.view'
                    ],
                    [
                        'title' => 'Add Product',
                        'url' => '/admin/products/create',
                        'permission' => 'admin.products.create'
                    ],
                    [
                        'title' => 'Attributes',
                        'url' => '/admin/products/attributes',
                        'permission' => 'admin.products.attributes'
                    ],
                    [
                        'title' => 'Reviews',
                        'url' => '/admin/products/reviews',
                        'permission' => 'admin.products.reviews'
                    ]
                ]
            ]
        ];
    }
    
    public function getPermissions(): array
    {
        return [
            // Products
            'admin.products.view' => 'View products',
            'admin.products.create' => 'Create products',
            'admin.products.update' => 'Update products',
            'admin.products.delete' => 'Delete products',
            'admin.products.bulk' => 'Bulk operations on products',
            'admin.products.import' => 'Import products',
            'admin.products.export' => 'Export products',
            
            // Variants
            'admin.products.variants' => 'Manage product variants',
            
            // Images
            'admin.products.images' => 'Manage product images',
            
            // Attributes
            'admin.products.attributes' => 'Manage product attributes',
            
            // Reviews
            'admin.products.reviews' => 'Manage product reviews',
            'admin.products.reviews.moderate' => 'Moderate product reviews'
        ];
    }
}