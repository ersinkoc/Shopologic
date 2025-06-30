<?php

declare(strict_types=1);
namespace Shopologic\Plugins\BundleBuilder;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Smart Bundle Builder Plugin
 * 
 * Intelligent product bundling with dynamic discounts and analytics
 */
class BundleBuilderPlugin extends AbstractPlugin
{
    private $bundleEngine;
    private $discountCalculator;
    private $analyticsTracker;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->initializeBundleAnalytics();
    }

    private function registerServices(): void
    {
        $this->bundleEngine = new Services\BundleEngine($this->api);
        $this->discountCalculator = new Services\DiscountCalculator($this->api);
        $this->analyticsTracker = new Services\BundleAnalytics($this->api);
    }

    private function registerHooks(): void
    {
        // Bundle suggestions
        Hook::addFilter('product.display', [$this, 'addBundleSuggestions'], 20, 2);
        Hook::addFilter('cart.display', [$this, 'suggestBundleCompletion'], 20, 1);
        
        // Bundle processing
        Hook::addAction('cart.item_added', [$this, 'checkBundleOpportunities'], 10, 1);
        Hook::addFilter('cart.item_price', [$this, 'applyBundleDiscounts'], 10, 2);
        
        // Analytics
        Hook::addAction('order.completed', [$this, 'trackBundlePurchase'], 10, 1);
        
        // Admin
        Hook::addFilter('admin.product.form', [$this, 'addBundleSettings'], 10, 2);
        Hook::addAction('admin.dashboard.widgets', [$this, 'addBundleWidget'], 10, 1);
    }

    public function addBundleSuggestions($content, $product): string
    {
        if (!$this->getConfig('auto_suggest_bundles', true)) {
            return $content;
        }

        $bundles = $this->bundleEngine->findBundlesForProduct($product->id);
        
        if (empty($bundles)) {
            return $content;
        }

        $bundleWidget = $this->api->view('bundle-builder/product-bundles', [
            'product' => $product,
            'bundles' => $bundles,
            'savings' => $this->calculateBundleSavings($bundles)
        ]);

        return $content . $bundleWidget;
    }

    public function suggestBundleCompletion($cartDisplay): string
    {
        $cart = $this->api->service('CartService');
        $cartItems = $cart->getItems();
        
        if (count($cartItems) < 2) {
            return $cartDisplay;
        }

        $incompleteBundles = $this->bundleEngine->findIncompleteBundles($cartItems);
        
        if (empty($incompleteBundles)) {
            return $cartDisplay;
        }

        $completionWidget = $this->api->view('bundle-builder/bundle-completion', [
            'incomplete_bundles' => $incompleteBundles,
            'potential_savings' => $this->calculatePotentialSavings($incompleteBundles)
        ]);

        return $cartDisplay . $completionWidget;
    }

    public function checkBundleOpportunities($cartItem): void
    {
        $cart = $this->api->service('CartService');
        $allItems = $cart->getItems();
        
        // Check if adding this item completes any bundles
        $completedBundles = $this->bundleEngine->checkCompletedBundles($allItems);
        
        foreach ($completedBundles as $bundle) {
            $this->notifyBundleCompletion($bundle);
            $this->applyBundleToCart($bundle, $allItems);
        }
        
        // Suggest complementary products
        $suggestions = $this->bundleEngine->suggestComplementaryProducts($cartItem->product_id, $allItems);
        
        if (!empty($suggestions)) {
            $this->api->notification()->flash([
                'type' => 'info',
                'message' => 'Complete your bundle and save ' . $this->getConfig('bundle_discount_percentage', 10) . '%!',
                'actions' => $suggestions
            ]);
        }
    }

    public function applyBundleDiscounts($price, $cartItem): float
    {
        $applicableBundles = $this->bundleEngine->getApplicableBundles($cartItem);
        
        if (empty($applicableBundles)) {
            return $price;
        }

        $bestDiscount = $this->discountCalculator->calculateBestDiscount($cartItem, $applicableBundles);
        
        if ($bestDiscount > 0) {
            $discountedPrice = $price * (1 - $bestDiscount);
            
            // Add bundle information to cart item metadata
            $this->api->cart()->updateItemMetadata($cartItem->id, [
                'bundle_discount' => $bestDiscount,
                'bundle_id' => $bestDiscount['bundle_id'],
                'original_price' => $price
            ]);
            
            return $discountedPrice;
        }

        return $price;
    }

    public function trackBundlePurchase($order): void
    {
        $bundleItems = [];
        
        foreach ($order->items as $item) {
            if (isset($item->metadata['bundle_id'])) {
                $bundleItems[$item->metadata['bundle_id']][] = $item;
            }
        }

        foreach ($bundleItems as $bundleId => $items) {
            $this->analyticsTracker->recordBundleSale([
                'bundle_id' => $bundleId,
                'order_id' => $order->id,
                'items' => $items,
                'total_value' => array_sum(array_column($items, 'price')),
                'discount_given' => array_sum(array_column($items, 'discount')),
                'customer_id' => $order->customer_id
            ]);
        }
    }

    public function addBundleSettings($form, $product): string
    {
        $existingBundles = $this->bundleEngine->getBundlesForProduct($product->id);
        
        $bundleSettings = $this->api->view('bundle-builder/admin-settings', [
            'product' => $product,
            'existing_bundles' => $existingBundles,
            'available_products' => $this->getAvailableProducts($product),
            'discount_percentage' => $this->getConfig('bundle_discount_percentage', 10)
        ]);

        return $form . $bundleSettings;
    }

    public function addBundleWidget($widgets): array
    {
        $widgets['bundle_performance'] = [
            'title' => 'Bundle Performance',
            'template' => 'bundle-builder/dashboard-widget',
            'data' => $this->getBundlePerformanceData()
        ];

        return $widgets;
    }

    private function calculateBundleSavings($bundles): array
    {
        $savings = [];
        
        foreach ($bundles as $bundle) {
            $totalPrice = array_sum(array_column($bundle['products'], 'price'));
            $bundlePrice = $totalPrice * (1 - $bundle['discount']);
            $savings[$bundle['id']] = [
                'amount' => $totalPrice - $bundlePrice,
                'percentage' => $bundle['discount'] * 100
            ];
        }

        return $savings;
    }

    private function calculatePotentialSavings($incompleteBundles): float
    {
        $totalSavings = 0;
        
        foreach ($incompleteBundles as $bundle) {
            $missingValue = array_sum(array_column($bundle['missing_products'], 'price'));
            $potentialDiscount = $missingValue * $bundle['discount'];
            $totalSavings += $potentialDiscount;
        }

        return $totalSavings;
    }

    private function notifyBundleCompletion($bundle): void
    {
        $this->api->notification()->success([
            'title' => '🎉 Bundle Completed!',
            'message' => "You've unlocked a {$bundle['discount_percentage']}% discount on your {$bundle['name']}!",
            'duration' => 5000
        ]);
    }

    private function applyBundleToCart($bundle, $cartItems): void
    {
        // Group cart items by bundle
        $bundleItemIds = array_column($bundle['products'], 'id');
        
        foreach ($cartItems as $item) {
            if (in_array($item->product_id, $bundleItemIds)) {
                $this->api->cart()->updateItemMetadata($item->id, [
                    'bundle_id' => $bundle['id'],
                    'bundle_name' => $bundle['name'],
                    'bundle_discount' => $bundle['discount']
                ]);
            }
        }
    }

    private function getAvailableProducts($currentProduct): array
    {
        return $this->api->database()->table('products')
            ->where('id', '!=', $currentProduct->id)
            ->where('status', 'active')
            ->where('stock_quantity', '>', 0)
            ->orderBy('category_id')
            ->limit(100)
            ->get()
            ->toArray();
    }

    private function getBundlePerformanceData(): array
    {
        return [
            'total_bundles_sold' => $this->analyticsTracker->getTotalBundlesSold(),
            'revenue_from_bundles' => $this->analyticsTracker->getBundleRevenue(),
            'average_bundle_value' => $this->analyticsTracker->getAverageBundleValue(),
            'top_bundles' => $this->analyticsTracker->getTopBundles(5),
            'bundle_conversion_rate' => $this->analyticsTracker->getBundleConversionRate()
        ];
    }

    private function initializeBundleAnalytics(): void
    {
        // Schedule analytics aggregation
        $this->api->scheduler()->addJob('bundle_analytics', '0 1 * * *', function() {
            $this->analyticsTracker->aggregateDailyMetrics();
        });

        // Auto-create bundles based on purchase patterns
        $this->api->scheduler()->addJob('auto_bundle_creation', '0 2 * * 0', function() {
            if ($this->getConfig('auto_suggest_bundles', true)) {
                $this->bundleEngine->createBundlesFromPurchasePatterns();
            }
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->get('/bundles/suggestions/{product_id}', 'Controllers\BundleController@getSuggestions');
        $this->api->router()->post('/bundles/create', 'Controllers\BundleController@createBundle');
        $this->api->router()->get('/bundles/analytics', 'Controllers\BundleController@getAnalytics');
        $this->api->router()->post('/bundles/apply', 'Controllers\BundleController@applyBundleToCart');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultBundles();
    }

    private function createDefaultBundles(): void
    {
        // Create some starter bundles based on common patterns
        $starterBundles = [
            [
                'name' => 'Starter Pack',
                'description' => 'Perfect for beginners',
                'discount' => 0.10,
                'min_items' => 3,
                'active' => true
            ],
            [
                'name' => 'Pro Bundle',
                'description' => 'Professional package',
                'discount' => 0.15,
                'min_items' => 5,
                'active' => true
            ]
        ];

        foreach ($starterBundles as $bundle) {
            $this->api->database()->table('product_bundles')->insert($bundle);
        }
    }

    /**
     * Register Services
     */
    protected function registerServices(): void
    {
        // TODO: Implement registerServices
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Hooks
     */
    protected function registerHooks(): void
    {
        // TODO: Implement registerHooks
    }

    /**
     * Register Routes
     */
    protected function registerRoutes(): void
    {
        // TODO: Implement registerRoutes
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