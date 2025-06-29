<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Models;

use Shopologic\Core\Database\Model;

class StripePaymentMethod extends Model
{
    protected string $table = 'stripe_payment_methods';
    
    protected array $fillable = [
        'stripe_customer_id',
        'payment_method_id',
        'type',
        'card',
        'billing_details',
        'is_default',
        'livemode'
    ];
    
    protected array $casts = [
        'card' => 'json',
        'billing_details' => 'json',
        'is_default' => 'boolean',
        'livemode' => 'boolean'
    ];
    
    /**
     * Get the Stripe customer
     */
    public function stripeCustomer()
    {
        return $this->belongsTo(StripeCustomer::class, 'stripe_customer_id');
    }
    
    /**
     * Get card brand
     */
    public function getCardBrand(): ?string
    {
        return $this->card['brand'] ?? null;
    }
    
    /**
     * Get card last 4 digits
     */
    public function getCardLast4(): ?string
    {
        return $this->card['last4'] ?? null;
    }
    
    /**
     * Get card expiry
     */
    public function getCardExpiry(): ?string
    {
        if (!isset($this->card['exp_month']) || !isset($this->card['exp_year'])) {
            return null;
        }
        
        return sprintf('%02d/%d', $this->card['exp_month'], $this->card['exp_year']);
    }
    
    /**
     * Format for display
     */
    public function getDisplayName(): string
    {
        if ($this->type === 'card') {
            return sprintf(
                '%s ending in %s',
                ucfirst($this->getCardBrand() ?? 'Card'),
                $this->getCardLast4() ?? '****'
            );
        }
        
        return ucfirst($this->type);
    }
}