<?php

declare(strict_types=1);

namespace Shopologic\Core\Kernel;

/**
 * HTTP exception with status code
 */
class HttpException extends \RuntimeException
{
    private int $statusCode;
    private array $headers;

    public function __construct(
        int $statusCode,
        string $message = '',
        array $headers = [],
        ?\Throwable $previous = null,
        int $code = 0
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}