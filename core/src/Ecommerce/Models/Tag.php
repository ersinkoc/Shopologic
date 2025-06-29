<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;

class Tag extends Model
{
    protected string $table = 'tags';
    
    protected array $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get products with this tag
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_tags');
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
}