<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Services;

use Shopologic\Core\Ecommerce\Models\Customer;
use Shopologic\Plugins\PaymentStripe\Repository\StripeCustomerRepository;
use Shopologic\Plugins\PaymentStripe\Repository\StripePaymentMethodRepository;
use Illuminate\Support\Collection;

class StripePaymentMethodService
{
    private StripeClient $client;
    private StripeCustomerRepository $customerRepository;
    private StripePaymentMethodRepository $methodRepository;

    public function __construct(
        StripeClient $client,
        StripeCustomerRepository $customerRepository,
        StripePaymentMethodRepository $methodRepository
    ) {
        $this->client = $client;
        $this->customerRepository = $customerRepository;
        $this->methodRepository = $methodRepository;
    }

    /**
     * Get all payment methods for a customer
     */
    public function getCustomerPaymentMethods(Customer $customer): Collection
    {
        $stripeCustomer = $this->customerRepository->findByCustomerId($customer->id);
        
        if (!$stripeCustomer) {
            return collect();
        }

        return $this->methodRepository->getByStripeCustomerId($stripeCustomer->id);
    }

    /**
     * Attach a payment method to a customer
     */
    public function attachPaymentMethod(string $paymentMethodId, Customer $customer): bool
    {
        try {
            $stripeCustomer = $this->customerRepository->findByCustomerId($customer->id);
            
            if (!$stripeCustomer) {
                throw new \Exception('Stripe customer not found');
            }

            // Attach to customer in Stripe
            $paymentMethod = $this->client->attachPaymentMethod($paymentMethodId, [
                'customer' => $stripeCustomer->stripe_id
            ]);

            // Save to database
            $this->savePaymentMethod($paymentMethod, $stripeCustomer->id);

            return true;

        } catch (\Exception $e) {
            logger()->error('Failed to attach payment method', [
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Detach a payment method from a customer
     */
    public function detachPaymentMethod(string $paymentMethodId, Customer $customer): bool
    {
        try {
            // Verify ownership
            $method = $this->methodRepository->findByPaymentMethodId($paymentMethodId);
            
            if (!$method || $method->stripeCustomer->customer_id !== $customer->id) {
                return false;
            }

            // Detach from Stripe
            $this->client->detachPaymentMethod($paymentMethodId);

            // Remove from database
            $this->methodRepository->delete($method->id);

            return true;

        } catch (\Exception $e) {
            logger()->error('Failed to detach payment method', [
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Set a payment method as default
     */
    public function setDefaultPaymentMethod(string $paymentMethodId, Customer $customer): bool
    {
        try {
            $stripeCustomer = $this->customerRepository->findByCustomerId($customer->id);
            
            if (!$stripeCustomer) {
                return false;
            }

            // Update in Stripe
            $this->client->updateCustomer($stripeCustomer->stripe_id, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId
                ]
            ]);

            // Update in database
            $this->methodRepository->setDefault($stripeCustomer->id, $paymentMethodId);

            // Update customer record
            $stripeCustomer->default_payment_method = $paymentMethodId;
            $stripeCustomer->save();

            return true;

        } catch (\Exception $e) {
            logger()->error('Failed to set default payment method', [
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Save payment method to database
     */
    private function savePaymentMethod(array $paymentMethod, int $stripeCustomerId): void
    {
        $isDefault = $this->methodRepository->countByStripeCustomerId($stripeCustomerId) === 0;

        $this->methodRepository->create([
            'stripe_customer_id' => $stripeCustomerId,
            'payment_method_id' => $paymentMethod['id'],
            'type' => $paymentMethod['type'],
            'card' => json_encode($paymentMethod['card'] ?? []),
            'billing_details' => json_encode($paymentMethod['billing_details'] ?? []),
            'is_default' => $isDefault,
            'livemode' => $paymentMethod['livemode'] ?? false
        ]);
    }
}