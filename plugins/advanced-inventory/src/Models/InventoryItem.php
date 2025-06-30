<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory\Models;

use JsonSerializable;

class InventoryItem implements JsonSerializable
{
    private int $id;
    private int $productId;
    private int $warehouseId;
    private int $quantity;
    private int $reservedQuantity;
    private float $averageCost;
    private ?string $lastMovementAt;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(
        int $id,
        int $productId,
        int $warehouseId,
        int $quantity,
        int $reservedQuantity = 0,
        float $averageCost = 0.0,
        ?string $lastMovementAt = null,
        string $createdAt = '',
        string $updatedAt = ''
    ) {
        $this->id = $id;
        $this->productId = $productId;
        $this->warehouseId = $warehouseId;
        $this->quantity = $quantity;
        $this->reservedQuantity = $reservedQuantity;
        $this->averageCost = $averageCost;
        $this->lastMovementAt = $lastMovementAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getWarehouseId(): int
    {
        return $this->warehouseId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getReservedQuantity(): int
    {
        return $this->reservedQuantity;
    }

    public function setReservedQuantity(int $reservedQuantity): void
    {
        $this->reservedQuantity = $reservedQuantity;
    }

    public function getAvailableQuantity(): int
    {
        return max(0, $this->quantity - $this->reservedQuantity);
    }

    public function getAverageCost(): float
    {
        return $this->averageCost;
    }

    public function setAverageCost(float $averageCost): void
    {
        $this->averageCost = $averageCost;
    }

    public function getTotalValue(): float
    {
        return $this->quantity * $this->averageCost;
    }

    public function getLastMovementAt(): ?string
    {
        return $this->lastMovementAt;
    }

    public function setLastMovementAt(?string $lastMovementAt): void
    {
        $this->lastMovementAt = $lastMovementAt;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    public function isInStock(): bool
    {
        return $this->getAvailableQuantity() > 0;
    }

    public function isLowStock(int $threshold = 10): bool
    {
        return $this->getAvailableQuantity() <= $threshold && $this->getAvailableQuantity() > 0;
    }

    public function isOutOfStock(): bool
    {
        return $this->getAvailableQuantity() <= 0;
    }

    public function canFulfill(int $requestedQuantity): bool
    {
        return $this->getAvailableQuantity() >= $requestedQuantity;
    }

    public function reserve(int $quantity): bool
    {
        if ($this->getAvailableQuantity() >= $quantity) {
            $this->reservedQuantity += $quantity;
            return true;
        }
        return false;
    }

    public function release(int $quantity): bool
    {
        if ($this->reservedQuantity >= $quantity) {
            $this->reservedQuantity -= $quantity;
            return true;
        }
        return false;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'warehouse_id' => $this->warehouseId,
            'quantity' => $this->quantity,
            'reserved_quantity' => $this->reservedQuantity,
            'available_quantity' => $this->getAvailableQuantity(),
            'average_cost' => $this->averageCost,
            'total_value' => $this->getTotalValue(),
            'last_movement_at' => $this->lastMovementAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'status' => [
                'in_stock' => $this->isInStock(),
                'low_stock' => $this->isLowStock(),
                'out_of_stock' => $this->isOutOfStock()
            ]
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}