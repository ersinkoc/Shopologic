<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping\Methods;

use Shopologic\Core\Ecommerce\Cart\Cart;
use Shopologic\Core\Ecommerce\Shipping\Address;

class FlatRateMethod implements ShippingMethodInterface
{
    protected float $rate = 10.00;
    protected int $estimatedDays = 5;

    public function getName(): string
    {
        return 'flat_rate';
    }

    public function getDisplayName(): string
    {
        return 'Standard Shipping';
    }

    public function getDescription(): string
    {
        return 'Fixed rate shipping for all orders';
    }

    public function isAvailable(Cart $cart, Address $shippingAddress): bool
    {
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
        return $this->rate;
    }

    public function getEstimatedDays(): int
    {
        return $this->estimatedDays;
    }

    public function getSupportedCountries(): array
    {
        return ['US', 'CA', 'GB', 'AU', 'NZ'];
    }

    /**
     * Set the flat rate
     */
    public function setRate(float $rate): void
    {
        $this->rate = $rate;
    }

    /**
     * Set estimated days
     */
    public function setEstimatedDays(int $days): void
    {
        $this->estimatedDays = $days;
    }
}