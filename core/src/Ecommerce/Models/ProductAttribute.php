<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;

class ProductAttribute extends Model
{
    protected string $table = 'product_attributes';
    
    protected array $fillable = [
        'product_id',
        'name',
        'value',
        'group',
        'position',
    ];
    
    protected array $casts = [
        'product_id' => 'integer',
        'position' => 'integer',
    ];

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}