<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping\Methods;

use Shopologic\Core\Ecommerce\Cart\Cart;
use Shopologic\Core\Ecommerce\Shipping\Address;

class WeightBasedMethod implements ShippingMethodInterface
{
    protected array $rates = [
        ['max_weight' => 1, 'cost' => 5.00],
        ['max_weight' => 5, 'cost' => 10.00],
        ['max_weight' => 10, 'cost' => 15.00],
        ['max_weight' => 20, 'cost' => 25.00],
        ['max_weight' => PHP_FLOAT_MAX, 'cost' => 40.00],
    ];
    
    protected int $estimatedDays = 3;

    public function getName(): string
    {
        return 'weight_based';
    }

    public function getDisplayName(): string
    {
        return 'Express Shipping';
    }

    public function getDescription(): string
    {
        return 'Fast shipping based on package weight';
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
        $totalWeight = $this->calculateTotalWeight($cart);
        
        foreach ($this->rates as $rate) {
            if ($totalWeight <= $rate['max_weight']) {
                return $rate['cost'];
            }
        }
        
        return null;
    }

    public function getEstimatedDays(): int
    {
        return $this->estimatedDays;
    }

    public function getSupportedCountries(): array
    {
        return ['US', 'CA', 'GB', 'AU', 'NZ', 'DE', 'FR'];
    }

    /**
     * Calculate total weight of cart items
     */
    protected function calculateTotalWeight(Cart $cart): float
    {
        $totalWeight = 0;
        
        foreach ($cart->items() as $item) {
            if ($item->product->requires_shipping) {
                $totalWeight += $item->getWeight();
            }
        }
        
        return $totalWeight;
    }

    /**
     * Set weight-based rates
     */
    public function setRates(array $rates): void
    {
        $this->rates = $rates;
    }

    /**
     * Set estimated days
     */
    public function setEstimatedDays(int $days): void
    {
        $this->estimatedDays = $days;
    }
}