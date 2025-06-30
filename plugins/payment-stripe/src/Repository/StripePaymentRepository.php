<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Repository;

use Shopologic\Core\Database\DB;
use Shopologic\Plugins\PaymentStripe\Models\StripePayment;
use Shopologic\Plugins\PaymentStripe\Models\StripeRefund;
use Illuminate\Support\Collection;

class StripePaymentRepository\n{
    /**
     * Create a new payment record
     */
    public function create(array $data): StripePayment
    {
        $id = DB::table('stripe_payments')->insertGetId($data);
        return $this->find($id);
    }

    /**
     * Find payment by ID
     */
    public function find(int $id): ?StripePayment
    {
        $data = DB::table('stripe_payments')->where('id', $id)->first();
        return $data ? new StripePayment((array)$data) : null;
    }

    /**
     * Find payment by payment intent ID
     */
    public function findByPaymentIntentId(string $paymentIntentId): ?StripePayment
    {
        $data = DB::table('stripe_payments')
            ->where('payment_intent_id', $paymentIntentId)
            ->first();
            
        return $data ? new StripePayment((array)$data) : null;
    }

    /**
     * Find payment by charge ID
     */
    public function findByChargeId(string $chargeId): ?StripePayment
    {
        $data = DB::table('stripe_payments')
            ->where('charge_id', $chargeId)
            ->first();
            
        return $data ? new StripePayment((array)$data) : null;
    }

    /**
     * Get pending payments for status sync
     */
    public function getPendingPayments(): Collection
    {
        return DB::table('stripe_payments')
            ->whereIn('status', ['processing', 'requires_action', 'requires_capture'])
            ->where('created_at', '>', now()->subHours(24))
            ->get()
            ->map(fn($data) => new StripePayment((array)$data));
    }

    /**
     * Create a refund record
     */
    public function createRefund(array $data): StripeRefund
    {
        $id = DB::table('stripe_refunds')->insertGetId($data);
        return $this->findRefund($id);
    }

    /**
     * Find refund by ID
     */
    public function findRefund(int $id): ?StripeRefund
    {
        $data = DB::table('stripe_refunds')->where('id', $id)->first();
        return $data ? new StripeRefund((array)$data) : null;
    }

    /**
     * Find refund by charge ID
     */
    public function findRefundByChargeId(string $chargeId): ?StripeRefund
    {
        $payment = $this->findByChargeId($chargeId);
        
        if (!$payment) {
            return null;
        }

        $data = DB::table('stripe_refunds')
            ->where('stripe_payment_id', $payment->id)
            ->orderBy('created_at', 'desc')
            ->first();
            
        return $data ? new StripeRefund((array)$data) : null;
    }

    /**
     * Log webhook event
     */
    public function logWebhook(array $data): int
    {
        return DB::table('stripe_webhooks')->insertGetId($data);
    }

    /**
     * Mark webhook as processed
     */
    public function markWebhookProcessed(string $eventId): void
    {
        DB::table('stripe_webhooks')
            ->where('event_id', $eventId)
            ->update([
                'processed' => true,
                'processed_at' => now()
            ]);
    }

    /**
     * Get payments by order ID
     */
    public function getByOrderId(int $orderId): Collection
    {
        return DB::table('stripe_payments')
            ->where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($data) => new StripePayment((array)$data));
    }

    /**
     * Get payments by customer ID
     */
    public function getByCustomerId(int $customerId): Collection
    {
        return DB::table('stripe_payments')
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(fn($data) => new StripePayment((array)$data));
    }

    /**
     * Get payment statistics for a date range
     */
    public function getStatistics(\DateTime $startDate, \DateTime $endDate): array
    {
        $stats = DB::table('stripe_payments')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'succeeded')
            ->selectRaw('
                COUNT(*) as total_payments,
                SUM(amount) as total_amount,
                AVG(amount) as average_amount,
                currency
            ')
            ->groupBy('currency')
            ->get();

        return $stats->toArray();
    }
}