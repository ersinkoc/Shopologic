<?php

declare(strict_types=1);
namespace Shopologic\Plugins\CoreCommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Relations\BelongsTo;

class ProductImage extends Model
{
    protected string $table = 'product_images';
    
    protected array $fillable = [
        'product_id', 'url', 'alt_text', 'title',
        'is_primary', 'sort_order'
    ];
    
    protected array $casts = [
        'product_id' => 'integer',
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}