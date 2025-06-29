<?php

declare(strict_types=1);

namespace Shopologic\Core\Http\Client;

class HttpResponse
{
    protected int $statusCode;
    protected string $body;
    protected array $info;

    public function __construct(int $statusCode, string $body, array $info = [])
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->info = $info;
    }

    /**
     * Get status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get response body
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get response info
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * Get response as JSON
     */
    public function json(): mixed
    {
        return json_decode($this->body, true);
    }

    /**
     * Check if response is successful
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if response is redirect
     */
    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Check if response is client error
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if response is server error
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }
}