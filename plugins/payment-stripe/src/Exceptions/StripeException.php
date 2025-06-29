<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Exceptions;

class StripeException extends \Exception
{
    private ?string $stripeCode;
    private ?string $stripeType;

    public function __construct(
        string $message,
        int $code = 0,
        ?string $stripeCode = null,
        ?string $stripeType = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->stripeCode = $stripeCode;
        $this->stripeType = $stripeType;
    }

    public function getStripeCode(): ?string
    {
        return $this->stripeCode;
    }

    public function getStripeType(): ?string
    {
        return $this->stripeType;
    }
}