<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping;

use Shopologic\Core\Ecommerce\Models\CustomerAddress;

class PickupRequest
{
    private CustomerAddress $pickupAddress;
    private string $pickupDate;
    private string $readyTime;
    private string $closeTime;
    private int $packageCount;
    private float $totalWeight;
    private ?string $specialInstructions;
    private array $metadata;

    public function __construct(
        CustomerAddress $pickupAddress,
        string $pickupDate,
        string $readyTime,
        string $closeTime,
        int $packageCount,
        float $totalWeight,
        ?string $specialInstructions = null,
        array $metadata = []
    ) {
        $this->pickupAddress = $pickupAddress;
        $this->pickupDate = $pickupDate;
        $this->readyTime = $readyTime;
        $this->closeTime = $closeTime;
        $this->packageCount = $packageCount;
        $this->totalWeight = $totalWeight;
        $this->specialInstructions = $specialInstructions;
        $this->metadata = $metadata;
    }

    public function getPickupAddress(): CustomerAddress
    {
        return $this->pickupAddress;
    }

    public function getPickupDate(): string
    {
        return $this->pickupDate;
    }

    public function getReadyTime(): string
    {
        return $this->readyTime;
    }

    public function getCloseTime(): string
    {
        return $this->closeTime;
    }

    public function getPackageCount(): int
    {
        return $this->packageCount;
    }

    public function getTotalWeight(): float
    {
        return $this->totalWeight;
    }

    public function getSpecialInstructions(): ?string
    {
        return $this->specialInstructions;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}