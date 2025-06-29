<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;

class Category extends Model
{
    protected string $table = 'categories';
    
    protected array $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'image',
        'position',
        'is_active',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];
    
    protected array $casts = [
        'parent_id' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get parent category
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get child categories
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position');
    }

    /**
     * Get all descendants
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get products in this category
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_categories');
    }

    /**
     * Get all products including from child categories
     */
    public function allProducts()
    {
        $categoryIds = $this->getAllDescendantIds();
        $categoryIds[] = $this->id;
        
        return Product::whereHas('categories', function ($query) use ($categoryIds) {
            $query->whereIn('categories.id', $categoryIds);
        });
    }

    /**
     * Get all descendant IDs
     */
    public function getAllDescendantIds(): array
    {
        $ids = [];
        
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }
        
        return $ids;
    }

    /**
     * Get all ancestors
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $parent = $this->parent;
        
        while ($parent) {
            $ancestors[] = $parent;
            $parent = $parent->parent;
        }
        
        return array_reverse($ancestors);
    }

    /**
     * Get breadcrumb path
     */
    public function getBreadcrumb(): array
    {
        $breadcrumb = $this->getAncestors();
        $breadcrumb[] = $this;
        
        return $breadcrumb;
    }

    /**
     * Get full path (for URLs)
     */
    public function getPath(): string
    {
        $segments = [];
        
        foreach ($this->getBreadcrumb() as $category) {
            $segments[] = $category->slug;
        }
        
        return implode('/', $segments);
    }

    /**
     * Check if category has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if category is root
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if category is leaf
     */
    public function isLeaf(): bool
    {
        return !$this->hasChildren();
    }

    /**
     * Get depth level
     */
    public function getDepth(): int
    {
        return count($this->getAncestors());
    }

    /**
     * Move category to new parent
     */
    public function moveTo(?Category $parent): bool
    {
        // Prevent moving to self or descendants
        if ($parent) {
            if ($parent->id === $this->id) {
                return false;
            }
            
            $descendantIds = $this->getAllDescendantIds();
            if (in_array($parent->id, $descendantIds)) {
                return false;
            }
        }
        
        $this->parent_id = $parent?->id;
        return $this->save();
    }

    /**
     * Reorder categories
     */
    public static function reorder(array $order): void
    {
        foreach ($order as $position => $id) {
            static::where('id', $id)->update(['position' => $position]);
        }
    }

    /**
     * Generate unique slug
     */
    public function generateSlug(): string
    {
        $baseSlug = $this->slugify($this->name);
        $slug = $baseSlug;
        $counter = 1;
        
        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Convert string to slug
     */
    protected function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        
        return $text;
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for root categories
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get tree structure
     */
    public static function getTree(): array
    {
        $categories = static::orderBy('position')->get();
        return static::buildTree($categories);
    }

    /**
     * Build tree from flat list
     */
    protected static function buildTree($categories, $parentId = null): array
    {
        $tree = [];
        
        foreach ($categories as $category) {
            if ($category->parent_id == $parentId) {
                $node = $category->toArray();
                $children = static::buildTree($categories, $category->id);
                
                if ($children) {
                    $node['children'] = $children;
                }
                
                $tree[] = $node;
            }
        }
        
        return $tree;
    }
}