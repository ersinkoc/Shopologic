<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment\Gateways;

use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Payment\PaymentResult;

interface PaymentGatewayInterface
{
    /**
     * Get gateway name
     */
    public function getName(): string;

    /**
     * Get display name
     */
    public function getDisplayName(): string;

    /**
     * Get description
     */
    public function getDescription(): string;

    /**
     * Check if gateway is available
     */
    public function isAvailable(): bool;

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array;

    /**
     * Charge payment
     */
    public function charge(Order $order, array $paymentData): PaymentResult;

    /**
     * Refund payment
     */
    public function refund(string $transactionId, float $amount, string $reason = ''): PaymentResult;

    /**
     * Void payment
     */
    public function void(string $transactionId): PaymentResult;

    /**
     * Get transaction details
     */
    public function getTransaction(string $transactionId): ?array;
}