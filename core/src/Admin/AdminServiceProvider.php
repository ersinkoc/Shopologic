<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin;

use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\Auth\AuthManager;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\Template\TemplateEngineInterface;

/**
 * Admin panel service provider
 */
class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register MenuBuilder
        $this->container->singleton(MenuBuilder::class, function () {
            return new MenuBuilder();
        });
        
        // Register AdminPanel
        $this->container->singleton(AdminPanel::class, function ($container) {
            return new AdminPanel(
                $container->get(AuthManager::class),
                $container->get(EventDispatcherInterface::class),
                $container->get(TemplateEngineInterface::class),
                $container->get(MenuBuilder::class),
                $container->get('config')['admin'] ?? []
            );
        });
        
        // Register aliases
        $this->container->alias('admin', AdminPanel::class);
        $this->container->alias('admin.menu', MenuBuilder::class);
    }
    
    public function boot(): void
    {
        // Register menu groups
        $this->registerMenuGroups();
        
        // Register routes
        $this->registerRoutes();
        
        // Register middleware
        $this->registerMiddleware();
        
        // Register view composers
        $this->registerViewComposers();
        
        // Register event listeners
        $this->registerEventListeners();
        
        // Publish assets
        $this->publishAssets();
    }
    
    private function registerMenuGroups(): void
    {
        $menuBuilder = $this->container->get('admin.menu');
        
        $menuBuilder->addGroup('main', [
            'title' => 'Main',
            'order' => 0,
            'icon' => 'home'
        ]);
        
        $menuBuilder->addGroup('catalog', [
            'title' => 'Catalog',
            'order' => 10,
            'icon' => 'box'
        ]);
        
        $menuBuilder->addGroup('sales', [
            'title' => 'Sales',
            'order' => 20,
            'icon' => 'shopping-cart'
        ]);
        
        $menuBuilder->addGroup('marketing', [
            'title' => 'Marketing',
            'order' => 30,
            'icon' => 'megaphone'
        ]);
        
        $menuBuilder->addGroup('analytics', [
            'title' => 'Analytics',
            'order' => 40,
            'icon' => 'chart-line'
        ]);
        
        $menuBuilder->addGroup('content', [
            'title' => 'Content',
            'order' => 50,
            'icon' => 'file-text'
        ]);
        
        $menuBuilder->addGroup('system', [
            'title' => 'System',
            'order' => 60,
            'icon' => 'cog'
        ]);
    }
    
    private function registerRoutes(): void
    {
        if (!$this->container->has('router')) {
            return;
        }
        
        $router = $this->container->get('router');
        
        // Admin authentication routes
        $router->get('/admin/login', 'Admin\AuthController@showLogin')->name('admin.login');
        $router->post('/admin/login', 'Admin\AuthController@login');
        $router->post('/admin/logout', 'Admin\AuthController@logout')->name('admin.logout');
        $router->get('/admin/forgot-password', 'Admin\AuthController@showForgotPassword')->name('admin.forgot-password');
        $router->post('/admin/forgot-password', 'Admin\AuthController@forgotPassword');
        $router->get('/admin/reset-password/{token}', 'Admin\AuthController@showResetPassword')->name('admin.reset-password');
        $router->post('/admin/reset-password', 'Admin\AuthController@resetPassword');
        
        // Two-factor authentication
        $router->get('/admin/2fa', 'Admin\TwoFactorController@show')->name('admin.2fa');
        $router->post('/admin/2fa', 'Admin\TwoFactorController@verify');
        $router->get('/admin/2fa/setup', 'Admin\TwoFactorController@setup')->name('admin.2fa.setup');
        $router->post('/admin/2fa/enable', 'Admin\TwoFactorController@enable');
        $router->post('/admin/2fa/disable', 'Admin\TwoFactorController@disable');
        
        // Admin routes group with middleware
        $router->group([
            'prefix' => 'admin',
            'middleware' => ['auth:admin', 'admin.2fa', 'admin.permissions'],
            'namespace' => 'Admin'
        ], function ($router) {
            // Register module routes
            $admin = $this->container->get('admin');
            
            foreach ($admin->getModules() as $module) {
                foreach ($module->getRoutes() as $route) {
                    [$method, $path, $action] = $route;
                    $router->$method($path, $action);
                }
            }
        });
    }
    
    private function registerMiddleware(): void
    {
        if (!$this->container->has('middleware')) {
            return;
        }
        
        $middleware = $this->container->get('middleware');
        
        // Admin authentication middleware
        $middleware->register('auth:admin', Middleware\AdminAuthMiddleware::class);
        
        // Two-factor authentication middleware
        $middleware->register('admin.2fa', Middleware\TwoFactorMiddleware::class);
        
        // Permission checking middleware
        $middleware->register('admin.permissions', Middleware\PermissionMiddleware::class);
        
        // Admin activity logging middleware
        $middleware->register('admin.log', Middleware\ActivityLogMiddleware::class);
        
        // IP whitelist middleware
        $middleware->register('admin.ip', Middleware\IpWhitelistMiddleware::class);
    }
    
    private function registerViewComposers(): void
    {
        if (!$this->container->has('view')) {
            return;
        }
        
        $view = $this->container->get('view');
        
        // Share admin panel instance with all admin views
        $view->composer('admin.*', function ($view) {
            $view->with('admin', $this->container->get('admin'));
        });
        
        // Share current user with all admin views
        $view->composer('admin.*', function ($view) {
            $view->with('currentUser', $this->container->get('auth')->user());
        });
        
        // Share notifications with admin layout
        $view->composer('admin.layout', function ($view) {
            $view->with('notifications', $this->getNotifications());
        });
    }
    
    private function registerEventListeners(): void
    {
        $events = $this->container->get('events');
        
        // Log admin activities
        $events->listen('admin.*', function ($event, $data) {
            $this->logActivity($event, $data);
        });
        
        // Clear admin cache on certain events
        $events->listen([
            'admin.settings.updated',
            'admin.menu.updated',
            'admin.permissions.updated'
        ], function () {
            $this->container->get('cache')->tags(['admin'])->flush();
        });
        
        // Send notifications on important events
        $events->listen([
            'admin.user.created',
            'admin.role.updated',
            'admin.security.breach'
        ], function ($event, $data) {
            $this->sendAdminNotification($event, $data);
        });
    }
    
    private function publishAssets(): void
    {
        // Publish admin CSS and JS assets
        $this->publishes([
            __DIR__ . '/../../resources/admin/css' => public_path('admin/css'),
            __DIR__ . '/../../resources/admin/js' => public_path('admin/js'),
            __DIR__ . '/../../resources/admin/images' => public_path('admin/images'),
            __DIR__ . '/../../resources/admin/fonts' => public_path('admin/fonts')
        ], 'admin-assets');
        
        // Publish admin views
        $this->publishes([
            __DIR__ . '/../../resources/views/admin' => resource_path('views/admin')
        ], 'admin-views');
        
        // Publish admin config
        $this->publishes([
            __DIR__ . '/../../config/admin.php' => config_path('admin.php')
        ], 'admin-config');
    }
    
    private function getNotifications(): array
    {
        // Get notifications for current admin user
        $user = $this->container->get('auth')->user();
        
        if (!$user) {
            return [];
        }
        
        return $this->container->get('db')
            ->table('admin_notifications')
            ->where('user_id', $user->id)
            ->where('read', false)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }
    
    private function logActivity(string $event, array $data): void
    {
        $user = $this->container->get('auth')->user();
        
        if (!$user) {
            return;
        }
        
        $this->container->get('db')->table('admin_activity_log')->insert([
            'user_id' => $user->id,
            'event' => $event,
            'description' => $this->getEventDescription($event, $data),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'data' => json_encode($data),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function getEventDescription(string $event, array $data): string
    {
        // Generate human-readable description based on event
        $descriptions = [
            'admin.login' => 'Logged into admin panel',
            'admin.logout' => 'Logged out of admin panel',
            'admin.product.created' => 'Created product: ' . ($data['name'] ?? ''),
            'admin.product.updated' => 'Updated product: ' . ($data['name'] ?? ''),
            'admin.product.deleted' => 'Deleted product: ' . ($data['name'] ?? ''),
            'admin.order.updated' => 'Updated order #' . ($data['id'] ?? ''),
            'admin.user.created' => 'Created user: ' . ($data['email'] ?? ''),
            'admin.settings.updated' => 'Updated settings: ' . ($data['section'] ?? '')
        ];
        
        return $descriptions[$event] ?? $event;
    }
    
    private function sendAdminNotification(string $event, array $data): void
    {
        // Get admin users who should receive notifications
        $admins = $this->container->get('db')
            ->table('users')
            ->where('role', 'admin')
            ->where('notifications_enabled', true)
            ->get();
        
        foreach ($admins as $admin) {
            $this->container->get('db')->table('admin_notifications')->insert([
                'user_id' => $admin->id,
                'type' => $this->getNotificationType($event),
                'title' => $this->getNotificationTitle($event),
                'message' => $this->getNotificationMessage($event, $data),
                'data' => json_encode($data),
                'read' => false,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    private function getNotificationType(string $event): string
    {
        if (strpos($event, 'security') !== false) {
            return 'danger';
        }
        
        if (strpos($event, 'error') !== false || strpos($event, 'failed') !== false) {
            return 'warning';
        }
        
        return 'info';
    }
    
    private function getNotificationTitle(string $event): string
    {
        $titles = [
            'admin.user.created' => 'New User Created',
            'admin.role.updated' => 'Role Permissions Updated',
            'admin.security.breach' => 'Security Alert'
        ];
        
        return $titles[$event] ?? 'System Notification';
    }
    
    private function getNotificationMessage(string $event, array $data): string
    {
        $messages = [
            'admin.user.created' => 'A new admin user has been created: ' . ($data['email'] ?? ''),
            'admin.role.updated' => 'Permissions have been updated for role: ' . ($data['name'] ?? ''),
            'admin.security.breach' => 'Suspicious activity detected: ' . ($data['description'] ?? '')
        ];
        
        return $messages[$event] ?? 'An important system event has occurred.';
    }
}