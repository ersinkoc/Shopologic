<?php

declare(strict_types=1);
namespace Shopologic\Plugins\CoreCommerce\Contracts;

use Shopologic\Plugins\CoreCommerce\Models\Order;
use Shopologic\Plugins\CoreCommerce\Models\Cart;
use Shopologic\Core\Database\Collection;

interface OrderServiceInterface
{
    public function createFromCart(Cart $cart, array $customerData, array $shippingData): Order;
    
    public function find(int $id): ?Order;
    
    public function findByNumber(string $orderNumber): ?Order;
    
    public function getByCustomer(int $customerId): Collection;
    
    public function paginate(array $filters = [], int $perPage = 20, int $page = 1): array;
    
    public function updateStatus(int $orderId, string $status, ?string $comment = null): bool;
    
    public function cancel(int $orderId, string $reason): bool;
    
    public function refund(int $orderId, ?float $amount = null, string $reason = ''): bool;
    
    public function ship(int $orderId, array $trackingData): bool;
    
    public function complete(int $orderId): bool;
    
    public function calculateTotals(Order $order): array;
    
    public function updateInventory(Order $order): bool;
    
    public function generateInvoice(int $orderId): string;
    
    public function generateDailyReport(string $date): array;
    
    public function getStatuses(): array;
    
    public function canTransitionTo(Order $order, string $newStatus): bool;
}