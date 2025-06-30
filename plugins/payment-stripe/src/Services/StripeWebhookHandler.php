<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Services;

use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Plugins\PaymentStripe\Repository\StripeWebhookRepository;
use Shopologic\Plugins\PaymentStripe\Repository\StripePaymentRepository;
use Shopologic\Core\Logging\LoggerInterface;

class StripeWebhookHandler\n{
    private EventDispatcherInterface $eventDispatcher;
    private StripeWebhookRepository $webhookRepository;
    private StripePaymentRepository $paymentRepository;
    private LoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        StripeWebhookRepository $webhookRepository,
        StripePaymentRepository $paymentRepository,
        LoggerInterface $logger
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->webhookRepository = $webhookRepository;
        $this->paymentRepository = $paymentRepository;
        $this->logger = $logger;
    }

    /**
     * Process a webhook event
     */
    public function processWebhook(array $event): bool
    {
        try {
            // Check if already processed
            if ($this->webhookRepository->isProcessed($event['id'])) {
                $this->logger->info('Webhook already processed', ['event_id' => $event['id']]);
                return true;
            }

            // Log the webhook
            $webhook = $this->webhookRepository->create([
                'event_id' => $event['id'],
                'event_type' => $event['type'],
                'payload' => json_encode($event),
                'livemode' => $event['livemode'] ?? false,
                'processed' => false
            ]);

            // Process based on event type
            $processed = match($event['type']) {
                'payment_intent.succeeded' => $this->handlePaymentSucceeded($event['data']['object']),
                'payment_intent.payment_failed' => $this->handlePaymentFailed($event['data']['object']),
                'payment_intent.canceled' => $this->handlePaymentCanceled($event['data']['object']),
                'charge.refunded' => $this->handleChargeRefunded($event['data']['object']),
                'payment_method.attached' => $this->handlePaymentMethodAttached($event['data']['object']),
                'payment_method.detached' => $this->handlePaymentMethodDetached($event['data']['object']),
                'customer.updated' => $this->handleCustomerUpdated($event['data']['object']),
                'customer.deleted' => $this->handleCustomerDeleted($event['data']['object']),
                default => $this->handleUnknownEvent($event)
            };

            // Mark as processed
            if ($processed) {
                $webhook->processed = true;
                $webhook->processed_at = now();
                $webhook->save();
            }

            return $processed;

        } catch (\RuntimeException $e) {
            $this->logger->error('Webhook processing failed', [
                'event_id' => $event['id'] ?? 'unknown',
                'event_type' => $event['type'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            // Save error
            if (isset($webhook)) {
                $webhook->error = $e->getMessage();
                $webhook->save();
            }

            return false;
        }
    }

    private function handlePaymentSucceeded(array $paymentIntent): bool
    {
        $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntent['id']);
        
        if (!$payment) {
            $this->logger->warning('Payment not found for succeeded event', [
                'payment_intent_id' => $paymentIntent['id']
            ]);
            return false;
        }

        $payment->status = 'succeeded';
        $payment->charge_id = $paymentIntent['charges']['data'][0]['id'] ?? null;
        $payment->save();

        // Dispatch event
        $this->eventDispatcher->dispatch(new PaymentCompletedEvent($payment));

        return true;
    }

    private function handlePaymentFailed(array $paymentIntent): bool
    {
        $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntent['id']);
        
        if (!$payment) {
            return false;
        }

        $payment->status = 'failed';
        $payment->failure_reason = $paymentIntent['last_payment_error']['message'] ?? 'Unknown error';
        $payment->failure_code = $paymentIntent['last_payment_error']['code'] ?? null;
        $payment->save();

        // Dispatch event
        $this->eventDispatcher->dispatch(new PaymentFailedEvent($payment));

        return true;
    }

    private function handlePaymentCanceled(array $paymentIntent): bool
    {
        $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntent['id']);
        
        if (!$payment) {
            return false;
        }

        $payment->status = 'canceled';
        $payment->save();

        // Dispatch event
        $this->eventDispatcher->dispatch(new PaymentCanceledEvent($payment));

        return true;
    }

    private function handleChargeRefunded(array $charge): bool
    {
        $payment = $this->paymentRepository->findByChargeId($charge['id']);
        
        if (!$payment) {
            return false;
        }

        // Check if fully refunded
        $refundedAmount = $charge['amount_refunded'] / 100;
        
        if ($refundedAmount >= $payment->amount) {
            $payment->status = 'refunded';
        } else {
            $payment->status = 'partially_refunded';
        }
        
        $payment->save();

        // Dispatch event
        $this->eventDispatcher->dispatch(new PaymentRefundedEvent($payment, $refundedAmount));

        return true;
    }

    private function handlePaymentMethodAttached(array $paymentMethod): bool
    {
        // This is handled by the StripeCustomerService
        $this->logger->info('Payment method attached', [
            'payment_method_id' => $paymentMethod['id'],
            'customer' => $paymentMethod['customer']
        ]);

        return true;
    }

    private function handlePaymentMethodDetached(array $paymentMethod): bool
    {
        // Remove from database
        $method = $this->paymentRepository->findPaymentMethodById($paymentMethod['id']);
        
        if ($method) {
            $method->delete();
        }

        return true;
    }

    private function handleCustomerUpdated(array $customer): bool
    {
        // Sync customer data
        $stripeCustomer = $this->paymentRepository->findCustomerByStripeId($customer['id']);
        
        if ($stripeCustomer) {
            $stripeCustomer->email = $customer['email'];
            $stripeCustomer->name = $customer['name'];
            $stripeCustomer->phone = $customer['phone'];
            $stripeCustomer->save();
        }

        return true;
    }

    private function handleCustomerDeleted(array $customer): bool
    {
        // Mark customer as deleted
        $stripeCustomer = $this->paymentRepository->findCustomerByStripeId($customer['id']);
        
        if ($stripeCustomer) {
            $stripeCustomer->delete();
        }

        return true;
    }

    private function handleUnknownEvent(array $event): bool
    {
        $this->logger->info('Unhandled webhook event', [
            'event_id' => $event['id'],
            'event_type' => $event['type']
        ]);

        // Still mark as processed to avoid reprocessing
        return true;
    }
}

// Event classes
class PaymentCompletedEvent\n{
    public function __construct(public $payment) {}
}

class PaymentFailedEvent\n{
    public function __construct(public $payment) {}
}

class PaymentCanceledEvent\n{
    public function __construct(public $payment) {}
}

class PaymentRefundedEvent\n{
    public function __construct(public $payment, public float $amount) {}
}