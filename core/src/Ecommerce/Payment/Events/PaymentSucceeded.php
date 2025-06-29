<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment\Events;

use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Payment\PaymentResult;

class PaymentSucceeded
{
    public Order $order;
    public PaymentResult $result;

    public function __construct(Order $order, PaymentResult $result)
    {
        $this->order = $order;
        $this->result = $result;
    }
}