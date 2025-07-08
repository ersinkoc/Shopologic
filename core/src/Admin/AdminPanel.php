<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin;

use Shopologic\Core\Auth\AuthManager;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\Template\TemplateEngineInterface;

/**
 * Admin panel system with role-based access control
 */
class AdminPanel
{
    private AuthManager $auth;
    private EventDispatcherInterface $events;
    private TemplateEngineInterface $template;
    private MenuBuilder $menuBuilder;
    private array $config;
    private array $modules = [];
    private array $widgets = [];

    public function __construct(
        AuthManager $auth,
        EventDispatcherInterface $events,
        TemplateEngineInterface $template,
        MenuBuilder $menuBuilder,
        array $config = []
    ) {
        $this->auth = $auth;
        $this->events = $events;
        $this->template = $template;
        $this->menuBuilder = $menuBuilder;
        $this->config = array_merge([
            'base_url' => '/admin',
            'theme' => 'default',
            'locale' => 'en',
            'items_per_page' => 20,
            'enable_2fa' => true,
            'session_lifetime' => 3600,
            'allow_impersonation' => true
        ], $config);
        
        $this->registerDefaultModules();
    }

    /**
     * Register admin module
     */
    public function registerModule(string $name, AdminModuleInterface $module): void
    {
        $this->modules[$name] = $module;
        $module->register($this);
        
        $this->events->dispatch('admin.module_registered', [
            'name' => $name,
            'module' => $module
        ]);
    }

    /**
     * Get all modules
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Register dashboard widget
     */
    public function registerWidget(string $position, DashboardWidgetInterface $widget): void
    {
        if (!isset($this->widgets[$position])) {
            $this->widgets[$position] = [];
        }
        
        $this->widgets[$position][] = $widget;
    }

    /**
     * Get admin menu
     */
    public function getMenu(): array
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return [];
        }
        
        return $this->menuBuilder->build($user);
    }

    /**
     * Get dashboard widgets
     */
    public function getDashboardWidgets(string $position = null): array
    {
        if ($position) {
            return $this->widgets[$position] ?? [];
        }
        
        $allWidgets = [];
        foreach ($this->widgets as $pos => $widgets) {
            foreach ($widgets as $widget) {
                if ($widget->canView($this->auth->user())) {
                    $allWidgets[] = $widget;
                }
            }
        }
        
        return $allWidgets;
    }

    /**
     * Check if user has permission
     */
    public function can(string $permission): bool
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return false;
        }
        
        return $user->hasPermission($permission);
    }

    /**
     * Get admin configuration
     */
    public function getConfig(string $key = null)
    {
        if ($key) {
            return $this->config[$key] ?? null;
        }
        
        return $this->config;
    }

    /**
     * Render admin view
     */
    public function render(string $view, array $data = []): string
    {
        $data = array_merge($data, [
            'admin' => $this,
            'menu' => $this->getMenu(),
            'user' => $this->auth->user(),
            'config' => $this->config
        ]);
        
        return $this->template->render('admin/' . $view, $data);
    }

    /**
     * Handle impersonation
     */
    public function impersonate(int $userId): bool
    {
        if (!$this->config['allow_impersonation']) {
            return false;
        }
        
        if (!$this->can('admin.users.impersonate')) {
            return false;
        }
        
        $originalUserId = $this->auth->id();
        
        if ($this->auth->loginUsingId($userId)) {
            $_SESSION['admin.impersonating'] = $originalUserId;
            
            $this->events->dispatch('admin.impersonation_started', [
                'admin_id' => $originalUserId,
                'user_id' => $userId
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Stop impersonation
     */
    public function stopImpersonation(): bool
    {
        $originalUserId = $_SESSION['admin.impersonating'] ?? null;
        
        if (!$originalUserId) {
            return false;
        }
        
        $impersonatedUserId = $this->auth->id();
        
        if ($this->auth->loginUsingId($originalUserId)) {
            unset($_SESSION['admin.impersonating']);
            
            $this->events->dispatch('admin.impersonation_stopped', [
                'admin_id' => $originalUserId,
                'user_id' => $impersonatedUserId
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Check if currently impersonating
     */
    public function isImpersonating(): bool
    {
        return isset($_SESSION['admin.impersonating']);
    }

    // Private methods

    private function registerDefaultModules(): void
    {
        // Default modules will be registered here when implemented
        // For now, we'll skip this to avoid errors
        
        // Example:
        // $this->registerModule('dashboard', new Modules\DashboardModule());
        // $this->registerModule('products', new Modules\ProductsModule());
        // etc...
    }
}

/**
 * Admin module interface
 */
interface AdminModuleInterface
{
    public function register(AdminPanel $admin): void;
    public function getName(): string;
    public function getRoutes(): array;
    public function getMenuItems(): array;
    public function getPermissions(): array;
}

/**
 * Dashboard widget interface
 */
interface DashboardWidgetInterface
{
    public function getName(): string;
    public function render(): string;
    public function canView($user): bool;
    public function getPosition(): string;
    public function getOrder(): int;
}

/**
 * Menu builder for admin panel
 */
class MenuBuilder
{
    private array $items = [];
    private array $groups = [];

    /**
     * Add menu item
     */
    public function addItem(array $item): void
    {
        $defaults = [
            'title' => '',
            'url' => '#',
            'icon' => '',
            'permission' => null,
            'badge' => null,
            'children' => [],
            'order' => 0,
            'group' => 'main'
        ];
        
        $item = array_merge($defaults, $item);
        $group = $item['group'];
        
        if (!isset($this->items[$group])) {
            $this->items[$group] = [];
        }
        
        $this->items[$group][] = $item;
    }

    /**
     * Add menu group
     */
    public function addGroup(string $name, array $config): void
    {
        $this->groups[$name] = array_merge([
            'title' => '',
            'order' => 0,
            'icon' => ''
        ], $config);
    }

    /**
     * Build menu for user
     */
    public function build($user): array
    {
        $menu = [];
        
        // Sort groups
        uasort($this->groups, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });
        
        foreach ($this->groups as $groupName => $group) {
            if (!isset($this->items[$groupName])) {
                continue;
            }
            
            $groupItems = $this->filterItems($this->items[$groupName], $user);
            
            if (empty($groupItems)) {
                continue;
            }
            
            // Sort items
            usort($groupItems, function ($a, $b) {
                return $a['order'] <=> $b['order'];
            });
            
            $menu[] = [
                'group' => $group,
                'items' => $groupItems
            ];
        }
        
        return $menu;
    }

    private function filterItems(array $items, $user): array
    {
        $filtered = [];
        
        foreach ($items as $item) {
            // Check permission
            if ($item['permission'] && !$user->hasPermission($item['permission'])) {
                continue;
            }
            
            // Filter children
            if (!empty($item['children'])) {
                $item['children'] = $this->filterItems($item['children'], $user);
                
                // Skip if no children after filtering
                if (empty($item['children'])) {
                    continue;
                }
            }
            
            $filtered[] = $item;
        }
        
        return $filtered;
    }
}