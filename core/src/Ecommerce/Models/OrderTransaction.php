<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;

class OrderTransaction extends Model
{
    protected string $table = 'order_transactions';
    
    protected array $fillable = [
        'order_id',
        'type',
        'amount',
        'currency',
        'status',
        'gateway',
        'gateway_transaction_id',
        'gateway_response',
        'notes',
    ];
    
    protected array $casts = [
        'order_id' => 'integer',
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
    ];

    // Transaction types
    const TYPE_PAYMENT = 'payment';
    const TYPE_REFUND = 'refund';
    const TYPE_PARTIAL_REFUND = 'partial_refund';

    // Transaction statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}