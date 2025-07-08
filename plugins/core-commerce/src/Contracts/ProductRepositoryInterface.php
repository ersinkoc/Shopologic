<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Contracts;

interface ProductRepositoryInterface
{
    /**
     * Find a product by ID
     */
    public function find(int $id): ?array;
    
    /**
     * Find a product by slug
     */
    public function findBySlug(string $slug): ?array;
    
    /**
     * Get all products with pagination
     */
    public function paginate(int $perPage = 20, int $page = 1): array;
    
    /**
     * Create a new product
     */
    public function create(array $data): array;
    
    /**
     * Update a product
     */
    public function update(int $id, array $data): bool;
    
    /**
     * Delete a product
     */
    public function delete(int $id): bool;
    
    /**
     * Search products
     */
    public function search(string $query, array $filters = []): array;
}