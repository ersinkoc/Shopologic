<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping\Methods;

use Shopologic\Core\Ecommerce\Cart\Cart;
use Shopologic\Core\Ecommerce\Shipping\Address;

class PickupMethod implements ShippingMethodInterface
{
    protected array $locations = [
        [
            'name' => 'Main Store',
            'address' => '123 Main St, City, State 12345',
            'hours' => 'Mon-Fri 9AM-6PM, Sat 10AM-4PM',
        ],
    ];

    public function getName(): string
    {
        return 'pickup';
    }

    public function getDisplayName(): string
    {
        return 'Local Pickup';
    }

    public function getDescription(): string
    {
        return 'Pick up your order at our store';
    }

    public function isAvailable(Cart $cart, Address $shippingAddress): bool
    {
        // Always available if we have locations
        return !empty($this->locations);
    }

    public function calculateRate(Cart $cart, Address $shippingAddress): ?float
    {
        return 0.00;
    }

    public function getEstimatedDays(): int
    {
        return 0; // Same day pickup
    }

    public function getSupportedCountries(): array
    {
        return ['US']; // Only where stores are located
    }

    /**
     * Get pickup locations
     */
    public function getLocations(): array
    {
        return $this->locations;
    }

    /**
     * Set pickup locations
     */
    public function setLocations(array $locations): void
    {
        $this->locations = $locations;
    }

    /**
     * Add a pickup location
     */
    public function addLocation(array $location): void
    {
        $this->locations[] = $location;
    }
}