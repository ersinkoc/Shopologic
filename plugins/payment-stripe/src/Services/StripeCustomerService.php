<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Services;

use Shopologic\Core\Ecommerce\Models\Customer;
use Shopologic\Core\Ecommerce\Models\CustomerAddress;
use Shopologic\Plugins\PaymentStripe\Models\StripeCustomer;
use Shopologic\Plugins\PaymentStripe\Repository\StripeCustomerRepository;
use Shopologic\Plugins\PaymentStripe\Exceptions\StripeException;

class StripeCustomerService
{
    private StripeClient $client;
    private StripeCustomerRepository $repository;

    public function __construct(StripeClient $client, StripeCustomerRepository $repository)
    {
        $this->client = $client;
        $this->repository = $repository;
    }

    /**
     * Get or create a Stripe customer for a Shopologic customer
     */
    public function getOrCreateCustomer(Customer $customer, ?CustomerAddress $address = null): StripeCustomer
    {
        // Check if customer already exists
        $stripeCustomer = $this->repository->findByCustomerId($customer->id);
        
        if ($stripeCustomer) {
            // Update customer info if needed
            $this->updateCustomerIfNeeded($stripeCustomer, $customer, $address);
            return $stripeCustomer;
        }

        // Create new Stripe customer
        return $this->createCustomer($customer, $address);
    }

    /**
     * Create a new Stripe customer
     */
    public function createCustomer(Customer $customer, ?CustomerAddress $address = null): StripeCustomer
    {
        $customerData = [
            'email' => $customer->email,
            'name' => $customer->full_name,
            'phone' => $customer->phone,
            'metadata' => [
                'customer_id' => $customer->id,
                'source' => 'shopologic'
            ]
        ];

        // Add address if provided
        if ($address) {
            $customerData['address'] = [
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country_code
            ];
        }

        try {
            $stripeResponse = $this->client->createCustomer($customerData);

            // Save to database
            return $this->repository->create([
                'customer_id' => $customer->id,
                'stripe_id' => $stripeResponse['id'],
                'email' => $stripeResponse['email'],
                'name' => $stripeResponse['name'],
                'phone' => $stripeResponse['phone'],
                'metadata' => json_encode($stripeResponse['metadata']),
                'livemode' => $stripeResponse['livemode'] ?? false
            ]);

        } catch (\Exception $e) {
            throw new StripeException('Failed to create Stripe customer: ' . $e->getMessage());
        }
    }

    /**
     * Update customer information if changed
     */
    public function updateCustomerIfNeeded(StripeCustomer $stripeCustomer, Customer $customer, ?CustomerAddress $address = null): void
    {
        $updates = [];

        // Check for changes
        if ($stripeCustomer->email !== $customer->email) {
            $updates['email'] = $customer->email;
        }

        if ($stripeCustomer->name !== $customer->full_name) {
            $updates['name'] = $customer->full_name;
        }

        if ($stripeCustomer->phone !== $customer->phone) {
            $updates['phone'] = $customer->phone;
        }

        // Update address if provided
        if ($address) {
            $updates['address'] = [
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country_code
            ];
        }

        if (!empty($updates)) {
            try {
                $this->client->updateCustomer($stripeCustomer->stripe_id, $updates);

                // Update local record
                $stripeCustomer->email = $customer->email;
                $stripeCustomer->name = $customer->full_name;
                $stripeCustomer->phone = $customer->phone;
                $stripeCustomer->save();

            } catch (\Exception $e) {
                // Log error but don't fail the process
                logger()->error('Failed to update Stripe customer', [
                    'stripe_id' => $stripeCustomer->stripe_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Save a payment method for a customer
     */
    public function savePaymentMethod(array $paymentMethod): void
    {
        $stripeCustomer = $this->repository->findByStripeId($paymentMethod['customer']);
        
        if (!$stripeCustomer) {
            return;
        }

        // Check if it should be the default
        $isDefault = $this->repository->getPaymentMethodsCount($stripeCustomer->id) === 0;

        $this->repository->savePaymentMethod([
            'stripe_customer_id' => $stripeCustomer->id,
            'payment_method_id' => $paymentMethod['id'],
            'type' => $paymentMethod['type'],
            'card' => json_encode($paymentMethod['card'] ?? []),
            'billing_details' => json_encode($paymentMethod['billing_details'] ?? []),
            'is_default' => $isDefault,
            'livemode' => $paymentMethod['livemode'] ?? false
        ]);

        // Update default payment method in Stripe
        if ($isDefault) {
            try {
                $this->client->updateCustomer($stripeCustomer->stripe_id, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethod['id']
                    ]
                ]);

                $stripeCustomer->default_payment_method = $paymentMethod['id'];
                $stripeCustomer->save();

            } catch (\Exception $e) {
                logger()->error('Failed to set default payment method', [
                    'stripe_id' => $stripeCustomer->stripe_id,
                    'payment_method_id' => $paymentMethod['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Delete a Stripe customer
     */
    public function deleteCustomer(string $stripeId): bool
    {
        try {
            // Delete from Stripe
            $this->client->request('DELETE', '/customers/' . $stripeId);

            // Delete from database
            $this->repository->deleteByStripeId($stripeId);

            return true;

        } catch (\Exception $e) {
            logger()->error('Failed to delete Stripe customer', [
                'stripe_id' => $stripeId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Sync customer data from Stripe
     */
    public function syncCustomer(string $stripeId): ?StripeCustomer
    {
        try {
            $stripeData = $this->client->getCustomer($stripeId);
            
            $stripeCustomer = $this->repository->findByStripeId($stripeId);
            if (!$stripeCustomer) {
                return null;
            }

            // Update local data
            $stripeCustomer->email = $stripeData['email'];
            $stripeCustomer->name = $stripeData['name'];
            $stripeCustomer->phone = $stripeData['phone'];
            $stripeCustomer->default_payment_method = $stripeData['invoice_settings']['default_payment_method'] ?? null;
            $stripeCustomer->metadata = json_encode($stripeData['metadata']);
            $stripeCustomer->save();

            return $stripeCustomer;

        } catch (\Exception $e) {
            logger()->error('Failed to sync Stripe customer', [
                'stripe_id' => $stripeId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}