# 💳 Stripe Payment Gateway Plugin

![Quality Badge](https://img.shields.io/badge/Quality-57%25%20(F)-red)


Enterprise-grade Stripe integration with advanced fraud detection, subscription support, and comprehensive payment analytics.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate Stripe plugin
php cli/plugin.php activate payment-stripe

# Configure Stripe settings
php cli/stripe.php configure
```

## ✨ Key Features

### 💸 Advanced Payment Processing
- **Multiple Payment Methods** - Cards, digital wallets, bank transfers
- **3D Secure Authentication** - Enhanced security with SCA compliance
- **Subscription Support** - Recurring billing and subscription management
- **Multi-Party Payments** - Split payments and marketplace support
- **International Processing** - Global payment methods and currencies

### 🛡️ Security & Fraud Protection
- **Stripe Radar** - Advanced fraud detection and prevention
- **PCI Compliance** - Secure card data handling
- **Fraud Analytics** - Real-time fraud scoring and monitoring
- **Chargeback Protection** - Automated dispute management
- **Retry Logic** - Intelligent payment retry mechanisms

### 📊 Comprehensive Analytics
- **Payment Analytics** - Detailed transaction reporting
- **Performance Monitoring** - Success rates and failure analysis
- **Revenue Tracking** - Real-time revenue and fee calculations
- **Customer Insights** - Payment behavior and preferences
- **Webhook Management** - Real-time event processing

## 🏗️ Plugin Architecture

### Models
- **`StripePayment.php`** - Payment transaction management
- **`StripeCustomer.php`** - Customer profile synchronization
- **`StripePaymentMethod.php`** - Stored payment method management
- **`StripeRefund.php`** - Refund processing and tracking

### Services
- **`StripeClient.php`** - Core Stripe API communication
- **`StripeCustomerService.php`** - Customer lifecycle management
- **`StripePaymentMethodService.php`** - Payment method operations
- **`StripeFraudDetectionService.php`** - Fraud prevention and analysis
- **`StripeAnalyticsService.php`** - Payment analytics and reporting
- **`StripeRetryService.php`** - Failed payment retry logic
- **`StripeWebhookHandler.php`** - Webhook event processing

### Controllers
- **`StripeApiController.php`** - Payment processing API endpoints

### Repositories
- **`StripePaymentRepository.php`** - Payment data access
- **`StripeCustomerRepository.php`** - Customer data management
- **`StripePaymentMethodRepository.php`** - Payment method storage
- **`StripeFraudRepository.php`** - Fraud detection data
- **`StripeWebhookRepository.php`** - Webhook event logging

### Gateway
- **`StripeGateway.php`** - Payment gateway implementation

## 💳 Payment Processing

### Basic Payment Flow

```php
// Initialize Stripe gateway
$gateway = app(StripeGateway::class);

// Create payment intent
$paymentIntent = $gateway->createPayment([
    'amount' => 9999, // $99.99 in cents
    'currency' => 'usd',
    'customer_id' => 'cus_example123',
    'payment_method' => 'pm_card_visa',
    'description' => 'Order #ORD-2024-001',
    'metadata' => [
        'order_id' => 'ORD-2024-001',
        'customer_email' => 'customer@example.com'
    ]
]);

// Confirm payment
$result = $gateway->confirmPayment($paymentIntent->id, [
    'return_url' => 'https://example.com/return'
]);

// Handle payment result
if ($result->status === 'succeeded') {
    // Payment successful
    $order->markAsPaid($result->id);
} else {
    // Handle payment failure
    $order->markAsPaymentFailed($result->last_payment_error);
}
```

### Advanced Payment Features

```php
// Setup payment with 3D Secure
$payment = $gateway->createPayment([
    'amount' => 15000,
    'currency' => 'usd',
    'confirmation_method' => 'manual',
    'confirm' => true,
    'use_stripe_sdk' => true
]);

// Process subscription payment
$subscription = $gateway->createSubscription([
    'customer' => 'cus_example123',
    'items' => [
        ['price' => 'price_monthly_premium']
    ],
    'trial_period_days' => 14,
    'collection_method' => 'charge_automatically'
]);

// Handle marketplace payments
$marketplacePayment = $gateway->createPayment([
    'amount' => 5000,
    'currency' => 'usd',
    'application_fee_amount' => 500, // 10% marketplace fee
    'transfer_data' => [
        'destination' => 'acct_vendor123'
    ]
]);
```

## 🔗 Cross-Plugin Integration

### Payment Gateway Interface
Implements `PaymentGatewayInterface` for seamless integration:

```php
interface PaymentGatewayInterface {
    public function createPayment(PaymentRequest $request): PaymentResponse;
    public function capturePayment(string $paymentId, float $amount = null): PaymentResponse;
    public function refundPayment(string $paymentId, float $amount = null): PaymentResponse;
    public function voidPayment(string $paymentId): PaymentResponse;
    public function handleWebhook(Request $request): WebhookResponse;
}
```

### Integration Examples

```php
// Use with order processing
$orderService = app(OrderServiceInterface::class);
$paymentGateway = app(PaymentGatewayInterface::class);

// Process order payment
$paymentRequest = new PaymentRequest([
    'amount' => $order->total,
    'currency' => $order->currency,
    'order_id' => $order->id,
    'customer_id' => $order->customer_id
]);

$paymentResult = $paymentGateway->createPayment($paymentRequest);

if ($paymentResult->isSuccessful()) {
    $orderService->markOrderAsPaid($order->id, $paymentResult->getTransactionId());
}
```

## 🛡️ Security Features

### Fraud Detection Integration

```php
// Advanced fraud detection
$fraudService = app(StripeFraudDetectionService::class);

// Analyze payment for fraud risk
$riskAnalysis = $fraudService->analyzePayment([
    'amount' => 25000,
    'currency' => 'usd',
    'customer_id' => 'cus_example123',
    'payment_method' => 'pm_card_visa',
    'billing_address' => $billingAddress,
    'shipping_address' => $shippingAddress
]);

// Check fraud score
if ($riskAnalysis->risk_score > 75) {
    // High risk - require additional verification
    $payment = $gateway->createPayment([
        'amount' => 25000,
        'currency' => 'usd',
        'radar_options' => [
            'session' => $sessionId
        ]
    ]);
}

// Monitor fraud patterns
$fraudService->recordFraudAttempt([
    'payment_intent_id' => $paymentIntent->id,
    'risk_factors' => $riskAnalysis->risk_factors,
    'action_taken' => 'blocked'
]);
```

### PCI Compliance Features

```php
// Secure customer data handling
$customerService = app(StripeCustomerService::class);

// Create secure customer profile
$customer = $customerService->createCustomer([
    'email' => 'customer@example.com',
    'name' => 'John Doe',
    'phone' => '+1555123456',
    'metadata' => [
        'internal_id' => $internalCustomerId
    ]
]);

// Store payment method securely
$paymentMethod = $customerService->attachPaymentMethod($customer->id, [
    'payment_method' => 'pm_card_visa',
    'set_as_default' => true
]);

// Retrieve stored payment methods
$paymentMethods = $customerService->getPaymentMethods($customer->id);
```

## ⚡ Real-Time Webhooks

### Webhook Event Handling

```php
// Register webhook event handlers
$webhookHandler = app(StripeWebhookHandler::class);

// Payment success webhook
$webhookHandler->register('payment_intent.succeeded', function($event) {
    $paymentIntent = $event->data->object;
    
    // Update order status
    $orderId = $paymentIntent->metadata->order_id;
    $orderService = app(OrderServiceInterface::class);
    $orderService->markOrderAsPaid($orderId, $paymentIntent->id);
    
    // Trigger fulfillment
    event(new OrderPaidEvent($orderId, $paymentIntent->amount_received));
});

// Payment failure webhook
$webhookHandler->register('payment_intent.payment_failed', function($event) {
    $paymentIntent = $event->data->object;
    
    // Handle payment failure
    $retryService = app(StripeRetryService::class);
    $retryService->scheduleRetry($paymentIntent->id, $paymentIntent->last_payment_error);
});

// Subscription events
$webhookHandler->register('invoice.payment_succeeded', function($event) {
    $invoice = $event->data->object;
    // Handle successful subscription payment
});
```

### Webhook Security

```php
// Verify webhook signatures
$webhookHandler->setEndpointSecret('whsec_your_webhook_secret');

// Process webhook with verification
public function handleWebhook(Request $request): WebhookResponse
{
    $payload = $request->getContent();
    $signature = $request->header('Stripe-Signature');
    
    try {
        $event = $webhookHandler->verifyAndProcess($payload, $signature);
        return new WebhookResponse('success', 200);
    } catch (SignatureVerificationException $e) {
        return new WebhookResponse('invalid signature', 400);
    }
}
```

## 📊 Analytics & Reporting

### Payment Analytics

```php
// Comprehensive payment analytics
$analyticsService = app(StripeAnalyticsService::class);

// Get payment performance metrics
$metrics = $analyticsService->getPaymentMetrics([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'breakdown_by' => 'day'
]);

// Analyze success rates
$successRates = $analyticsService->getSuccessRates([
    'group_by' => 'payment_method_type',
    'period' => 'last_30_days'
]);

// Revenue analytics
$revenue = $analyticsService->getRevenueAnalytics([
    'include_fees' => true,
    'currency' => 'usd',
    'period' => 'monthly'
]);

// Customer payment insights
$customerInsights = $analyticsService->getCustomerPaymentInsights($customerId);
```

## 🧪 Automated Testing

### Test Coverage
- **Unit Tests** - Payment processing logic
- **Integration Tests** - Stripe API communication
- **Webhook Tests** - Event handling verification
- **Security Tests** - Fraud detection and PCI compliance

### Example Tests

```php
class StripePaymentTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_payment_creation' => [$this, 'testPaymentCreation'],
            'test_refund_processing' => [$this, 'testRefundProcessing'],
            'test_webhook_handling' => [$this, 'testWebhookHandling']
        ];
    }
    
    public function testPaymentCreation(): void
    {
        $gateway = new StripeGateway($mockClient);
        $request = new PaymentRequest(['amount' => 1000, 'currency' => 'usd']);
        $response = $gateway->createPayment($request);
        Assert::assertTrue($response->isSuccessful());
    }
}
```

## 🛠️ Configuration

### Plugin Settings

```json
{
    "publishable_key": "pk_test_...",
    "secret_key": "sk_test_...",
    "webhook_secret": "whsec_...",
    "capture_method": "automatic",
    "payment_methods": ["card", "apple_pay", "google_pay"],
    "currencies": ["usd", "eur", "gbp"],
    "fraud_protection": true,
    "3d_secure": "automatic",
    "retry_attempts": 3,
    "webhook_tolerance": 300
}
```

### Database Tables
- `stripe_payments` - Payment transaction records
- `stripe_customers` - Customer profile synchronization
- `stripe_payment_methods` - Stored payment method data
- `stripe_refunds` - Refund transaction tracking
- `stripe_webhooks` - Webhook event logging
- `stripe_fraud_attempts` - Fraud detection records

## 📚 API Endpoints

### REST API
- `POST /api/v1/payments/stripe/intent` - Create payment intent
- `POST /api/v1/payments/stripe/confirm` - Confirm payment
- `POST /api/v1/payments/stripe/refund` - Process refund
- `GET /api/v1/payments/stripe/methods` - List payment methods
- `POST /api/v1/payments/stripe/webhook` - Webhook endpoint

### Usage Examples

```bash
# Create payment intent
curl -X POST /api/v1/payments/stripe/intent \
  -H "Content-Type: application/json" \
  -d '{"amount": 2000, "currency": "usd", "customer_id": "123"}'

# Process refund
curl -X POST /api/v1/payments/stripe/refund \
  -H "Content-Type: application/json" \
  -d '{"payment_intent_id": "pi_123", "amount": 1000}'
```

## 🔧 Installation & Setup

### Requirements
- PHP 8.3+
- Stripe account with API keys
- SSL certificate for production
- Webhook endpoint configuration

### Installation

```bash
# Activate plugin
php cli/plugin.php activate payment-stripe

# Run migrations
php cli/migrate.php up

# Configure Stripe settings
php cli/stripe.php configure --live
```

### Stripe Configuration

```bash
# Set up webhooks
php cli/stripe.php setup-webhooks

# Test connection
php cli/stripe.php test-connection

# Sync payment methods
php cli/stripe.php sync-payment-methods
```

## 📖 Documentation

- **Stripe Setup Guide** - Account configuration and API setup
- **Payment Flow Documentation** - Integration patterns and best practices
- **Webhook Configuration** - Event handling and security
- **Fraud Prevention** - Security features and fraud detection

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Comprehensive payment processing capabilities
- ✅ Cross-plugin integration via standardized interfaces
- ✅ Real-time webhook event processing
- ✅ Advanced fraud detection and security
- ✅ Automated testing framework
- ✅ Complete documentation and examples

---

**Stripe Payment Gateway** - Enterprise payment processing for Shopologic