<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping;

class ShippingRate
{
    private string $serviceCode;
    private string $serviceName;
    private float $cost;
    private string $currency;
    private ?int $estimatedDays;
    private ?string $estimatedDeliveryDate;
    private array $metadata;

    public function __construct(
        string $serviceCode,
        string $serviceName,
        float $cost,
        string $currency,
        ?int $estimatedDays = null,
        ?string $estimatedDeliveryDate = null,
        array $metadata = []
    ) {
        $this->serviceCode = $serviceCode;
        $this->serviceName = $serviceName;
        $this->cost = $cost;
        $this->currency = $currency;
        $this->estimatedDays = $estimatedDays;
        $this->estimatedDeliveryDate = $estimatedDeliveryDate;
        $this->metadata = $metadata;
    }

    public function getServiceCode(): string
    {
        return $this->serviceCode;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getCost(): float
    {
        return $this->cost;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getEstimatedDays(): ?int
    {
        return $this->estimatedDays;
    }

    public function getEstimatedDeliveryDate(): ?string
    {
        return $this->estimatedDeliveryDate;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'service_code' => $this->serviceCode,
            'service_name' => $this->serviceName,
            'cost' => $this->cost,
            'currency' => $this->currency,
            'estimated_days' => $this->estimatedDays,
            'estimated_delivery_date' => $this->estimatedDeliveryDate,
            'metadata' => $this->metadata
        ];
    }
}