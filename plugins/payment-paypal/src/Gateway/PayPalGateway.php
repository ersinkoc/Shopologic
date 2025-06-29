<?php
declare(strict_types=1);

namespace PayPalPayment\Gateway;

use Core\Payment\PaymentGatewayInterface;
use Core\Payment\PaymentResult;
use Core\Ecommerce\Models\Order;
use PayPalPayment\Services\PayPalService;
use PayPalPayment\Exceptions\PayPalException;

class PayPalGateway implements PaymentGatewayInterface
{
    private PayPalService $paypalService;
    private array $config;
    
    public function __construct(PayPalService $paypalService, array $config)
    {
        $this->paypalService = $paypalService;
        $this->config = $config;
    }
    
    /**
     * Get gateway identifier
     */
    public function getId(): string
    {
        return 'paypal';
    }
    
    /**
     * Get gateway display name
     */
    public function getName(): string
    {
        return 'PayPal';
    }
    
    /**
     * Check if gateway is available for use
     */
    public function isAvailable(): bool
    {
        return !empty($this->config['client_id']) 
            && !empty($this->config['client_secret']);
    }
    
    /**
     * Get supported features
     */
    public function getSupportedFeatures(): array
    {
        return [
            'refunds',
            'partial_refunds',
            'recurring',
            'tokenization',
            'webhooks'
        ];
    }
    
    /**
     * Process a payment
     */
    public function processPayment(Order $order, array $paymentData): PaymentResult
    {
        try {
            // Validate payment data
            if (!isset($paymentData['paypal_order_id'])) {
                throw new PayPalException('PayPal order ID is required');
            }
            
            // Capture the payment
            $captureResult = $this->paypalService->captureOrder(
                $paymentData['paypal_order_id']
            );
            
            // Process the result
            if ($captureResult['status'] === 'COMPLETED') {
                return PaymentResult::success([
                    'transaction_id' => $captureResult['id'],
                    'paypal_order_id' => $paymentData['paypal_order_id'],
                    'payer_email' => $captureResult['payer']['email_address'] ?? null,
                    'payment_source' => $this->getPaymentSource($captureResult),
                    'raw_response' => $captureResult
                ]);
            }
            
            return PaymentResult::failed(
                'Payment capture failed: ' . ($captureResult['status'] ?? 'Unknown status')
            );
            
        } catch (PayPalException $e) {
            return PaymentResult::failed($e->getMessage(), [
                'error_code' => $e->getCode(),
                'error_details' => $e->getDetails()
            ]);
        } catch (\Exception $e) {
            return PaymentResult::failed(
                'An unexpected error occurred: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create a payment intent for client-side processing
     */
    public function createPaymentIntent(Order $order, array $options = []): array
    {
        try {
            $orderData = [
                'intent' => $this->config['payment_action'] === 'authorize' 
                    ? 'AUTHORIZE' 
                    : 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $order->id,
                    'amount' => [
                        'currency_code' => $order->currency,
                        'value' => $this->formatAmount($order->total)
                    ],
                    'description' => $this->getOrderDescription($order),
                    'invoice_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                    'soft_descriptor' => substr($this->config['site_name'] ?? 'SHOPOLOGIC', 0, 22)
                ]],
                'application_context' => [
                    'brand_name' => $this->config['site_name'] ?? 'Shopologic',
                    'locale' => $options['locale'] ?? 'en-US',
                    'shipping_preference' => $order->requires_shipping 
                        ? 'SET_PROVIDED_ADDRESS' 
                        : 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $options['return_url'] ?? '',
                    'cancel_url' => $options['cancel_url'] ?? ''
                ]
            ];
            
            // Add shipping address if required
            if ($order->requires_shipping && $order->shipping_address) {
                $orderData['purchase_units'][0]['shipping'] = [
                    'name' => [
                        'full_name' => $order->shipping_address->name
                    ],
                    'address' => [
                        'address_line_1' => $order->shipping_address->line1,
                        'address_line_2' => $order->shipping_address->line2,
                        'admin_area_2' => $order->shipping_address->city,
                        'admin_area_1' => $order->shipping_address->state,
                        'postal_code' => $order->shipping_address->postal_code,
                        'country_code' => $order->shipping_address->country
                    ]
                ];
            }
            
            $result = $this->paypalService->createOrder($orderData);
            
            return [
                'success' => true,
                'order_id' => $result['id'],
                'status' => $result['status'],
                'links' => $result['links'] ?? []
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process a refund
     */
    public function refund(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        try {
            $refundData = [];
            
            if ($amount > 0) {
                $refundData['amount'] = [
                    'currency_code' => $this->config['base_currency'] ?? 'USD',
                    'value' => $this->formatAmount($amount)
                ];
            }
            
            if (!empty($reason)) {
                $refundData['note_to_payer'] = $reason;
            }
            
            $result = $this->paypalService->refundCapture($transactionId, $refundData);
            
            if (isset($result['id']) && $result['status'] === 'COMPLETED') {
                return PaymentResult::success([
                    'refund_id' => $result['id'],
                    'status' => $result['status'],
                    'amount' => $amount
                ]);
            }
            
            return PaymentResult::failed(
                'Refund failed: ' . ($result['message'] ?? 'Unknown error')
            );
            
        } catch (\Exception $e) {
            return PaymentResult::failed(
                'Refund error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Validate webhook notification
     */
    public function validateWebhook(array $headers, string $body): bool
    {
        try {
            return $this->paypalService->verifyWebhookSignature(
                $this->config['webhook_id'] ?? '',
                $headers,
                $body
            );
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Process webhook notification
     */
    public function processWebhook(array $data): array
    {
        $eventType = $data['event_type'] ?? '';
        $resource = $data['resource'] ?? [];
        
        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                return [
                    'action' => 'payment_completed',
                    'transaction_id' => $resource['id'] ?? '',
                    'order_id' => $resource['custom_id'] ?? '',
                    'amount' => $resource['amount']['value'] ?? 0,
                    'status' => 'completed'
                ];
                
            case 'PAYMENT.CAPTURE.REFUNDED':
                return [
                    'action' => 'payment_refunded',
                    'transaction_id' => $resource['id'] ?? '',
                    'refund_id' => $data['resource']['refund_id'] ?? '',
                    'amount' => $resource['amount']['value'] ?? 0,
                    'status' => 'refunded'
                ];
                
            case 'PAYMENT.CAPTURE.DENIED':
                return [
                    'action' => 'payment_denied',
                    'transaction_id' => $resource['id'] ?? '',
                    'order_id' => $resource['custom_id'] ?? '',
                    'status' => 'failed'
                ];
                
            default:
                return [
                    'action' => 'unknown',
                    'event_type' => $eventType,
                    'data' => $data
                ];
        }
    }
    
    /**
     * Get payment source from capture result
     */
    private function getPaymentSource(array $captureResult): string
    {
        if (isset($captureResult['payment_source']['paypal'])) {
            return 'paypal';
        }
        
        if (isset($captureResult['payment_source']['card'])) {
            $card = $captureResult['payment_source']['card'];
            return ($card['brand'] ?? 'card') . ' ****' . ($card['last_digits'] ?? '');
        }
        
        return 'unknown';
    }
    
    /**
     * Format amount for PayPal API
     */
    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
    
    /**
     * Get order description
     */
    private function getOrderDescription(Order $order): string
    {
        $itemCount = $order->items->count();
        return "Order {$order->order_number} ({$itemCount} items)";
    }
}