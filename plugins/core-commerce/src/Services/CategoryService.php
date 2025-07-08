<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Services;

use Shopologic\Plugins\CoreCommerce\Contracts\CategoryRepositoryInterface;

class CategoryService
{
    private CategoryRepositoryInterface $repository;
    
    public function __construct(CategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
    
    public function getAllCategories(): array
    {
        return $this->repository->all();
    }
    
    public function getCategoryTree(): array
    {
        return $this->repository->tree();
    }
    
    public function getCategory(int $id): ?array
    {
        return $this->repository->find($id);
    }
    
    public function getCategoryBySlug(string $slug): ?array
    {
        return $this->repository->findBySlug($slug);
    }
    
    public function createCategory(array $data): array
    {
        return $this->repository->create($data);
    }
    
    public function updateCategory(int $id, array $data): bool
    {
        return $this->repository->update($id, $data);
    }
}