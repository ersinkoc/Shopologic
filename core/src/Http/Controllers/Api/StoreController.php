<?php

declare(strict_types=1);

namespace Shopologic\Core\Http\Controllers\Api;

use Shopologic\Core\Http\Controllers\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\MultiStore\StoreManager;

class StoreController extends Controller
{
    private StoreManager $storeManager;

    public function __construct(StoreManager $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * Switch to a different store
     */
    public function switch(Request $request): Response
    {
        $storeId = $request->get('store_id');
        
        if (!$storeId) {
            return $this->json([
                'success' => false,
                'message' => 'Store ID is required'
            ], 400);
        }
        
        try {
            // Check if user has access to the store
            $store = $this->storeManager->getStore($storeId);
            
            if (!$store || !$store->is_active) {
                return $this->json([
                    'success' => false,
                    'message' => 'Store not found or inactive'
                ], 404);
            }
            
            $user = $this->auth()->user();
            if (!$this->storeManager->hasAccess($store, $user)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Access denied to this store'
                ], 403);
            }
            
            // Switch to the store
            $this->storeManager->switchToStore($storeId);
            
            // Store in session for persistence
            $request->session()->set('current_store_id', $storeId);
            
            return $this->json([
                'success' => true,
                'message' => 'Switched to store: ' . $store->name,
                'store' => [
                    'id' => $store->id,
                    'code' => $store->code,
                    'name' => $store->name,
                    'url' => $store->getUrl()
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current store info
     */
    public function current(): Response
    {
        $store = $this->storeManager->getCurrentStore();
        
        if (!$store) {
            return $this->json([
                'success' => false,
                'message' => 'No store selected'
            ], 404);
        }
        
        return $this->json([
            'success' => true,
            'store' => [
                'id' => $store->id,
                'code' => $store->code,
                'name' => $store->name,
                'domain' => $store->domain,
                'locale' => $store->locale,
                'currency' => $store->currency,
                'timezone' => $store->timezone,
                'theme' => $store->theme,
                'url' => $store->getUrl()
            ]
        ]);
    }

    /**
     * List stores accessible to current user
     */
    public function accessible(): Response
    {
        $user = $this->auth()->user();
        
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }
        
        // Get all active stores
        $stores = $this->storeManager->getActiveStores();
        
        // Filter by user access
        $accessibleStores = [];
        foreach ($stores as $store) {
            if ($this->storeManager->hasAccess($store, $user)) {
                $role = $this->storeManager->getUserRole($store, $user);
                $accessibleStores[] = [
                    'id' => $store->id,
                    'code' => $store->code,
                    'name' => $store->name,
                    'domain' => $store->domain,
                    'role' => $role,
                    'url' => $store->getUrl()
                ];
            }
        }
        
        return $this->json([
            'success' => true,
            'stores' => $accessibleStores,
            'current_store_id' => $this->storeManager->getCurrentStore()?->id
        ]);
    }
}