<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedEx\Repository;

use Shopologic\Core\Database\DB;
use Shopologic\Plugins\ShippingFedEx\Models\FedExShipment;
use Illuminate\Support\Collection;

class FedExShipmentRepository
{
    /**
     * Create a new shipment
     */
    public function create(array $data): FedExShipment
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();
        
        $id = DB::table('fedex_shipments')->insertGetId($data);
        return $this->find($id);
    }

    /**
     * Find shipment by ID
     */
    public function find(int $id): ?FedExShipment
    {
        $data = DB::table('fedex_shipments')->where('id', $id)->first();
        return $data ? new FedExShipment((array)$data) : null;
    }

    /**
     * Find shipment by tracking number
     */
    public function findByTrackingNumber(string $trackingNumber): ?FedExShipment
    {
        $data = DB::table('fedex_shipments')
            ->where('tracking_number', $trackingNumber)
            ->first();
            
        return $data ? new FedExShipment((array)$data) : null;
    }

    /**
     * Find shipments by order ID
     */
    public function findByOrderId(int $orderId): Collection
    {
        return DB::table('fedex_shipments')
            ->where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($data) => new FedExShipment((array)$data));
    }

    /**
     * Get active shipments (not delivered or cancelled)
     */
    public function getActiveShipments(): Collection
    {
        return DB::table('fedex_shipments')
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->where('created_at', '>', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(fn($data) => new FedExShipment((array)$data));
    }

    /**
     * Update shipment
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = now();
        
        return DB::table('fedex_shipments')
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Get cached rates
     */
    public function getCachedRates(string $cacheKey): ?object
    {
        return DB::table('fedex_rate_cache')
            ->where('cache_key', $cacheKey)
            ->where('created_at', '>', now()->subHours(4))
            ->first();
    }

    /**
     * Cache rates
     */
    public function cacheRates(string $cacheKey, array $rates): void
    {
        // Extract postal codes from cache key
        $parts = explode('_', $cacheKey);
        $fromPostal = $parts[2] ?? '';
        $toPostal = $parts[3] ?? '';
        $weight = $parts[4] ?? 0;

        DB::table('fedex_rate_cache')->insert([
            'cache_key' => $cacheKey,
            'rates' => json_encode($rates),
            'from_postal' => $fromPostal,
            'to_postal' => $toPostal,
            'total_weight' => $weight,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Clean up old rate cache
     */
    public function cleanupRateCache(int $daysToKeep = 7): int
    {
        return DB::table('fedex_rate_cache')
            ->where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }

    /**
     * Get shipment statistics
     */
    public function getStatistics(\DateTime $startDate, \DateTime $endDate): array
    {
        $stats = DB::table('fedex_shipments')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_shipments,
                SUM(rate) as total_shipping_cost,
                AVG(rate) as average_shipping_cost,
                COUNT(DISTINCT order_id) as unique_orders,
                service_type,
                status
            ')
            ->groupBy(['service_type', 'status'])
            ->get();

        return $stats->toArray();
    }

    /**
     * Get delivery performance metrics
     */
    public function getDeliveryPerformance(\DateTime $startDate, \DateTime $endDate): array
    {
        return DB::table('fedex_shipments')
            ->whereBetween('shipped_at', [$startDate, $endDate])
            ->whereNotNull('delivered_at')
            ->selectRaw('
                service_type,
                COUNT(*) as total_delivered,
                AVG(EXTRACT(EPOCH FROM (delivered_at - shipped_at))/86400) as avg_delivery_days,
                MIN(EXTRACT(EPOCH FROM (delivered_at - shipped_at))/86400) as min_delivery_days,
                MAX(EXTRACT(EPOCH FROM (delivered_at - shipped_at))/86400) as max_delivery_days
            ')
            ->groupBy('service_type')
            ->get()
            ->toArray();
    }
}