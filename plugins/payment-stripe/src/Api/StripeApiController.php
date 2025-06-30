<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Api;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\JsonResponse;
use Shopologic\Core\Controller\ApiController;
use Shopologic\Plugins\PaymentStripe\Services\StripeClient;
use Shopologic\Plugins\PaymentStripe\Services\StripeWebhookHandler;
use Shopologic\Plugins\PaymentStripe\Services\StripeCustomerService;
use Shopologic\Plugins\PaymentStripe\Services\StripePaymentMethodService;
use Shopologic\Plugins\PaymentStripe\Gateway\StripeGateway;
use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Auth\Auth;

class StripeApiController extends ApiController
{
    private StripeClient $stripeClient;
    private StripeWebhookHandler $webhookHandler;
    private StripeCustomerService $customerService;
    private StripePaymentMethodService $paymentMethodService;
    private StripeGateway $gateway;

    public function __construct(
        StripeClient $stripeClient,
        StripeWebhookHandler $webhookHandler,
        StripeCustomerService $customerService,
        StripePaymentMethodService $paymentMethodService,
        StripeGateway $gateway
    ) {
        $this->stripeClient = $stripeClient;
        $this->webhookHandler = $webhookHandler;
        $this->customerService = $customerService;
        $this->paymentMethodService = $paymentMethodService;
        $this->gateway = $gateway;
    }

    /**
     * Process a payment
     */
    public function processPayment(Request $request): JsonResponse
    {
        try {
            $validated = $this->validate($request, [
                'order_id' => 'required|integer',
                'payment_method_id' => 'required|string',
                'save_payment_method' => 'boolean'
            ]);

            $order = Order::findOrFail($validated['order_id']);
            
            // Verify order belongs to current user
            if ($order->customer_id !== Auth::id()) {
                return $this->respondWithError('Unauthorized', 403);
            }

            // Process payment through gateway
            $paymentRequest = new \Shopologic\Core\Ecommerce\Payment\PaymentRequest(
                orderId: $order->id,
                amount: $order->total,
                currency: $order->currency,
                customer: $order->customer,
                paymentMethodId: $validated['payment_method_id'],
                billingAddress: $order->billingAddress,
                shippingAddress: $order->shippingAddress,
                returnUrl: '/checkout/confirmation?order=' . $order->id
            );

            $response = $this->gateway->createPayment($paymentRequest);

            if ($response->isSuccessful()) {
                return $this->respondWithSuccess([
                    'success' => true,
                    'transaction_id' => $response->getTransactionId(),
                    'status' => $response->getStatus(),
                    'requires_action' => $response->requiresAction(),
                    'action_url' => $response->getActionUrl(),
                    'client_secret' => $response->getClientSecret()
                ]);
            } else {
                return $this->respondWithError($response->getError(), 400);
            }

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Create a payment intent
     */
    public function createPaymentIntent(Request $request): JsonResponse
    {
        try {
            $validated = $this->validate($request, [
                'order_id' => 'required|integer',
                'amount' => 'required|numeric|min:0.50',
                'currency' => 'required|string|size:3'
            ]);

            $order = Order::findOrFail($validated['order_id']);
            
            // Verify order belongs to current user
            if ($order->customer_id !== Auth::id()) {
                return $this->respondWithError('Unauthorized', 403);
            }

            $paymentData = $this->gateway->preparePayment($order);

            return $this->respondWithSuccess($paymentData);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Create a setup intent for saving payment methods
     */
    public function createSetupIntent(Request $request): JsonResponse
    {
        try {
            $customer = Auth::user();
            if (!$customer) {
                return $this->respondWithError('Authentication required', 401);
            }

            $stripeCustomer = $this->customerService->getOrCreateCustomer($customer);

            $setupIntent = $this->stripeClient->createSetupIntent([
                'customer' => $stripeCustomer->stripe_id,
                'usage' => 'off_session',
                'metadata' => [
                    'customer_id' => $customer->id
                ]
            ]);

            return $this->respondWithSuccess([
                'client_secret' => $setupIntent['client_secret']
            ]);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Get saved payment methods
     */
    public function getPaymentMethods(Request $request): JsonResponse
    {
        try {
            $customer = Auth::user();
            if (!$customer) {
                return $this->respondWithError('Authentication required', 401);
            }

            $methods = $this->paymentMethodService->getCustomerPaymentMethods($customer);

            return $this->respondWithCollection($methods->map(function ($method) {
                return [
                    'id' => $method->payment_method_id,
                    'type' => $method->type,
                    'card' => $method->card,
                    'is_default' => $method->is_default,
                    'created_at' => $method->created_at->toIso8601String()
                ];
            }));

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Handle Stripe webhooks
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $response = $this->gateway->handleWebhook($request);

            if ($response->isSuccessful()) {
                return new JsonResponse(['received' => true], 200);
            } else {
                return new JsonResponse(['error' => $response->getMessage()], $response->getStatusCode());
            }

        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $this->validate($request, [
                'amount' => 'numeric|min:0.01',
                'reason' => 'string|in:duplicate,fraudulent,requested_by_customer'
            ]);

            // Verify permission
            if (!Auth::user()->can('payment.stripe.refund')) {
                return $this->respondWithError('Insufficient permissions', 403);
            }

            $response = $this->gateway->refundPayment($id, $validated['amount'] ?? null);

            if ($response->isSuccessful()) {
                return $this->respondWithSuccess([
                    'refund_id' => $response->getRefundId(),
                    'status' => $response->getStatus(),
                    'amount' => $response->getAmount(),
                    'currency' => $response->getCurrency()
                ]);
            } else {
                return $this->respondWithError($response->getError(), 400);
            }

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Get Stripe settings (admin only)
     */
    public function getSettings(Request $request): JsonResponse
    {
        try {
            // Verify admin permission
            if (!Auth::user()->can('payment.stripe.configure')) {
                return $this->respondWithError('Insufficient permissions', 403);
            }

            $config = $this->gateway->getConfiguration();
            
            // Remove sensitive data
            unset($config['secret_key']);
            unset($config['webhook_secret']);

            return $this->respondWithSuccess($config);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Update Stripe settings (admin only)
     */
    public function updateSettings(Request $request): JsonResponse
    {
        try {
            // Verify admin permission
            if (!Auth::user()->can('payment.stripe.configure')) {
                return $this->respondWithError('Insufficient permissions', 403);
            }

            $validated = $this->validate($request, [
                'publishable_key' => 'string',
                'secret_key' => 'string',
                'webhook_secret' => 'string',
                'capture_method' => 'string|in:automatic,manual',
                'statement_descriptor' => 'string|max:22',
                'enable_3d_secure' => 'boolean',
                'save_payment_methods' => 'boolean',
                'supported_currencies' => 'array'
            ]);

            // Validate configuration
            $validationResult = $this->gateway->validateConfiguration($validated);
            
            if (!$validationResult->isValid()) {
                return $this->respondWithValidationError($validationResult->getErrors());
            }

            // Save configuration
            $plugin = $this->container->get('plugin.manager')->getPlugin('payment-stripe');
            $plugin->updatePluginConfig($validated);

            return $this->respondWithSuccess(['message' => 'Settings updated successfully']);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Test Stripe connection (admin only)
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            // Verify admin permission
            if (!Auth::user()->can('payment.stripe.configure')) {
                return $this->respondWithError('Insufficient permissions', 403);
            }

            // Try to retrieve account info
            $response = $this->stripeClient->request('GET', '/account');

            return $this->respondWithSuccess([
                'connected' => true,
                'account_id' => $response['id'],
                'business_name' => $response['business_profile']['name'] ?? null,
                'country' => $response['country'],
                'default_currency' => $response['default_currency']
            ]);

        } catch (\RuntimeException $e) {
            return $this->respondWithError('Connection failed: ' . $e->getMessage(), 500);
        }
    }
}