<?php

namespace Shopologic\Plugins\CoreCommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Relations\HasMany;
use Shopologic\Core\Database\Relations\BelongsTo;
use Shopologic\Core\Database\Relations\BelongsToMany;
use Shopologic\Core\Database\Builder;

class Category extends Model
{
    protected string $table = 'categories';
    
    protected array $fillable = [
        'name', 'slug', 'description', 'parent_id',
        'image', 'icon', 'sort_order', 'is_active',
        'meta_title', 'meta_description', 'meta_keywords'
    ];
    
    protected array $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
    
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('name');
    }
    
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_categories')
                    ->withTimestamps();
    }
    
    public function allProducts(): BelongsToMany
    {
        return $this->products()->union(
            Product::query()
                ->join('product_categories', 'products.id', '=', 'product_categories.product_id')
                ->join('categories', 'product_categories.category_id', '=', 'categories.id')
                ->where('categories.parent_id', $this->id)
                ->select('products.*')
        );
    }
    
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
    
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
    
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
    
    public function getPath(): array
    {
        $path = [];
        $category = $this;
        
        while ($category) {
            array_unshift($path, $category);
            $category = $category->parent;
        }
        
        return $path;
    }
    
    public function getUrl(): string
    {
        return url('/categories/' . $this->slug);
    }
    
    public function getProductCount(): int
    {
        return $this->products()->count();
    }
    
    public function getTotalProductCount(): int
    {
        $count = $this->getProductCount();
        
        foreach ($this->children as $child) {
            $count += $child->getTotalProductCount();
        }
        
        return $count;
    }
    
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
    
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }
    
    public function getLevel(): int
    {
        $level = 0;
        $category = $this;
        
        while ($category->parent_id) {
            $level++;
            $category = $category->parent;
        }
        
        return $level;
    }
    
    public function getAllChildren(): array
    {
        $children = [];
        
        foreach ($this->children as $child) {
            $children[] = $child;
            $children = array_merge($children, $child->getAllChildren());
        }
        
        return $children;
    }
    
    public function getAllChildrenIds(): array
    {
        $ids = [];
        
        foreach ($this->getAllChildren() as $child) {
            $ids[] = $child->id;
        }
        
        return $ids;
    }
}