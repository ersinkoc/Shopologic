<?php

namespace Shopologic\Plugins\CoreCommerce\Contracts;

use Shopologic\Plugins\CoreCommerce\Models\Category;
use Shopologic\Core\Database\Collection;

interface CategoryRepositoryInterface
{
    public function find(int $id): ?Category;
    
    public function findBySlug(string $slug): ?Category;
    
    public function all(): Collection;
    
    public function getRootCategories(): Collection;
    
    public function getChildren(int $parentId): Collection;
    
    public function getTree(): array;
    
    public function create(array $data): Category;
    
    public function update(int $id, array $data): bool;
    
    public function delete(int $id): bool;
    
    public function move(int $categoryId, ?int $newParentId): bool;
}