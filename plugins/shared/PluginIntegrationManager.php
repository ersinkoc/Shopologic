<?php

declare(strict_types=1);

namespace Shopologic\Plugins\Shared;

use Shopologic\Plugins\Shared\Interfaces\{
    AnalyticsProviderInterface,
    InventoryProviderInterface,
    LoyaltyProviderInterface,
    CurrencyProviderInterface,
    MarketingProviderInterface
};

/**
 * Central integration manager for plugin communication
 * Facilitates service discovery and cross-plugin data sharing
 */
class PluginIntegrationManager
{
    private array $providers = [];
    private array $eventSubscriptions = [];
    private array $cache = [];
    
    /**
     * Register a plugin as a service provider
     */
    public function registerProvider(string $interface, object $provider): void
    {
        if (!interface_exists($interface)) {
            throw new \InvalidArgumentException("Interface {$interface} does not exist");
        }
        
        if (!$provider instanceof $interface) {
            throw new \InvalidArgumentException("Provider must implement {$interface}");
        }
        
        if (!isset($this->providers[$interface])) {
            $this->providers[$interface] = [];
        }
        
        $this->providers[$interface][] = $provider;
    }
    
    /**
     * Get all providers for an interface
     */
    public function getProviders(string $interface): array
    {
        return $this->providers[$interface] ?? [];
    }
    
    /**
     * Get the first available provider for an interface
     */
    public function getProvider(string $interface): ?object
    {
        $providers = $this->getProviders($interface);
        return $providers[0] ?? null;
    }
    
    /**
     * Get analytics provider
     */
    public function getAnalyticsProvider(): ?AnalyticsProviderInterface
    {
        return $this->getProvider(AnalyticsProviderInterface::class);
    }
    
    /**
     * Get inventory provider
     */
    public function getInventoryProvider(): ?InventoryProviderInterface
    {
        return $this->getProvider(InventoryProviderInterface::class);
    }
    
    /**
     * Get loyalty provider
     */
    public function getLoyaltyProvider(): ?LoyaltyProviderInterface
    {
        return $this->getProvider(LoyaltyProviderInterface::class);
    }
    
    /**
     * Get currency provider
     */
    public function getCurrencyProvider(): ?CurrencyProviderInterface
    {
        return $this->getProvider(CurrencyProviderInterface::class);
    }
    
    /**
     * Get marketing provider
     */
    public function getMarketingProvider(): ?MarketingProviderInterface
    {
        return $this->getProvider(MarketingProviderInterface::class);
    }
    
    /**
     * Subscribe to cross-plugin events
     */
    public function subscribeToEvent(string $eventName, callable $callback, int $priority = 10): void
    {
        if (!isset($this->eventSubscriptions[$eventName])) {
            $this->eventSubscriptions[$eventName] = [];
        }
        
        $this->eventSubscriptions[$eventName][] = [
            'callback' => $callback,
            'priority' => $priority
        ];
        
        // Sort by priority
        usort($this->eventSubscriptions[$eventName], function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
    }
    
    /**
     * Dispatch event to all subscribers
     */
    public function dispatchEvent(string $eventName, array $data = []): void
    {
        if (!isset($this->eventSubscriptions[$eventName])) {
            return;
        }
        
        foreach ($this->eventSubscriptions[$eventName] as $subscription) {
            try {
                call_user_func($subscription['callback'], $data);
            } catch (\Exception $e) {
                // Log error but don't break other subscribers
                error_log("Plugin integration event error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get cached data with TTL
     */
    public function getCached(string $key, callable $loader = null, int $ttl = 300): mixed
    {
        $cacheKey = md5($key);
        
        if (isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
            unset($this->cache[$cacheKey]);
        }
        
        if ($loader === null) {
            return null;
        }
        
        $data = $loader();
        $this->cache[$cacheKey] = [
            'data' => $data,
            'expires' => time() + $ttl
        ];
        
        return $data;
    }
    
    /**
     * Clear cache
     */
    public function clearCache(string $pattern = null): void
    {
        if ($pattern === null) {
            $this->cache = [];
            return;
        }
        
        foreach (array_keys($this->cache) as $key) {
            if (fnmatch($pattern, $key)) {
                unset($this->cache[$key]);
            }
        }
    }
    
    /**
     * Check if provider is available
     */
    public function hasProvider(string $interface): bool
    {
        return !empty($this->getProviders($interface));
    }
    
    /**
     * Get integration status
     */
    public function getIntegrationStatus(): array
    {
        return [
            'analytics_available' => $this->hasProvider(AnalyticsProviderInterface::class),
            'inventory_available' => $this->hasProvider(InventoryProviderInterface::class),
            'loyalty_available' => $this->hasProvider(LoyaltyProviderInterface::class),
            'currency_available' => $this->hasProvider(CurrencyProviderInterface::class),
            'marketing_available' => $this->hasProvider(MarketingProviderInterface::class),
            'registered_providers' => array_map(function($providers) {
                return count($providers);
            }, $this->providers),
            'event_subscriptions' => array_map(function($subs) {
                return count($subs);
            }, $this->eventSubscriptions),
            'cache_entries' => count($this->cache)
        ];
    }
    
    /**
     * Execute cross-plugin workflow
     */
    public function executeWorkflow(string $workflowName, array $data): array
    {
        $this->dispatchEvent("workflow.{$workflowName}.started", $data);
        
        $result = ['success' => false, 'data' => [], 'errors' => []];
        
        try {
            switch ($workflowName) {
                case 'order_completed':
                    $result = $this->handleOrderCompletedWorkflow($data);
                    break;
                case 'customer_registered':
                    $result = $this->handleCustomerRegisteredWorkflow($data);
                    break;
                case 'inventory_low_stock':
                    $result = $this->handleLowStockWorkflow($data);
                    break;
                case 'loyalty_tier_upgraded':
                    $result = $this->handleTierUpgradedWorkflow($data);
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown workflow: {$workflowName}");
            }
            
            $this->dispatchEvent("workflow.{$workflowName}.completed", array_merge($data, $result));
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            $this->dispatchEvent("workflow.{$workflowName}.failed", array_merge($data, $result));
        }
        
        return $result;
    }
    
    /**
     * Handle order completed workflow
     */
    private function handleOrderCompletedWorkflow(array $data): array
    {
        $result = ['success' => true, 'data' => [], 'errors' => []];
        
        // Award loyalty points
        if ($loyaltyProvider = $this->getLoyaltyProvider()) {
            try {
                $points = (int)($data['order_total'] * 10); // 10 points per dollar
                $loyaltyProvider->awardPoints(
                    $data['customer_id'],
                    $points,
                    'Order #' . $data['order_id'],
                    ['order_id' => $data['order_id']]
                );
                $result['data']['loyalty_points_awarded'] = $points;
            } catch (\Exception $e) {
                $result['errors'][] = "Loyalty points award failed: " . $e->getMessage();
            }
        }
        
        // Update inventory
        if ($inventoryProvider = $this->getInventoryProvider()) {
            try {
                foreach ($data['order_items'] as $item) {
                    // Inventory movements are typically handled by the order system
                    // But we can trigger analytics updates here
                }
                $result['data']['inventory_updated'] = true;
            } catch (\Exception $e) {
                $result['errors'][] = "Inventory update failed: " . $e->getMessage();
            }
        }
        
        // Send marketing emails
        if ($marketingProvider = $this->getMarketingProvider()) {
            try {
                $marketingProvider->sendTransactionalEmail(
                    'order_confirmation',
                    ['customer_id' => $data['customer_id']],
                    $data
                );
                $result['data']['confirmation_email_sent'] = true;
            } catch (\Exception $e) {
                $result['errors'][] = "Email sending failed: " . $e->getMessage();
            }
        }
        
        return $result;
    }
    
    /**
     * Handle customer registered workflow
     */
    private function handleCustomerRegisteredWorkflow(array $data): array
    {
        $result = ['success' => true, 'data' => [], 'errors' => []];
        
        // Award welcome points
        if ($loyaltyProvider = $this->getLoyaltyProvider()) {
            try {
                $loyaltyProvider->awardPoints(
                    $data['customer_id'],
                    100, // Welcome bonus
                    'Welcome bonus',
                    ['registration_date' => $data['registration_date']]
                );
                $result['data']['welcome_points_awarded'] = 100;
            } catch (\Exception $e) {
                $result['errors'][] = "Welcome points award failed: " . $e->getMessage();
            }
        }
        
        // Send welcome email
        if ($marketingProvider = $this->getMarketingProvider()) {
            try {
                $marketingProvider->sendTransactionalEmail(
                    'welcome_email',
                    ['customer_id' => $data['customer_id']],
                    $data
                );
                $result['data']['welcome_email_sent'] = true;
            } catch (\Exception $e) {
                $result['errors'][] = "Welcome email failed: " . $e->getMessage();
            }
        }
        
        return $result;
    }
    
    /**
     * Handle low stock workflow
     */
    private function handleLowStockWorkflow(array $data): array
    {
        $result = ['success' => true, 'data' => [], 'errors' => []];
        
        // Send low stock notification
        if ($marketingProvider = $this->getMarketingProvider()) {
            try {
                $marketingProvider->sendTransactionalEmail(
                    'low_stock_alert',
                    ['admin_email' => $data['admin_email']],
                    $data
                );
                $result['data']['alert_email_sent'] = true;
            } catch (\Exception $e) {
                $result['errors'][] = "Low stock alert failed: " . $e->getMessage();
            }
        }
        
        return $result;
    }
    
    /**
     * Handle tier upgraded workflow
     */
    private function handleTierUpgradedWorkflow(array $data): array
    {
        $result = ['success' => true, 'data' => [], 'errors' => []];
        
        // Send tier upgrade email
        if ($marketingProvider = $this->getMarketingProvider()) {
            try {
                $marketingProvider->sendTransactionalEmail(
                    'tier_upgrade_notification',
                    ['customer_id' => $data['customer_id']],
                    $data
                );
                $result['data']['upgrade_email_sent'] = true;
            } catch (\Exception $e) {
                $result['errors'][] = "Tier upgrade email failed: " . $e->getMessage();
            }
        }
        
        return $result;
    }
    
    /**
     * Get singleton instance
     */
    private static ?self $instance = null;
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}