<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment\Gateways;

use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Payment\PaymentResult;

class TestGateway implements PaymentGatewayInterface
{
    protected array $testCards = [
        '4111111111111111' => 'success',
        '4000000000000002' => 'declined',
        '4000000000000127' => 'error',
    ];

    public function getName(): string
    {
        return 'test';
    }

    public function getDisplayName(): string
    {
        return 'Test Gateway';
    }

    public function getDescription(): string
    {
        return 'Test payment gateway for development';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP'];
    }

    public function charge(Order $order, array $paymentData): PaymentResult
    {
        $cardNumber = $paymentData['card_number'] ?? '';
        $cardNumber = str_replace(' ', '', $cardNumber);
        
        // Simulate processing delay
        usleep(500000); // 0.5 seconds
        
        $transactionId = 'test_' . uniqid();
        
        // Check test card numbers
        if (isset($this->testCards[$cardNumber])) {
            $result = $this->testCards[$cardNumber];
            
            switch ($result) {
                case 'success':
                    return new PaymentResult(
                        true,
                        'Payment successful',
                        $transactionId,
                        [
                            'card_last_four' => substr($cardNumber, -4),
                            'card_brand' => 'Visa',
                        ]
                    );
                    
                case 'declined':
                    return new PaymentResult(
                        false,
                        'Your card was declined',
                        null,
                        ['decline_code' => 'generic_decline']
                    );
                    
                case 'error':
                    return new PaymentResult(
                        false,
                        'An error occurred processing your payment',
                        null,
                        ['error_code' => 'processing_error']
                    );
            }
        }
        
        // Default: approve all other cards
        return new PaymentResult(
            true,
            'Payment successful',
            $transactionId,
            [
                'card_last_four' => substr($cardNumber, -4),
                'card_brand' => $this->detectCardBrand($cardNumber),
            ]
        );
    }

    public function refund(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        // Simulate processing delay
        usleep(300000); // 0.3 seconds
        
        return new PaymentResult(
            true,
            'Refund processed successfully',
            'refund_' . uniqid(),
            [
                'original_transaction' => $transactionId,
                'refunded_amount' => $amount,
                'reason' => $reason,
            ]
        );
    }

    public function void(string $transactionId): PaymentResult
    {
        return new PaymentResult(
            true,
            'Transaction voided successfully',
            'void_' . uniqid(),
            ['original_transaction' => $transactionId]
        );
    }

    public function getTransaction(string $transactionId): ?array
    {
        return [
            'id' => $transactionId,
            'status' => 'completed',
            'amount' => 100.00,
            'currency' => 'USD',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Detect card brand from number
     */
    protected function detectCardBrand(string $cardNumber): string
    {
        $firstDigit = substr($cardNumber, 0, 1);
        $firstTwo = substr($cardNumber, 0, 2);
        
        if ($firstDigit === '4') {
            return 'Visa';
        } elseif (in_array($firstTwo, ['51', '52', '53', '54', '55'])) {
            return 'Mastercard';
        } elseif (in_array($firstTwo, ['34', '37'])) {
            return 'American Express';
        } elseif ($firstTwo === '60') {
            return 'Discover';
        }
        
        return 'Unknown';
    }
}