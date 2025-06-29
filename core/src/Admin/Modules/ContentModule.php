<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin\Modules;

use Shopologic\Core\Admin\AdminPanel;
use Shopologic\Core\Admin\AdminModuleInterface;

/**
 * Content management module for admin panel
 */
class ContentModule implements AdminModuleInterface
{
    public function register(AdminPanel $admin): void
    {
        // Register menu items
        $menuBuilder = $admin->getMenu();
        
        $menuBuilder->addItem([
            'title' => 'Content',
            'url' => '/admin/content',
            'icon' => 'file-text',
            'permission' => 'admin.content.view',
            'order' => 60,
            'group' => 'content',
            'children' => [
                [
                    'title' => 'Pages',
                    'url' => '/admin/content/pages',
                    'permission' => 'admin.content.pages'
                ],
                [
                    'title' => 'Blog Posts',
                    'url' => '/admin/content/blog',
                    'permission' => 'admin.content.blog'
                ],
                [
                    'title' => 'Media Library',
                    'url' => '/admin/content/media',
                    'permission' => 'admin.content.media'
                ],
                [
                    'title' => 'Banners',
                    'url' => '/admin/content/banners',
                    'permission' => 'admin.content.banners'
                ],
                [
                    'title' => 'FAQ',
                    'url' => '/admin/content/faq',
                    'permission' => 'admin.content.faq'
                ],
                [
                    'title' => 'Menus',
                    'url' => '/admin/content/menus',
                    'permission' => 'admin.content.menus'
                ]
            ]
        ]);
    }
    
    public function getName(): string
    {
        return 'content';
    }
    
    public function getRoutes(): array
    {
        return [
            // Pages
            ['GET', '/admin/content/pages', 'PagesController@index'],
            ['GET', '/admin/content/pages/create', 'PagesController@create'],
            ['POST', '/admin/content/pages', 'PagesController@store'],
            ['GET', '/admin/content/pages/{id}/edit', 'PagesController@edit'],
            ['PUT', '/admin/content/pages/{id}', 'PagesController@update'],
            ['DELETE', '/admin/content/pages/{id}', 'PagesController@destroy'],
            ['POST', '/admin/content/pages/{id}/publish', 'PagesController@publish'],
            ['POST', '/admin/content/pages/{id}/unpublish', 'PagesController@unpublish'],
            ['GET', '/admin/content/pages/{id}/preview', 'PagesController@preview'],
            ['GET', '/admin/content/pages/{id}/versions', 'PagesController@versions'],
            ['POST', '/admin/content/pages/{id}/restore/{version}', 'PagesController@restore'],
            
            // Blog
            ['GET', '/admin/content/blog', 'BlogController@index'],
            ['GET', '/admin/content/blog/create', 'BlogController@create'],
            ['POST', '/admin/content/blog', 'BlogController@store'],
            ['GET', '/admin/content/blog/{id}/edit', 'BlogController@edit'],
            ['PUT', '/admin/content/blog/{id}', 'BlogController@update'],
            ['DELETE', '/admin/content/blog/{id}', 'BlogController@destroy'],
            ['POST', '/admin/content/blog/{id}/publish', 'BlogController@publish'],
            ['GET', '/admin/content/blog/categories', 'BlogController@categories'],
            ['POST', '/admin/content/blog/categories', 'BlogController@storeCategory'],
            ['GET', '/admin/content/blog/tags', 'BlogController@tags'],
            
            // Media Library
            ['GET', '/admin/content/media', 'MediaController@index'],
            ['POST', '/admin/content/media/upload', 'MediaController@upload'],
            ['GET', '/admin/content/media/{id}', 'MediaController@show'],
            ['PUT', '/admin/content/media/{id}', 'MediaController@update'],
            ['DELETE', '/admin/content/media/{id}', 'MediaController@destroy'],
            ['POST', '/admin/content/media/bulk-upload', 'MediaController@bulkUpload'],
            ['POST', '/admin/content/media/organize', 'MediaController@organize'],
            ['GET', '/admin/content/media/folders', 'MediaController@folders'],
            ['POST', '/admin/content/media/folders', 'MediaController@createFolder'],
            
            // Banners
            ['GET', '/admin/content/banners', 'BannersController@index'],
            ['GET', '/admin/content/banners/create', 'BannersController@create'],
            ['POST', '/admin/content/banners', 'BannersController@store'],
            ['GET', '/admin/content/banners/{id}/edit', 'BannersController@edit'],
            ['PUT', '/admin/content/banners/{id}', 'BannersController@update'],
            ['DELETE', '/admin/content/banners/{id}', 'BannersController@destroy'],
            ['POST', '/admin/content/banners/{id}/activate', 'BannersController@activate'],
            ['POST', '/admin/content/banners/{id}/deactivate', 'BannersController@deactivate'],
            ['GET', '/admin/content/banners/{id}/stats', 'BannersController@stats'],
            
            // FAQ
            ['GET', '/admin/content/faq', 'FAQController@index'],
            ['GET', '/admin/content/faq/create', 'FAQController@create'],
            ['POST', '/admin/content/faq', 'FAQController@store'],
            ['GET', '/admin/content/faq/{id}/edit', 'FAQController@edit'],
            ['PUT', '/admin/content/faq/{id}', 'FAQController@update'],
            ['DELETE', '/admin/content/faq/{id}', 'FAQController@destroy'],
            ['POST', '/admin/content/faq/reorder', 'FAQController@reorder'],
            ['GET', '/admin/content/faq/categories', 'FAQController@categories'],
            
            // Menus
            ['GET', '/admin/content/menus', 'MenusController@index'],
            ['GET', '/admin/content/menus/create', 'MenusController@create'],
            ['POST', '/admin/content/menus', 'MenusController@store'],
            ['GET', '/admin/content/menus/{id}/edit', 'MenusController@edit'],
            ['PUT', '/admin/content/menus/{id}', 'MenusController@update'],
            ['DELETE', '/admin/content/menus/{id}', 'MenusController@destroy'],
            ['GET', '/admin/content/menus/{id}/items', 'MenusController@items'],
            ['POST', '/admin/content/menus/{id}/items', 'MenusController@addItem'],
            ['PUT', '/admin/content/menus/{id}/items/{itemId}', 'MenusController@updateItem'],
            ['DELETE', '/admin/content/menus/{id}/items/{itemId}', 'MenusController@deleteItem'],
            ['POST', '/admin/content/menus/{id}/reorder', 'MenusController@reorder']
        ];
    }
    
    public function getMenuItems(): array
    {
        return [
            [
                'title' => 'Content',
                'url' => '/admin/content',
                'icon' => 'file-text',
                'permission' => 'admin.content.view',
                'order' => 60,
                'children' => [
                    [
                        'title' => 'Pages',
                        'url' => '/admin/content/pages',
                        'permission' => 'admin.content.pages'
                    ],
                    [
                        'title' => 'Blog Posts',
                        'url' => '/admin/content/blog',
                        'permission' => 'admin.content.blog'
                    ],
                    [
                        'title' => 'Media Library',
                        'url' => '/admin/content/media',
                        'permission' => 'admin.content.media'
                    ],
                    [
                        'title' => 'Banners',
                        'url' => '/admin/content/banners',
                        'permission' => 'admin.content.banners'
                    ],
                    [
                        'title' => 'FAQ',
                        'url' => '/admin/content/faq',
                        'permission' => 'admin.content.faq'
                    ],
                    [
                        'title' => 'Menus',
                        'url' => '/admin/content/menus',
                        'permission' => 'admin.content.menus'
                    ]
                ]
            ]
        ];
    }
    
    public function getPermissions(): array
    {
        return [
            // General
            'admin.content.view' => 'View content management',
            
            // Pages
            'admin.content.pages' => 'Manage pages',
            'admin.content.pages.create' => 'Create pages',
            'admin.content.pages.update' => 'Update pages',
            'admin.content.pages.delete' => 'Delete pages',
            'admin.content.pages.publish' => 'Publish/unpublish pages',
            
            // Blog
            'admin.content.blog' => 'Manage blog posts',
            'admin.content.blog.create' => 'Create blog posts',
            'admin.content.blog.update' => 'Update blog posts',
            'admin.content.blog.delete' => 'Delete blog posts',
            'admin.content.blog.publish' => 'Publish blog posts',
            'admin.content.blog.categories' => 'Manage blog categories',
            
            // Media
            'admin.content.media' => 'Access media library',
            'admin.content.media.upload' => 'Upload media files',
            'admin.content.media.update' => 'Update media files',
            'admin.content.media.delete' => 'Delete media files',
            'admin.content.media.organize' => 'Organize media folders',
            
            // Banners
            'admin.content.banners' => 'Manage banners',
            'admin.content.banners.create' => 'Create banners',
            'admin.content.banners.update' => 'Update banners',
            'admin.content.banners.delete' => 'Delete banners',
            'admin.content.banners.stats' => 'View banner statistics',
            
            // FAQ
            'admin.content.faq' => 'Manage FAQ',
            'admin.content.faq.create' => 'Create FAQ items',
            'admin.content.faq.update' => 'Update FAQ items',
            'admin.content.faq.delete' => 'Delete FAQ items',
            
            // Menus
            'admin.content.menus' => 'Manage menus',
            'admin.content.menus.create' => 'Create menus',
            'admin.content.menus.update' => 'Update menus',
            'admin.content.menus.delete' => 'Delete menus'
        ];
    }
}