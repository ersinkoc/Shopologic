<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory\Services;

use AdvancedInventory\Repositories\InventoryRepository;
use AdvancedInventory\Repositories\MovementRepository;
use AdvancedInventory\Models\InventoryItem;
use AdvancedInventory\Models\StockMovement;
use AdvancedInventory\Exceptions\InsufficientStockException;
use AdvancedInventory\Exceptions\InvalidStockOperationException;

class InventoryManager\n{
    private InventoryRepository $inventoryRepository;
    private MovementRepository $movementRepository;
    private string $trackingMethod;

    public function __construct(
        InventoryRepository $inventoryRepository,
        MovementRepository $movementRepository,
        string $trackingMethod = 'fifo'
    ) {
        $this->inventoryRepository = $inventoryRepository;
        $this->movementRepository = $movementRepository;
        $this->trackingMethod = $trackingMethod;
    }

    /**
     * Add stock to inventory
     */
    public function addStock(
        int $productId,
        int $quantity,
        int $warehouseId,
        string $reason = 'adjustment',
        ?int $referenceId = null,
        array $metadata = []
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidStockOperationException('Quantity must be positive');
        }

        // Get or create inventory item
        $inventoryItem = $this->inventoryRepository->findByProductAndWarehouse($productId, $warehouseId);
        if (!$inventoryItem) {
            $inventoryItem = $this->createInventoryItem($productId, $warehouseId);
        }

        // Calculate new stock level
        $newQuantity = $inventoryItem->getQuantity() + $quantity;

        // Update inventory item
        $this->inventoryRepository->updateQuantity($inventoryItem->getId(), $newQuantity);

        // Record movement
        $movement = $this->movementRepository->create([
            'inventory_item_id' => $inventoryItem->getId(),
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'movement_type' => 'in',
            'quantity' => $quantity,
            'previous_quantity' => $inventoryItem->getQuantity(),
            'new_quantity' => $newQuantity,
            'reason' => $reason,
            'reference_type' => $this->getReferenceType($reason),
            'reference_id' => $referenceId,
            'cost_per_unit' => $metadata['cost_per_unit'] ?? null,
            'batch_number' => $metadata['batch_number'] ?? null,
            'expiry_date' => $metadata['expiry_date'] ?? null,
            'supplier_id' => $metadata['supplier_id'] ?? null,
            'user_id' => $metadata['user_id'] ?? null,
            'notes' => $metadata['notes'] ?? null
        ]);

        // Update cost basis if tracking method requires it
        $this->updateCostBasis($inventoryItem, $quantity, $metadata['cost_per_unit'] ?? 0);

        return $movement;
    }

    /**
     * Reduce stock from inventory
     */
    public function reduceStock(
        int $productId,
        int $quantity,
        int $warehouseId,
        string $reason = 'sale',
        ?int $referenceId = null,
        array $metadata = []
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidStockOperationException('Quantity must be positive');
        }

        $inventoryItem = $this->inventoryRepository->findByProductAndWarehouse($productId, $warehouseId);
        if (!$inventoryItem) {
            throw new InsufficientStockException("No inventory found for product {$productId} in warehouse {$warehouseId}");
        }

        if ($inventoryItem->getQuantity() < $quantity) {
            throw new InsufficientStockException(
                "Insufficient stock. Available: {$inventoryItem->getQuantity()}, Required: {$quantity}"
            );
        }

        // Calculate new stock level
        $newQuantity = $inventoryItem->getQuantity() - $quantity;

        // Update inventory item
        $this->inventoryRepository->updateQuantity($inventoryItem->getId(), $newQuantity);

        // Record movement
        $movement = $this->movementRepository->create([
            'inventory_item_id' => $inventoryItem->getId(),
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'movement_type' => 'out',
            'quantity' => $quantity,
            'previous_quantity' => $inventoryItem->getQuantity(),
            'new_quantity' => $newQuantity,
            'reason' => $reason,
            'reference_type' => $this->getReferenceType($reason),
            'reference_id' => $referenceId,
            'cost_per_unit' => $this->calculateCostPerUnit($inventoryItem, $quantity),
            'user_id' => $metadata['user_id'] ?? null,
            'notes' => $metadata['notes'] ?? null
        ]);

        // Update cost basis
        $this->updateCostBasisForReduction($inventoryItem, $quantity);

        return $movement;
    }

    /**
     * Transfer stock between warehouses
     */
    public function transferStock(
        int $productId,
        int $quantity,
        int $fromWarehouseId,
        int $toWarehouseId,
        ?int $referenceId = null,
        array $metadata = []
    ): array {
        if ($quantity <= 0) {
            throw new InvalidStockOperationException('Quantity must be positive');
        }

        if ($fromWarehouseId === $toWarehouseId) {
            throw new InvalidStockOperationException('Source and destination warehouses cannot be the same');
        }

        // Reduce from source warehouse
        $outMovement = $this->reduceStock(
            $productId,
            $quantity,
            $fromWarehouseId,
            'transfer_out',
            $referenceId,
            $metadata
        );

        // Add to destination warehouse
        $inMovement = $this->addStock(
            $productId,
            $quantity,
            $toWarehouseId,
            'transfer_in',
            $referenceId,
            array_merge($metadata, [
                'cost_per_unit' => $outMovement->getCostPerUnit()
            ])
        );

        return [$outMovement, $inMovement];
    }

    /**
     * Adjust stock to specific quantity
     */
    public function adjustStock(
        int $productId,
        int $newQuantity,
        int $warehouseId,
        string $reason = 'adjustment',
        ?int $referenceId = null,
        array $metadata = []
    ): StockMovement {
        if ($newQuantity < 0) {
            throw new InvalidStockOperationException('New quantity cannot be negative');
        }

        $inventoryItem = $this->inventoryRepository->findByProductAndWarehouse($productId, $warehouseId);
        if (!$inventoryItem) {
            if ($newQuantity > 0) {
                return $this->addStock($productId, $newQuantity, $warehouseId, $reason, $referenceId, $metadata);
            } else {
                throw new InvalidStockOperationException('Cannot adjust non-existent inventory to zero');
            }
        }

        $currentQuantity = $inventoryItem->getQuantity();
        $difference = $newQuantity - $currentQuantity;

        if ($difference === 0) {
            throw new InvalidStockOperationException('No adjustment needed - quantities are the same');
        }

        if ($difference > 0) {
            return $this->addStock($productId, $difference, $warehouseId, $reason, $referenceId, $metadata);
        } else {
            return $this->reduceStock($productId, abs($difference), $warehouseId, $reason, $referenceId, $metadata);
        }
    }

    /**
     * Get current stock level
     */
    public function getCurrentStock(int $productId, ?int $warehouseId = null): int
    {
        if ($warehouseId) {
            $inventoryItem = $this->inventoryRepository->findByProductAndWarehouse($productId, $warehouseId);
            return $inventoryItem ? $inventoryItem->getQuantity() : 0;
        }

        return $this->inventoryRepository->getTotalStockByProduct($productId);
    }

    /**
     * Get stock across all warehouses for a product
     */
    public function getStockByWarehouse(int $productId): array
    {
        return $this->inventoryRepository->getStockByWarehouse($productId);
    }

    /**
     * Get inventory valuation
     */
    public function getInventoryValuation(?int $warehouseId = null): array
    {
        $items = $warehouseId 
            ? $this->inventoryRepository->findByWarehouse($warehouseId)
            : $this->inventoryRepository->findAll();

        $totalValue = 0;
        $totalQuantity = 0;
        $details = [];

        foreach ($items as $item) {
            $value = $item->getQuantity() * $item->getAverageCost();
            $totalValue += $value;
            $totalQuantity += $item->getQuantity();
            
            $details[] = [
                'product_id' => $item->getProductId(),
                'warehouse_id' => $item->getWarehouseId(),
                'quantity' => $item->getQuantity(),
                'average_cost' => $item->getAverageCost(),
                'total_value' => $value
            ];
        }

        return [
            'total_value' => $totalValue,
            'total_quantity' => $totalQuantity,
            'items' => $details
        ];
    }

    /**
     * Get stock movements history
     */
    public function getMovementHistory(
        ?int $productId = null,
        ?int $warehouseId = null,
        ?string $movementType = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        return $this->movementRepository->findWithFilters([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'movement_type' => $movementType
        ], $limit, $offset);
    }

    /**
     * Create inventory item
     */
    private function createInventoryItem(int $productId, int $warehouseId): InventoryItem
    {
        return $this->inventoryRepository->create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'reserved_quantity' => 0,
            'average_cost' => 0,
            'last_movement_at' => now()
        ]);
    }

    /**
     * Update cost basis based on tracking method
     */
    private function updateCostBasis(InventoryItem $item, int $quantity, float $costPerUnit): void
    {
        switch ($this->trackingMethod) {
            case 'weighted_average':
                $totalCost = ($item->getQuantity() * $item->getAverageCost()) + ($quantity * $costPerUnit);
                $totalQuantity = $item->getQuantity() + $quantity;
                $newAverageCost = $totalQuantity > 0 ? $totalCost / $totalQuantity : 0;
                
                $this->inventoryRepository->updateAverageCost($item->getId(), $newAverageCost);
                break;
                
            case 'fifo':
            case 'lifo':
                // For FIFO/LIFO, we store individual batches
                $this->storeBatch($item->getId(), $quantity, $costPerUnit);
                break;
                
            case 'specific_identification':
                // Each unit has its own cost - handled separately
                break;
        }
    }

    /**
     * Update cost basis for stock reduction
     */
    private function updateCostBasisForReduction(InventoryItem $item, int $quantity): void
    {
        // Implementation depends on tracking method
        // For weighted average, no change needed
        // For FIFO/LIFO, remove from appropriate batches
    }

    /**
     * Calculate cost per unit for stock reduction
     */
    private function calculateCostPerUnit(InventoryItem $item, int $quantity): float
    {
        switch ($this->trackingMethod) {
            case 'weighted_average':
                return $item->getAverageCost();
                
            case 'fifo':
                return $this->calculateFifoCost($item->getId(), $quantity);
                
            case 'lifo':
                return $this->calculateLifoCost($item->getId(), $quantity);
                
            default:
                return $item->getAverageCost();
        }
    }

    /**
     * Store batch information for FIFO/LIFO tracking
     */
    private function storeBatch(int $inventoryItemId, int $quantity, float $costPerUnit): void
    {
        // Implementation would store batch information in separate table
    }

    /**
     * Calculate FIFO cost
     */
    private function calculateFifoCost(int $inventoryItemId, int $quantity): float
    {
        // Implementation would calculate cost based on oldest batches first
        return 0.0;
    }

    /**
     * Calculate LIFO cost
     */
    private function calculateLifoCost(int $inventoryItemId, int $quantity): float
    {
        // Implementation would calculate cost based on newest batches first
        return 0.0;
    }

    /**
     * Get reference type from reason
     */
    private function getReferenceType(string $reason): ?string
    {
        $mapping = [
            'sale' => 'order',
            'purchase' => 'purchase_order',
            'transfer_in' => 'transfer',
            'transfer_out' => 'transfer',
            'adjustment' => 'adjustment',
            'return' => 'return',
            'damage' => 'adjustment'
        ];

        return $mapping[$reason] ?? null;
    }
}