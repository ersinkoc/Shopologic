<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;

class ProductImage extends Model
{
    protected string $table = 'product_images';
    
    protected array $fillable = [
        'product_id',
        'path',
        'filename',
        'alt_text',
        'title',
        'position',
        'is_primary',
    ];
    
    protected array $casts = [
        'product_id' => 'integer',
        'position' => 'integer',
        'is_primary' => 'boolean',
    ];

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get full URL
     */
    public function getUrl(): string
    {
        return '/storage/' . $this->path;
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrl(int $width = 150, int $height = 150): string
    {
        // In a real implementation, this would generate/return a resized image
        return $this->getUrl();
    }

    /**
     * Set as primary image
     */
    public function setAsPrimary(): bool
    {
        // Remove primary flag from other images
        static::where('product_id', $this->product_id)
              ->where('id', '!=', $this->id)
              ->update(['is_primary' => false]);
        
        $this->is_primary = true;
        return $this->save();
    }
}