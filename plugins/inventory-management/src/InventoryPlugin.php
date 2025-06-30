<?php
declare(strict_types=1);

namespace Shopologic\Plugins\InventoryManagement;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Hook\HookSystem;
use InventoryManagement\Services\InventoryService;
use InventoryManagement\Services\WarehouseService;
use InventoryManagement\Services\SupplierService;
use InventoryManagement\Services\AlertService;
use InventoryManagement\Services\ReorderService;

/**
 * Advanced Inventory Management Plugin
 * 
 * Comprehensive inventory tracking with multi-warehouse support, low stock alerts,
 * supplier management, and automated reordering
 */
class InventoryPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'inventory-management';
    protected string $version = '1.0.0';
    
    /**
     * Plugin installation
     */
    public function install(): bool
    {
        $this->runMigrations();
        $this->setDefaultConfig();
        $this->createDefaultWarehouse();
        return true;
    }
    
    /**
     * Plugin activation
     */
    public function activate(): bool
    {
        $this->initializeInventoryTracking();
        $this->scheduleStockChecks();
        return true;
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): bool
    {
        $this->pauseStockChecks();
        return true;
    }
    
    /**
     * Plugin uninstall
     */
    public function uninstall(): bool
    {
        if ($this->confirmDataRemoval()) {
            $this->dropTables();
            $this->removeConfig();
        }
        return true;
    }
    
    /**
     * Plugin update
     */
    public function update(string $previousVersion): bool
    {
        $this->runUpdateMigrations($previousVersion);
        return true;
    }
    
    /**
     * Plugin boot
     */
    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->registerCronJobs();
        $this->registerWidgets();
        $this->registerPermissions();
    }
    
    /**
     * Register plugin services
     */
    protected function registerServices(): void
    {
        // Inventory service
        $this->container->singleton(InventoryService::class, function ($container) {
            return new InventoryService(
                $container->get('db'),
                $this->getConfig('stock_tracking_method', 'sku'),
                $this->getConfig()
            );
        });
        
        // Warehouse service
        $this->container->singleton(WarehouseService::class, function ($container) {
            return new WarehouseService(
                $container->get('db'),
                $this->getConfig('enable_multi_warehouse', false)
            );
        });
        
        // Supplier service
        $this->container->singleton(SupplierService::class, function ($container) {
            return new SupplierService(
                $container->get('db'),
                $container->get('events')
            );
        });
        
        // Alert service
        $this->container->singleton(AlertService::class, function ($container) {
            return new AlertService(
                $container->get('db'),
                $container->get('mail'),
                $this->getConfig('alert_recipients', [])
            );
        });
        
        // Reorder service
        $this->container->singleton(ReorderService::class, function ($container) {
            return new ReorderService(
                $container->get('db'),
                $container->get(SupplierService::class),
                $this->getConfig('reorder_point_formula', 'min_max')
            );
        });
    }
    
    /**
     * Register plugin hooks
     */
    protected function registerHooks(): void
    {
        // Order inventory management
        HookSystem::addAction('order.placed', [$this, 'reserveInventory'], 5);
        HookSystem::addAction('order.completed', [$this, 'deductInventory'], 5);
        HookSystem::addAction('order.cancelled', [$this, 'releaseInventory'], 5);
        
        // Stock alerts
        HookSystem::addAction('product.stock_low', [$this, 'handleLowStock'], 10);
        HookSystem::addAction('product.stock_out', [$this, 'handleOutOfStock'], 10);
        
        // Dashboard integration
        HookSystem::addAction('admin.dashboard', [$this, 'displayInventoryWidget'], 30);
        
        // Product management
        HookSystem::addFilter('product.stock_status', [$this, 'calculateStockStatus'], 10);
        HookSystem::addAction('product.updated', [$this, 'updateInventoryTracking'], 10);
        
        // Barcode integration
        if ($this->getConfig('enable_barcode_scanning', true)) {
            HookSystem::addAction('admin.product.form', [$this, 'addBarcodeField'], 10);
        }
    }
    
    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        // Stock management
        $this->registerRoute('GET', '/api/v1/inventory/stock', 
            'InventoryManagement\\Controllers\\InventoryController@getStock');
        $this->registerRoute('POST', '/api/v1/inventory/adjust', 
            'InventoryManagement\\Controllers\\InventoryController@adjustStock');
        $this->registerRoute('POST', '/api/v1/inventory/transfer', 
            'InventoryManagement\\Controllers\\InventoryController@transferStock');
        $this->registerRoute('GET', '/api/v1/inventory/movements', 
            'InventoryManagement\\Controllers\\InventoryController@getMovements');
        
        // Alerts
        $this->registerRoute('GET', '/api/v1/inventory/alerts', 
            'InventoryManagement\\Controllers\\AlertController@getAlerts');
        
        // Warehouses
        $this->registerRoute('GET', '/api/v1/warehouses', 
            'InventoryManagement\\Controllers\\WarehouseController@index');
        $this->registerRoute('POST', '/api/v1/warehouses', 
            'InventoryManagement\\Controllers\\WarehouseController@create');
        
        // Suppliers
        $this->registerRoute('GET', '/api/v1/suppliers', 
            'InventoryManagement\\Controllers\\SupplierController@index');
        
        // Purchase orders
        $this->registerRoute('POST', '/api/v1/purchase-orders', 
            'InventoryManagement\\Controllers\\PurchaseOrderController@create');
        
        // Import/Export
        $this->registerRoute('POST', '/api/v1/inventory/import', 
            'InventoryManagement\\Controllers\\ImportController@importStock');
    }
    
    /**
     * Register cron jobs
     */
    protected function registerCronJobs(): void
    {
        // Check stock levels hourly
        $this->scheduleJob('0 * * * *', [$this, 'checkStockLevels']);
        
        // Generate reorder suggestions daily at 6 AM
        $this->scheduleJob('0 6 * * *', [$this, 'generateReorderSuggestions']);
        
        // Check expiring stock daily at midnight
        if ($this->getConfig('enable_expiry_tracking', false)) {
            $this->scheduleJob('0 0 * * *', [$this, 'checkExpiringStock']);
        }
        
        // Calculate stock turnover weekly on Monday at 2 AM
        $this->scheduleJob('0 2 * * MON', [$this, 'calculateStockTurnover']);
    }
    
    /**
     * Register dashboard widgets
     */
    protected function registerWidgets(): void
    {
        $this->registerWidget('inventory_summary', Widgets\InventorySummaryWidget::class);
        $this->registerWidget('low_stock_alerts', Widgets\LowStockAlertsWidget::class);
        $this->registerWidget('recent_movements', Widgets\RecentMovementsWidget::class);
    }
    
    /**
     * Register permissions
     */
    protected function registerPermissions(): void
    {
        $this->addPermission('inventory.view', 'View inventory');
        $this->addPermission('inventory.manage', 'Manage inventory');
        $this->addPermission('inventory.adjust', 'Adjust stock levels');
        $this->addPermission('inventory.transfer', 'Transfer stock between warehouses');
        $this->addPermission('supplier.manage', 'Manage suppliers');
        $this->addPermission('warehouse.manage', 'Manage warehouses');
    }
    
    /**
     * Reserve inventory for placed order
     */
    public function reserveInventory(array $data): void
    {
        $order = $data['order'];
        $inventoryService = $this->container->get(InventoryService::class);
        
        foreach ($order->items as $item) {
            $inventoryService->reserveStock($item->product_id, $item->quantity, $order->id);
        }
    }
    
    /**
     * Deduct inventory for completed order
     */
    public function deductInventory(array $data): void
    {
        $order = $data['order'];
        $inventoryService = $this->container->get(InventoryService::class);
        
        foreach ($order->items as $item) {
            $inventoryService->deductStock($item->product_id, $item->quantity, [
                'type' => 'sale',
                'reference' => $order->id,
                'order_item_id' => $item->id
            ]);
        }
    }
    
    /**
     * Release inventory for cancelled order
     */
    public function releaseInventory(array $data): void
    {
        $order = $data['order'];
        $inventoryService = $this->container->get(InventoryService::class);
        
        foreach ($order->items as $item) {
            $inventoryService->releaseReservation($item->product_id, $item->quantity, $order->id);
        }
    }
    
    /**
     * Handle low stock notification
     */
    public function handleLowStock(array $data): void
    {
        if (!$this->getConfig('enable_low_stock_alerts', true)) {
            return;
        }
        
        $product = $data['product'];
        $alertService = $this->container->get(AlertService::class);
        
        $alertService->sendLowStockAlert($product);
        
        // Auto-reorder if enabled
        if ($this->getConfig('enable_auto_reorder', false)) {
            $reorderService = $this->container->get(ReorderService::class);
            $reorderService->createAutoReorder($product->id);
        }
    }
    
    /**
     * Handle out of stock notification
     */
    public function handleOutOfStock(array $data): void
    {
        $product = $data['product'];
        $alertService = $this->container->get(AlertService::class);
        
        $alertService->sendOutOfStockAlert($product);
    }
    
    /**
     * Display inventory widget on dashboard
     */
    public function displayInventoryWidget(): void
    {
        $inventoryService = $this->container->get(InventoryService::class);
        
        $stats = [
            'total_products' => $inventoryService->getTotalProducts(),
            'low_stock_count' => $inventoryService->getLowStockCount(),
            'out_of_stock_count' => $inventoryService->getOutOfStockCount(),
            'total_value' => $inventoryService->getTotalInventoryValue()
        ];
        
        echo $this->render('widgets/inventory-summary', compact('stats'));
    }
    
    /**
     * Calculate stock status for product
     */
    public function calculateStockStatus($status, array $data): string
    {
        $product = $data['product'];
        $inventoryService = $this->container->get(InventoryService::class);
        
        $stock = $inventoryService->getProductStock($product->id);
        $lowThreshold = $this->getConfig('low_stock_threshold', 10);
        $outThreshold = $this->getConfig('out_of_stock_threshold', 0);
        
        if ($stock <= $outThreshold) {
            return 'out_of_stock';
        } elseif ($stock <= $lowThreshold) {
            return 'low_stock';
        } else {
            return 'in_stock';
        }
    }
    
    /**
     * Check stock levels (cron job)
     */
    public function checkStockLevels(): void
    {
        $inventoryService = $this->container->get(InventoryService::class);
        $alertService = $this->container->get(AlertService::class);
        
        $lowStockProducts = $inventoryService->getLowStockProducts();
        $outOfStockProducts = $inventoryService->getOutOfStockProducts();
        
        foreach ($lowStockProducts as $product) {
            $alertService->triggerLowStockAlert($product);
        }
        
        foreach ($outOfStockProducts as $product) {
            $alertService->triggerOutOfStockAlert($product);
        }
        
        $this->logger->info('Stock level check completed', [
            'low_stock' => count($lowStockProducts),
            'out_of_stock' => count($outOfStockProducts)
        ]);
    }
    
    /**
     * Generate reorder suggestions (cron job)
     */
    public function generateReorderSuggestions(): void
    {
        $reorderService = $this->container->get(ReorderService::class);
        $suggestions = $reorderService->generateReorderSuggestions();
        
        $this->logger->info('Reorder suggestions generated', ['count' => count($suggestions)]);
        
        // Send summary email if configured
        if ($this->getConfig('enable_reorder_notifications', true)) {
            $alertService = $this->container->get(AlertService::class);
            $alertService->sendReorderSummary($suggestions);
        }
    }
    
    /**
     * Check expiring stock (cron job)
     */
    public function checkExpiringStock(): void
    {
        if (!$this->getConfig('enable_expiry_tracking', false)) {
            return;
        }
        
        $inventoryService = $this->container->get(InventoryService::class);
        $alertService = $this->container->get(AlertService::class);
        
        $alertDays = $this->getConfig('expiry_alert_days', 30);
        $expiringProducts = $inventoryService->getExpiringProducts($alertDays);
        
        foreach ($expiringProducts as $product) {
            $alertService->sendExpiryAlert($product);
        }
        
        $this->logger->info('Expiry check completed', ['expiring_count' => count($expiringProducts)]);
    }
    
    /**
     * Calculate stock turnover (cron job)
     */
    public function calculateStockTurnover(): void
    {
        $inventoryService = $this->container->get(InventoryService::class);
        $turnoverData = $inventoryService->calculateStockTurnover();
        
        $this->logger->info('Stock turnover calculated', ['products' => count($turnoverData)]);
    }
    
    /**
     * Initialize inventory tracking
     */
    protected function initializeInventoryTracking(): void
    {
        $inventoryService = $this->container->get(InventoryService::class);
        $inventoryService->initializeTracking();
    }
    
    /**
     * Schedule stock checks
     */
    protected function scheduleStockChecks(): void
    {
        $this->enableCronJob('checkStockLevels');
        $this->enableCronJob('generateReorderSuggestions');
    }
    
    /**
     * Pause stock checks
     */
    protected function pauseStockChecks(): void
    {
        $this->disableCronJob('checkStockLevels');
        $this->disableCronJob('generateReorderSuggestions');
    }
    
    /**
     * Create default warehouse
     */
    protected function createDefaultWarehouse(): void
    {
        $warehouseService = $this->container->get(WarehouseService::class);
        
        $defaultWarehouse = [
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'address' => '',
            'is_default' => true,
            'is_active' => true
        ];
        
        $warehouseService->create($defaultWarehouse);
    }
    
    /**
     * Run database migrations
     */
    protected function runMigrations(): void
    {
        $migrations = [
            'create_inventory_stock_table.php',
            'create_inventory_movements_table.php',
            'create_inventory_alerts_table.php',
            'create_warehouses_table.php',
            'create_warehouse_zones_table.php',
            'create_suppliers_table.php',
            'create_supplier_products_table.php',
            'create_purchase_orders_table.php',
            'create_purchase_order_items_table.php',
            'create_inventory_counts_table.php',
            'create_inventory_adjustments_table.php',
            'create_product_locations_table.php',
            'create_reorder_rules_table.php'
        ];
        
        foreach ($migrations as $migration) {
            $this->api->runMigration($this->getPath('migrations/' . $migration));
        }
    }
    
    /**
     * Set default configuration
     */
    protected function setDefaultConfig(): void
    {
        $defaults = [
            'enable_multi_warehouse' => false,
            'stock_tracking_method' => 'sku',
            'low_stock_threshold' => 10,
            'out_of_stock_threshold' => 0,
            'enable_backorders' => false,
            'backorder_limit' => -50,
            'stock_status_display' => 'exact',
            'enable_low_stock_alerts' => true,
            'enable_auto_reorder' => false,
            'reorder_point_formula' => 'min_max',
            'enable_barcode_scanning' => true,
            'barcode_format' => 'code128',
            'enable_cycle_counting' => true,
            'cycle_count_frequency' => 'monthly',
            'enable_expiry_tracking' => false,
            'expiry_alert_days' => 30,
            'cost_tracking_method' => 'average',
            'enable_serial_tracking' => false,
            'warehouse_transfer_approval' => true,
            'stock_valuation_method' => 'cost',
            'enable_bin_locations' => true
        ];
        
        foreach ($defaults as $key => $value) {
            if ($this->getConfig($key) === null) {
                $this->setConfig($key, $value);
            }
        }
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register ScheduledJobs
     */
    protected function registerScheduledJobs(): void
    {
        // TODO: Implement registerScheduledJobs
    }
}