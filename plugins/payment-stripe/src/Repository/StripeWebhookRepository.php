<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Repository;

use Shopologic\Core\Database\DB;

class StripeWebhookRepository  
{
    /**
     * Create webhook log entry
     */
    public function create(array $data): object
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();
        
        $id = DB::table('stripe_webhooks')->insertGetId($data);
        return DB::table('stripe_webhooks')->where('id', $id)->first();
    }

    /**
     * Check if webhook has been processed
     */
    public function isProcessed(string $eventId): bool
    {
        return DB::table('stripe_webhooks')
            ->where('event_id', $eventId)
            ->where('processed', true)
            ->exists();
    }

    /**
     * Clean up old webhook logs
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int
    {
        return DB::table('stripe_webhooks')
            ->where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }

    /**
     * Get recent webhook errors
     */
    public function getRecentErrors(int $limit = 10): array
    {
        return DB::table('stripe_webhooks')
            ->whereNotNull('error')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get webhook statistics
     */
    public function getStatistics(\DateTime $startDate, \DateTime $endDate): array
    {
        return [
            'total' => DB::table('stripe_webhooks')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
                
            'processed' => DB::table('stripe_webhooks')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('processed', true)
                ->count(),
                
            'failed' => DB::table('stripe_webhooks')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('error')
                ->count(),
                
            'by_type' => DB::table('stripe_webhooks')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('event_type')
                ->selectRaw('event_type, COUNT(*) as count')
                ->pluck('count', 'event_type')
                ->toArray()
        ];
    }
}