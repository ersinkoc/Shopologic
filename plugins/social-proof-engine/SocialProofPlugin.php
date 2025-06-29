<?php
namespace SocialProof;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Social Proof Engine Plugin
 * 
 * Real-time social proof notifications and FOMO triggers
 */
class SocialProofPlugin extends AbstractPlugin
{
    private $notificationEngine;
    private $metricsCollector;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->initializeRealtimeTracking();
    }

    private function registerServices(): void
    {
        $this->notificationEngine = new Services\NotificationEngine($this->api);
        $this->metricsCollector = new Services\MetricsCollector($this->api);
    }

    private function registerHooks(): void
    {
        // Track social proof events
        Hook::addAction('order.completed', [$this, 'trackPurchase'], 10, 1);
        Hook::addAction('product.viewed', [$this, 'trackProductView'], 10, 1);
        Hook::addAction('cart.item_added', [$this, 'trackCartAdd'], 10, 1);
        Hook::addAction('customer.registered', [$this, 'trackRegistration'], 10, 1);
        
        // Display social proof elements
        Hook::addAction('frontend.footer', [$this, 'addNotificationWidget'], 10);
        Hook::addFilter('product.sidebar', [$this, 'addSocialProofStats'], 10, 2);
        Hook::addFilter('cart.summary', [$this, 'addUrgencyIndicators'], 10, 1);
    }

    public function trackPurchase($order): void
    {
        foreach ($order->items as $item) {
            $this->metricsCollector->recordEvent('purchase', [
                'product_id' => $item->product_id,
                'customer_name' => $this->anonymizeName($order->customer_name),
                'location' => $order->billing_city . ', ' . $order->billing_state,
                'amount' => $item->price * $item->quantity,
                'timestamp' => time()
            ]);
        }
        
        // Trigger real-time notifications
        $this->notificationEngine->broadcastPurchase($order);
    }

    public function trackProductView($product): void
    {
        $this->metricsCollector->recordEvent('view', [
            'product_id' => $product->id,
            'session_id' => session_id(),
            'timestamp' => time()
        ]);
        
        // Update real-time viewer count
        $this->updateViewerCount($product->id);
    }

    public function trackCartAdd($cartItem): void
    {
        $this->metricsCollector->recordEvent('cart_add', [
            'product_id' => $cartItem->product_id,
            'quantity' => $cartItem->quantity,
            'timestamp' => time()
        ]);
    }

    public function trackRegistration($customer): void
    {
        $this->metricsCollector->recordEvent('registration', [
            'customer_name' => $this->anonymizeName($customer->name),
            'location' => $customer->city . ', ' . $customer->state,
            'timestamp' => time()
        ]);
    }

    public function addNotificationWidget(): void
    {
        if (!$this->getConfig('enable_purchase_notifications', true)) {
            return;
        }

        echo $this->api->view('social-proof/notification-widget', [
            'frequency' => $this->getConfig('notification_frequency', 15),
            'max_notifications' => $this->getConfig('max_notifications_per_page', 3),
            'recent_purchases' => $this->getRecentPurchases(),
            'recent_signups' => $this->getRecentSignups()
        ]);
    }

    public function addSocialProofStats($sidebar, $product): string
    {
        $stats = $this->generateProductStats($product->id);
        
        $proofWidget = $this->api->view('social-proof/product-stats', [
            'product' => $product,
            'stats' => $stats,
            'show_visitors' => $this->getConfig('enable_visitor_count', true),
            'show_low_stock' => $this->getConfig('enable_low_stock_alerts', true)
        ]);
        
        return $sidebar . $proofWidget;
    }

    public function addUrgencyIndicators($summary): string
    {
        $urgencyElements = [];
        
        // Low stock alerts
        if ($this->getConfig('enable_low_stock_alerts', true)) {
            $lowStockItems = $this->findLowStockItems();
            if (!empty($lowStockItems)) {
                $urgencyElements[] = $this->api->view('social-proof/low-stock-alert', [
                    'items' => $lowStockItems
                ]);
            }
        }
        
        // Recent activity
        $recentActivity = $this->getRecentCartActivity();
        if (!empty($recentActivity)) {
            $urgencyElements[] = $this->api->view('social-proof/recent-activity', [
                'activity' => $recentActivity
            ]);
        }
        
        return $summary . implode('', $urgencyElements);
    }

    private function generateProductStats($productId): array
    {
        $cacheKey = "social_proof_stats_{$productId}";
        
        return $this->api->cache()->remember($cacheKey, 300, function() use ($productId) {
            $stats = [];
            
            // Recent purchases (last 24 hours)
            $recentPurchases = $this->api->database()->table('social_proof_events')
                ->where('event_type', 'purchase')
                ->where('product_id', $productId)
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours')))
                ->count();
                
            if ($recentPurchases > 0) {
                $stats['recent_purchases'] = $recentPurchases;
            }
            
            // Current viewers (last 5 minutes)
            $currentViewers = $this->api->database()->table('social_proof_events')
                ->where('event_type', 'view')
                ->where('product_id', $productId)
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-5 minutes')))
                ->distinct('session_id')
                ->count();
                
            if ($currentViewers > 1) {
                $stats['current_viewers'] = $currentViewers;
            }
            
            // Total purchases this week
            $weeklyPurchases = $this->api->database()->table('social_proof_events')
                ->where('event_type', 'purchase')
                ->where('product_id', $productId)
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
                ->count();
                
            if ($weeklyPurchases > 0) {
                $stats['weekly_purchases'] = $weeklyPurchases;
            }
            
            // Stock level urgency
            $product = $this->api->service('ProductRepository')->find($productId);
            if ($product && $product->stock_quantity <= 10 && $product->stock_quantity > 0) {
                $stats['low_stock'] = $product->stock_quantity;
            }
            
            return $stats;
        });
    }

    private function getRecentPurchases(): array
    {
        return $this->api->database()->table('social_proof_events')
            ->where('event_type', 'purchase')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-2 hours')))
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    private function getRecentSignups(): array
    {
        return $this->api->database()->table('social_proof_events')
            ->where('event_type', 'registration')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function findLowStockItems(): array
    {
        $cartItems = $this->api->service('CartService')->getItems();
        $lowStockItems = [];
        
        foreach ($cartItems as $item) {
            $product = $this->api->service('ProductRepository')->find($item->product_id);
            if ($product && $product->stock_quantity <= 5) {
                $lowStockItems[] = [
                    'name' => $product->name,
                    'stock' => $product->stock_quantity,
                    'quantity_in_cart' => $item->quantity
                ];
            }
        }
        
        return $lowStockItems;
    }

    private function getRecentCartActivity(): array
    {
        return $this->api->database()->table('social_proof_events')
            ->where('event_type', 'cart_add')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 minutes')))
            ->join('products', 'social_proof_events.product_id', '=', 'products.id')
            ->select('products.name', 'social_proof_events.created_at')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    private function updateViewerCount($productId): void
    {
        $key = "viewers_{$productId}";
        $viewers = $this->api->cache()->get($key, []);
        
        // Add current session
        $sessionId = session_id();
        $viewers[$sessionId] = time();
        
        // Remove old sessions (older than 5 minutes)
        $cutoff = time() - 300;
        $viewers = array_filter($viewers, function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
        
        $this->api->cache()->put($key, $viewers, 600);
        
        // Broadcast viewer count update
        $this->notificationEngine->updateViewerCount($productId, count($viewers));
    }

    private function anonymizeName($name): string
    {
        if (!$this->getConfig('anonymize_customer_names', true)) {
            return $name;
        }
        
        $parts = explode(' ', $name);
        $firstName = $parts[0];
        $lastName = isset($parts[1]) ? substr($parts[1], 0, 1) . '.' : '';
        
        return $firstName . ' ' . $lastName;
    }

    private function initializeRealtimeTracking(): void
    {
        // Set up cleanup job for old events
        $this->api->scheduler()->addJob('cleanup_social_proof', '0 2 * * *', function() {
            $this->api->database()->table('social_proof_events')
                ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-30 days')))
                ->delete();
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->get('/social-proof/notifications', 'Controllers\SocialProofController@getNotifications');
        $this->api->router()->post('/social-proof/track-view', 'Controllers\SocialProofController@trackView');
        $this->api->router()->get('/social-proof/stats/{product_id}', 'Controllers\SocialProofController@getStats');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createSampleData();
    }

    private function createSampleData(): void
    {
        // Create some initial social proof events for demonstration
        $sampleEvents = [
            ['event_type' => 'purchase', 'data' => '{"customer_name": "John D.", "location": "New York, NY"}'],
            ['event_type' => 'registration', 'data' => '{"customer_name": "Sarah M.", "location": "Los Angeles, CA"}'],
            ['event_type' => 'purchase', 'data' => '{"customer_name": "Mike R.", "location": "Chicago, IL"}']
        ];

        foreach ($sampleEvents as $event) {
            $event['created_at'] = date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' minutes'));
            $this->api->database()->table('social_proof_events')->insert($event);
        }
    }
}