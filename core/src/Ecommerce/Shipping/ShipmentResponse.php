<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping;

class ShipmentResponse
{
    private bool $success;
    private ?string $shipmentId;
    private ?string $trackingNumber;
    private ?string $labelUrl;
    private ?float $cost;
    private ?string $estimatedDelivery;
    private ?string $error;

    public function __construct(
        bool $success,
        ?string $shipmentId = null,
        ?string $trackingNumber = null,
        ?string $labelUrl = null,
        ?float $cost = null,
        ?string $estimatedDelivery = null,
        ?string $error = null
    ) {
        $this->success = $success;
        $this->shipmentId = $shipmentId;
        $this->trackingNumber = $trackingNumber;
        $this->labelUrl = $labelUrl;
        $this->cost = $cost;
        $this->estimatedDelivery = $estimatedDelivery;
        $this->error = $error;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getShipmentId(): ?string
    {
        return $this->shipmentId;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function getLabelUrl(): ?string
    {
        return $this->labelUrl;
    }

    public function getCost(): ?float
    {
        return $this->cost;
    }

    public function getEstimatedDelivery(): ?string
    {
        return $this->estimatedDelivery;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}