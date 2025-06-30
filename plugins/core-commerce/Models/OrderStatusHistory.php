<?php

declare(strict_types=1);
namespace Shopologic\Plugins\CoreCommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    protected string $table = 'order_status_history';
    
    protected array $fillable = [
        'order_id', 'from_status', 'to_status',
        'comment', 'user_id'
    ];
    
    protected array $casts = [
        'order_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime'
    ];
    
    public $timestamps = false;
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}