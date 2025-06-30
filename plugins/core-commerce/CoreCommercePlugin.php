<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Container\ContainerInterface;
use Shopologic\Core\Hook\HookSystemInterface;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Plugins\CoreCommerce\Services\ProductService;
use Shopologic\Plugins\CoreCommerce\Services\CategoryService;
use Shopologic\Plugins\CoreCommerce\Services\CartService;
use Shopologic\Plugins\CoreCommerce\Services\OrderService;
use Shopologic\Plugins\CoreCommerce\Services\CustomerService;
use Shopologic\Plugins\CoreCommerce\Services\InventoryService;
use Shopologic\Plugins\CoreCommerce\Services\PricingService;
use Shopologic\Plugins\CoreCommerce\Services\AnalyticsService;
use Shopologic\Plugins\CoreCommerce\Services\SearchService;
use Shopologic\Plugins\CoreCommerce\Services\RecommendationService;
use Shopologic\Plugins\CoreCommerce\Services\PerformanceService;
use Shopologic\Plugins\CoreCommerce\Services\SecurityService;
use Shopologic\Plugins\CoreCommerce\Services\IntegrationService;
use Shopologic\Plugins\CoreCommerce\Services\CacheService;
use Shopologic\Plugins\CoreCommerce\Services\ValidationService;
use Shopologic\Plugins\CoreCommerce\Repositories\ProductRepository;
use Shopologic\Plugins\CoreCommerce\Repositories\CategoryRepository;
use Shopologic\Plugins\CoreCommerce\Repositories\OrderRepository;
use Shopologic\Plugins\CoreCommerce\Repositories\CustomerRepository;
use Shopologic\Plugins\CoreCommerce\Contracts\ProductRepositoryInterface;
use Shopologic\Plugins\CoreCommerce\Contracts\CategoryRepositoryInterface;
use Shopologic\Plugins\CoreCommerce\Contracts\CartServiceInterface;
use Shopologic\Plugins\CoreCommerce\Contracts\OrderServiceInterface;
use Shopologic\Plugins\CoreCommerce\Contracts\CustomerServiceInterface;

/**
 * Core Commerce Plugin - Enterprise E-commerce Foundation
 * 
 * Advanced e-commerce platform with AI-powered features, real-time analytics,
 * intelligent inventory management, and enterprise-grade performance optimization
 */
class CoreCommercePlugin extends AbstractPlugin
{
    public function install(): void
    {
        $this->runMigrations();
    }

    public function uninstall(): void
    {
        $this->rollbackMigrations();
    }

    public function activate(): void
    {
        $this->seedDefaultData();
    }

    public function deactivate(): void
    {
        // Nothing to do on deactivation
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        $this->runMigrations();
    }

    protected function registerServices(): void
    {
        // Register repositories
        $this->container->singleton(ProductRepositoryInterface::class, ProductRepository::class);
        $this->container->singleton(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->container->singleton(OrderRepository::class);
        $this->container->singleton(CustomerRepository::class);

        // Register core services
        $this->container->singleton(CartServiceInterface::class, CartService::class);
        $this->container->singleton(OrderServiceInterface::class, OrderService::class);
        $this->container->singleton(CustomerServiceInterface::class, CustomerService::class);
        $this->container->singleton(ProductService::class);
        $this->container->singleton(CategoryService::class);
        
        // Register advanced services
        $this->container->singleton(InventoryService::class);
        $this->container->singleton(PricingService::class);
        $this->container->singleton(AnalyticsService::class);
        $this->container->singleton(SearchService::class);
        $this->container->singleton(RecommendationService::class);
        $this->container->singleton(PerformanceService::class);
        $this->container->singleton(SecurityService::class);
        $this->container->singleton(IntegrationService::class);
        $this->container->singleton(CacheService::class);
        $this->container->singleton(ValidationService::class);

        // Tag services for discovery
        $this->container->tag([
            ProductRepository::class,
            CategoryRepository::class,
            OrderRepository::class,
            CustomerRepository::class
        ], 'repository');

        $this->container->tag([
            ProductService::class,
            CategoryService::class,
            CartService::class,
            OrderService::class,
            CustomerService::class,
            InventoryService::class,
            PricingService::class,
            AnalyticsService::class,
            SearchService::class
        ], 'service');
        
        $this->container->tag([
            RecommendationService::class,
            PerformanceService::class,
            SecurityService::class,
            IntegrationService::class
        ], 'advanced_service');
    }

    protected function registerEventListeners(): void
    {
        $dispatcher = $this->container->get(EventDispatcherInterface::class);
        
        // Register event listeners
        $dispatcher->listen('system.init', [$this, 'onSystemInit']);
        $dispatcher->listen('product.created', [$this, 'onProductCreated']);
        $dispatcher->listen('order.created', [$this, 'onOrderCreated']);
    }

    protected function registerHooks(): void
    {
        $hooks = $this->container->get(HookSystemInterface::class);
        
        // Core initialization hooks
        $hooks->addAction('init', [$this, 'initializeCommerce'], 5);
        $hooks->addAction('system.ready', [$this, 'initializeAdvancedFeatures'], 10);
        
        // Product hooks
        $hooks->addAction('product.before_save', [$this, 'validateProduct'], 10);
        $hooks->addAction('product.after_save', [$this, 'updateProductCache'], 10);
        $hooks->addAction('product.viewed', [$this, 'trackProductView'], 10);
        $hooks->addFilter('product.price', [$this, 'calculateDynamicPrice'], 10);
        $hooks->addFilter('product.availability', [$this, 'checkRealTimeInventory'], 10);
        
        // Cart hooks
        $hooks->addAction('cart.item_added', [$this, 'trackCartEvent'], 10);
        $hooks->addAction('cart.updated', [$this, 'recalculateRecommendations'], 10);
        $hooks->addFilter('cart.totals', [$this, 'applyDynamicPricing'], 10);
        $hooks->addFilter('cart.shipping_methods', [$this, 'optimizeShippingOptions'], 10);
        
        // Order hooks
        $hooks->addAction('order.placed', [$this, 'processOrderIntelligence'], 5);
        $hooks->addAction('order.status_changed', [$this, 'updateOrderAnalytics'], 10);
        $hooks->addFilter('order.fulfillment', [$this, 'optimizeFulfillment'], 10);
        
        // Customer hooks
        $hooks->addAction('customer.login', [$this, 'updateCustomerProfile'], 10);
        $hooks->addAction('customer.behavior_tracked', [$this, 'updateRecommendations'], 10);
        $hooks->addFilter('customer.pricing_tier', [$this, 'calculatePricingTier'], 10);
        
        // Performance hooks
        $hooks->addAction('page.load', [$this, 'optimizePagePerformance'], 5);
        $hooks->addFilter('database.query', [$this, 'optimizeQuery'], 10);
        $hooks->addAction('cache.miss', [$this, 'preloadRelatedData'], 10);
        
        // Security hooks
        $hooks->addAction('request.before', [$this, 'validateRequest'], 5);
        $hooks->addFilter('user.permissions', [$this, 'enforceSecurityPolicies'], 10);
        
        // Template hooks
        $hooks->addAction('template.product.after_title', [$this, 'renderSmartProductInfo'], 10);
        $hooks->addAction('template.cart.after_items', [$this, 'renderIntelligentCartFeatures'], 10);
        $hooks->addAction('template.checkout.before_payment', [$this, 'renderCheckoutOptimizations'], 10);
    }

    protected function registerRoutes(): void
    {
        // Core Product API
        $this->registerRoute('GET', '/api/v1/products', 'ProductController@index');
        $this->registerRoute('GET', '/api/v1/products/{id}', 'ProductController@show');
        $this->registerRoute('POST', '/api/v1/products', 'ProductController@store');
        $this->registerRoute('PUT', '/api/v1/products/{id}', 'ProductController@update');
        $this->registerRoute('DELETE', '/api/v1/products/{id}', 'ProductController@destroy');
        
        // Advanced Product API
        $this->registerRoute('GET', '/api/v1/products/{id}/recommendations', 'ProductController@getRecommendations');
        $this->registerRoute('GET', '/api/v1/products/{id}/analytics', 'ProductController@getAnalytics');
        $this->registerRoute('POST', '/api/v1/products/{id}/track-view', 'ProductController@trackView');
        $this->registerRoute('GET', '/api/v1/products/{id}/pricing-history', 'ProductController@getPricingHistory');
        $this->registerRoute('POST', '/api/v1/products/bulk-update', 'ProductController@bulkUpdate');
        $this->registerRoute('GET', '/api/v1/products/search/suggest', 'ProductController@searchSuggestions');
        
        // Category Management
        $this->registerRoute('GET', '/api/v1/categories', 'CategoryController@index');
        $this->registerRoute('GET', '/api/v1/categories/{id}', 'CategoryController@show');
        $this->registerRoute('POST', '/api/v1/categories', 'CategoryController@store');
        $this->registerRoute('PUT', '/api/v1/categories/{id}', 'CategoryController@update');
        $this->registerRoute('DELETE', '/api/v1/categories/{id}', 'CategoryController@destroy');
        $this->registerRoute('GET', '/api/v1/categories/{id}/performance', 'CategoryController@getPerformance');
        
        // Intelligent Cart API
        $this->registerRoute('GET', '/api/v1/cart', 'CartController@show');
        $this->registerRoute('POST', '/api/v1/cart/items', 'CartController@addItem');
        $this->registerRoute('PUT', '/api/v1/cart/items/{id}', 'CartController@updateItem');
        $this->registerRoute('DELETE', '/api/v1/cart/items/{id}', 'CartController@removeItem');
        $this->registerRoute('POST', '/api/v1/cart/clear', 'CartController@clear');
        $this->registerRoute('GET', '/api/v1/cart/recommendations', 'CartController@getRecommendations');
        $this->registerRoute('POST', '/api/v1/cart/optimize', 'CartController@optimizeCart');
        $this->registerRoute('GET', '/api/v1/cart/abandonment-prediction', 'CartController@predictAbandonment');
        
        // Advanced Order Management
        $this->registerRoute('GET', '/api/v1/orders', 'OrderController@index');
        $this->registerRoute('GET', '/api/v1/orders/{id}', 'OrderController@show');
        $this->registerRoute('POST', '/api/v1/orders', 'OrderController@store');
        $this->registerRoute('PUT', '/api/v1/orders/{id}/status', 'OrderController@updateStatus');
        $this->registerRoute('GET', '/api/v1/orders/{id}/tracking', 'OrderController@getTracking');
        $this->registerRoute('POST', '/api/v1/orders/{id}/fulfill', 'OrderController@fulfill');
        $this->registerRoute('GET', '/api/v1/orders/analytics', 'OrderController@getAnalytics');
        
        // Customer Intelligence
        $this->registerRoute('GET', '/api/v1/customers', 'CustomerController@index');
        $this->registerRoute('GET', '/api/v1/customers/{id}', 'CustomerController@show');
        $this->registerRoute('POST', '/api/v1/customers', 'CustomerController@store');
        $this->registerRoute('PUT', '/api/v1/customers/{id}', 'CustomerController@update');
        $this->registerRoute('GET', '/api/v1/customers/{id}/profile', 'CustomerController@getProfile');
        $this->registerRoute('GET', '/api/v1/customers/{id}/recommendations', 'CustomerController@getRecommendations');
        $this->registerRoute('GET', '/api/v1/customers/{id}/lifetime-value', 'CustomerController@getLifetimeValue');
        
        // Analytics & Insights
        $this->registerRoute('GET', '/api/v1/analytics/dashboard', 'AnalyticsController@dashboard');
        $this->registerRoute('GET', '/api/v1/analytics/sales-forecast', 'AnalyticsController@salesForecast');
        $this->registerRoute('GET', '/api/v1/analytics/customer-segments', 'AnalyticsController@customerSegments');
        $this->registerRoute('GET', '/api/v1/analytics/product-performance', 'AnalyticsController@productPerformance');
        
        // Search & Discovery
        $this->registerRoute('GET', '/api/v1/search', 'SearchController@search');
        $this->registerRoute('GET', '/api/v1/search/autocomplete', 'SearchController@autocomplete');
        $this->registerRoute('POST', '/api/v1/search/track', 'SearchController@trackSearch');
        $this->registerRoute('GET', '/api/v1/search/trending', 'SearchController@getTrending');
        
        // Inventory Management
        $this->registerRoute('GET', '/api/v1/inventory', 'InventoryController@index');
        $this->registerRoute('GET', '/api/v1/inventory/low-stock', 'InventoryController@getLowStock');
        $this->registerRoute('POST', '/api/v1/inventory/reorder', 'InventoryController@reorder');
        $this->registerRoute('GET', '/api/v1/inventory/forecast', 'InventoryController@getForecast');
        
        // Performance Monitoring
        $this->registerRoute('GET', '/api/v1/performance/metrics', 'PerformanceController@getMetrics');
        $this->registerRoute('GET', '/api/v1/performance/health', 'PerformanceController@healthCheck');
        $this->registerRoute('POST', '/api/v1/performance/optimize', 'PerformanceController@optimize');
    }

    protected function registerPermissions(): void
    {
        // Product permissions
        $this->addPermission('product.view', 'View products');
        $this->addPermission('product.create', 'Create products');
        $this->addPermission('product.update', 'Update products');
        $this->addPermission('product.delete', 'Delete products');
        
        // Category permissions
        $this->addPermission('category.view', 'View categories');
        $this->addPermission('category.manage', 'Manage categories');
        
        // Order permissions
        $this->addPermission('order.view', 'View orders');
        $this->addPermission('order.create', 'Create orders');
        $this->addPermission('order.update', 'Update orders');
        $this->addPermission('order.delete', 'Delete orders');
        
        // Customer permissions
        $this->addPermission('customer.view', 'View customers');
        $this->addPermission('customer.manage', 'Manage customers');
    }

    protected function registerScheduledJobs(): void
    {
        // Clean up abandoned carts every hour
        $this->scheduleJob('0 * * * *', [$this, 'cleanupAbandonedCarts']);
        
        // Update product search index every 6 hours
        $this->scheduleJob('0 */6 * * *', [$this, 'updateProductSearchIndex']);
        
        // Generate sales reports daily
        $this->scheduleJob('0 2 * * *', [$this, 'generateDailySalesReport']);
    }

    public function onSystemInit(): void
    {
        // Initialize commerce system
        $this->logger->info('Core commerce plugin initialized');
    }

    public function onProductCreated($event): void
    {
        // Handle product creation
        $product = $event->getProduct();
        $this->logger->info('Product created', ['product_id' => $product->getId()]);
        
        // Clear product cache
        $this->cache->tags(['products'])->flush();
    }

    public function onOrderCreated($event): void
    {
        // Handle order creation
        $order = $event->getOrder();
        $this->logger->info('Order created', ['order_id' => $order->getId()]);
        
        // Update inventory
        $orderService = $this->container->get(OrderServiceInterface::class);
        $orderService->updateInventory($order);
        
        // Send order confirmation email
        event('mail.send', [
            'template' => 'order_confirmation',
            'to' => $order->getCustomerEmail(),
            'data' => ['order' => $order]
        ]);
    }

    public function initializeCommerce(): void
    {
        // Initialize commerce components
        $this->logger->debug('Initializing commerce components');
    }

    public function renderProductPrice($product): void
    {
        echo $this->render('partials/product-price', ['product' => $product]);
    }

    public function renderCartTotals($cart): void
    {
        echo $this->render('partials/cart-totals', ['cart' => $cart]);
    }

    public function filterProductPrice($price, $product): float
    {
        // Apply any global price modifications
        return apply_filters('commerce.product.price', $price, $product);
    }

    public function filterCartTotals($totals, $cart): array
    {
        // Apply any cart total modifications
        return apply_filters('commerce.cart.totals', $totals, $cart);
    }

    public function cleanupAbandonedCarts(): void
    {
        $cartService = $this->container->get(CartServiceInterface::class);
        $deleted = $cartService->cleanupAbandoned(24); // 24 hours
        
        $this->logger->info('Cleaned up abandoned carts', ['count' => $deleted]);
    }

    public function updateProductSearchIndex(): void
    {
        $productService = $this->container->get(ProductService::class);
        $indexed = $productService->reindexAll();
        
        $this->logger->info('Updated product search index', ['count' => $indexed]);
    }

    public function generateDailySalesReport(): void
    {
        $orderService = $this->container->get(OrderServiceInterface::class);
        $report = $orderService->generateDailyReport(date('Y-m-d', strtotime('-1 day')));
        
        event('report.generated', ['type' => 'daily_sales', 'report' => $report]);
        
        $this->logger->info('Generated daily sales report', ['date' => $report['date']]);
    }

    private function runMigrations(): void
    {
        $migrationPath = __DIR__ . '/migrations';
        $migrations = glob($migrationPath . '/*.php');
        
        foreach ($migrations as $migration) {
            require_once $migration;
            $className = basename($migration, '.php');
            $migrationClass = new $className($this->database);
            $migrationClass->up();
        }
    }

    private function rollbackMigrations(): void
    {
        $migrationPath = __DIR__ . '/migrations';
        $migrations = array_reverse(glob($migrationPath . '/*.php'));
        
        foreach ($migrations as $migration) {
            require_once $migration;
            $className = basename($migration, '.php');
            $migrationClass = new $className($this->database);
            $migrationClass->down();
        }
    }

    private function seedDefaultData(): void
    {
        // Seed default categories
        $categoryRepo = $this->container->get(CategoryRepositoryInterface::class);
        
        $defaultCategories = [
            ['name' => 'Electronics', 'slug' => 'electronics'],
            ['name' => 'Clothing', 'slug' => 'clothing'],
            ['name' => 'Home & Garden', 'slug' => 'home-garden'],
            ['name' => 'Books', 'slug' => 'books'],
            ['name' => 'Sports & Outdoors', 'slug' => 'sports-outdoors']
        ];
        
        foreach ($defaultCategories as $category) {
            if (!$categoryRepo->findBySlug($category['slug'])) {
                $categoryRepo->create($category);
            }
        }
    }
}