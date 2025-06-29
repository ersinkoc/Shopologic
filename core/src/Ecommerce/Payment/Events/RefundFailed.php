<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment\Events;

use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Payment\PaymentResult;

class RefundFailed
{
    public Order $order;
    public float $amount;
    public PaymentResult $result;

    public function __construct(Order $order, float $amount, PaymentResult $result)
    {
        $this->order = $order;
        $this->amount = $amount;
        $this->result = $result;
    }
}