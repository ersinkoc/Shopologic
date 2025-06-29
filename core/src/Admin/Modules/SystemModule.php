<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin\Modules;

use Shopologic\Core\Admin\AdminPanel;
use Shopologic\Core\Admin\AdminModuleInterface;

/**
 * System management module for admin panel
 */
class SystemModule implements AdminModuleInterface
{
    public function register(AdminPanel $admin): void
    {
        // Register menu items
        $menuBuilder = $admin->getMenu();
        
        $menuBuilder->addItem([
            'title' => 'System',
            'url' => '/admin/system',
            'icon' => 'server',
            'permission' => 'admin.system.view',
            'order' => 80,
            'group' => 'system',
            'children' => [
                [
                    'title' => 'Users & Roles',
                    'url' => '/admin/system/users',
                    'permission' => 'admin.system.users'
                ],
                [
                    'title' => 'Plugins',
                    'url' => '/admin/system/plugins',
                    'permission' => 'admin.system.plugins'
                ],
                [
                    'title' => 'Themes',
                    'url' => '/admin/system/themes',
                    'permission' => 'admin.system.themes'
                ],
                [
                    'title' => 'Cache Management',
                    'url' => '/admin/system/cache',
                    'permission' => 'admin.system.cache'
                ],
                [
                    'title' => 'Queue Jobs',
                    'url' => '/admin/system/queue',
                    'permission' => 'admin.system.queue'
                ],
                [
                    'title' => 'Logs',
                    'url' => '/admin/system/logs',
                    'permission' => 'admin.system.logs'
                ],
                [
                    'title' => 'Backups',
                    'url' => '/admin/system/backups',
                    'permission' => 'admin.system.backups'
                ],
                [
                    'title' => 'System Info',
                    'url' => '/admin/system/info',
                    'permission' => 'admin.system.info'
                ],
                [
                    'title' => 'Maintenance',
                    'url' => '/admin/system/maintenance',
                    'permission' => 'admin.system.maintenance'
                ]
            ]
        ]);
    }
    
    public function getName(): string
    {
        return 'system';
    }
    
    public function getRoutes(): array
    {
        return [
            // Users & Roles
            ['GET', '/admin/system/users', 'UsersController@index'],
            ['GET', '/admin/system/users/create', 'UsersController@create'],
            ['POST', '/admin/system/users', 'UsersController@store'],
            ['GET', '/admin/system/users/{id}/edit', 'UsersController@edit'],
            ['PUT', '/admin/system/users/{id}', 'UsersController@update'],
            ['DELETE', '/admin/system/users/{id}', 'UsersController@destroy'],
            ['POST', '/admin/system/users/{id}/reset-password', 'UsersController@resetPassword'],
            ['POST', '/admin/system/users/{id}/toggle-2fa', 'UsersController@toggle2FA'],
            ['GET', '/admin/system/users/{id}/activity', 'UsersController@activity'],
            
            // Roles & Permissions
            ['GET', '/admin/system/roles', 'RolesController@index'],
            ['GET', '/admin/system/roles/create', 'RolesController@create'],
            ['POST', '/admin/system/roles', 'RolesController@store'],
            ['GET', '/admin/system/roles/{id}/edit', 'RolesController@edit'],
            ['PUT', '/admin/system/roles/{id}', 'RolesController@update'],
            ['DELETE', '/admin/system/roles/{id}', 'RolesController@destroy'],
            ['GET', '/admin/system/permissions', 'RolesController@permissions'],
            
            // Plugins
            ['GET', '/admin/system/plugins', 'PluginsController@index'],
            ['GET', '/admin/system/plugins/marketplace', 'PluginsController@marketplace'],
            ['POST', '/admin/system/plugins/install', 'PluginsController@install'],
            ['POST', '/admin/system/plugins/{id}/activate', 'PluginsController@activate'],
            ['POST', '/admin/system/plugins/{id}/deactivate', 'PluginsController@deactivate'],
            ['POST', '/admin/system/plugins/{id}/uninstall', 'PluginsController@uninstall'],
            ['GET', '/admin/system/plugins/{id}/settings', 'PluginsController@settings'],
            ['PUT', '/admin/system/plugins/{id}/settings', 'PluginsController@updateSettings'],
            ['POST', '/admin/system/plugins/{id}/update', 'PluginsController@update'],
            
            // Themes
            ['GET', '/admin/system/themes', 'ThemesController@index'],
            ['GET', '/admin/system/themes/marketplace', 'ThemesController@marketplace'],
            ['POST', '/admin/system/themes/install', 'ThemesController@install'],
            ['POST', '/admin/system/themes/{id}/activate', 'ThemesController@activate'],
            ['POST', '/admin/system/themes/{id}/uninstall', 'ThemesController@uninstall'],
            ['GET', '/admin/system/themes/{id}/customize', 'ThemesController@customize'],
            ['PUT', '/admin/system/themes/{id}/customize', 'ThemesController@updateCustomization'],
            ['GET', '/admin/system/themes/{id}/preview', 'ThemesController@preview'],
            
            // Cache Management
            ['GET', '/admin/system/cache', 'CacheController@index'],
            ['POST', '/admin/system/cache/clear', 'CacheController@clear'],
            ['POST', '/admin/system/cache/clear/{type}', 'CacheController@clearType'],
            ['POST', '/admin/system/cache/warm', 'CacheController@warm'],
            ['GET', '/admin/system/cache/stats', 'CacheController@stats'],
            
            // Queue Jobs
            ['GET', '/admin/system/queue', 'QueueController@index'],
            ['GET', '/admin/system/queue/failed', 'QueueController@failed'],
            ['POST', '/admin/system/queue/{id}/retry', 'QueueController@retry'],
            ['DELETE', '/admin/system/queue/{id}', 'QueueController@delete'],
            ['POST', '/admin/system/queue/retry-all', 'QueueController@retryAll'],
            ['POST', '/admin/system/queue/flush', 'QueueController@flush'],
            ['GET', '/admin/system/queue/workers', 'QueueController@workers'],
            
            // Logs
            ['GET', '/admin/system/logs', 'LogsController@index'],
            ['GET', '/admin/system/logs/{file}', 'LogsController@show'],
            ['DELETE', '/admin/system/logs/{file}', 'LogsController@delete'],
            ['GET', '/admin/system/logs/{file}/download', 'LogsController@download'],
            ['POST', '/admin/system/logs/clear', 'LogsController@clear'],
            
            // Backups
            ['GET', '/admin/system/backups', 'BackupsController@index'],
            ['POST', '/admin/system/backups/create', 'BackupsController@create'],
            ['GET', '/admin/system/backups/{id}/download', 'BackupsController@download'],
            ['POST', '/admin/system/backups/{id}/restore', 'BackupsController@restore'],
            ['DELETE', '/admin/system/backups/{id}', 'BackupsController@delete'],
            ['GET', '/admin/system/backups/schedule', 'BackupsController@schedule'],
            ['PUT', '/admin/system/backups/schedule', 'BackupsController@updateSchedule'],
            
            // System Info
            ['GET', '/admin/system/info', 'SystemInfoController@index'],
            ['GET', '/admin/system/info/phpinfo', 'SystemInfoController@phpinfo'],
            ['GET', '/admin/system/info/database', 'SystemInfoController@database'],
            ['GET', '/admin/system/info/storage', 'SystemInfoController@storage'],
            ['GET', '/admin/system/info/requirements', 'SystemInfoController@requirements'],
            
            // Maintenance
            ['GET', '/admin/system/maintenance', 'MaintenanceController@index'],
            ['POST', '/admin/system/maintenance/enable', 'MaintenanceController@enable'],
            ['POST', '/admin/system/maintenance/disable', 'MaintenanceController@disable'],
            ['PUT', '/admin/system/maintenance/settings', 'MaintenanceController@updateSettings'],
            ['POST', '/admin/system/maintenance/clear-temp', 'MaintenanceController@clearTemp'],
            ['POST', '/admin/system/maintenance/optimize', 'MaintenanceController@optimize'],
            ['GET', '/admin/system/maintenance/health', 'MaintenanceController@healthCheck']
        ];
    }
    
    public function getMenuItems(): array
    {
        return [
            [
                'title' => 'System',
                'url' => '/admin/system',
                'icon' => 'server',
                'permission' => 'admin.system.view',
                'order' => 80,
                'children' => [
                    [
                        'title' => 'Users & Roles',
                        'url' => '/admin/system/users',
                        'permission' => 'admin.system.users'
                    ],
                    [
                        'title' => 'Plugins',
                        'url' => '/admin/system/plugins',
                        'permission' => 'admin.system.plugins'
                    ],
                    [
                        'title' => 'Themes',
                        'url' => '/admin/system/themes',
                        'permission' => 'admin.system.themes'
                    ],
                    [
                        'title' => 'Cache Management',
                        'url' => '/admin/system/cache',
                        'permission' => 'admin.system.cache'
                    ],
                    [
                        'title' => 'Queue Jobs',
                        'url' => '/admin/system/queue',
                        'permission' => 'admin.system.queue'
                    ],
                    [
                        'title' => 'Logs',
                        'url' => '/admin/system/logs',
                        'permission' => 'admin.system.logs'
                    ],
                    [
                        'title' => 'Backups',
                        'url' => '/admin/system/backups',
                        'permission' => 'admin.system.backups'
                    ],
                    [
                        'title' => 'System Info',
                        'url' => '/admin/system/info',
                        'permission' => 'admin.system.info'
                    ],
                    [
                        'title' => 'Maintenance',
                        'url' => '/admin/system/maintenance',
                        'permission' => 'admin.system.maintenance'
                    ]
                ]
            ]
        ];
    }
    
    public function getPermissions(): array
    {
        return [
            // General
            'admin.system.view' => 'View system management',
            
            // Users & Roles
            'admin.system.users' => 'Manage users',
            'admin.system.users.create' => 'Create users',
            'admin.system.users.update' => 'Update users',
            'admin.system.users.delete' => 'Delete users',
            'admin.system.users.impersonate' => 'Impersonate users',
            'admin.system.roles' => 'Manage roles',
            'admin.system.roles.create' => 'Create roles',
            'admin.system.roles.update' => 'Update roles',
            'admin.system.roles.delete' => 'Delete roles',
            'admin.system.permissions' => 'Manage permissions',
            
            // Plugins
            'admin.system.plugins' => 'View plugins',
            'admin.system.plugins.install' => 'Install plugins',
            'admin.system.plugins.activate' => 'Activate/deactivate plugins',
            'admin.system.plugins.uninstall' => 'Uninstall plugins',
            'admin.system.plugins.settings' => 'Configure plugin settings',
            
            // Themes
            'admin.system.themes' => 'View themes',
            'admin.system.themes.install' => 'Install themes',
            'admin.system.themes.activate' => 'Activate themes',
            'admin.system.themes.customize' => 'Customize themes',
            'admin.system.themes.uninstall' => 'Uninstall themes',
            
            // Cache
            'admin.system.cache' => 'View cache management',
            'admin.system.cache.clear' => 'Clear cache',
            'admin.system.cache.warm' => 'Warm cache',
            
            // Queue
            'admin.system.queue' => 'View queue jobs',
            'admin.system.queue.retry' => 'Retry failed jobs',
            'admin.system.queue.delete' => 'Delete jobs',
            
            // Logs
            'admin.system.logs' => 'View system logs',
            'admin.system.logs.delete' => 'Delete log files',
            
            // Backups
            'admin.system.backups' => 'View backups',
            'admin.system.backups.create' => 'Create backups',
            'admin.system.backups.restore' => 'Restore backups',
            'admin.system.backups.delete' => 'Delete backups',
            'admin.system.backups.schedule' => 'Configure backup schedule',
            
            // System Info
            'admin.system.info' => 'View system information',
            'admin.system.info.phpinfo' => 'View PHP information',
            
            // Maintenance
            'admin.system.maintenance' => 'View maintenance',
            'admin.system.maintenance.enable' => 'Enable maintenance mode',
            'admin.system.maintenance.optimize' => 'Optimize system'
        ];
    }
}