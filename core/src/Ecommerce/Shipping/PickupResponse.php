<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping;

class PickupResponse
{
    private bool $success;
    private ?string $confirmationNumber;
    private ?string $pickupDate;
    private ?float $pickupCharge;
    private ?string $error;

    public function __construct(
        bool $success,
        ?string $confirmationNumber = null,
        ?string $pickupDate = null,
        ?float $pickupCharge = null,
        ?string $error = null
    ) {
        $this->success = $success;
        $this->confirmationNumber = $confirmationNumber;
        $this->pickupDate = $pickupDate;
        $this->pickupCharge = $pickupCharge;
        $this->error = $error;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getConfirmationNumber(): ?string
    {
        return $this->confirmationNumber;
    }

    public function getPickupDate(): ?string
    {
        return $this->pickupDate;
    }

    public function getPickupCharge(): ?float
    {
        return $this->pickupCharge;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}