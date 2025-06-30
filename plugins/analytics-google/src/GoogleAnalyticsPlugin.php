<?php
declare(strict_types=1);

namespace Shopologic\Plugins\AnalyticsGoogle;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Hook\HookSystem;
use GoogleAnalytics\Services\AnalyticsService;
use GoogleAnalytics\Services\TrackingService;
use GoogleAnalytics\Services\ConversionService;

/**
 * Google Analytics Integration Plugin
 * 
 * Comprehensive Google Analytics 4 (GA4) and Universal Analytics integration
 * with enhanced e-commerce tracking, conversion tracking, and custom dimensions
 */
class GoogleAnalyticsPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'analytics-google';
    protected string $version = '1.0.0';
    
    /**
     * Plugin installation
     */
    public function install(): bool
    {
        $this->runMigrations();
        $this->setDefaultConfig();
        return true;
    }
    
    /**
     * Plugin activation
     */
    public function activate(): bool
    {
        $this->initializeTracking();
        $this->scheduleOfflineSync();
        return true;
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): bool
    {
        $this->pauseTracking();
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
    }
    
    /**
     * Register plugin services
     */
    protected function registerServices(): void
    {
        // Analytics service
        $this->container->singleton(AnalyticsService::class, function ($container) {
            return new AnalyticsService(
                $this->getConfig('ga4_measurement_id'),
                $this->getConfig('universal_tracking_id'),
                $this->getConfig()
            );
        });
        
        // Tracking service
        $this->container->singleton(TrackingService::class, function ($container) {
            return new TrackingService(
                $container->get(AnalyticsService::class),
                $this->getConfig('enable_enhanced_ecommerce', true)
            );
        });
        
        // Conversion service
        $this->container->singleton(ConversionService::class, function ($container) {
            return new ConversionService(
                $container->get('db'),
                $this->getConfig('conversion_events', ['purchase', 'sign_up'])
            );
        });
    }
    
    /**
     * Register plugin hooks
     */
    protected function registerHooks(): void
    {
        // Inject tracking code
        HookSystem::addAction('page.head', [$this, 'injectTrackingCode'], 5);
        
        // E-commerce tracking
        HookSystem::addAction('checkout.completed', [$this, 'trackPurchase'], 10);
        HookSystem::addAction('cart.item_added', [$this, 'trackAddToCart'], 10);
        HookSystem::addAction('product.viewed', [$this, 'trackProductView'], 10);
        
        // User events
        HookSystem::addAction('user.registered', [$this, 'trackSignUp'], 10);
        HookSystem::addAction('user.logged_in', [$this, 'trackLogin'], 10);
        
        // Search tracking
        HookSystem::addAction('search.performed', [$this, 'trackSearch'], 10);
        
        // Custom event tracking
        HookSystem::addAction('analytics.track_event', [$this, 'trackCustomEvent'], 10);
    }
    
    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        $this->registerRoute('POST', '/api/v1/analytics/event', 
            'GoogleAnalytics\\Controllers\\AnalyticsController@trackEvent');
            
        $this->registerRoute('GET', '/api/v1/analytics/reports', 
            'GoogleAnalytics\\Controllers\\AnalyticsController@getReports');
    }
    
    /**
     * Register cron jobs
     */
    protected function registerCronJobs(): void
    {
        // Sync offline conversions daily at 2 AM
        $this->scheduleJob('0 2 * * *', [$this, 'syncOfflineConversions']);
    }
    
    /**
     * Inject Google Analytics tracking code
     */
    public function injectTrackingCode(): void
    {
        if (!$this->isConfigured()) {
            return;
        }
        
        $trackingService = $this->container->get(TrackingService::class);
        echo $trackingService->getTrackingCode();
    }
    
    /**
     * Track purchase completion
     */
    public function trackPurchase(array $data): void
    {
        $order = $data['order'];
        $trackingService = $this->container->get(TrackingService::class);
        
        $trackingService->trackPurchase($order);
        
        // Store conversion for offline sync
        $this->storeConversion('purchase', [
            'transaction_id' => $order->id,
            'value' => $order->total,
            'currency' => $order->currency ?? $this->getConfig('currency', 'USD')
        ]);
    }
    
    /**
     * Track add to cart
     */
    public function trackAddToCart(array $data): void
    {
        $cartItem = $data['cart_item'];
        $trackingService = $this->container->get(TrackingService::class);
        
        $trackingService->trackAddToCart($cartItem);
    }
    
    /**
     * Track product view
     */
    public function trackProductView(array $data): void
    {
        $product = $data['product'];
        $trackingService = $this->container->get(TrackingService::class);
        
        $trackingService->trackProductView($product);
    }
    
    /**
     * Track user sign up
     */
    public function trackSignUp(array $data): void
    {
        $user = $data['user'];
        $trackingService = $this->container->get(TrackingService::class);
        
        $trackingService->trackSignUp($user);
        
        // Store conversion if enabled
        if (in_array('sign_up', $this->getConfig('conversion_events', []))) {
            $this->storeConversion('sign_up', [
                'user_id' => $user->id,
                'method' => $data['method'] ?? 'direct'
            ]);
        }
    }
    
    /**
     * Track user login
     */
    public function trackLogin(array $data): void
    {
        $user = $data['user'];
        $trackingService = $this->container->get(TrackingService::class);
        
        $trackingService->trackLogin($user);
    }
    
    /**
     * Track search
     */
    public function trackSearch(array $data): void
    {
        $searchQuery = $data['query'];
        $results = $data['results'] ?? [];
        $trackingService = $this->container->get(TrackingService::class);
        
        $trackingService->trackSearch($searchQuery, count($results));
    }
    
    /**
     * Track custom event
     */
    public function trackCustomEvent(array $data): void
    {
        $trackingService = $this->container->get(TrackingService::class);
        $trackingService->trackCustomEvent($data);
    }
    
    /**
     * Sync offline conversions
     */
    public function syncOfflineConversions(): void
    {
        if (!$this->getConfig('server_side_tracking', false)) {
            return;
        }
        
        $conversionService = $this->container->get(ConversionService::class);
        $synced = $conversionService->syncOfflineConversions();
        
        $this->logger->info('Synced offline conversions', ['count' => $synced]);
    }
    
    /**
     * Check if plugin is properly configured
     */
    protected function isConfigured(): bool
    {
        return !empty($this->getConfig('ga4_measurement_id'));
    }
    
    /**
     * Store conversion for offline sync
     */
    protected function storeConversion(string $eventName, array $data): void
    {
        $conversionService = $this->container->get(ConversionService::class);
        $conversionService->storeConversion($eventName, $data);
    }
    
    /**
     * Initialize tracking
     */
    protected function initializeTracking(): void
    {
        if ($this->isConfigured()) {
            $trackingService = $this->container->get(TrackingService::class);
            $trackingService->initialize();
        }
    }
    
    /**
     * Pause tracking
     */
    protected function pauseTracking(): void
    {
        // Clear any queued events
        $this->api->cache()->forget('analytics_queue');
    }
    
    /**
     * Schedule offline sync
     */
    protected function scheduleOfflineSync(): void
    {
        if ($this->getConfig('server_side_tracking', false)) {
            // Enable cron job for offline sync
            $this->enableCronJob('syncOfflineConversions');
        }
    }
    
    /**
     * Run database migrations
     */
    protected function runMigrations(): void
    {
        $migrations = [
            'create_google_analytics_events_table.php',
            'create_google_analytics_conversions_table.php'
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
            'enable_enhanced_ecommerce' => true,
            'enable_user_id_tracking' => true,
            'enable_demographics' => false,
            'conversion_events' => ['purchase', 'sign_up'],
            'currency' => 'USD',
            'anonymize_ip' => true,
            'cookie_domain' => 'auto',
            'debug_mode' => false,
            'server_side_tracking' => false
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