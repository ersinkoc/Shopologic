<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping\Methods;

use Shopologic\Core\Ecommerce\Cart\Cart;
use Shopologic\Core\Ecommerce\Shipping\Address;

class FreeShippingMethod implements ShippingMethodInterface
{
    protected float $minimumAmount = 50.00;
    protected int $estimatedDays = 7;

    public function getName(): string
    {
        return 'free_shipping';
    }

    public function getDisplayName(): string
    {
        return 'Free Shipping';
    }

    public function getDescription(): string
    {
        return "Free shipping on orders over \${$this->minimumAmount}";
    }

    public function isAvailable(Cart $cart, Address $shippingAddress): bool
    {
        // Check if cart meets minimum amount
        if ($cart->getSubtotal() < $this->minimumAmount) {
            return false;
        }
        
        // Check if any items require shipping
        foreach ($cart->items() as $item) {
            if ($item->product->requires_shipping) {
                return true;
            }
        }
        
        return false;
    }

    public function calculateRate(Cart $cart, Address $shippingAddress): ?float
    {
        return 0.00;
    }

    public function getEstimatedDays(): int
    {
        return $this->estimatedDays;
    }

    public function getSupportedCountries(): array
    {
        return ['US', 'CA'];
    }

    /**
     * Set minimum amount for free shipping
     */
    public function setMinimumAmount(float $amount): void
    {
        $this->minimumAmount = $amount;
    }

    /**
     * Set estimated days
     */
    public function setEstimatedDays(int $days): void
    {
        $this->estimatedDays = $days;
    }
}