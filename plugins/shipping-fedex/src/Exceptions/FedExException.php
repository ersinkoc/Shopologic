<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedex\Exceptions;

class FedExException extends \Exception
{
    private ?string $fedexCode;
    private ?array $errors;

    public function __construct(
        string $message,
        int $code = 0,
        ?string $fedexCode = null,
        ?array $errors = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->fedexCode = $fedexCode;
        $this->errors = $errors;
    }

    public function getFedExCode(): ?string
    {
        return $this->fedexCode;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }
}