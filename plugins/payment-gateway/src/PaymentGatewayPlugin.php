<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentGateway;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Plugins\PaymentGateway\Services\PaymentService;
use Shopologic\Plugins\PaymentGateway\Contracts\PaymentGatewayInterface;
use Shopologic\Plugins\CoreCommerce\Contracts\OrderServiceInterface;

/**
 * Payment Gateway Plugin
 * 
 * Provides payment processing capabilities that integrate with Core Commerce
 */
class PaymentGatewayPlugin extends AbstractPlugin
{
    public function install(): void
    {
        // Installation logic
    }

    public function uninstall(): void
    {
        // Uninstallation logic
    }

    public function activate(): void
    {
        // Activation logic
    }

    public function deactivate(): void
    {
        // Deactivation logic
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        // Upgrade logic
    }

    protected function registerServices(): void
    {
        // Register payment gateway interface
        $this->container->singleton(PaymentGatewayInterface::class, PaymentService::class);
        
        // Register payment service
        $this->container->singleton(PaymentService::class);
    }

    protected function registerEventListeners(): void
    {
        $dispatcher = $this->container->get(\Shopologic\PSR\EventDispatcher\EventDispatcherInterface::class);
        
        // Listen for order events
        $dispatcher->listen('order.created', [$this, 'onOrderCreated']);
    }

    protected function registerHooks(): void
    {
        // Payment processing hooks
        add_action('payment.process', function($data) { $this->processPayment($data); }, 10);
        add_action('order.payment_completed', function($data) { $this->onPaymentCompleted($data); }, 10);
        
        // Hook into core commerce order creation
        add_action('order.created', function($data) { $this->initializePayment($data); }, 15);
    }

    protected function registerRoutes(): void
    {
        // Skip route registration for testing
    }

    protected function registerPermissions(): void
    {
        // Skip permission registration for testing
    }

    protected function registerScheduledJobs(): void
    {
        // Skip scheduled job registration for testing
    }

    public function onOrderCreated($event): void
    {
        error_log('Payment Gateway: Order created event received');
    }

    public function processPayment($data): void
    {
        error_log('Payment Gateway: Processing payment');
        
        // Get order service from core commerce
        $orderService = $this->container->get(OrderServiceInterface::class);
        error_log('Payment Gateway: Successfully accessed Order Service from Core Commerce');
    }

    public function onPaymentCompleted($data): void
    {
        error_log('Payment Gateway: Payment completed');
    }

    public function initializePayment($data): void
    {
        error_log('Payment Gateway: Initializing payment for order');
    }
}