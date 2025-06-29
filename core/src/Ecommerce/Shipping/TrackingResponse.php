<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping;

class TrackingResponse
{
    private bool $success;
    private string $trackingNumber;
    private ?string $status;
    private ?string $statusDescription;
    private ?string $estimatedDelivery;
    private ?string $actualDelivery;
    private ?string $currentLocation;
    private array $events;
    private ?string $error;

    public function __construct(
        bool $success,
        string $trackingNumber,
        ?string $status = null,
        ?string $statusDescription = null,
        ?string $estimatedDelivery = null,
        ?string $actualDelivery = null,
        ?string $currentLocation = null,
        array $events = [],
        ?string $error = null
    ) {
        $this->success = $success;
        $this->trackingNumber = $trackingNumber;
        $this->status = $status;
        $this->statusDescription = $statusDescription;
        $this->estimatedDelivery = $estimatedDelivery;
        $this->actualDelivery = $actualDelivery;
        $this->currentLocation = $currentLocation;
        $this->events = $events;
        $this->error = $error;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getTrackingNumber(): string
    {
        return $this->trackingNumber;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getStatusDescription(): ?string
    {
        return $this->statusDescription;
    }

    public function getEstimatedDelivery(): ?string
    {
        return $this->estimatedDelivery;
    }

    public function getActualDelivery(): ?string
    {
        return $this->actualDelivery;
    }

    public function getCurrentLocation(): ?string
    {
        return $this->currentLocation;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered' || $this->actualDelivery !== null;
    }
}