<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment;

class PaymentResponse
{
    private bool $success;
    private ?string $transactionId;
    private string $status;
    private bool $requiresAction;
    private ?string $actionUrl;
    private ?string $clientSecret;
    private ?string $error;
    private ?string $errorCode;
    private array $data;

    public function __construct(
        bool $success,
        ?string $transactionId,
        string $status,
        bool $requiresAction = false,
        ?string $actionUrl = null,
        ?string $clientSecret = null,
        ?string $error = null,
        ?string $errorCode = null,
        array $data = []
    ) {
        $this->success = $success;
        $this->transactionId = $transactionId;
        $this->status = $status;
        $this->requiresAction = $requiresAction;
        $this->actionUrl = $actionUrl;
        $this->clientSecret = $clientSecret;
        $this->error = $error;
        $this->errorCode = $errorCode;
        $this->data = $data;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function requiresAction(): bool
    {
        return $this->requiresAction;
    }

    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getData(): array
    {
        return $this->data;
    }
}