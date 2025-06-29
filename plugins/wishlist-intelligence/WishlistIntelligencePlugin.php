<?php
namespace WishlistIntelligence;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Wishlist Intelligence Plugin
 * 
 * Smart wishlist with price alerts, availability tracking, and recommendations
 */
class WishlistIntelligencePlugin extends AbstractPlugin
{
    private $wishlistManager;
    private $alertEngine;
    private $recommendationEngine;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->schedulePriceChecks();
    }

    private function registerServices(): void
    {
        $this->wishlistManager = new Services\WishlistManager($this->api);
        $this->alertEngine = new Services\AlertEngine($this->api);
        $this->recommendationEngine = new Services\WishlistRecommendations($this->api);
    }

    private function registerHooks(): void
    {
        // Wishlist UI integration
        Hook::addFilter('product.actions', [$this, 'addWishlistButton'], 10, 2);
        Hook::addFilter('customer.dashboard', [$this, 'addWishlistSection'], 10, 2);
        Hook::addFilter('header.user_menu', [$this, 'addWishlistLink'], 10, 1);
        
        // Price and stock monitoring
        Hook::addAction('product.price_changed', [$this, 'checkPriceAlerts'], 10, 2);
        Hook::addAction('product.back_in_stock', [$this, 'notifyStockAvailability'], 10, 1);
        Hook::addAction('product.low_stock', [$this, 'notifyLowStock'], 10, 2);
        
        // Analytics and recommendations
        Hook::addAction('wishlist.item_added', [$this, 'analyzeWishlistAddition'], 10, 2);
        Hook::addAction('wishlist.item_purchased', [$this, 'trackConversion'], 10, 2);
        
        // Social features
        Hook::addFilter('wishlist.display', [$this, 'addSharingOptions'], 10, 2);
    }

    public function addWishlistButton($actions, $product): string
    {
        $customerId = $this->api->auth()->user()?->id;
        $isInWishlist = $customerId ? $this->wishlistManager->isInWishlist($customerId, $product->id) : false;
        
        $button = $this->api->view('wishlist/button', [
            'product' => $product,
            'is_in_wishlist' => $isInWishlist,
            'require_login' => !$customerId
        ]);

        return $actions . $button;
    }

    public function addWishlistSection($dashboard, $customer): string
    {
        $wishlistItems = $this->wishlistManager->getCustomerWishlist($customer->id);
        $priceAlerts = $this->alertEngine->getActiveAlerts($customer->id);
        $recommendations = $this->getConfig('smart_recommendations', true) 
            ? $this->recommendationEngine->getRecommendations($customer->id, $wishlistItems)
            : [];

        $wishlistWidget = $this->api->view('wishlist/dashboard-widget', [
            'items' => $wishlistItems,
            'price_alerts' => $priceAlerts,
            'recommendations' => $recommendations,
            'analytics' => $this->getWishlistAnalytics($customer->id)
        ]);

        return $dashboard . $wishlistWidget;
    }

    public function addWishlistLink($menu): string
    {
        $customerId = $this->api->auth()->user()?->id;
        
        if (!$customerId) {
            return $menu;
        }

        $count = $this->wishlistManager->getWishlistCount($customerId);
        
        $link = $this->api->view('wishlist/menu-link', [
            'count' => $count,
            'has_alerts' => $this->alertEngine->hasActiveAlerts($customerId)
        ]);

        return $menu . $link;
    }

    public function checkPriceAlerts($product, $priceChange): void
    {
        if (!$this->getConfig('enable_price_alerts', true)) {
            return;
        }

        // Find all wishlists containing this product
        $affectedWishlists = $this->api->database()->table('wishlist_items')
            ->where('product_id', $product->id)
            ->where('price_alert_enabled', true)
            ->get();

        foreach ($affectedWishlists as $wishlistItem) {
            $alert = $this->alertEngine->getPriceAlert($wishlistItem->id);
            
            if (!$alert) continue;

            // Check if price dropped below alert threshold
            if ($priceChange['new_price'] <= $alert->target_price) {
                $this->sendPriceDropNotification($wishlistItem, $product, $priceChange);
                
                // Record alert trigger
                $this->alertEngine->recordAlertTrigger($alert->id, [
                    'old_price' => $priceChange['old_price'],
                    'new_price' => $priceChange['new_price'],
                    'savings' => $priceChange['old_price'] - $priceChange['new_price'],
                    'percentage_drop' => (($priceChange['old_price'] - $priceChange['new_price']) / $priceChange['old_price']) * 100
                ]);
            }
        }
    }

    public function notifyStockAvailability($product): void
    {
        if (!$this->getConfig('enable_stock_alerts', true)) {
            return;
        }

        // Find customers waiting for this product
        $waitingCustomers = $this->api->database()->table('wishlist_items')
            ->where('product_id', $product->id)
            ->where('stock_alert_enabled', true)
            ->join('wishlists', 'wishlist_items.wishlist_id', '=', 'wishlists.id')
            ->select('wishlists.customer_id', 'wishlist_items.*')
            ->get();

        foreach ($waitingCustomers as $item) {
            $customer = $this->api->service('CustomerRepository')->find($item->customer_id);
            
            $this->api->notification()->send($customer->id, [
                'type' => 'stock_available',
                'title' => '🎉 Back in Stock!',
                'message' => "{$product->name} is now available",
                'action' => [
                    'label' => 'Add to Cart',
                    'url' => "/products/{$product->slug}"
                ],
                'email' => true,
                'push' => true
            ]);

            // Update analytics
            $this->analyticsTracker->recordStockAlert($product->id, $customer->id);
        }
    }

    public function notifyLowStock($product, $quantity): void
    {
        // Notify customers who have this in wishlist about low stock
        $interestedCustomers = $this->api->database()->table('wishlist_items')
            ->where('product_id', $product->id)
            ->join('wishlists', 'wishlist_items.wishlist_id', '=', 'wishlists.id')
            ->select('wishlists.customer_id')
            ->distinct()
            ->get();

        foreach ($interestedCustomers as $item) {
            $this->api->notification()->send($item->customer_id, [
                'type' => 'low_stock',
                'title' => '⚠️ Low Stock Alert',
                'message' => "Only {$quantity} left of {$product->name} in your wishlist",
                'priority' => 'high'
            ]);
        }
    }

    public function analyzeWishlistAddition($customerId, $productId): void
    {
        // Track wishlist patterns
        $this->analyticsTracker->recordWishlistAdd($customerId, $productId, [
            'source' => $this->api->request()->header('referer'),
            'device' => $this->api->request()->device(),
            'session_duration' => $this->api->session()->duration()
        ]);

        // Update recommendation model
        $this->recommendationEngine->updateCustomerPreferences($customerId, $productId);

        // Check for wishlist milestones
        $wishlistSize = $this->wishlistManager->getWishlistCount($customerId);
        
        if ($wishlistSize % 10 === 0 && $wishlistSize > 0) {
            $this->sendMilestoneNotification($customerId, $wishlistSize);
        }
    }

    public function trackConversion($customerId, $productId): void
    {
        $wishlistItem = $this->wishlistManager->getWishlistItem($customerId, $productId);
        
        if ($wishlistItem) {
            $daysInWishlist = (time() - strtotime($wishlistItem->created_at)) / 86400;
            
            $this->analyticsTracker->recordConversion([
                'customer_id' => $customerId,
                'product_id' => $productId,
                'days_in_wishlist' => $daysInWishlist,
                'had_price_alert' => $wishlistItem->price_alert_enabled,
                'price_at_add' => $wishlistItem->price_when_added,
                'price_at_purchase' => $this->api->service('ProductRepository')->find($productId)->price
            ]);
        }
    }

    public function addSharingOptions($wishlistDisplay, $wishlist): string
    {
        if (!$wishlist->is_public) {
            return $wishlistDisplay;
        }

        $sharingWidget = $this->api->view('wishlist/sharing-options', [
            'wishlist' => $wishlist,
            'share_url' => $this->generateShareUrl($wishlist),
            'social_platforms' => ['facebook', 'twitter', 'pinterest', 'email']
        ]);

        return $wishlistDisplay . $sharingWidget;
    }

    private function sendPriceDropNotification($wishlistItem, $product, $priceChange): void
    {
        $customer = $this->api->service('CustomerRepository')->find($wishlistItem->customer_id);
        $savings = $priceChange['old_price'] - $priceChange['new_price'];
        $percentOff = round(($savings / $priceChange['old_price']) * 100);

        $this->api->notification()->send($customer->id, [
            'type' => 'price_drop',
            'title' => "💰 Price Drop Alert!",
            'message' => "{$product->name} is now {$percentOff}% off - Save \${$savings}!",
            'action' => [
                'label' => 'Shop Now',
                'url' => "/products/{$product->slug}"
            ],
            'email' => true,
            'push' => true,
            'priority' => 'high'
        ]);

        // Add to special price drop collection
        $this->wishlistManager->addToPriceDrops($customer->id, $product->id, [
            'original_price' => $priceChange['old_price'],
            'new_price' => $priceChange['new_price'],
            'savings' => $savings,
            'percent_off' => $percentOff
        ]);
    }

    private function getWishlistAnalytics($customerId): array
    {
        return [
            'total_items' => $this->wishlistManager->getWishlistCount($customerId),
            'total_value' => $this->wishlistManager->getWishlistValue($customerId),
            'items_purchased' => $this->analyticsTracker->getPurchasedCount($customerId),
            'average_days_to_purchase' => $this->analyticsTracker->getAverageDaysToPurchase($customerId),
            'price_drops_caught' => $this->alertEngine->getTriggeredAlertsCount($customerId)
        ];
    }

    private function sendMilestoneNotification($customerId, $milestone): void
    {
        $rewards = [
            10 => ['discount' => 5, 'message' => 'Your wishlist is growing! Here\'s 5% off your next purchase'],
            25 => ['discount' => 10, 'message' => 'Wishlist expert! Enjoy 10% off'],
            50 => ['discount' => 15, 'message' => 'Golden wishlist! Take 15% off any item from your list']
        ];

        if (isset($rewards[$milestone])) {
            $reward = $rewards[$milestone];
            
            // Create discount code
            $code = $this->createMilestoneDiscount($customerId, $reward['discount']);
            
            $this->api->notification()->send($customerId, [
                'type' => 'wishlist_milestone',
                'title' => '🎊 Wishlist Milestone!',
                'message' => $reward['message'],
                'data' => ['discount_code' => $code]
            ]);
        }
    }

    private function generateShareUrl($wishlist): string
    {
        $shareToken = $this->wishlistManager->generateShareToken($wishlist->id);
        return $this->api->url("/wishlist/shared/{$shareToken}");
    }

    private function createMilestoneDiscount($customerId, $percentage): string
    {
        $code = 'WISH' . strtoupper(substr(md5($customerId . time()), 0, 6));
        
        $this->api->service('DiscountService')->create([
            'code' => $code,
            'type' => 'percentage',
            'value' => $percentage,
            'usage_limit' => 1,
            'customer_limit' => $customerId,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
        ]);

        return $code;
    }

    private function schedulePriceChecks(): void
    {
        // Check for price changes every hour
        $this->api->scheduler()->addJob('wishlist_price_check', '0 * * * *', function() {
            $this->alertEngine->checkAllPriceAlerts();
        });

        // Send wishlist reminders weekly
        $this->api->scheduler()->addJob('wishlist_reminders', '0 10 * * 1', function() {
            $this->wishlistManager->sendWeeklyReminders();
        });

        // Clean up old wishlist items monthly
        $this->api->scheduler()->addJob('wishlist_cleanup', '0 2 1 * *', function() {
            $this->wishlistManager->cleanupOldItems(365); // Items older than 1 year
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->get('/wishlist', 'Controllers\WishlistController@index');
        $this->api->router()->post('/wishlist/add', 'Controllers\WishlistController@addItem');
        $this->api->router()->delete('/wishlist/{id}', 'Controllers\WishlistController@removeItem');
        $this->api->router()->post('/wishlist/price-alert', 'Controllers\WishlistController@setPriceAlert');
        $this->api->router()->get('/wishlist/shared/{token}', 'Controllers\WishlistController@viewShared');
        $this->api->router()->post('/wishlist/import', 'Controllers\WishlistController@importFromCart');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultAlertTemplates();
    }

    private function createDefaultAlertTemplates(): void
    {
        $templates = [
            [
                'type' => 'price_drop',
                'subject' => '💰 Price Drop on Your Wishlist Item!',
                'template' => 'wishlist/emails/price-drop'
            ],
            [
                'type' => 'back_in_stock',
                'subject' => '🎉 Your Wishlist Item is Back!',
                'template' => 'wishlist/emails/back-in-stock'
            ],
            [
                'type' => 'low_stock',
                'subject' => '⚠️ Low Stock Alert',
                'template' => 'wishlist/emails/low-stock'
            ]
        ];

        foreach ($templates as $template) {
            $this->api->database()->table('notification_templates')->insert($template);
        }
    }
}