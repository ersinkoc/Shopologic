<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Models;

use Shopologic\Core\Database\Model;

class StripePayment extends Model
{
    protected string $table = 'stripe_payments';
    
    protected array $fillable = [
        'order_id',
        'customer_id',
        'stripe_customer_id',
        'payment_intent_id',
        'charge_id',
        'amount',
        'captured_amount',
        'currency',
        'status',
        'capture_method',
        'payment_method_id',
        'payment_method_details',
        'metadata',
        'failure_reason',
        'failure_code',
        'livemode'
    ];
    
    protected array $casts = [
        'amount' => 'decimal:2',
        'captured_amount' => 'decimal:2',
        'payment_method_details' => 'json',
        'metadata' => 'json',
        'livemode' => 'boolean'
    ];
    
    /**
     * Get the order
     */
    public function order()
    {
        return $this->belongsTo(\Shopologic\Core\Ecommerce\Models\Order::class);
    }
    
    /**
     * Get the customer
     */
    public function customer()
    {
        return $this->belongsTo(\Shopologic\Core\Ecommerce\Models\Customer::class);
    }
    
    /**
     * Get the Stripe customer
     */
    public function stripeCustomer()
    {
        return $this->belongsTo(StripeCustomer::class, 'stripe_customer_id');
    }
    
    /**
     * Get refunds
     */
    public function refunds()
    {
        return $this->hasMany(StripeRefund::class, 'stripe_payment_id');
    }
    
    /**
     * Check if payment is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded';
    }
    
    /**
     * Check if payment requires action
     */
    public function requiresAction(): bool
    {
        return $this->status === 'requires_action';
    }
    
    /**
     * Check if payment can be refunded
     */
    public function canBeRefunded(): bool
    {
        return in_array($this->status, ['succeeded', 'partially_refunded']) && 
               $this->charge_id !== null;
    }
    
    /**
     * Get refunded amount
     */
    public function getRefundedAmount(): float
    {
        return (float) $this->refunds()
            ->where('status', 'succeeded')
            ->sum('amount');
    }
    
    /**
     * Get remaining refundable amount
     */
    public function getRefundableAmount(): float
    {
        return $this->amount - $this->getRefundedAmount();
    }
}