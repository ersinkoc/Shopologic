<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin\Modules;

use Shopologic\Core\Admin\AdminPanel;
use Shopologic\Core\Admin\AdminModuleInterface;

/**
 * Categories management module for admin panel
 */
class CategoriesModule implements AdminModuleInterface
{
    public function register(AdminPanel $admin): void
    {
        // Register menu items
        $menuBuilder = $admin->getMenu();
        
        $menuBuilder->addItem([
            'title' => 'Categories',
            'url' => '/admin/categories',
            'icon' => 'folder',
            'permission' => 'admin.categories.view',
            'order' => 11,
            'group' => 'catalog',
            'badge' => function() {
                return app('db')->table('categories')->count();
            }
        ]);
    }
    
    public function getName(): string
    {
        return 'categories';
    }
    
    public function getRoutes(): array
    {
        return [
            ['GET', '/admin/categories', 'CategoriesController@index'],
            ['GET', '/admin/categories/tree', 'CategoriesController@tree'],
            ['GET', '/admin/categories/create', 'CategoriesController@create'],
            ['POST', '/admin/categories', 'CategoriesController@store'],
            ['GET', '/admin/categories/{id}/edit', 'CategoriesController@edit'],
            ['PUT', '/admin/categories/{id}', 'CategoriesController@update'],
            ['DELETE', '/admin/categories/{id}', 'CategoriesController@destroy'],
            ['POST', '/admin/categories/reorder', 'CategoriesController@reorder'],
            ['POST', '/admin/categories/{id}/move', 'CategoriesController@move'],
            ['POST', '/admin/categories/bulk-action', 'CategoriesController@bulkAction']
        ];
    }
    
    public function getMenuItems(): array
    {
        return [
            [
                'title' => 'Categories',
                'url' => '/admin/categories',
                'icon' => 'folder',
                'permission' => 'admin.categories.view',
                'order' => 11
            ]
        ];
    }
    
    public function getPermissions(): array
    {
        return [
            'admin.categories.view' => 'View categories',
            'admin.categories.create' => 'Create categories',
            'admin.categories.update' => 'Update categories',
            'admin.categories.delete' => 'Delete categories',
            'admin.categories.reorder' => 'Reorder categories'
        ];
    }
}