<?php

namespace Shopologic\Plugins\CoreCommerce\Contracts;

use Shopologic\Plugins\CoreCommerce\Models\Customer;
use Shopologic\Plugins\CoreCommerce\Models\CustomerAddress;
use Shopologic\Core\Database\Collection;

interface CustomerServiceInterface
{
    public function find(int $id): ?Customer;
    
    public function findByEmail(string $email): ?Customer;
    
    public function create(array $data): Customer;
    
    public function update(int $id, array $data): bool;
    
    public function delete(int $id): bool;
    
    public function paginate(array $filters = [], int $perPage = 20, int $page = 1): array;
    
    public function search(string $query): Collection;
    
    public function addAddress(int $customerId, array $addressData): CustomerAddress;
    
    public function updateAddress(int $addressId, array $addressData): bool;
    
    public function deleteAddress(int $addressId): bool;
    
    public function setDefaultAddress(int $customerId, int $addressId, string $type = 'billing'): bool;
    
    public function getAddresses(int $customerId): Collection;
    
    public function getOrders(int $customerId): Collection;
    
    public function getGroups(int $customerId): Collection;
    
    public function addToGroup(int $customerId, int $groupId): bool;
    
    public function removeFromGroup(int $customerId, int $groupId): bool;
    
    public function updateLastLogin(int $customerId): bool;
    
    public function getTotalSpent(int $customerId): float;
    
    public function getOrderCount(int $customerId): int;
}