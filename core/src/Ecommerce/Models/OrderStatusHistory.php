<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;

class OrderStatusHistory extends Model
{
    protected string $table = 'order_status_history';
    
    protected array $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'comment',
        'user_id',
    ];
    
    protected array $casts = [
        'order_id' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * Get the order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}