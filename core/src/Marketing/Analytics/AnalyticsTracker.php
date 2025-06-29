<?php

declare(strict_types=1);

namespace Shopologic\Core\Marketing\Analytics;

use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\Session\SessionManager;

/**
 * Analytics tracking service for various platforms
 */
class AnalyticsTracker
{
    private EventDispatcherInterface $eventDispatcher;
    private SessionManager $session;
    private array $config;
    private array $events = [];
    private array $customDimensions = [];
    private array $ecommerceItems = [];

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        SessionManager $session,
        array $config = []
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->session = $session;
        $this->config = array_merge([
            'google_analytics_id' => '',
            'google_tag_manager_id' => '',
            'facebook_pixel_id' => '',
            'enable_enhanced_ecommerce' => true,
            'track_user_id' => true,
            'anonymize_ip' => true,
            'debug_mode' => false
        ], $config);
    }

    /**
     * Track page view
     */
    public function trackPageView(string $title = '', string $path = ''): self
    {
        $this->events[] = [
            'type' => 'pageview',
            'data' => [
                'page_title' => $title,
                'page_path' => $path ?: $_SERVER['REQUEST_URI'] ?? '/',
                'page_location' => $this->getCurrentUrl()
            ]
        ];
        
        return $this;
    }

    /**
     * Track custom event
     */
    public function trackEvent(
        string $category,
        string $action,
        ?string $label = null,
        ?float $value = null
    ): self {
        $this->events[] = [
            'type' => 'event',
            'data' => [
                'event_category' => $category,
                'event_action' => $action,
                'event_label' => $label,
                'value' => $value
            ]
        ];
        
        return $this;
    }

    /**
     * Track ecommerce purchase
     */
    public function trackPurchase(array $order): self
    {
        $this->events[] = [
            'type' => 'purchase',
            'data' => [
                'transaction_id' => $order['id'],
                'value' => $order['total'],
                'currency' => $order['currency'] ?? 'USD',
                'tax' => $order['tax'] ?? 0,
                'shipping' => $order['shipping'] ?? 0,
                'coupon' => $order['coupon'] ?? null,
                'items' => $order['items'] ?? []
            ]
        ];
        
        // Track for Facebook Pixel
        if ($this->config['facebook_pixel_id']) {
            $this->events[] = [
                'type' => 'facebook_purchase',
                'data' => [
                    'value' => $order['total'],
                    'currency' => $order['currency'] ?? 'USD',
                    'content_ids' => array_column($order['items'] ?? [], 'id'),
                    'content_type' => 'product',
                    'num_items' => count($order['items'] ?? [])
                ]
            ];
        }
        
        return $this;
    }

    /**
     * Track product view
     */
    public function trackProductView(array $product): self
    {
        $this->events[] = [
            'type' => 'view_item',
            'data' => [
                'currency' => $product['currency'] ?? 'USD',
                'value' => $product['price'],
                'items' => [[
                    'item_id' => $product['id'],
                    'item_name' => $product['name'],
                    'price' => $product['price'],
                    'item_category' => $product['category'] ?? null,
                    'item_brand' => $product['brand'] ?? null
                ]]
            ]
        ];
        
        // Track for Facebook Pixel
        if ($this->config['facebook_pixel_id']) {
            $this->events[] = [
                'type' => 'facebook_view_content',
                'data' => [
                    'content_ids' => [$product['id']],
                    'content_type' => 'product',
                    'value' => $product['price'],
                    'currency' => $product['currency'] ?? 'USD'
                ]
            ];
        }
        
        return $this;
    }

    /**
     * Track add to cart
     */
    public function trackAddToCart(array $item): self
    {
        $this->events[] = [
            'type' => 'add_to_cart',
            'data' => [
                'currency' => $item['currency'] ?? 'USD',
                'value' => $item['price'] * $item['quantity'],
                'items' => [[
                    'item_id' => $item['id'],
                    'item_name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'item_category' => $item['category'] ?? null,
                    'item_brand' => $item['brand'] ?? null
                ]]
            ]
        ];
        
        // Track for Facebook Pixel
        if ($this->config['facebook_pixel_id']) {
            $this->events[] = [
                'type' => 'facebook_add_to_cart',
                'data' => [
                    'content_ids' => [$item['id']],
                    'content_type' => 'product',
                    'value' => $item['price'] * $item['quantity'],
                    'currency' => $item['currency'] ?? 'USD'
                ]
            ];
        }
        
        return $this;
    }

    /**
     * Track search
     */
    public function trackSearch(string $query, int $resultsCount = 0): self
    {
        $this->events[] = [
            'type' => 'search',
            'data' => [
                'search_term' => $query,
                'results_count' => $resultsCount
            ]
        ];
        
        return $this;
    }

    /**
     * Track user login
     */
    public function trackLogin(string $method = 'email'): self
    {
        $this->events[] = [
            'type' => 'login',
            'data' => [
                'method' => $method
            ]
        ];
        
        return $this;
    }

    /**
     * Track user registration
     */
    public function trackSignUp(string $method = 'email'): self
    {
        $this->events[] = [
            'type' => 'sign_up',
            'data' => [
                'method' => $method
            ]
        ];
        
        return $this;
    }

    /**
     * Set user ID for tracking
     */
    public function setUserId($userId): self
    {
        if ($this->config['track_user_id']) {
            $this->customDimensions['user_id'] = $userId;
        }
        
        return $this;
    }

    /**
     * Set custom dimension
     */
    public function setCustomDimension(string $name, $value): self
    {
        $this->customDimensions[$name] = $value;
        
        return $this;
    }

    /**
     * Add ecommerce item for tracking
     */
    public function addItem(array $item): self
    {
        $this->ecommerceItems[] = [
            'item_id' => $item['id'],
            'item_name' => $item['name'],
            'price' => $item['price'],
            'quantity' => $item['quantity'] ?? 1,
            'item_category' => $item['category'] ?? null,
            'item_category2' => $item['category2'] ?? null,
            'item_category3' => $item['category3'] ?? null,
            'item_brand' => $item['brand'] ?? null,
            'item_variant' => $item['variant'] ?? null
        ];
        
        return $this;
    }

    /**
     * Render tracking scripts
     */
    public function render(): string
    {
        $output = '';
        
        // Apply filters
        $this->events = $this->eventDispatcher->filter('analytics.events', $this->events);
        
        // Google Analytics
        if ($this->config['google_analytics_id']) {
            $output .= $this->renderGoogleAnalytics();
        }
        
        // Google Tag Manager
        if ($this->config['google_tag_manager_id']) {
            $output .= $this->renderGoogleTagManager();
        }
        
        // Facebook Pixel
        if ($this->config['facebook_pixel_id']) {
            $output .= $this->renderFacebookPixel();
        }
        
        // Custom tracking code
        $output .= $this->eventDispatcher->filter('analytics.custom_code', '');
        
        return $output;
    }

    /**
     * Get pending events
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Clear events
     */
    public function clearEvents(): self
    {
        $this->events = [];
        $this->ecommerceItems = [];
        
        return $this;
    }

    // Private methods

    private function renderGoogleAnalytics(): string
    {
        $gaId = $this->config['google_analytics_id'];
        $debugMode = $this->config['debug_mode'] ? 'true' : 'false';
        
        $script = <<<JS
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$gaId}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  
  gtag('config', '{$gaId}', {
    'debug_mode': {$debugMode},
    'anonymize_ip': {$this->config['anonymize_ip']}
  });
JS;
        
        // Add custom dimensions
        if (!empty($this->customDimensions)) {
            $script .= "\n  gtag('set', " . json_encode($this->customDimensions) . ");";
        }
        
        // Add events
        foreach ($this->events as $event) {
            if (in_array($event['type'], ['pageview', 'event', 'purchase', 'view_item', 'add_to_cart', 'search', 'login', 'sign_up'])) {
                $eventName = $event['type'] === 'event' ? $event['data']['event_action'] : $event['type'];
                $eventData = $this->prepareGoogleAnalyticsData($event['data']);
                $script .= "\n  gtag('event', '{$eventName}', " . json_encode($eventData) . ");";
            }
        }
        
        $script .= "\n</script>\n<!-- End Google Analytics -->\n";
        
        return $script;
    }

    private function renderGoogleTagManager(): string
    {
        $gtmId = $this->config['google_tag_manager_id'];
        
        $script = <<<JS
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$gtmId}');</script>
<!-- End Google Tag Manager -->

<script>
JS;
        
        // Push events to dataLayer
        foreach ($this->events as $event) {
            $dataLayerEvent = $this->prepareDataLayerEvent($event);
            $script .= "\ndataLayer.push(" . json_encode($dataLayerEvent) . ");";
        }
        
        $script .= "\n</script>\n";
        
        return $script;
    }

    private function renderFacebookPixel(): string
    {
        $pixelId = $this->config['facebook_pixel_id'];
        
        $script = <<<JS
<!-- Facebook Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$pixelId}');
fbq('track', 'PageView');
JS;
        
        // Add Facebook events
        foreach ($this->events as $event) {
            if (strpos($event['type'], 'facebook_') === 0) {
                $fbEvent = str_replace('facebook_', '', $event['type']);
                $fbEvent = $this->pascalCase($fbEvent);
                $script .= "\nfbq('track', '{$fbEvent}', " . json_encode($event['data']) . ");";
            }
        }
        
        $script .= "\n</script>\n";
        $script .= '<noscript><img height="1" width="1" style="display:none" ';
        $script .= 'src="https://www.facebook.com/tr?id=' . $pixelId . '&ev=PageView&noscript=1"/></noscript>';
        $script .= "\n<!-- End Facebook Pixel Code -->\n";
        
        return $script;
    }

    private function getCurrentUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        return $protocol . '://' . $host . $uri;
    }

    private function prepareGoogleAnalyticsData(array $data): array
    {
        // Remove null values
        return array_filter($data, function ($value) {
            return $value !== null;
        });
    }

    private function prepareDataLayerEvent(array $event): array
    {
        $dataLayer = ['event' => $event['type']];
        
        foreach ($event['data'] as $key => $value) {
            if ($value !== null) {
                $dataLayer[$key] = $value;
            }
        }
        
        return $dataLayer;
    }

    private function pascalCase(string $string): string
    {
        return str_replace('_', '', ucwords($string, '_'));
    }
}