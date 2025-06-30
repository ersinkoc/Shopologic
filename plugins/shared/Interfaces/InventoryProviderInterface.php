<?php

declare(strict_types=1);

namespace Shopologic\Plugins\Shared\Interfaces;

/**
 * Interface for plugins that provide inventory data
 * Allows integration with inventory management systems
 */
interface InventoryProviderInterface
{
    /**
     * Get current stock level for a product
     */
    public function getStockLevel(string $productId, string $locationId = null): int;
    
    /**
     * Check if product is in stock
     */
    public function isInStock(string $productId, int $quantity = 1, string $locationId = null): bool;
    
    /**
     * Reserve inventory for an order
     */
    public function reserveInventory(string $productId, int $quantity, string $orderId, string $locationId = null): bool;
    
    /**
     * Release reserved inventory
     */
    public function releaseReservation(string $reservationId): bool;
    
    /**
     * Get inventory movements for analytics
     */
    public function getInventoryMovements(\DateTime $startDate, \DateTime $endDate, array $filters = []): array;
    
    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts(): array;
    
    /**
     * Subscribe to inventory level changes
     */
    public function subscribeToInventoryChanges(string $productId, callable $callback): void;
}