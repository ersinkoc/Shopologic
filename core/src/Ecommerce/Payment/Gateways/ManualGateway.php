<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment\Gateways;

use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Payment\PaymentResult;

class ManualGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'manual';
    }

    public function getDisplayName(): string
    {
        return 'Manual Payment';
    }

    public function getDescription(): string
    {
        return 'Process payment manually (bank transfer, check, etc.)';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];
    }

    public function charge(Order $order, array $paymentData): PaymentResult
    {
        // Manual payments are always marked as pending
        return new PaymentResult(
            true,
            'Order received. Awaiting payment confirmation.',
            'manual_' . uniqid(),
            [
                'payment_instructions' => $paymentData['instructions'] ?? 'Please follow the payment instructions sent to your email.',
                'requires_manual_confirmation' => true,
            ]
        );
    }

    public function refund(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        // Manual refunds require manual processing
        return new PaymentResult(
            true,
            'Refund initiated. Manual processing required.',
            'manual_refund_' . uniqid(),
            [
                'original_transaction' => $transactionId,
                'refunded_amount' => $amount,
                'reason' => $reason,
                'requires_manual_processing' => true,
            ]
        );
    }

    public function void(string $transactionId): PaymentResult
    {
        return new PaymentResult(
            true,
            'Transaction void initiated. Manual processing required.',
            'manual_void_' . uniqid(),
            [
                'original_transaction' => $transactionId,
                'requires_manual_processing' => true,
            ]
        );
    }

    public function getTransaction(string $transactionId): ?array
    {
        return [
            'id' => $transactionId,
            'status' => 'pending',
            'requires_manual_confirmation' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}