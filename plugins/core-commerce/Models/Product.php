<?php

declare(strict_types=1);
namespace Shopologic\Plugins\CoreCommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Relations\HasMany;
use Shopologic\Core\Database\Relations\BelongsTo;
use Shopologic\Core\Database\Relations\BelongsToMany;
use Shopologic\Core\Database\Relations\MorphMany;
use Shopologic\Core\Database\Builder;

class Product extends Model
{
    protected string $table = 'products';
    
    protected array $fillable = [
        'name', 'slug', 'description', 'short_description',
        'sku', 'price', 'sale_price', 'cost_price',
        'status', 'type', 'weight', 'dimensions',
        'category_id', 'brand_id', 'tax_class_id',
        'manage_stock', 'stock_quantity', 'stock_status',
        'is_featured', 'meta_title', 'meta_description',
        'meta_keywords', 'attributes', 'options'
    ];
    
    protected array $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:3',
        'dimensions' => 'json',
        'attributes' => 'json',
        'options' => 'json',
        'is_featured' => 'boolean',
        'manage_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
    
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }
    
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
    
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
    
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')
                    ->withTimestamps();
    }
    
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tags')
                    ->withTimestamps();
    }
    
    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_related', 'product_id', 'related_id')
                    ->withTimestamps();
    }
    
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
    
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function($q) {
            $q->where('manage_stock', false)
              ->orWhere(function($q2) {
                  $q2->where('manage_stock', true)
                     ->where('stock_quantity', '>', 0);
              });
        });
    }
    
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
    
    public function scopeOnSale(Builder $query): Builder
    {
        return $query->whereNotNull('sale_price')
                     ->where('sale_price', '>', 0)
                     ->where('sale_price', '<', $this->getConnection()->raw('price'));
    }
    
    public function getEffectivePrice(): float
    {
        if ($this->isOnSale()) {
            return (float) $this->sale_price;
        }
        
        return (float) $this->price;
    }
    
    public function isOnSale(): bool
    {
        return $this->sale_price !== null && 
               $this->sale_price > 0 && 
               $this->sale_price < $this->price;
    }
    
    public function hasStock(int $quantity = 1): bool
    {
        if (!$this->manage_stock) {
            return $this->stock_status === 'in_stock';
        }
        
        return $this->stock_quantity >= $quantity;
    }
    
    public function getDiscountPercentage(): ?float
    {
        if (!$this->isOnSale()) {
            return null;
        }
        
        return round((($this->price - $this->sale_price) / $this->price) * 100, 0);
    }
    
    public function getAverageRating(): float
    {
        return $this->reviews()->average('rating') ?? 0;
    }
    
    public function getReviewCount(): int
    {
        return $this->reviews()->count();
    }
    
    public function getPrimaryImage(): ?ProductImage
    {
        return $this->images()->where('is_primary', true)->first() 
               ?? $this->images()->first();
    }
    
    public function getUrl(): string
    {
        return url('/products/' . $this->slug);
    }
    
    public function getTotalSold(): int
    {
        return $this->orderItems()
                    ->whereHas('order', function($query) {
                        $query->whereIn('status', ['completed', 'shipped', 'delivered']);
                    })
                    ->sum('quantity');
    }
}