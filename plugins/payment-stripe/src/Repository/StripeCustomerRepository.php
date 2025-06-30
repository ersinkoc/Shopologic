<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Repository;

use Shopologic\Core\Database\DB;
use Shopologic\Plugins\PaymentStripe\Models\StripeCustomer;
use Shopologic\Plugins\PaymentStripe\Models\StripePaymentMethod;
use Illuminate\Support\Collection;

class StripeCustomerRepository\n{
    /**
     * Create a new Stripe customer record
     */
    public function create(array $data): StripeCustomer
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();
        
        $id = DB::table('stripe_customers')->insertGetId($data);
        return $this->find($id);
    }

    /**
     * Find by ID
     */
    public function find(int $id): ?StripeCustomer
    {
        $data = DB::table('stripe_customers')->where('id', $id)->first();
        return $data ? new StripeCustomer((array)$data) : null;
    }

    /**
     * Find by Shopologic customer ID
     */
    public function findByCustomerId(int $customerId): ?StripeCustomer
    {
        $data = DB::table('stripe_customers')
            ->where('customer_id', $customerId)
            ->first();
            
        return $data ? new StripeCustomer((array)$data) : null;
    }

    /**
     * Find by Stripe ID
     */
    public function findByStripeId(string $stripeId): ?StripeCustomer
    {
        $data = DB::table('stripe_customers')
            ->where('stripe_id', $stripeId)
            ->first();
            
        return $data ? new StripeCustomer((array)$data) : null;
    }

    /**
     * Update customer
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = now();
        
        return DB::table('stripe_customers')
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Delete by Stripe ID
     */
    public function deleteByStripeId(string $stripeId): bool
    {
        return DB::table('stripe_customers')
            ->where('stripe_id', $stripeId)
            ->delete() > 0;
    }

    /**
     * Save payment method
     */
    public function savePaymentMethod(array $data): StripePaymentMethod
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();
        
        $id = DB::table('stripe_payment_methods')->insertGetId($data);
        return $this->findPaymentMethod($id);
    }

    /**
     * Find payment method
     */
    public function findPaymentMethod(int $id): ?StripePaymentMethod
    {
        $data = DB::table('stripe_payment_methods')->where('id', $id)->first();
        return $data ? new StripePaymentMethod((array)$data) : null;
    }

    /**
     * Get payment methods count for a customer
     */
    public function getPaymentMethodsCount(int $stripeCustomerId): int
    {
        return DB::table('stripe_payment_methods')
            ->where('stripe_customer_id', $stripeCustomerId)
            ->count();
    }

    /**
     * Get all customers with payment methods
     */
    public function getCustomersWithPaymentMethods(): Collection
    {
        return DB::table('stripe_customers as sc')
            ->join('stripe_payment_methods as spm', 'sc.id', '=', 'spm.stripe_customer_id')
            ->select('sc.*')
            ->distinct()
            ->get()
            ->map(fn($data) => new StripeCustomer((array)$data));
    }
}