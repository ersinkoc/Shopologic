<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Models;

use Shopologic\Core\Database\Model;

class StripeCustomer extends Model
{
    protected string $table = 'stripe_customers';
    
    protected array $fillable = [
        'customer_id',
        'stripe_id',
        'email',
        'name',
        'phone',
        'metadata',
        'default_payment_method',
        'livemode'
    ];
    
    protected array $casts = [
        'metadata' => 'json',
        'livemode' => 'boolean'
    ];
    
    /**
     * Get the Shopologic customer
     */
    public function customer()
    {
        return $this->belongsTo(\Shopologic\Core\Ecommerce\Models\Customer::class);
    }
    
    /**
     * Get payment methods
     */
    public function paymentMethods()
    {
        return $this->hasMany(StripePaymentMethod::class, 'stripe_customer_id');
    }
    
    /**
     * Get payments
     */
    public function payments()
    {
        return $this->hasMany(StripePayment::class, 'stripe_customer_id');
    }
    
    /**
     * Get default payment method
     */
    public function defaultPaymentMethod()
    {
        return $this->paymentMethods()
            ->where('payment_method_id', $this->default_payment_method)
            ->first();
    }
}