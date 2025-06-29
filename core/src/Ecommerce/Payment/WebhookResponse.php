<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment;

class WebhookResponse
{
    private bool $success;
    private string $message;
    private int $statusCode;

    public function __construct(bool $success, string $message = '', int $statusCode = 200)
    {
        $this->success = $success;
        $this->message = $message;
        $this->statusCode = $statusCode;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}