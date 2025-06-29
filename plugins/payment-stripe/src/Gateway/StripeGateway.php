<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Gateway;

use Shopologic\Core\Ecommerce\Payment\PaymentGatewayInterface;
use Shopologic\Core\Ecommerce\Payment\PaymentRequest;
use Shopologic\Core\Ecommerce\Payment\PaymentResponse;
use Shopologic\Core\Ecommerce\Payment\RefundRequest;
use Shopologic\Core\Ecommerce\Payment\RefundResponse;
use Shopologic\Core\Ecommerce\Payment\WebhookResponse;
use Shopologic\Core\Ecommerce\Payment\ValidationResult;
use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Http\Request;
use Shopologic\Plugins\PaymentStripe\Services\StripeClient;
use Shopologic\Plugins\PaymentStripe\Services\StripeCustomerService;
use Shopologic\Plugins\PaymentStripe\Repository\StripePaymentRepository;
use Shopologic\Plugins\PaymentStripe\Models\StripePayment;
use Shopologic\Plugins\PaymentStripe\Exceptions\StripeException;

class StripeGateway implements PaymentGatewayInterface
{
    private StripeClient $client;
    private StripePaymentRepository $paymentRepository;
    private StripeCustomerService $customerService;
    private array $config;

    public function __construct(
        StripeClient $client,
        StripePaymentRepository $paymentRepository,
        StripeCustomerService $customerService,
        array $config = []
    ) {
        $this->client = $client;
        $this->paymentRepository = $paymentRepository;
        $this->customerService = $customerService;
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function getDisplayName(): string
    {
        return 'Stripe';
    }

    public function isAvailable(): bool
    {
        return !empty($this->config['secret_key']) && !empty($this->config['publishable_key']);
    }

    public function getConfiguration(): array
    {
        return [
            'publishable_key' => $this->config['publishable_key'] ?? '',
            'capture_method' => $this->config['capture_method'] ?? 'automatic',
            'enable_3d_secure' => $this->config['enable_3d_secure'] ?? true,
            'save_payment_methods' => $this->config['save_payment_methods'] ?? true,
            'supported_currencies' => $this->config['supported_currencies'] ?? ['USD'],
            'statement_descriptor' => $this->config['statement_descriptor'] ?? null
        ];
    }

    public function validateConfiguration(array $config): ValidationResult
    {
        $errors = [];

        if (empty($config['secret_key'])) {
            $errors['secret_key'] = 'Secret key is required';
        }

        if (empty($config['publishable_key'])) {
            $errors['publishable_key'] = 'Publishable key is required';
        }

        if (!empty($config['statement_descriptor']) && strlen($config['statement_descriptor']) > 22) {
            $errors['statement_descriptor'] = 'Statement descriptor must be 22 characters or less';
        }

        if (!empty($config['webhook_secret'])) {
            // Validate webhook secret format
            if (!preg_match('/^whsec_[a-zA-Z0-9]+$/', $config['webhook_secret'])) {
                $errors['webhook_secret'] = 'Invalid webhook secret format';
            }
        }

        return new ValidationResult(empty($errors), $errors);
    }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        try {
            // Get or create Stripe customer
            $stripeCustomer = $this->customerService->getOrCreateCustomer(
                $request->getCustomer(),
                $request->getBillingAddress()
            );

            // Prepare payment intent data
            $intentData = [
                'amount' => $this->convertAmountToStripeFormat($request->getAmount(), $request->getCurrency()),
                'currency' => strtolower($request->getCurrency()),
                'customer' => $stripeCustomer->stripe_id,
                'description' => $this->getPaymentDescription($request),
                'metadata' => [
                    'order_id' => $request->getOrderId(),
                    'customer_id' => $request->getCustomer()->id,
                    'source' => 'shopologic'
                ],
                'capture_method' => $this->config['capture_method'] ?? 'automatic',
                'payment_method' => $request->getPaymentMethodId(),
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => $request->getReturnUrl()
            ];

            // Add statement descriptor if configured
            if (!empty($this->config['statement_descriptor'])) {
                $intentData['statement_descriptor'] = $this->config['statement_descriptor'];
            }

            // Add shipping address if provided
            if ($shippingAddress = $request->getShippingAddress()) {
                $intentData['shipping'] = [
                    'name' => $shippingAddress->name,
                    'address' => [
                        'line1' => $shippingAddress->line1,
                        'line2' => $shippingAddress->line2,
                        'city' => $shippingAddress->city,
                        'state' => $shippingAddress->state,
                        'postal_code' => $shippingAddress->postal_code,
                        'country' => $shippingAddress->country_code
                    ]
                ];
            }

            // Create payment intent
            $paymentIntent = $this->client->createPaymentIntent($intentData);

            // Store payment record
            $stripePayment = $this->paymentRepository->create([
                'order_id' => $request->getOrderId(),
                'customer_id' => $request->getCustomer()->id,
                'stripe_customer_id' => $stripeCustomer->id,
                'payment_intent_id' => $paymentIntent['id'],
                'amount' => $request->getAmount(),
                'currency' => $request->getCurrency(),
                'status' => $paymentIntent['status'],
                'capture_method' => $intentData['capture_method'],
                'metadata' => json_encode($intentData['metadata'])
            ]);

            // Prepare response based on payment status
            if ($paymentIntent['status'] === 'requires_action') {
                return new PaymentResponse(
                    success: false,
                    transactionId: $paymentIntent['id'],
                    status: 'requires_action',
                    requiresAction: true,
                    actionUrl: $paymentIntent['next_action']['redirect_to_url']['url'] ?? null,
                    clientSecret: $paymentIntent['client_secret'],
                    data: [
                        'payment_intent_id' => $paymentIntent['id'],
                        'requires_3d_secure' => true
                    ]
                );
            }

            if ($paymentIntent['status'] === 'succeeded') {
                return new PaymentResponse(
                    success: true,
                    transactionId: $paymentIntent['id'],
                    status: 'completed',
                    requiresAction: false,
                    data: [
                        'payment_intent_id' => $paymentIntent['id'],
                        'charge_id' => $paymentIntent['charges']['data'][0]['id'] ?? null
                    ]
                );
            }

            // Payment is processing
            return new PaymentResponse(
                success: true,
                transactionId: $paymentIntent['id'],
                status: 'processing',
                requiresAction: false,
                data: ['payment_intent_id' => $paymentIntent['id']]
            );

        } catch (StripeException $e) {
            return new PaymentResponse(
                success: false,
                transactionId: null,
                status: 'failed',
                error: $e->getMessage(),
                errorCode: $e->getStripeCode()
            );
        } catch (\Exception $e) {
            return new PaymentResponse(
                success: false,
                transactionId: null,
                status: 'failed',
                error: 'Payment processing failed: ' . $e->getMessage()
            );
        }
    }

    public function capturePayment(string $paymentId, float $amount = null): PaymentResponse
    {
        try {
            $stripePayment = $this->paymentRepository->findByPaymentIntentId($paymentId);
            if (!$stripePayment) {
                throw new \Exception('Payment not found');
            }

            $captureData = [];
            if ($amount !== null) {
                $captureData['amount_to_capture'] = $this->convertAmountToStripeFormat($amount, $stripePayment->currency);
            }

            $paymentIntent = $this->client->capturePaymentIntent($paymentId, $captureData);

            // Update payment status
            $stripePayment->status = $paymentIntent['status'];
            $stripePayment->captured_amount = $paymentIntent['amount_captured'] / 100;
            $stripePayment->save();

            return new PaymentResponse(
                success: true,
                transactionId: $paymentIntent['id'],
                status: 'captured',
                data: [
                    'captured_amount' => $paymentIntent['amount_captured'] / 100,
                    'charge_id' => $paymentIntent['charges']['data'][0]['id'] ?? null
                ]
            );

        } catch (\Exception $e) {
            return new PaymentResponse(
                success: false,
                transactionId: $paymentId,
                status: 'failed',
                error: 'Capture failed: ' . $e->getMessage()
            );
        }
    }

    public function refundPayment(string $paymentId, float $amount = null): RefundResponse
    {
        try {
            $stripePayment = $this->paymentRepository->findByPaymentIntentId($paymentId);
            if (!$stripePayment) {
                throw new \Exception('Payment not found');
            }

            $refundData = [
                'payment_intent' => $paymentId,
                'metadata' => [
                    'order_id' => $stripePayment->order_id,
                    'reason' => 'requested_by_customer'
                ]
            ];

            if ($amount !== null) {
                $refundData['amount'] = $this->convertAmountToStripeFormat($amount, $stripePayment->currency);
            }

            $refund = $this->client->createRefund($refundData);

            // Store refund record
            $this->paymentRepository->createRefund([
                'stripe_payment_id' => $stripePayment->id,
                'refund_id' => $refund['id'],
                'amount' => $refund['amount'] / 100,
                'currency' => $refund['currency'],
                'status' => $refund['status'],
                'reason' => $refund['reason'] ?? 'requested_by_customer'
            ]);

            return new RefundResponse(
                success: true,
                refundId: $refund['id'],
                status: $refund['status'],
                amount: $refund['amount'] / 100,
                currency: strtoupper($refund['currency'])
            );

        } catch (\Exception $e) {
            return new RefundResponse(
                success: false,
                refundId: null,
                status: 'failed',
                error: 'Refund failed: ' . $e->getMessage()
            );
        }
    }

    public function voidPayment(string $paymentId): PaymentResponse
    {
        try {
            $paymentIntent = $this->client->cancelPaymentIntent($paymentId);

            // Update payment status
            $stripePayment = $this->paymentRepository->findByPaymentIntentId($paymentId);
            if ($stripePayment) {
                $stripePayment->status = 'canceled';
                $stripePayment->save();
            }

            return new PaymentResponse(
                success: true,
                transactionId: $paymentIntent['id'],
                status: 'voided'
            );

        } catch (\Exception $e) {
            return new PaymentResponse(
                success: false,
                transactionId: $paymentId,
                status: 'failed',
                error: 'Void failed: ' . $e->getMessage()
            );
        }
    }

    public function handleWebhook(Request $request): WebhookResponse
    {
        try {
            // Verify webhook signature
            $signature = $request->getHeader('Stripe-Signature');
            $payload = $request->getContent();
            
            if (!$this->verifyWebhook($request)) {
                return new WebhookResponse(false, 'Invalid signature', 401);
            }

            $event = json_decode($payload, true);

            // Log webhook
            $this->paymentRepository->logWebhook([
                'event_id' => $event['id'],
                'event_type' => $event['type'],
                'payload' => $payload,
                'processed' => false
            ]);

            // Handle different event types
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentSucceeded($event['data']['object']);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailed($event['data']['object']);
                    break;

                case 'charge.refunded':
                    $this->handleChargeRefunded($event['data']['object']);
                    break;

                case 'payment_intent.canceled':
                    $this->handlePaymentCanceled($event['data']['object']);
                    break;

                case 'payment_method.attached':
                    $this->handlePaymentMethodAttached($event['data']['object']);
                    break;

                default:
                    // Log unhandled event types
                    $this->logger->info('Unhandled Stripe webhook event', ['type' => $event['type']]);
            }

            // Mark webhook as processed
            $this->paymentRepository->markWebhookProcessed($event['id']);

            return new WebhookResponse(true, 'Webhook processed successfully');

        } catch (\Exception $e) {
            $this->logger->error('Stripe webhook error', ['error' => $e->getMessage()]);
            return new WebhookResponse(false, 'Webhook processing failed', 500);
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        try {
            $signature = $request->getHeader('Stripe-Signature');
            $payload = $request->getContent();
            $secret = $this->config['webhook_secret'] ?? '';

            if (empty($secret)) {
                $this->logger->warning('Stripe webhook secret not configured');
                return false;
            }

            return $this->client->verifyWebhookSignature($payload, $signature, $secret);

        } catch (\Exception $e) {
            $this->logger->error('Webhook verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function preparePayment(Order $order): array
    {
        try {
            // Create payment intent for the order
            $customer = $this->customerService->getOrCreateCustomer(
                $order->customer,
                $order->billingAddress
            );

            $intentData = [
                'amount' => $this->convertAmountToStripeFormat($order->total, $order->currency),
                'currency' => strtolower($order->currency),
                'customer' => $customer->stripe_id,
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ],
                'setup_future_usage' => $this->config['save_payment_methods'] ? 'off_session' : null
            ];

            $paymentIntent = $this->client->createPaymentIntent($intentData);

            return [
                'client_secret' => $paymentIntent['client_secret'],
                'payment_intent_id' => $paymentIntent['id'],
                'publishable_key' => $this->config['publishable_key']
            ];

        } catch (\Exception $e) {
            throw new StripeException('Failed to prepare payment: ' . $e->getMessage());
        }
    }

    public function syncPaymentStatuses(): void
    {
        $pendingPayments = $this->paymentRepository->getPendingPayments();

        foreach ($pendingPayments as $payment) {
            try {
                $paymentIntent = $this->client->getPaymentIntent($payment->payment_intent_id);
                
                if ($payment->status !== $paymentIntent['status']) {
                    $payment->status = $paymentIntent['status'];
                    $payment->save();

                    // Trigger status change event
                    event('stripe.payment.status_changed', [
                        'payment' => $payment,
                        'old_status' => $payment->status,
                        'new_status' => $paymentIntent['status']
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to sync payment status', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function convertAmountToStripeFormat(float $amount, string $currency): int
    {
        // Stripe expects amounts in the smallest currency unit (cents for USD)
        $zeroDecimalCurrencies = ['JPY', 'KRW', 'VND', 'CLP', 'PYG', 'ISK', 'UGX'];
        
        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return (int) $amount;
        }
        
        return (int) ($amount * 100);
    }

    private function getPaymentDescription(PaymentRequest $request): string
    {
        return sprintf(
            'Payment for Order #%s - Customer: %s',
            $request->getOrderId(),
            $request->getCustomer()->email
        );
    }

    private function handlePaymentSucceeded(array $paymentIntent): void
    {
        $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntent['id']);
        if ($payment) {
            $payment->status = 'succeeded';
            $payment->charge_id = $paymentIntent['charges']['data'][0]['id'] ?? null;
            $payment->save();

            event('payment.completed', ['payment' => $payment]);
        }
    }

    private function handlePaymentFailed(array $paymentIntent): void
    {
        $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntent['id']);
        if ($payment) {
            $payment->status = 'failed';
            $payment->failure_reason = $paymentIntent['last_payment_error']['message'] ?? 'Unknown error';
            $payment->save();

            event('payment.failed', ['payment' => $payment]);
        }
    }

    private function handleChargeRefunded(array $charge): void
    {
        // Update refund status
        $refund = $this->paymentRepository->findRefundByChargeId($charge['id']);
        if ($refund) {
            $refund->status = 'completed';
            $refund->save();

            event('refund.completed', ['refund' => $refund]);
        }
    }

    private function handlePaymentCanceled(array $paymentIntent): void
    {
        $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntent['id']);
        if ($payment) {
            $payment->status = 'canceled';
            $payment->save();

            event('payment.canceled', ['payment' => $payment]);
        }
    }

    private function handlePaymentMethodAttached(array $paymentMethod): void
    {
        // Store payment method for future use
        $this->customerService->savePaymentMethod($paymentMethod);
    }
}