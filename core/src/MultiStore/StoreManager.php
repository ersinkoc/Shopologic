<?php

declare(strict_types=1);

namespace Shopologic\Core\MultiStore;

use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Container\Container;

/**
 * Manages multi-store functionality and tenant isolation
 */
class StoreManager
{
    private CacheInterface $cache;
    private EventDispatcherInterface $eventDispatcher;
    private Container $container;
    private ?Store $currentStore = null;
    private array $config;
    private array $storeCache = [];

    public function __construct(
        CacheInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        Container $container,
        array $config = []
    ) {
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->container = $container;
        $this->config = array_merge([
            'detection_order' => ['domain', 'subdomain', 'path'],
            'cache_ttl' => 3600,
            'fallback_to_default' => true
        ], $config);
    }

    /**
     * Detect and set current store from request
     */
    public function detectStore(Request $request): ?Store
    {
        $host = $request->getHost();
        $path = $request->getPath();
        
        // Check cache first
        $cacheKey = 'store_detection_' . md5($host . '|' . $path);
        $cachedStoreId = $this->cache->get($cacheKey);
        
        if ($cachedStoreId !== null) {
            $store = $this->getStore($cachedStoreId);
            if ($store && $store->is_active) {
                $this->setCurrentStore($store);
                return $store;
            }
        }
        
        // Detect store based on configuration order
        $store = null;
        foreach ($this->config['detection_order'] as $method) {
            $store = $this->detectByMethod($method, $host, $path);
            if ($store) {
                break;
            }
        }
        
        // Fallback to default store
        if (!$store && $this->config['fallback_to_default']) {
            $store = Store::getDefault();
        }
        
        if ($store) {
            // Cache the detection result
            $this->cache->set($cacheKey, $store->id, $this->config['cache_ttl']);
            $this->setCurrentStore($store);
        }
        
        return $store;
    }

    /**
     * Get current store
     */
    public function getCurrentStore(): ?Store
    {
        return $this->currentStore;
    }

    /**
     * Set current store
     */
    public function setCurrentStore(Store $store): void
    {
        $this->currentStore = $store;
        
        // Apply store configuration
        $this->applyStoreConfiguration($store);
        
        // Trigger event
        $this->eventDispatcher->dispatch('store.switched', ['store' => $store]);
    }

    /**
     * Get store by ID
     */
    public function getStore($id): ?Store
    {
        if (isset($this->storeCache[$id])) {
            return $this->storeCache[$id];
        }
        
        $store = Store::find($id);
        if ($store) {
            $this->storeCache[$id] = $store;
        }
        
        return $store;
    }

    /**
     * Get all active stores
     */
    public function getActiveStores(): array
    {
        $cacheKey = 'active_stores';
        $stores = $this->cache->get($cacheKey);
        
        if ($stores === null) {
            $stores = Store::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->all();
            
            $this->cache->set($cacheKey, $stores, $this->config['cache_ttl']);
        }
        
        return $stores;
    }

    /**
     * Create new store
     */
    public function createStore(array $data): Store
    {
        // Validate unique identifiers
        $this->validateStoreData($data);
        
        // Create store
        $store = new Store($data);
        $store->save();
        
        // Create default settings
        $this->createDefaultSettings($store);
        
        // Clear cache
        $this->clearStoreCache();
        
        // Trigger event
        $this->eventDispatcher->dispatch('store.created', ['store' => $store]);
        
        return $store;
    }

    /**
     * Update store
     */
    public function updateStore(Store $store, array $data): Store
    {
        // Validate if identifiers are being changed
        if (isset($data['code']) && $data['code'] !== $store->code) {
            $this->validateStoreCode($data['code']);
        }
        
        if (isset($data['domain']) && $data['domain'] !== $store->domain) {
            $this->validateStoreDomain($data['domain']);
        }
        
        if (isset($data['subdomain']) && $data['subdomain'] !== $store->subdomain) {
            $this->validateStoreSubdomain($data['subdomain']);
        }
        
        // Update store
        $store->fill($data);
        $store->save();
        
        // Clear cache
        $this->clearStoreCache();
        
        // Trigger event
        $this->eventDispatcher->dispatch('store.updated', ['store' => $store]);
        
        return $store;
    }

    /**
     * Delete store
     */
    public function deleteStore(Store $store): bool
    {
        if ($store->is_default) {
            throw new StoreException('Cannot delete default store');
        }
        
        // Check if store has orders
        if ($store->orders()->count() > 0) {
            throw new StoreException('Cannot delete store with existing orders');
        }
        
        // Delete store relationships
        $store->users()->detach();
        $store->products()->detach();
        $store->categories()->detach();
        $store->settings()->delete();
        
        // Delete store
        $result = $store->delete();
        
        // Clear cache
        $this->clearStoreCache();
        
        // Trigger event
        $this->eventDispatcher->dispatch('store.deleted', ['store_id' => $store->id]);
        
        return $result;
    }

    /**
     * Switch context to a specific store
     */
    public function switchToStore($storeId): void
    {
        $store = $this->getStore($storeId);
        
        if (!$store) {
            throw new StoreException('Store not found');
        }
        
        if (!$store->is_active) {
            throw new StoreException('Store is not active');
        }
        
        $this->setCurrentStore($store);
    }

    /**
     * Get store-specific configuration
     */
    public function getStoreConfig(string $key, $default = null)
    {
        if (!$this->currentStore) {
            return $default;
        }
        
        return $this->currentStore->getConfig($key, $default);
    }

    /**
     * Check if current user has access to store
     */
    public function hasAccess(Store $store, $user = null): bool
    {
        if (!$user) {
            $user = $this->container->get('auth')->user();
        }
        
        if (!$user) {
            return false;
        }
        
        // Super admins have access to all stores
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        // Check if user is associated with the store
        return $store->users()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Get user's role in store
     */
    public function getUserRole(Store $store, $user = null): ?string
    {
        if (!$user) {
            $user = $this->container->get('auth')->user();
        }
        
        if (!$user) {
            return null;
        }
        
        $storeUser = $store->users()
            ->where('user_id', $user->id)
            ->first();
        
        return $storeUser ? $storeUser->pivot->role : null;
    }

    // Private methods

    private function detectByMethod(string $method, string $host, string $path): ?Store
    {
        switch ($method) {
            case 'domain':
                return Store::findByDomain($host);
                
            case 'subdomain':
                $subdomain = explode('.', $host)[0];
                return Store::findBySubdomain($subdomain);
                
            case 'path':
                // Extract first path segment
                $segments = explode('/', trim($path, '/'));
                if (!empty($segments)) {
                    return Store::findByPathPrefix($segments[0]);
                }
                return null;
                
            default:
                return null;
        }
    }

    private function applyStoreConfiguration(Store $store): void
    {
        // Set locale
        if ($store->locale) {
            $this->container->get('translator')->setLocale($store->locale);
        }
        
        // Set timezone
        if ($store->timezone) {
            date_default_timezone_set($store->timezone);
        }
        
        // Set theme
        if ($store->theme) {
            $this->container->get('config')->set('theme.active', $store->theme);
        }
        
        // Apply store-specific configuration
        $config = $store->config ?? [];
        foreach ($config as $key => $value) {
            $this->container->get('config')->set($key, $value);
        }
        
        // Set store context in container
        $this->container->instance('current_store', $store);
    }

    private function validateStoreData(array $data): void
    {
        // Validate required fields
        $required = ['code', 'name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new StoreException("Field '{$field}' is required");
            }
        }
        
        // Validate unique code
        $this->validateStoreCode($data['code']);
        
        // Validate domain if provided
        if (!empty($data['domain'])) {
            $this->validateStoreDomain($data['domain']);
        }
        
        // Validate subdomain if provided
        if (!empty($data['subdomain'])) {
            $this->validateStoreSubdomain($data['subdomain']);
        }
        
        // Validate path prefix if provided
        if (!empty($data['path_prefix'])) {
            $this->validateStorePathPrefix($data['path_prefix']);
        }
        
        // Ensure at least one identification method is provided
        if (empty($data['domain']) && empty($data['subdomain']) && empty($data['path_prefix'])) {
            throw new StoreException('Store must have at least one identification method (domain, subdomain, or path prefix)');
        }
    }

    private function validateStoreCode(string $code): void
    {
        if (!preg_match('/^[a-z0-9_]+$/', $code)) {
            throw new StoreException('Store code must contain only lowercase letters, numbers, and underscores');
        }
        
        if (Store::where('code', $code)->exists()) {
            throw new StoreException('Store code already exists');
        }
    }

    private function validateStoreDomain(string $domain): void
    {
        if (!filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
            throw new StoreException('Invalid domain format');
        }
        
        if (Store::where('domain', $domain)->exists()) {
            throw new StoreException('Domain already assigned to another store');
        }
    }

    private function validateStoreSubdomain(string $subdomain): void
    {
        if (!preg_match('/^[a-z0-9-]+$/', $subdomain)) {
            throw new StoreException('Subdomain must contain only lowercase letters, numbers, and hyphens');
        }
        
        if (Store::where('subdomain', $subdomain)->exists()) {
            throw new StoreException('Subdomain already assigned to another store');
        }
    }

    private function validateStorePathPrefix(string $prefix): void
    {
        if (!preg_match('/^[a-z0-9-\/]+$/', $prefix)) {
            throw new StoreException('Path prefix must contain only lowercase letters, numbers, hyphens, and slashes');
        }
        
        if (Store::where('path_prefix', $prefix)->exists()) {
            throw new StoreException('Path prefix already assigned to another store');
        }
    }

    private function createDefaultSettings(Store $store): void
    {
        $defaultSettings = [
            'general.store_name' => $store->name,
            'general.store_email' => 'info@' . ($store->domain ?? 'example.com'),
            'general.store_phone' => '',
            'general.store_address' => '',
            'catalog.products_per_page' => 20,
            'catalog.default_sort' => 'newest',
            'checkout.guest_checkout' => true,
            'checkout.require_phone' => false,
            'email.from_name' => $store->name,
            'email.from_address' => 'noreply@' . ($store->domain ?? 'example.com')
        ];
        
        foreach ($defaultSettings as $key => $value) {
            $setting = new StoreSettings([
                'store_id' => $store->id,
                'key' => $key,
                'value' => $value
            ]);
            $setting->save();
        }
    }

    private function clearStoreCache(): void
    {
        $this->cache->deleteByPrefix('store_');
        $this->cache->delete('active_stores');
        $this->storeCache = [];
    }
}

class StoreException extends \Exception {}