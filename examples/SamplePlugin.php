<?php

declare(strict_types=1);

namespace Examples;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;

/**
 * Sample plugin demonstrating the plugin architecture
 */
class SamplePlugin extends AbstractPlugin
{
    public function getName(): string
    {
        return 'Sample Plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDependencies(): array
    {
        return [
            'core-commerce' => '>=1.0.0',
        ];
    }

    /**
     * Called when plugin is activated
     */
    public function activate(): void
    {
        // Create database tables
        $this->createTables();
        
        // Set default options
        $this->setDefaultOptions();
    }

    /**
     * Called when plugin is deactivated
     */
    public function deactivate(): void
    {
        // Clean up temporary data
        $this->cleanupCache();
    }

    /**
     * Called when plugin is booted (on every request)
     */
    public function boot(): void
    {
        // Register hooks
        $this->registerHooks();
        
        // Register routes
        $this->registerRoutes();
        
        // Register services
        $this->registerServices();
    }

    /**
     * Called when plugin is installed
     */
    public function install(): void
    {
        // Run installation migrations
        $this->runMigrations();
    }

    /**
     * Called when plugin is uninstalled
     */
    public function uninstall(): void
    {
        // Remove database tables
        $this->dropTables();
        
        // Remove options
        $this->removeOptions();
    }

    /**
     * Called when plugin is updated
     */
    public function update(string $previousVersion): void
    {
        // Run update migrations based on version
        if (version_compare($previousVersion, '0.9.0', '<')) {
            $this->migrateFrom090();
        }
    }

    /**
     * Register plugin hooks
     */
    protected function registerHooks(): void
    {
        // Actions
        HookSystem::addAction('init', [$this, 'onInit']);
        HookSystem::addAction('order_created', [$this, 'onOrderCreated'], 20);
        HookSystem::addAction('product_saved', [$this, 'onProductSaved']);
        
        // Filters
        HookSystem::addFilter('product_price', [$this, 'filterProductPrice'], 10, 2);
        HookSystem::addFilter('order_statuses', [$this, 'filterOrderStatuses']);
        HookSystem::addFilter('admin_menu', [$this, 'filterAdminMenu']);
        
        // Conditional actions
        HookSystem::addConditionalAction(
            'order_completed',
            [$this, 'shouldSendNotification'],
            [$this, 'sendOrderNotification']
        );
        
        // Async actions for heavy operations
        HookSystem::addAsyncAction('generate_report', [$this, 'generateReport']);
    }

    /**
     * Register plugin routes
     */
    protected function registerRoutes(): void
    {
        // API routes
        $this->registerRoute('GET', '/api/sample/data', [$this, 'getData']);
        $this->registerRoute('POST', '/api/sample/process', [$this, 'processData']);
        
        // Admin routes
        $this->registerAdminRoute('GET', '/admin/sample', [$this, 'adminIndex']);
        $this->registerAdminRoute('GET', '/admin/sample/settings', [$this, 'adminSettings']);
    }

    /**
     * Register plugin services
     */
    protected function registerServices(): void
    {
        // Register service bindings
        $this->container->bind('sample.processor', function($container) {
            return new \Examples\Services\DataProcessor(
                $container->get('db'),
                $container->get('cache')
            );
        });
        
        // Tag services
        $this->container->tag(['sample.processor'], 'data_processor');
    }

    // Hook callbacks
    // --------------

    public function onInit(): void
    {
        // Initialize plugin features
        echo "Sample plugin initialized\n";
    }

    public function onOrderCreated($order): void
    {
        // Process new orders
        echo "Processing order #{$order->id} in sample plugin\n";
        
        // Trigger custom hook for other plugins
        HookSystem::doAction('sample_order_processed', $order);
    }

    public function onProductSaved($product): void
    {
        // Clear product cache
        $this->clearProductCache($product->id);
    }

    public function filterProductPrice($price, $product): float
    {
        // Apply custom pricing logic
        if ($this->isSpecialProduct($product)) {
            $price *= 0.95; // 5% discount
        }
        
        return $price;
    }

    public function filterOrderStatuses($statuses): array
    {
        // Add custom order status
        $statuses['custom_processing'] = 'Custom Processing';
        
        return $statuses;
    }

    public function filterAdminMenu($menu): array
    {
        // Add plugin menu item
        $menu['sample'] = [
            'title' => 'Sample Plugin',
            'icon' => 'fa-plug',
            'url' => '/admin/sample',
            'children' => [
                'settings' => [
                    'title' => 'Settings',
                    'url' => '/admin/sample/settings',
                ],
            ],
        ];
        
        return $menu;
    }

    public function shouldSendNotification($order): bool
    {
        // Check if notification should be sent
        return $order->total > 100 && $order->customer->is_subscribed;
    }

    public function sendOrderNotification($order): void
    {
        // Send notification
        echo "Sending special notification for order #{$order->id}\n";
    }

    public function generateReport($params): void
    {
        // Generate report asynchronously
        echo "Generating report with params: " . json_encode($params) . "\n";
        // This would run in background
    }

    // Route handlers
    // --------------

    public function getData($request): array
    {
        return [
            'status' => 'success',
            'data' => [
                'plugin' => $this->getName(),
                'version' => $this->getVersion(),
            ],
        ];
    }

    public function processData($request): array
    {
        $data = $request->input('data');
        
        // Process the data
        $result = $this->container->get('sample.processor')->process($data);
        
        return [
            'status' => 'success',
            'result' => $result,
        ];
    }

    public function adminIndex($request): string
    {
        return $this->view('admin/index', [
            'title' => 'Sample Plugin Dashboard',
            'stats' => $this->getStats(),
        ]);
    }

    public function adminSettings($request): string
    {
        if ($request->isMethod('POST')) {
            $this->saveSettings($request->all());
            return $this->redirect('/admin/sample/settings')->with('success', 'Settings saved');
        }
        
        return $this->view('admin/settings', [
            'settings' => $this->getSettings(),
        ]);
    }

    // Helper methods
    // --------------

    protected function createTables(): void
    {
        // Create plugin tables
        echo "Creating sample plugin tables\n";
    }

    protected function dropTables(): void
    {
        // Drop plugin tables
        echo "Dropping sample plugin tables\n";
    }

    protected function setDefaultOptions(): void
    {
        // Set default plugin options
        echo "Setting default options\n";
    }

    protected function removeOptions(): void
    {
        // Remove plugin options
        echo "Removing plugin options\n";
    }

    protected function cleanupCache(): void
    {
        // Clean up cache
        echo "Cleaning up cache\n";
    }

    protected function runMigrations(): void
    {
        // Run installation migrations
        echo "Running migrations\n";
    }

    protected function migrateFrom090(): void
    {
        // Migrate from version 0.9.0
        echo "Migrating from version 0.9.0\n";
    }

    protected function clearProductCache($productId): void
    {
        // Clear product cache
        echo "Clearing cache for product #{$productId}\n";
    }

    protected function isSpecialProduct($product): bool
    {
        // Check if product is special
        return isset($product->meta['special']) && $product->meta['special'] === true;
    }

    protected function getStats(): array
    {
        return [
            'total_processed' => 1234,
            'active_items' => 56,
        ];
    }

    protected function getSettings(): array
    {
        return [
            'enabled' => true,
            'api_key' => 'sample-key',
        ];
    }

    protected function saveSettings($data): void
    {
        // Save settings
        echo "Saving settings: " . json_encode($data) . "\n";
    }
}