<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Contracts;

interface CategoryRepositoryInterface
{
    /**
     * Find a category by ID
     */
    public function find(int $id): ?array;
    
    /**
     * Find a category by slug
     */
    public function findBySlug(string $slug): ?array;
    
    /**
     * Get all categories
     */
    public function all(): array;
    
    /**
     * Get categories tree
     */
    public function tree(): array;
    
    /**
     * Create a new category
     */
    public function create(array $data): array;
    
    /**
     * Update a category
     */
    public function update(int $id, array $data): bool;
    
    /**
     * Delete a category
     */
    public function delete(int $id): bool;
}