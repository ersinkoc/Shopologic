<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Cart\Events;

class CouponApplied
{
    public string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }
}