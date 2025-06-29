<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;

class ProductVariantOption extends Model
{
    protected string $table = 'product_variant_options';
    
    protected array $fillable = [
        'variant_id',
        'option_name',
        'option_value',
    ];
    
    protected array $casts = [
        'variant_id' => 'integer',
    ];

    /**
     * Get the variant
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}