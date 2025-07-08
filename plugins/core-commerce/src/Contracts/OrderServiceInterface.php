<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Contracts;

interface OrderServiceInterface
{
    /**
     * Create order from cart
     */
    public function createFromCart(string $sessionId, array $customerData): array;
    
    /**
     * Get order by ID
     */
    public function find(int $id): ?array;
    
    /**
     * Get orders with pagination
     */
    public function paginate(int $perPage = 20, int $page = 1): array;
    
    /**
     * Update order status
     */
    public function updateStatus(int $orderId, string $status): bool;
    
    /**
     * Update inventory after order
     */
    public function updateInventory($order): void;
    
    /**
     * Generate daily report
     */
    public function generateDailyReport(string $date): array;
}