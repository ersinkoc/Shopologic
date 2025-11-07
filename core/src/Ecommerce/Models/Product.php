<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\MultiStore\Traits\ShareableAcrossStores;

class Product extends Model
{
    use ShareableAcrossStores;
    protected string $table = 'products';
    
    protected array $fillable = [
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'price',
        'compare_price',
        'cost',
        'weight',
        'width',
        'height',
        'depth',
        'quantity',
        'track_quantity',
        'allow_backorder',
        'requires_shipping',
        'is_digital',
        'is_active',
        'featured',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];
    
    protected array $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:3',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'depth' => 'decimal:2',
        'quantity' => 'integer',
        'track_quantity' => 'boolean',
        'allow_backorder' => 'boolean',
        'requires_shipping' => 'boolean',
        'is_digital' => 'boolean',
        'is_active' => 'boolean',
        'featured' => 'boolean',
    ];

    /**
     * Get product categories
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    /**
     * Get product images
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * Get product variants
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get product attributes
     */
    public function attributes()
    {
        return $this->hasMany(ProductAttribute::class);
    }

    /**
     * Get product reviews
     */
    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Get product tags
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'product_tags');
    }

    /**
     * Check if product is in stock
     */
    public function inStock(): bool
    {
        if (!$this->track_quantity) {
            return true;
        }
        
        return $this->quantity > 0 || $this->allow_backorder;
    }

    /**
     * Get available quantity
     */
    public function getAvailableQuantity(): int
    {
        if (!$this->track_quantity) {
            return PHP_INT_MAX;
        }
        
        return max(0, $this->quantity);
    }

    /**
     * Decrease stock (with transaction and row-level locking to prevent race conditions)
     */
    public function decreaseStock(int $quantity): bool
    {
        if (!$this->track_quantity) {
            return true;
        }

        // Use database transaction with row-level locking to prevent overselling
        $connection = $this->getConnection();

        try {
            // Start transaction
            $connection->beginTransaction();

            // Lock the row for update (SELECT FOR UPDATE)
            $query = "SELECT quantity, allow_backorder FROM {$this->table} WHERE id = ? FOR UPDATE";
            $stmt = $connection->prepare($query);
            $stmt->execute([$this->id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $connection->rollback();
                return false;
            }

            $currentQuantity = (int) $row['quantity'];
            $allowBackorder = (bool) $row['allow_backorder'];

            // Check if we have enough stock
            if ($currentQuantity < $quantity && !$allowBackorder) {
                $connection->rollback();
                return false;
            }

            // Prevent selling when quantity is 0 or negative
            if ($currentQuantity <= 0 && !$allowBackorder) {
                $connection->rollback();
                return false;
            }

            // Update quantity
            $newQuantity = $currentQuantity - $quantity;
            $updateQuery = "UPDATE {$this->table} SET quantity = ? WHERE id = ?";
            $updateStmt = $connection->prepare($updateQuery);
            $result = $updateStmt->execute([$newQuantity, $this->id]);

            if ($result) {
                $connection->commit();
                // Update model instance
                $this->quantity = $newQuantity;
                return true;
            } else {
                $connection->rollback();
                return false;
            }
        } catch (\Exception $e) {
            $connection->rollback();
            error_log("Stock decrease failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increase stock
     */
    public function increaseStock(int $quantity): bool
    {
        if (!$this->track_quantity) {
            return true;
        }
        
        $this->quantity += $quantity;
        return $this->save();
    }

    /**
     * Get formatted price
     */
    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentage(): ?int
    {
        if (!$this->compare_price || $this->compare_price <= $this->price) {
            return null;
        }
        
        $discount = (($this->compare_price - $this->price) / $this->compare_price) * 100;
        return (int) round($discount);
    }

    /**
     * Get primary image
     */
    public function getPrimaryImage(): ?ProductImage
    {
        return $this->images()->where('is_primary', true)->first() 
            ?? $this->images()->first();
    }

    /**
     * Generate unique slug
     * BUG FIX (BUG-006): Added iteration limit to prevent infinite loops
     */
    public function generateSlug(): string
    {
        $baseSlug = $this->slugify($this->name);
        $slug = $baseSlug;
        $counter = 1;
        $maxAttempts = 1000; // Prevent infinite loops

        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            if ($counter >= $maxAttempts) {
                // If we've tried 1000 variations, use a UUID suffix for uniqueness
                $slug = $baseSlug . '-' . substr(md5(uniqid()), 0, 8);
                break;
            }

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
        // Convert to lowercase
        $text = strtolower($text);
        
        // Replace non-alphanumeric characters with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        // Remove leading/trailing hyphens
        $text = trim($text, '-');
        
        return $text;
    }

    /**
     * Scope for active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured products
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Scope for in-stock products
     */
    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_quantity', false)
              ->orWhere('quantity', '>', 0)
              ->orWhere('allow_backorder', true);
        });
    }

    /**
     * Calculate average rating
     */
    public function getAverageRating(): float
    {
        $avg = $this->reviews()->where('is_approved', true)->avg('rating');
        return round($avg ?? 0, 1);
    }

    /**
     * Get review count
     */
    public function getReviewCount(): int
    {
        return $this->reviews()->where('is_approved', true)->count();
    }

    /**
     * Get store-specific price
     */
    public function getStorePrice($storeId): ?float
    {
        $storeData = $this->getStoreData($storeId);
        return $storeData ? ($storeData['price'] ?? $this->price) : $this->price;
    }

    /**
     * Get store-specific stock
     */
    public function getStoreStock($storeId): ?int
    {
        $storeData = $this->getStoreData($storeId);
        return $storeData ? ($storeData['stock'] ?? $this->quantity) : $this->quantity;
    }

    /**
     * Check if product is active in store
     */
    public function isActiveInStore($storeId): bool
    {
        $storeData = $this->getStoreData($storeId);
        return $storeData ? ($storeData['is_active'] ?? true) : false;
    }

    /**
     * Override pivot columns for store relationship
     */
    protected function getStorePivotColumns(): array
    {
        return ['price', 'stock', 'is_active', 'meta'];
    }
}