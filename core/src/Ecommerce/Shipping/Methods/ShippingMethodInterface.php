<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping\Methods;

use Shopologic\Core\Ecommerce\Cart\Cart;
use Shopologic\Core\Ecommerce\Shipping\Address;

interface ShippingMethodInterface
{
    /**
     * Get method name
     */
    public function getName(): string;

    /**
     * Get display name
     */
    public function getDisplayName(): string;

    /**
     * Get description
     */
    public function getDescription(): string;

    /**
     * Check if method is available
     */
    public function isAvailable(Cart $cart, Address $shippingAddress): bool;

    /**
     * Calculate shipping rate
     */
    public function calculateRate(Cart $cart, Address $shippingAddress): ?float;

    /**
     * Get estimated delivery days
     */
    public function getEstimatedDays(): int;

    /**
     * Get supported countries
     */
    public function getSupportedCountries(): array;
}