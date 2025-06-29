<?php

declare(strict_types=1);

namespace Shopologic\Core\MultiStore\Traits;

use Shopologic\Core\MultiStore\Store;

/**
 * Trait for models that can be shared across multiple stores
 */
trait ShareableAcrossStores
{
    /**
     * Get stores relationship
     */
    public function stores()
    {
        $pivotTable = $this->getTable() . '_stores';
        
        return $this->belongsToMany(Store::class, $pivotTable)
            ->withPivot($this->getStorePivotColumns())
            ->withTimestamps();
    }

    /**
     * Check if shared with store
     */
    public function isSharedWithStore($storeId): bool
    {
        return $this->stores()
            ->where('stores.id', $storeId)
            ->exists();
    }

    /**
     * Share with store
     */
    public function shareWithStore($storeId, array $pivotData = []): void
    {
        if (!$this->isSharedWithStore($storeId)) {
            $this->stores()->attach($storeId, $pivotData);
        } else {
            $this->stores()->updateExistingPivot($storeId, $pivotData);
        }
    }

    /**
     * Unshare from store
     */
    public function unshareFromStore($storeId): void
    {
        $this->stores()->detach($storeId);
    }

    /**
     * Share with multiple stores
     */
    public function shareWithStores(array $storeIds, array $pivotData = []): void
    {
        $syncData = [];
        foreach ($storeIds as $storeId) {
            $syncData[$storeId] = $pivotData;
        }
        
        $this->stores()->sync($syncData);
    }

    /**
     * Get store-specific data
     */
    public function getStoreData($storeId, string $key = null)
    {
        $store = $this->stores()
            ->where('stores.id', $storeId)
            ->first();
        
        if (!$store) {
            return null;
        }
        
        $pivotData = $store->pivot->toArray();
        
        return $key ? ($pivotData[$key] ?? null) : $pivotData;
    }

    /**
     * Update store-specific data
     */
    public function updateStoreData($storeId, array $data): void
    {
        $this->stores()->updateExistingPivot($storeId, $data);
    }

    /**
     * Scope to stores
     */
    public function scopeForStores($query, array $storeIds)
    {
        $pivotTable = $this->getTable() . '_stores';
        
        return $query->whereIn('id', function ($subquery) use ($pivotTable, $storeIds) {
            $subquery->select($this->getForeignKey())
                ->from($pivotTable)
                ->whereIn('store_id', $storeIds);
        });
    }

    /**
     * Scope to current store
     */
    public function scopeForCurrentStore($query)
    {
        $storeManager = app(\Shopologic\Core\MultiStore\StoreManager::class);
        $currentStore = $storeManager->getCurrentStore();
        
        if ($currentStore) {
            return $this->scopeForStores($query, [$currentStore->id]);
        }
        
        return $query;
    }

    /**
     * Get pivot columns for store relationship
     */
    protected function getStorePivotColumns(): array
    {
        // Override in model to specify custom pivot columns
        return ['is_active'];
    }

    /**
     * Get foreign key name
     */
    protected function getForeignKey(): string
    {
        return $this->getTable() . '_id';
    }
}