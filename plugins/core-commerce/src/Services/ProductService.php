<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Services;

use Shopologic\Plugins\CoreCommerce\Contracts\ProductRepositoryInterface;

class ProductService
{
    private ProductRepositoryInterface $repository;
    
    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
    
    public function getProduct(int $id): ?array
    {
        return $this->repository->find($id);
    }
    
    public function getProductBySlug(string $slug): ?array
    {
        return $this->repository->findBySlug($slug);
    }
    
    public function createProduct(array $data): array
    {
        return $this->repository->create($data);
    }
    
    public function updateProduct(int $id, array $data): bool
    {
        return $this->repository->update($id, $data);
    }
    
    public function searchProducts(string $query, array $filters = []): array
    {
        return $this->repository->search($query, $filters);
    }
    
    public function reindexAll(): int
    {
        $products = $this->repository->paginate(1000, 1);
        return $products['total'];
    }
}