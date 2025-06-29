<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Models;

use Shopologic\Core\Database\Model;

class StripeRefund extends Model
{
    protected string $table = 'stripe_refunds';
    
    protected array $fillable = [
        'stripe_payment_id',
        'refund_id',
        'amount',
        'currency',
        'status',
        'reason',
        'failure_reason',
        'metadata'
    ];
    
    protected array $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'json'
    ];
    
    /**
     * Get the payment
     */
    public function payment()
    {
        return $this->belongsTo(StripePayment::class, 'stripe_payment_id');
    }
    
    /**
     * Check if refund is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded';
    }
    
    /**
     * Check if refund failed
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'canceled']);
    }
    
    /**
     * Get formatted reason
     */
    public function getFormattedReason(): string
    {
        $reasons = [
            'duplicate' => 'Duplicate payment',
            'fraudulent' => 'Fraudulent payment',
            'requested_by_customer' => 'Customer request',
            'expired_uncaptured_charge' => 'Expired uncaptured charge'
        ];
        
        return $reasons[$this->reason] ?? $this->reason;
    }
}