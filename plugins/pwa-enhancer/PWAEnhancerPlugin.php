<?php
namespace PWAEnhancer;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * PWA Enhancer Plugin
 * 
 * Progressive Web App features with offline capability and push notifications
 */
class PWAEnhancerPlugin extends AbstractPlugin
{
    private $manifestGenerator;
    private $serviceWorkerManager;
    private $pushNotificationService;
    private $offlineCacheManager;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->initializePWAFeatures();
    }

    private function registerServices(): void
    {
        $this->manifestGenerator = new Services\ManifestGenerator($this->api);
        $this->serviceWorkerManager = new Services\ServiceWorkerManager($this->api);
        $this->pushNotificationService = new Services\PushNotificationService($this->api);
        $this->offlineCacheManager = new Services\OfflineCacheManager($this->api);
    }

    private function registerHooks(): void
    {
        // PWA setup
        Hook::addAction('frontend.head', [$this, 'addPWAMeta'], 5);
        Hook::addAction('frontend.head', [$this, 'registerServiceWorker'], 10);
        Hook::addFilter('frontend.manifest', [$this, 'generateManifest'], 10, 1);
        
        // Service worker events
        Hook::addAction('service_worker.install', [$this, 'handleServiceWorkerInstall'], 10, 1);
        Hook::addAction('service_worker.activate', [$this, 'handleServiceWorkerActivate'], 10, 1);
        Hook::addAction('service_worker.fetch', [$this, 'handleServiceWorkerFetch'], 10, 1);
        
        // Push notifications
        Hook::addAction('order.completed', [$this, 'sendOrderNotification'], 10, 1);
        Hook::addAction('product.back_in_stock', [$this, 'sendStockNotification'], 10, 1);
        Hook::addAction('cart.abandoned', [$this, 'sendAbandonedCartNotification'], 10, 2);
        
        // Offline functionality
        Hook::addFilter('product.list', [$this, 'markOfflineAvailable'], 10, 1);
        Hook::addAction('page.rendered', [$this, 'cachePageForOffline'], 10, 1);
        
        // Installation prompts
        Hook::addFilter('frontend.footer', [$this, 'addInstallPrompt'], 10, 1);
    }

    public function addPWAMeta(): void
    {
        $themeColor = $this->getConfig('theme_color', '#007cba');
        $appName = $this->getConfig('app_name', 'Shopologic Store');
        
        echo $this->api->view('pwa/meta-tags', [
            'manifest_url' => '/manifest.json',
            'theme_color' => $themeColor,
            'app_name' => $appName,
            'icons' => $this->getAppIcons(),
            'viewport' => 'width=device-width, initial-scale=1, viewport-fit=cover'
        ]);
    }

    public function registerServiceWorker(): void
    {
        if (!$this->getConfig('enable_offline_mode', true)) {
            return;
        }

        echo $this->api->view('pwa/service-worker-registration', [
            'service_worker_url' => '/service-worker.js',
            'scope' => '/',
            'update_check_interval' => 3600000, // 1 hour
            'enable_background_sync' => true,
            'enable_push' => $this->getConfig('enable_push_notifications', true)
        ]);
    }

    public function generateManifest($existingManifest): array
    {
        $manifest = [
            'name' => $this->getConfig('app_name', 'Shopologic Store'),
            'short_name' => $this->getConfig('app_short_name', 'Shop'),
            'description' => $this->getConfig('app_description', 'Your favorite online store'),
            'start_url' => '/?utm_source=pwa',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => $this->getConfig('theme_color', '#007cba'),
            'orientation' => 'portrait-primary',
            'icons' => $this->generateIconSet(),
            'categories' => ['shopping'],
            'screenshots' => $this->getAppScreenshots(),
            'related_applications' => [],
            'prefer_related_applications' => false,
            'shortcuts' => $this->generateShortcuts(),
            'features' => [
                'payment',
                'credentials',
                'notifications'
            ]
        ];

        // Merge with existing manifest
        return array_merge($existingManifest, $manifest);
    }

    public function handleServiceWorkerInstall($event): void
    {
        $cacheName = 'shopologic-v' . $this->getVersion();
        $urlsToCache = $this->getEssentialUrls();
        
        // Pre-cache essential resources
        $this->serviceWorkerManager->precacheResources($cacheName, $urlsToCache);
        
        // Cache product images for offline browsing
        if ($this->getConfig('cache_product_images', true)) {
            $popularProducts = $this->getPopularProducts(20);
            $this->serviceWorkerManager->cacheProductImages($popularProducts);
        }
    }

    public function handleServiceWorkerActivate($event): void
    {
        // Clean up old caches
        $currentCache = 'shopologic-v' . $this->getVersion();
        $this->serviceWorkerManager->cleanOldCaches($currentCache);
        
        // Update offline page
        $this->updateOfflinePage();
    }

    public function handleServiceWorkerFetch($request): ?array
    {
        $url = $request['url'];
        $method = $request['method'];
        
        // Network first for API calls
        if (strpos($url, '/api/') !== false) {
            return $this->handleApiRequest($request);
        }
        
        // Cache first for assets
        if ($this->isAssetRequest($url)) {
            return $this->handleAssetRequest($request);
        }
        
        // Network first with fallback for pages
        return $this->handlePageRequest($request);
    }

    public function sendOrderNotification($order): void
    {
        if (!$this->getConfig('enable_push_notifications', true)) {
            return;
        }

        $customer = $this->api->service('CustomerRepository')->find($order->customer_id);
        $subscription = $this->pushNotificationService->getSubscription($customer->id);
        
        if (!$subscription) {
            return;
        }

        $notification = [
            'title' => '🎉 Order Confirmed!',
            'body' => "Your order #{$order->id} has been confirmed and is being processed.",
            'icon' => '/images/icons/icon-192.png',
            'badge' => '/images/icons/badge-72.png',
            'tag' => 'order-' . $order->id,
            'data' => [
                'url' => '/orders/' . $order->id,
                'order_id' => $order->id
            ],
            'actions' => [
                ['action' => 'track', 'title' => 'Track Order'],
                ['action' => 'view', 'title' => 'View Details']
            ]
        ];

        $this->pushNotificationService->send($subscription, $notification);
    }

    public function sendStockNotification($product): void
    {
        if (!$this->getConfig('enable_push_notifications', true)) {
            return;
        }

        // Find customers who have this product in wishlist
        $interestedCustomers = $this->api->database()->table('wishlist_items')
            ->where('product_id', $product->id)
            ->where('stock_alert_enabled', true)
            ->join('wishlists', 'wishlist_items.wishlist_id', '=', 'wishlists.id')
            ->select('wishlists.customer_id')
            ->get();

        foreach ($interestedCustomers as $item) {
            $subscription = $this->pushNotificationService->getSubscription($item->customer_id);
            
            if ($subscription) {
                $notification = [
                    'title' => '📦 Back in Stock!',
                    'body' => "{$product->name} is now available",
                    'icon' => $product->thumbnail,
                    'tag' => 'stock-' . $product->id,
                    'data' => ['url' => '/products/' . $product->slug]
                ];
                
                $this->pushNotificationService->send($subscription, $notification);
            }
        }
    }

    public function sendAbandonedCartNotification($customerId, $cart): void
    {
        if (!$this->getConfig('enable_push_notifications', true)) {
            return;
        }

        // Schedule notification for 2 hours later
        $this->api->scheduler()->schedule('+2 hours', function() use ($customerId, $cart) {
            $subscription = $this->pushNotificationService->getSubscription($customerId);
            
            if (!$subscription) {
                return;
            }

            $notification = [
                'title' => '🛒 You left items in your cart',
                'body' => 'Complete your purchase and save 10% with code RETURN10',
                'icon' => '/images/icons/icon-192.png',
                'tag' => 'abandoned-cart',
                'data' => ['url' => '/cart'],
                'actions' => [
                    ['action' => 'complete', 'title' => 'Complete Purchase'],
                    ['action' => 'dismiss', 'title' => 'Not Interested']
                ]
            ];

            $this->pushNotificationService->send($subscription, $notification);
        });
    }

    public function markOfflineAvailable($products): array
    {
        $cachedProductIds = $this->offlineCacheManager->getCachedProductIds();
        
        foreach ($products as &$product) {
            $product['offline_available'] = in_array($product['id'], $cachedProductIds);
        }
        
        return $products;
    }

    public function cachePageForOffline($page): void
    {
        if (!$this->shouldCachePage($page)) {
            return;
        }

        $this->offlineCacheManager->cachePage([
            'url' => $page['url'],
            'html' => $page['html'],
            'title' => $page['title'],
            'meta' => $page['meta'],
            'timestamp' => time()
        ]);
    }

    public function addInstallPrompt($footer): string
    {
        if (!$this->shouldShowInstallPrompt()) {
            return $footer;
        }

        $prompt = $this->api->view('pwa/install-prompt', [
            'app_name' => $this->getConfig('app_name', 'Shopologic Store'),
            'benefits' => [
                'Work offline',
                'Faster loading',
                'Push notifications',
                'Add to home screen'
            ]
        ]);

        return $footer . $prompt;
    }

    private function generateIconSet(): array
    {
        $sizes = [72, 96, 128, 144, 152, 192, 384, 512];
        $icons = [];
        
        foreach ($sizes as $size) {
            $icons[] = [
                'src' => "/images/icons/icon-{$size}.png",
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ];
        }
        
        return $icons;
    }

    private function generateShortcuts(): array
    {
        return [
            [
                'name' => 'New Arrivals',
                'short_name' => 'New',
                'description' => 'View latest products',
                'url' => '/products/new?utm_source=pwa-shortcut',
                'icons' => [['src' => '/images/icons/new-96.png', 'sizes' => '96x96']]
            ],
            [
                'name' => 'Categories',
                'short_name' => 'Shop',
                'description' => 'Browse categories',
                'url' => '/categories?utm_source=pwa-shortcut',
                'icons' => [['src' => '/images/icons/categories-96.png', 'sizes' => '96x96']]
            ],
            [
                'name' => 'My Orders',
                'short_name' => 'Orders',
                'description' => 'Track your orders',
                'url' => '/account/orders?utm_source=pwa-shortcut',
                'icons' => [['src' => '/images/icons/orders-96.png', 'sizes' => '96x96']]
            ]
        ];
    }

    private function getEssentialUrls(): array
    {
        return [
            '/',
            '/offline',
            '/cart',
            '/categories',
            '/css/app.css',
            '/js/app.js',
            '/images/logo.png',
            '/images/icons/icon-192.png'
        ];
    }

    private function handleApiRequest($request): array
    {
        return [
            'strategy' => 'network-first',
            'cache_name' => 'api-cache',
            'cache_duration' => 300, // 5 minutes
            'fallback' => [
                'status' => 503,
                'body' => json_encode(['error' => 'Offline'])
            ]
        ];
    }

    private function handleAssetRequest($request): array
    {
        return [
            'strategy' => 'cache-first',
            'cache_name' => 'assets-cache',
            'cache_duration' => 86400 * 30, // 30 days
            'update_in_background' => true
        ];
    }

    private function handlePageRequest($request): array
    {
        return [
            'strategy' => 'network-first',
            'cache_name' => 'pages-cache',
            'cache_duration' => 3600, // 1 hour
            'fallback' => '/offline'
        ];
    }

    private function isAssetRequest($url): bool
    {
        $extensions = ['js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2'];
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        
        return in_array($extension, $extensions);
    }

    private function shouldCachePage($page): bool
    {
        $cacheablePages = [
            '/',
            '/categories',
            '/products',
            '/about',
            '/contact'
        ];
        
        return in_array($page['url'], $cacheablePages) || 
               strpos($page['url'], '/products/') === 0 ||
               strpos($page['url'], '/categories/') === 0;
    }

    private function shouldShowInstallPrompt(): bool
    {
        // Don't show if already installed
        if ($this->api->request()->header('X-Requested-With') === 'PWA') {
            return false;
        }
        
        // Don't show if dismissed recently
        $dismissed = $this->api->session()->get('pwa_prompt_dismissed');
        if ($dismissed && (time() - $dismissed) < 604800) { // 7 days
            return false;
        }
        
        // Show after 3 page views
        $pageViews = $this->api->session()->get('page_views', 0);
        return $pageViews >= 3;
    }

    private function updateOfflinePage(): void
    {
        $offlineHtml = $this->api->view('pwa/offline-page', [
            'app_name' => $this->getConfig('app_name', 'Shopologic Store'),
            'cached_products' => $this->offlineCacheManager->getCachedProducts(),
            'cached_categories' => $this->offlineCacheManager->getCachedCategories()
        ]);
        
        $this->offlineCacheManager->updateOfflinePage($offlineHtml);
    }

    private function getPopularProducts($limit): array
    {
        return $this->api->database()->table('products')
            ->join('order_items', 'products.id', '=', 'order_items.product_id')
            ->select('products.*', $this->api->database()->raw('COUNT(order_items.id) as order_count'))
            ->where('products.status', 'active')
            ->groupBy('products.id')
            ->orderBy('order_count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function initializePWAFeatures(): void
    {
        // Generate service worker file
        $this->api->scheduler()->addJob('generate_service_worker', '0 */6 * * *', function() {
            $this->serviceWorkerManager->generateServiceWorker();
        });
        
        // Clean expired offline cache
        $this->api->scheduler()->addJob('clean_offline_cache', '0 3 * * *', function() {
            $this->offlineCacheManager->cleanExpiredCache();
        });
        
        // Update app shell
        $this->api->scheduler()->addJob('update_app_shell', '0 4 * * 0', function() {
            $this->serviceWorkerManager->updateAppShell();
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->get('/manifest.json', 'Controllers\PWAController@manifest');
        $this->api->router()->get('/service-worker.js', 'Controllers\PWAController@serviceWorker');
        $this->api->router()->post('/pwa/subscribe', 'Controllers\PWAController@subscribe');
        $this->api->router()->post('/pwa/notification', 'Controllers\PWAController@sendNotification');
        $this->api->router()->get('/pwa/offline-data', 'Controllers\PWAController@getOfflineData');
        $this->api->router()->get('/offline', 'Controllers\PWAController@offlinePage');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->generatePWAAssets();
        $this->createOfflinePage();
    }

    private function generatePWAAssets(): void
    {
        // Generate app icons
        $this->manifestGenerator->generateIcons();
        
        // Generate splash screens
        $this->manifestGenerator->generateSplashScreens();
        
        // Create initial service worker
        $this->serviceWorkerManager->generateServiceWorker();
    }

    private function createOfflinePage(): void
    {
        $offlineContent = $this->api->view('pwa/offline-template', [
            'app_name' => $this->getConfig('app_name', 'Shopologic Store')
        ]);
        
        $this->api->filesystem()->put('public/offline.html', $offlineContent);
    }
}