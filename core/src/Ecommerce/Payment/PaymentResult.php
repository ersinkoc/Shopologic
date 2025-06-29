<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment;

class PaymentResult
{
    protected bool $successful;
    protected string $message;
    protected ?string $transactionId;
    protected array $data;

    public function __construct(
        bool $successful,
        string $message = '',
        ?string $transactionId = null,
        array $data = []
    ) {
        $this->successful = $successful;
        $this->message = $message;
        $this->transactionId = $transactionId;
        $this->data = $data;
    }

    /**
     * Check if payment was successful
     */
    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    /**
     * Get message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get transaction ID
     */
    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    /**
     * Get additional data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get specific data value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'message' => $this->message,
            'transaction_id' => $this->transactionId,
            'data' => $this->data,
        ];
    }
}