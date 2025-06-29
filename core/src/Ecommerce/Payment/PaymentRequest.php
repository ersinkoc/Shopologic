<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment;

use Shopologic\Core\Ecommerce\Models\Customer;
use Shopologic\Core\Ecommerce\Models\CustomerAddress;

class PaymentRequest
{
    private string $orderId;
    private float $amount;
    private string $currency;
    private Customer $customer;
    private ?string $paymentMethodId;
    private ?CustomerAddress $billingAddress;
    private ?CustomerAddress $shippingAddress;
    private string $returnUrl;
    private array $metadata = [];

    public function __construct(
        string $orderId,
        float $amount,
        string $currency,
        Customer $customer,
        ?string $paymentMethodId = null,
        ?CustomerAddress $billingAddress = null,
        ?CustomerAddress $shippingAddress = null,
        string $returnUrl = '/',
        array $metadata = []
    ) {
        $this->orderId = $orderId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->customer = $customer;
        $this->paymentMethodId = $paymentMethodId;
        $this->billingAddress = $billingAddress;
        $this->shippingAddress = $shippingAddress;
        $this->returnUrl = $returnUrl;
        $this->metadata = $metadata;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getPaymentMethodId(): ?string
    {
        return $this->paymentMethodId;
    }

    public function getBillingAddress(): ?CustomerAddress
    {
        return $this->billingAddress;
    }

    public function getShippingAddress(): ?CustomerAddress
    {
        return $this->shippingAddress;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}