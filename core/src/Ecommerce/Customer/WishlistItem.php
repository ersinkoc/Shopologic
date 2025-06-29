<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Customer;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Ecommerce\Models\Product;

class WishlistItem extends Model
{
    protected string $table = 'wishlist_items';
    
    protected array $fillable = [
        'user_id',
        'product_id',
    ];
    
    protected array $casts = [
        'user_id' => 'integer',
        'product_id' => 'integer',
    ];

    /**
     * Get the customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}