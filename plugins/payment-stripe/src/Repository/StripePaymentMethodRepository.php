<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Repository;

use Shopologic\Core\Database\DB;
use Shopologic\Plugins\PaymentStripe\Models\StripePaymentMethod;
use Illuminate\Support\Collection;

class StripePaymentMethodRepository
{
    /**
     * Create a new payment method
     */
    public function create(array $data): StripePaymentMethod
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();
        
        $id = DB::table('stripe_payment_methods')->insertGetId($data);
        return $this->find($id);
    }

    /**
     * Find by ID
     */
    public function find(int $id): ?StripePaymentMethod
    {
        $data = DB::table('stripe_payment_methods')->where('id', $id)->first();
        return $data ? new StripePaymentMethod((array)$data) : null;
    }

    /**
     * Find by payment method ID
     */
    public function findByPaymentMethodId(string $paymentMethodId): ?StripePaymentMethod
    {
        $data = DB::table('stripe_payment_methods')
            ->where('payment_method_id', $paymentMethodId)
            ->first();
            
        return $data ? new StripePaymentMethod((array)$data) : null;
    }

    /**
     * Get by Stripe customer ID
     */
    public function getByStripeCustomerId(int $stripeCustomerId): Collection
    {
        return DB::table('stripe_payment_methods')
            ->where('stripe_customer_id', $stripeCustomerId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($data) => new StripePaymentMethod((array)$data));
    }

    /**
     * Count by Stripe customer ID
     */
    public function countByStripeCustomerId(int $stripeCustomerId): int
    {
        return DB::table('stripe_payment_methods')
            ->where('stripe_customer_id', $stripeCustomerId)
            ->count();
    }

    /**
     * Set default payment method
     */
    public function setDefault(int $stripeCustomerId, string $paymentMethodId): void
    {
        // Remove current default
        DB::table('stripe_payment_methods')
            ->where('stripe_customer_id', $stripeCustomerId)
            ->update(['is_default' => false]);
            
        // Set new default
        DB::table('stripe_payment_methods')
            ->where('stripe_customer_id', $stripeCustomerId)
            ->where('payment_method_id', $paymentMethodId)
            ->update(['is_default' => true]);
    }

    /**
     * Delete payment method
     */
    public function delete(int $id): bool
    {
        return DB::table('stripe_payment_methods')
            ->where('id', $id)
            ->delete() > 0;
    }
}