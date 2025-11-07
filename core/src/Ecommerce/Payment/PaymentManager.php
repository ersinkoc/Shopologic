<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment;

use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Events\EventDispatcher;
use Shopologic\Core\Ecommerce\Payment\Gateways\PaymentGatewayInterface;

class PaymentManager
{
    protected array $gateways = [];
    protected EventDispatcher $events;
    protected string $defaultGateway = 'test';

    public function __construct(EventDispatcher $events)
    {
        $this->events = $events;
        $this->registerDefaultGateways();
    }

    /**
     * Register default payment gateways
     */
    protected function registerDefaultGateways(): void
    {
        $this->registerGateway('test', new Gateways\TestGateway());
        $this->registerGateway('manual', new Gateways\ManualGateway());
    }

    /**
     * Register a payment gateway
     */
    public function registerGateway(string $name, PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$name] = $gateway;
    }

    /**
     * Get a payment gateway
     */
    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: $this->defaultGateway;
        
        if (!isset($this->gateways[$name])) {
            throw new \InvalidArgumentException("Payment gateway [{$name}] is not registered.");
        }
        
        return $this->gateways[$name];
    }

    /**
     * Process payment for an order
     * BUG FIX (BUG-005): Wrapped in database transaction to ensure atomicity
     */
    public function processPayment(Order $order, array $paymentData): PaymentResult
    {
        $gateway = $this->gateway($order->payment_method);

        $this->events->dispatch(new Events\PaymentProcessing($order));

        try {
            $result = $gateway->charge($order, $paymentData);

            if ($result->isSuccessful()) {
                // Wrap order update and transaction creation in database transaction
                // to prevent inconsistent state if any operation fails
                $db = $order->getConnection();

                $db->transaction(function() use ($order, $result) {
                    $order->markAsPaid($result->getTransactionId());

                    $order->transactions()->create([
                        'type' => 'payment',
                        'amount' => $order->total_amount,
                        'currency' => $order->currency ?? 'USD',
                        'status' => 'completed',
                        'gateway' => $order->payment_method,
                        'gateway_transaction_id' => $result->getTransactionId(),
                        'gateway_response' => $result->getData(),
                    ]);
                });

                $this->events->dispatch(new Events\PaymentSucceeded($order, $result));
            } else {
                $this->events->dispatch(new Events\PaymentFailed($order, $result));
            }

            return $result;
        } catch (\Exception $e) {
            $result = new PaymentResult(false, $e->getMessage());
            $this->events->dispatch(new Events\PaymentFailed($order, $result));

            return $result;
        }
    }

    /**
     * Process refund for an order
     */
    public function processRefund(Order $order, float $amount, string $reason = ''): PaymentResult
    {
        $gateway = $this->gateway($order->payment_method);
        
        // Find the original payment transaction
        $paymentTransaction = $order->transactions()
            ->where('type', 'payment')
            ->where('status', 'completed')
            ->first();
        
        if (!$paymentTransaction) {
            return new PaymentResult(false, 'No payment transaction found');
        }
        
        $this->events->dispatch(new Events\RefundProcessing($order, $amount));
        
        try {
            $result = $gateway->refund(
                $paymentTransaction->gateway_transaction_id,
                $amount,
                $reason
            );
            
            if ($result->isSuccessful()) {
                $order->refund($amount, $reason);
                $this->events->dispatch(new Events\RefundSucceeded($order, $amount, $result));
            } else {
                $this->events->dispatch(new Events\RefundFailed($order, $amount, $result));
            }
            
            return $result;
        } catch (\Exception $e) {
            $result = new PaymentResult(false, $e->getMessage());
            $this->events->dispatch(new Events\RefundFailed($order, $amount, $result));
            
            return $result;
        }
    }

    /**
     * Get available payment methods
     */
    public function getAvailablePaymentMethods(): array
    {
        $methods = [];
        
        foreach ($this->gateways as $name => $gateway) {
            if ($gateway->isAvailable()) {
                $methods[$name] = [
                    'name' => $gateway->getName(),
                    'display_name' => $gateway->getDisplayName(),
                    'description' => $gateway->getDescription(),
                    'supported_currencies' => $gateway->getSupportedCurrencies(),
                ];
            }
        }
        
        return $methods;
    }

    /**
     * Validate payment method
     */
    public function validatePaymentMethod(string $method, string $currency = 'USD'): bool
    {
        if (!isset($this->gateways[$method])) {
            return false;
        }
        
        $gateway = $this->gateways[$method];
        
        return $gateway->isAvailable() && 
               in_array($currency, $gateway->getSupportedCurrencies());
    }

    /**
     * Set default gateway
     */
    public function setDefaultGateway(string $name): void
    {
        if (!isset($this->gateways[$name])) {
            throw new \InvalidArgumentException("Payment gateway [{$name}] is not registered.");
        }
        
        $this->defaultGateway = $name;
    }
}