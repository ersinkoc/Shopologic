<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Contracts;

interface CustomerServiceInterface
{
    /**
     * Find customer by ID
     */
    public function find(int $id): ?array;
    
    /**
     * Find customer by email
     */
    public function findByEmail(string $email): ?array;
    
    /**
     * Create new customer
     */
    public function create(array $data): array;
    
    /**
     * Update customer
     */
    public function update(int $id, array $data): bool;
    
    /**
     * Get customer orders
     */
    public function getOrders(int $customerId): array;
    
    /**
     * Get customer profile
     */
    public function getProfile(int $customerId): array;
}