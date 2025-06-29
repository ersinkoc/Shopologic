<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment;

class RefundResponse
{
    private bool $success;
    private ?string $refundId;
    private string $status;
    private ?float $amount;
    private ?string $currency;
    private ?string $error;

    public function __construct(
        bool $success,
        ?string $refundId,
        string $status,
        ?float $amount = null,
        ?string $currency = null,
        ?string $error = null
    ) {
        $this->success = $success;
        $this->refundId = $refundId;
        $this->status = $status;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->error = $error;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getRefundId(): ?string
    {
        return $this->refundId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}