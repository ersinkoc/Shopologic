<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping;

class LabelResponse
{
    private bool $success;
    private ?string $labelData;
    private ?string $format;
    private ?string $trackingNumber;
    private ?string $error;

    public function __construct(
        bool $success,
        ?string $labelData = null,
        ?string $format = null,
        ?string $trackingNumber = null,
        ?string $error = null
    ) {
        $this->success = $success;
        $this->labelData = $labelData;
        $this->format = $format;
        $this->trackingNumber = $trackingNumber;
        $this->error = $error;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getLabelData(): ?string
    {
        return $this->labelData;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}