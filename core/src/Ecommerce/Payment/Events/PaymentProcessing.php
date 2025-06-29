<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment\Events;

use Shopologic\Core\Ecommerce\Models\Order;

class PaymentProcessing
{
    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }
}