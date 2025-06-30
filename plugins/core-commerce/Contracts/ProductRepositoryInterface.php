<?php

declare(strict_types=1);
namespace Shopologic\Plugins\CoreCommerce\Contracts;

use Shopologic\Plugins\CoreCommerce\Models\Product;
use Shopologic\Core\Database\Collection;

interface ProductRepositoryInterface
{
    public function find(int $id): ?Product;
    
    public function findBySlug(string $slug): ?Product;
    
    public function findBySku(string $sku): ?Product;
    
    public function all(): Collection;
    
    public function paginate(int $perPage = 20, int $page = 1): array;
    
    public function search(string $query, array $filters = []): Collection;
    
    public function findByCategory(int $categoryId): Collection;
    
    public function findFeatured(int $limit = 10): Collection;
    
    public function findOnSale(int $limit = 10): Collection;
    
    public function create(array $data): Product;
    
    public function update(int $id, array $data): bool;
    
    public function delete(int $id): bool;
    
    public function updateStock(int $id, int $quantity): bool;
    
    public function decrementStock(int $id, int $quantity): bool;
    
    public function incrementStock(int $id, int $quantity): bool;
}