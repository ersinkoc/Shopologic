<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Plugin\Hook;
use Shopologic\Core\Container\ContainerInterface;
use AdvancedInventory\Services\{
    InventoryManager,
    WarehouseManager,
    SupplierManager,
    StockTracker,
    ReorderEngine,
    BarcodeGenerator,;
    AnalyticsService,;
    AlertService;
};
use AdvancedInventory\Repositories\{
    InventoryRepository,
    WarehouseRepository,;
    SupplierRepository,;
    MovementRepository;
};
use AdvancedInventory\Controllers\{
    InventoryController,
    WarehouseController,
    SupplierController,
    AdjustmentController,
    TransferController,
    PurchaseOrderController,
    AnalyticsController,;
    AlertController,;
    ReorderController;
};

class AdvancedInventoryPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'advanced-inventory';
    protected string $version = '1.0.0';
    protected string $description = 'Enterprise-grade inventory management system';
    protected string $author = 'Shopologic Team';
    protected array $dependencies = ['shopologic/commerce', 'shopologic/analytics'];

    private InventoryManager $inventoryManager;
    private WarehouseManager $warehouseManager;
    private SupplierManager $supplierManager;
    private StockTracker $stockTracker;
    private ReorderEngine $reorderEngine;
    private BarcodeGenerator $barcodeGenerator;
    private AnalyticsService $analyticsService;
    private AlertService $alertService;

    /**
     * Plugin installation
     */
    public function install(): void
    {
        // Run database migrations
        $this->runMigrations();
        
        // Create default warehouse
        $this->createDefaultWarehouse();
        
        // Set default configuration
        $this->setDefaultConfiguration();
        
        // Create necessary directories
        $this->createDirectories();
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Register services
        $this->registerServices();
        
        // Register hooks and filters
        $this->registerHooks();
        
        // Register API routes
        $this->registerRoutes();
        
        // Schedule background tasks
        $this->scheduleBackgroundTasks();
        
        // Initialize analytics
        $this->initializeAnalytics();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Unschedule background tasks
        $this->unscheduleBackgroundTasks();
        
        // Clear caches
        $this->clearCaches();
        
        // Save current state
        $this->savePluginState();
    }

    /**
     * Plugin uninstallation
     */
    public function uninstall(): void
    {
        // Note: Database cleanup is optional and user-configurable
        if ($this->getConfig('cleanup_on_uninstall', false)) {
            $this->cleanupDatabase();
        }
        
        // Remove configuration
        $this->removeConfiguration();
        
        // Clean up files
        $this->cleanupFiles();
    }

    /**
     * Plugin update
     */
    public function update(string $previousVersion): void
    {
        // Run version-specific updates
        if (version_compare($previousVersion, '1.0.0', '<')) {
            $this->updateTo100();
        }
        
        // Update database schema if needed
        $this->runMigrations();
        
        // Update configuration schema
        $this->updateConfiguration();
    }

    /**
     * Plugin boot - called when plugin is loaded
     */
    public function boot(): void
    {
        // Initialize core services
        $this->initializeServices();
        
        // Register event listeners
        $this->registerEventListeners();
        
        // Load plugin configuration
        $this->loadConfiguration();
    }

    /**
     * Register services with the container
     */
    protected function registerServices(): void
    {
        $container = $this->getContainer();
        
        // Register repositories
        $container->singleton(InventoryRepository::class);
        $container->singleton(WarehouseRepository::class);
        $container->singleton(SupplierRepository::class);
        $container->singleton(MovementRepository::class);
        
        // Register core services
        $container->singleton(InventoryManager::class, function ($container) {
            return new InventoryManager(
                $container->get(InventoryRepository::class),
                $container->get(MovementRepository::class),
                $this->getConfig('stock_tracking_method', 'fifo')
            );
        });
        
        $container->singleton(WarehouseManager::class, function ($container) {
            return new WarehouseManager(
                $container->get(WarehouseRepository::class)
            );
        });
        
        $container->singleton(SupplierManager::class, function ($container) {
            return new SupplierManager(
                $container->get(SupplierRepository::class)
            );
        });
        
        $container->singleton(StockTracker::class, function ($container) {
            return new StockTracker(
                $container->get(InventoryRepository::class),
                $container->get(MovementRepository::class)
            );
        });
        
        $container->singleton(ReorderEngine::class, function ($container) {
            return new ReorderEngine(
                $container->get(InventoryRepository::class),
                $container->get(SupplierRepository::class),
                $this->getConfig('auto_reorder_enabled', true)
            );
        });
        
        $container->singleton(BarcodeGenerator::class, function ($container) {
            return new BarcodeGenerator(
                $this->getConfig('barcode_generation.format', 'code128')
            );
        });
        
        $container->singleton(AnalyticsService::class, function ($container) {
            return new AnalyticsService(
                $container->get(InventoryRepository::class),
                $container->get(MovementRepository::class)
            );
        });
        
        $container->singleton(AlertService::class, function ($container) {
            return new AlertService(
                $container->get(InventoryRepository::class),
                $this->getConfig('notification_settings', [])
            );
        });
        
        // Register controllers
        $container->singleton(InventoryController::class);
        $container->singleton(WarehouseController::class);
        $container->singleton(SupplierController::class);
        $container->singleton(AdjustmentController::class);
        $container->singleton(TransferController::class);
        $container->singleton(PurchaseOrderController::class);
        $container->singleton(AnalyticsController::class);
        $container->singleton(AlertController::class);
        $container->singleton(ReorderController::class);
    }

    /**
     * Initialize services
     */
    protected function initializeServices(): void
    {
        $container = $this->getContainer();
        
        $this->inventoryManager = $container->get(InventoryManager::class);
        $this->warehouseManager = $container->get(WarehouseManager::class);
        $this->supplierManager = $container->get(SupplierManager::class);
        $this->stockTracker = $container->get(StockTracker::class);
        $this->reorderEngine = $container->get(ReorderEngine::class);
        $this->barcodeGenerator = $container->get(BarcodeGenerator::class);
        $this->analyticsService = $container->get(AnalyticsService::class);
        $this->alertService = $container->get(AlertService::class);
    }

    /**
     * Register hooks and filters
     */
    protected function registerHooks(): void
    {
        // Order lifecycle hooks
        Hook::addAction('order.created', [$this, 'handleOrderCreated'], 10);
        Hook::addAction('payment.completed', [$this, 'reserveInventory'], 5);
        Hook::addAction('shipment.created', [$this, 'reduceInventory'], 5);
        Hook::addAction('order.cancelled', [$this, 'releaseReservation'], 10);
        Hook::addAction('refund.processed', [$this, 'restoreInventory'], 10);
        
        // Product hooks
        Hook::addAction('product.updated', [$this, 'handleProductUpdated'], 10);
        Hook::addAction('product.created', [$this, 'handleProductCreated'], 10);
        
        // Filters
        Hook::addFilter('product.stock_status', [$this, 'filterStockStatus'], 10);
        Hook::addFilter('checkout.validation', [$this, 'validateInventoryAvailability'], 15);
        Hook::addFilter('product.is_in_stock', [$this, 'checkStockAvailability'], 10);
        
        // Admin hooks
        Hook::addAction('admin_menu', [$this, 'registerAdminMenu']);
        Hook::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // AJAX hooks
        Hook::addAction('wp_ajax_scan_barcode', [$this, 'handleBarcodeScans']);
        Hook::addAction('wp_ajax_quick_stock_update', [$this, 'handleQuickStockUpdate']);
    }

    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        $this->registerRoute('GET', '/api/v1/inventory/items', 'InventoryController@index');
        $this->registerRoute('GET', '/api/v1/inventory/items/{id}', 'InventoryController@show');
        $this->registerRoute('POST', '/api/v1/inventory/adjustments', 'AdjustmentController@create');
        $this->registerRoute('POST', '/api/v1/inventory/transfers', 'TransferController@create');
        $this->registerRoute('GET', '/api/v1/warehouses', 'WarehouseController@index');
        $this->registerRoute('POST', '/api/v1/warehouses', 'WarehouseController@create');
        $this->registerRoute('GET', '/api/v1/suppliers', 'SupplierController@index');
        $this->registerRoute('POST', '/api/v1/suppliers', 'SupplierController@create');
        $this->registerRoute('POST', '/api/v1/purchase-orders', 'PurchaseOrderController@create');
        $this->registerRoute('GET', '/api/v1/inventory/analytics', 'AnalyticsController@dashboard');
        $this->registerRoute('GET', '/api/v1/inventory/low-stock', 'AlertController@lowStock');
        $this->registerRoute('POST', '/api/v1/inventory/reorder/{id}', 'ReorderController@trigger');
    }

    /**
     * Handle order created event
     */
    public function handleOrderCreated($order): void
    {
        if ($this->getConfig('reservation_settings.reserve_on_order', true)) {
            foreach ($order->getItems() as $item) {
                $this->stockTracker->reserveStock(
                    $item->getProductId(),
                    $item->getQuantity(),
                    $order->getId(),
                    $this->getConfig('reservation_settings.reservation_timeout', 30)
                );
            }
        }
    }

    /**
     * Reserve inventory on payment completion
     */
    public function reserveInventory($payment): void
    {
        $order = $payment->getOrder();
        
        foreach ($order->getItems() as $item) {
            $this->stockTracker->confirmReservation(
                $item->getProductId(),
                $item->getQuantity(),
                $order->getId()
            );
        }
    }

    /**
     * Reduce inventory on shipment
     */
    public function reduceInventory($shipment): void
    {
        foreach ($shipment->getItems() as $item) {
            $this->inventoryManager->reduceStock(
                $item->getProductId(),
                $item->getQuantity(),
                $shipment->getWarehouseId(),
                'shipment',
                $shipment->getId()
            );
        }
    }

    /**
     * Validate inventory availability during checkout
     */
    public function validateInventoryAvailability($validation, $cart): array
    {
        foreach ($cart->getItems() as $item) {
            $available = $this->stockTracker->getAvailableStock($item->getProductId());
            
            if ($available < $item->getQuantity()) {
                $validation['errors'][] = sprintf(
                    'Only %d units of %s are available',
                    $available,
                    $item->getProductName()
                );
            }
        }
        
        return $validation;
    }

    /**
     * Filter stock status
     */
    public function filterStockStatus($status, $productId): string
    {
        $stock = $this->stockTracker->getCurrentStock($productId);
        $threshold = $this->getConfig('low_stock_threshold', 10);
        
        if ($stock <= 0) {
            return 'out_of_stock';
        } elseif ($stock <= $threshold) {
            return 'low_stock';
        }
        
        return 'in_stock';
    }

    /**
     * Scheduled task: Process reorder rules
     */
    public function processReorderRules(): void
    {
        if (!$this->getConfig('auto_reorder_enabled', true)) {
            return;
        }
        
        $lowStockItems = $this->reorderEngine->findItemsRequiringReorder();
        
        foreach ($lowStockItems as $item) {
            try {
                $this->reorderEngine->createPurchaseOrder($item);
                
                $this->logInfo("Auto-reorder created for product {$item['product_id']}");
            } catch (\RuntimeException $e) {
                $this->logError("Failed to create auto-reorder: " . $e->getMessage());
            }
        }
    }

    /**
     * Scheduled task: Generate stock alerts
     */
    public function generateStockAlerts(): void
    {
        $alerts = $this->alertService->generateAlerts();
        
        foreach ($alerts as $alert) {
            // Send notification
            Hook::doAction('notification.send', $alert);
        }
    }

    /**
     * Scheduled task: Optimize inventory levels
     */
    public function optimizeInventoryLevels(): void
    {
        $recommendations = $this->analyticsService->generateOptimizationRecommendations();
        
        foreach ($recommendations as $recommendation) {
            // Store recommendations for review
            $this->saveOptimizationRecommendation($recommendation);
        }
    }

    /**
     * Scheduled task: Release expired reservations
     */
    public function releaseExpiredReservations(): void
    {
        $expired = $this->stockTracker->findExpiredReservations();
        
        foreach ($expired as $reservation) {
            $this->stockTracker->releaseReservation($reservation['id']);
        }
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Inventory Management',
            'Inventory',
            'inventory.view',
            'inventory',
            [$this, 'renderInventoryDashboard'],
            'dashicons-archive',
            25
        );
        
        add_submenu_page(
            'inventory',
            'All Items',
            'All Items',
            'inventory.view',
            'inventory-items',
            [$this, 'renderInventoryItems']
        );
        
        add_submenu_page(
            'inventory',
            'Warehouses',
            'Warehouses',
            'warehouse.manage',
            'warehouses',
            [$this, 'renderWarehouses']
        );
        
        add_submenu_page(
            'inventory',
            'Suppliers',
            'Suppliers',
            'supplier.manage',
            'suppliers',
            [$this, 'renderSuppliers']
        );
        
        add_submenu_page(
            'inventory',
            'Purchase Orders',
            'Purchase Orders',
            'supplier.manage',
            'purchase-orders',
            [$this, 'renderPurchaseOrders']
        );
        
        add_submenu_page(
            'inventory',
            'Stock Movements',
            'Movements',
            'inventory.view',
            'stock-movements',
            [$this, 'renderStockMovements']
        );
        
        add_submenu_page(
            'inventory',
            'Analytics',
            'Analytics',
            'analytics.view',
            'inventory-analytics',
            [$this, 'renderAnalytics']
        );
        
        add_submenu_page(
            'inventory',
            'Settings',
            'Settings',
            'inventory.manage',
            'inventory-settings',
            [$this, 'renderSettings']
        );
    }

    /**
     * Create default warehouse
     */
    private function createDefaultWarehouse(): void
    {
        $this->warehouseManager->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'address' => '',
            'is_default' => true,
            'is_active' => true
        ]);
    }

    /**
     * Set default configuration
     */
    private function setDefaultConfiguration(): void
    {
        $defaults = [
            'stock_tracking_method' => 'fifo',
            'low_stock_threshold' => 10,
            'auto_reorder_enabled' => true,
            'reorder_lead_time_days' => 7,
            'barcode_generation' => [
                'enabled' => true,
                'format' => 'code128',
                'auto_generate' => true
            ],
            'reservation_settings' => [
                'reserve_on_order' => true,
                'reservation_timeout' => 30
            ],
            'notification_settings' => [
                'low_stock_alerts' => true,
                'out_of_stock_alerts' => true,
                'overstock_alerts' => false,
                'delivery_alerts' => true
            ],
            'analytics_settings' => [
                'track_movements' => true,
                'forecasting_enabled' => true,
                'demand_planning' => true
            ]
        ];
        
        foreach ($defaults as $key => $value) {
            if (!$this->hasConfig($key)) {
                $this->setConfig($key, $value);
            }
        }
    }

    /**
     * Create necessary directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/exports',
            $this->getPluginPath() . '/imports',
            $this->getPluginPath() . '/barcodes',
            $this->getPluginPath() . '/reports'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
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
     * Register Permissions
     */
    protected function registerPermissions(): void
    {
        // TODO: Implement registerPermissions
    }

    /**
     * Register ScheduledJobs
     */
    protected function registerScheduledJobs(): void
    {
        // TODO: Implement registerScheduledJobs
    }
}