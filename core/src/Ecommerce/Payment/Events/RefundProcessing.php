<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment\Events;

use Shopologic\Core\Ecommerce\Models\Order;

class RefundProcessing
{
    public Order $order;
    public float $amount;

    public function __construct(Order $order, float $amount)
    {
        $this->order = $order;
        $this->amount = $amount;
    }
}