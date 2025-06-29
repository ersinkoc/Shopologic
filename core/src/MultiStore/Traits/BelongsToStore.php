<?php

declare(strict_types=1);

namespace Shopologic\Core\MultiStore\Traits;

use Shopologic\Core\MultiStore\Store;
use Shopologic\Core\MultiStore\StoreManager;

/**
 * Trait for models that belong to a specific store
 */
trait BelongsToStore
{
    /**
     * Boot the trait
     */
    public static function bootBelongsToStore(): void
    {
        // Automatically set store_id on create
        static::creating(function ($model) {
            if (empty($model->store_id)) {
                $storeManager = app(StoreManager::class);
                $currentStore = $storeManager->getCurrentStore();
                
                if ($currentStore) {
                    $model->store_id = $currentStore->id;
                }
            }
        });
        
        // Add global scope to filter by current store
        static::addGlobalScope('store', function ($query) {
            $storeManager = app(StoreManager::class);
            $currentStore = $storeManager->getCurrentStore();
            
            if ($currentStore && !static::$disableStoreScope) {
                $query->where('store_id', $currentStore->id);
            }
        });
    }

    /**
     * Get the store relationship
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Scope query to specific store
     */
    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope query to multiple stores
     */
    public function scopeForStores($query, array $storeIds)
    {
        return $query->whereIn('store_id', $storeIds);
    }

    /**
     * Check if model belongs to current store
     */
    public function belongsToCurrentStore(): bool
    {
        $storeManager = app(StoreManager::class);
        $currentStore = $storeManager->getCurrentStore();
        
        return $currentStore && $this->store_id == $currentStore->id;
    }

    /**
     * Disable store scope temporarily
     */
    public static function withoutStoreScope(callable $callback)
    {
        static::$disableStoreScope = true;
        
        try {
            return $callback();
        } finally {
            static::$disableStoreScope = false;
        }
    }

    /**
     * Get models from all stores
     */
    public static function allStores()
    {
        return static::withoutStoreScope(function () {
            return static::query();
        });
    }

    /**
     * Property to control store scope
     */
    protected static bool $disableStoreScope = false;
}