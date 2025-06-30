<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory\Integrations;

use Shopologic\Plugins\Shared\Interfaces\InventoryProviderInterface;
use AdvancedInventory\Services\InventoryManager;
use AdvancedInventory\Services\StockLevelManager;
use AdvancedInventory\Repositories\InventoryItemRepository;
use AdvancedInventory\Repositories\InventoryMovementRepository;

/**
 * Adapter to expose Advanced Inventory functionality to other plugins
 */
class InventoryProviderAdapter implements InventoryProviderInterface
{
    private InventoryManager $inventoryManager;
    private StockLevelManager $stockLevelManager;
    private InventoryItemRepository $inventoryRepository;
    private InventoryMovementRepository $movementRepository;
    private array $subscribers = [];
    
    public function __construct(
        InventoryManager $inventoryManager,
        StockLevelManager $stockLevelManager,
        InventoryItemRepository $inventoryRepository,
        InventoryMovementRepository $movementRepository
    ) {
        $this->inventoryManager = $inventoryManager;
        $this->stockLevelManager = $stockLevelManager;
        $this->inventoryRepository = $inventoryRepository;
        $this->movementRepository = $movementRepository;
    }
    
    /**
     * Get current stock level for a product
     */
    public function getStockLevel(string $productId, string $locationId = null): int
    {
        $item = $this->inventoryRepository->findByProduct($productId, $locationId);
        return $item ? $item->quantity_on_hand : 0;
    }
    
    /**
     * Check if product is in stock
     */
    public function isInStock(string $productId, int $quantity = 1, string $locationId = null): bool
    {
        $availableStock = $this->getStockLevel($productId, $locationId);
        return $availableStock >= $quantity;
    }
    
    /**
     * Reserve inventory for an order
     */
    public function reserveInventory(string $productId, int $quantity, string $orderId, string $locationId = null): bool
    {
        try {
            $item = $this->inventoryRepository->findByProduct($productId, $locationId);
            
            if (!$item || $item->quantity_on_hand < $quantity) {
                return false;
            }
            
            // Create reservation movement
            $this->inventoryManager->createMovement([
                'inventory_item_id' => $item->id,
                'type' => 'reservation',
                'quantity' => -$quantity,
                'reference_type' => 'order',
                'reference_id' => $orderId,
                'movement_date' => now(),
                'reason' => 'Order reservation',
                'metadata' => [
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'location_id' => $locationId
                ]
            ]);
            
            // Update reserved quantity
            $item->quantity_reserved += $quantity;
            $item->save();
            
            // Notify subscribers
            $this->notifyInventoryChange($productId, [
                'type' => 'reservation',
                'quantity' => $quantity,
                'order_id' => $orderId,
                'location_id' => $locationId,
                'new_available' => $item->quantity_on_hand - $item->quantity_reserved
            ]);
            
            return true;
        } catch (\RuntimeException $e) {
            error_log("Inventory reservation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Release reserved inventory
     */
    public function releaseReservation(string $reservationId): bool
    {
        try {
            $movement = $this->movementRepository->find($reservationId);
            
            if (!$movement || $movement->type !== 'reservation') {
                return false;
            }
            
            $item = $movement->inventoryItem;
            $quantity = abs($movement->quantity);
            
            // Create release movement
            $this->inventoryManager->createMovement([
                'inventory_item_id' => $item->id,
                'type' => 'reservation_release',
                'quantity' => $quantity,
                'reference_type' => 'reservation',
                'reference_id' => $reservationId,
                'movement_date' => now(),
                'reason' => 'Reservation released',
                'metadata' => $movement->metadata
            ]);
            
            // Update reserved quantity
            $item->quantity_reserved = max(0, $item->quantity_reserved - $quantity);
            $item->save();
            
            // Notify subscribers
            $this->notifyInventoryChange($movement->getMetadata('product_id'), [
                'type' => 'reservation_release',
                'quantity' => $quantity,
                'reservation_id' => $reservationId,
                'new_available' => $item->quantity_on_hand - $item->quantity_reserved
            ]);
            
            return true;
        } catch (\RuntimeException $e) {
            error_log("Reservation release failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get inventory movements for analytics
     */
    public function getInventoryMovements(\DateTime $startDate, \DateTime $endDate, array $filters = []): array
    {
        $query = $this->movementRepository->query()
            ->whereBetween('movement_date', [$startDate, $endDate]);
        
        if (isset($filters['product_id'])) {
            $query->whereHas('inventoryItem', function($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }
        
        if (isset($filters['location_id'])) {
            $query->whereHas('inventoryItem', function($q) use ($filters) {
                $q->where('location_id', $filters['location_id']);
            });
        }
        
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        return $query->get()
            ->map(function($movement) {
                return [
                    'id' => $movement->id,
                    'product_id' => $movement->inventoryItem->product_id,
                    'location_id' => $movement->inventoryItem->location_id,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'movement_date' => $movement->movement_date->format('Y-m-d H:i:s'),
                    'reason' => $movement->reason,
                    'reference_type' => $movement->reference_type,
                    'reference_id' => $movement->reference_id,
                    'metadata' => $movement->metadata
                ];
            })
            ->toArray();
    }
    
    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts(): array
    {
        return $this->stockLevelManager->getLowStockItems()
            ->map(function($item) {
                return [
                    'product_id' => $item->product_id,
                    'location_id' => $item->location_id,
                    'current_stock' => $item->quantity_on_hand,
                    'reorder_point' => $item->stockLevel->reorder_point ?? 0,
                    'recommended_order' => $item->stockLevel->getRecommendedOrderQuantity() ?? 0,
                    'severity' => $this->calculateAlertSeverity($item),
                    'last_movement_date' => $item->last_movement_date?->format('Y-m-d H:i:s')
                ];
            })
            ->toArray();
    }
    
    /**
     * Subscribe to inventory level changes
     */
    public function subscribeToInventoryChanges(string $productId, callable $callback): void
    {
        if (!isset($this->subscribers[$productId])) {
            $this->subscribers[$productId] = [];
        }
        
        $this->subscribers[$productId][] = $callback;
    }
    
    /**
     * Notify subscribers of inventory changes
     */
    private function notifyInventoryChange(string $productId, array $data): void
    {
        if (!isset($this->subscribers[$productId])) {
            return;
        }
        
        foreach ($this->subscribers[$productId] as $callback) {
            try {
                call_user_func($callback, array_merge($data, ['product_id' => $productId]));
            } catch (\RuntimeException $e) {
                error_log("Inventory change callback error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Calculate alert severity
     */
    private function calculateAlertSeverity($item): string
    {
        $stockLevel = $item->stockLevel;
        if (!$stockLevel) {
            return 'medium';
        }
        
        $currentStock = $item->quantity_on_hand;
        $reorderPoint = $stockLevel->reorder_point;
        $minimumStock = $stockLevel->minimum_stock;
        
        if ($currentStock <= 0) {
            return 'critical';
        } elseif ($currentStock <= $minimumStock) {
            return 'high';
        } elseif ($currentStock <= $reorderPoint) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Get inventory summary for location
     */
    public function getLocationSummary(string $locationId = null): array
    {
        $query = $this->inventoryRepository->query();
        
        if ($locationId) {
            $query->where('location_id', $locationId);
        }
        
        $items = $query->get();
        
        return [
            'total_items' => $items->count(),
            'total_value' => $items->sum(function($item) {
                return $item->quantity_on_hand * $item->unit_cost;
            }),
            'low_stock_items' => $items->filter(function($item) {
                return $item->stockLevel && $item->quantity_on_hand <= $item->stockLevel->reorder_point;
            })->count(),
            'out_of_stock_items' => $items->where('quantity_on_hand', '<=', 0)->count(),
            'reserved_quantity' => $items->sum('quantity_reserved'),
            'available_quantity' => $items->sum(function($item) {
                return $item->quantity_on_hand - $item->quantity_reserved;
            })
        ];
    }
    
    /**
     * Get product movement history
     */
    public function getProductMovementHistory(string $productId, int $days = 30): array
    {
        return $this->movementRepository->query()
            ->whereHas('inventoryItem', function($q) use ($productId) {
                $q->where('product_id', $productId);
            })
            ->where('movement_date', '>=', now()->subDays($days))
            ->orderBy('movement_date', 'desc')
            ->get()
            ->map(function($movement) {
                return [
                    'date' => $movement->movement_date->format('Y-m-d H:i:s'),
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'reason' => $movement->reason,
                    'reference' => $movement->reference_type . '#' . $movement->reference_id
                ];
            })
            ->toArray();
    }
}