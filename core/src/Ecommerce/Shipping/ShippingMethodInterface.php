<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping;

use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Models\CustomerAddress;

interface ShippingMethodInterface
{
    /**
     * Get the unique identifier for this shipping method
     */
    public function getId(): string;

    /**
     * Get the display name for this shipping method
     */
    public function getName(): string;

    /**
     * Get the description of this shipping method
     */
    public function getDescription(): string;

    /**
     * Check if this shipping method is available for the given order
     */
    public function isAvailable(Order $order): bool;

    /**
     * Calculate shipping rates for the given shipping request
     */
    public function calculateRates(ShippingRequest $request): array;

    /**
     * Create a shipment for the given order
     */
    public function createShipment(Order $order, array $options = []): ShipmentResponse;

    /**
     * Generate a shipping label
     */
    public function generateLabel(string $shipmentId): LabelResponse;

    /**
     * Track a shipment
     */
    public function trackShipment(string $trackingNumber): TrackingResponse;

    /**
     * Cancel a shipment
     */
    public function cancelShipment(string $shipmentId): bool;

    /**
     * Get available services for this shipping method
     */
    public function getAvailableServices(): array;

    /**
     * Validate a shipping address
     */
    public function validateAddress(CustomerAddress $address): AddressValidationResponse;

    /**
     * Schedule a pickup
     */
    public function schedulePickup(PickupRequest $request): PickupResponse;
}