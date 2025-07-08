<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentGateway\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Process a payment
     */
    public function processPayment(array $paymentData): array;
    
    /**
     * Refund a payment
     */
    public function refundPayment(string $transactionId, float $amount): bool;
    
    /**
     * Check payment status
     */
    public function getPaymentStatus(string $transactionId): string;
}