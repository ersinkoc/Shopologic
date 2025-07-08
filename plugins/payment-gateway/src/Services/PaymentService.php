<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentGateway\Services;

use Shopologic\Plugins\PaymentGateway\Contracts\PaymentGatewayInterface;

class PaymentService implements PaymentGatewayInterface
{
    public function __construct()
    {
        // Stub implementation for testing
    }
    
    public function processPayment(array $paymentData): array
    {
        // Stub implementation
        return [
            'transaction_id' => 'txn_' . rand(1000, 9999),
            'status' => 'completed',
            'amount' => $paymentData['amount'] ?? 0
        ];
    }
    
    public function refundPayment(string $transactionId, float $amount): bool
    {
        // Stub implementation
        return true;
    }
    
    public function getPaymentStatus(string $transactionId): string
    {
        // Stub implementation
        return 'completed';
    }
}