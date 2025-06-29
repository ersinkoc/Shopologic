<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment;

class RefundRequest
{
    private string $paymentId;
    private ?float $amount;
    private string $reason;
    private array $metadata;

    public function __construct(
        string $paymentId,
        ?float $amount = null,
        string $reason = 'requested_by_customer',
        array $metadata = []
    ) {
        $this->paymentId = $paymentId;
        $this->amount = $amount;
        $this->reason = $reason;
        $this->metadata = $metadata;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}