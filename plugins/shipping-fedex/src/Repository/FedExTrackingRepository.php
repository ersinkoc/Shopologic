<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedex\Repository;

use Shopologic\Core\Database\DB;
use Illuminate\Support\Collection;

class FedExTrackingRepository\n{
    /**
     * Create or update tracking event
     */
    public function createOrUpdate(array $data): bool
    {
        $existing = DB::table('fedex_tracking_events')
            ->where('tracking_number', $data['tracking_number'])
            ->where('event_timestamp', $data['event_timestamp'])
            ->where('event_type', $data['event_type'])
            ->first();

        if ($existing) {
            // Update if details changed
            return DB::table('fedex_tracking_events')
                ->where('id', $existing->id)
                ->update([
                    'event_description' => $data['event_description'],
                    'location' => $data['location'],
                    'details' => $data['details'],
                    'updated_at' => now()
                ]) > 0;
        }

        // Insert new event
        $data['created_at'] = now();
        $data['updated_at'] = now();
        
        return DB::table('fedex_tracking_events')->insert($data);
    }

    /**
     * Get events by tracking number
     */
    public function getEventsByTrackingNumber(string $trackingNumber): Collection
    {
        return DB::table('fedex_tracking_events')
            ->where('tracking_number', $trackingNumber)
            ->orderBy('event_timestamp', 'desc')
            ->get();
    }

    /**
     * Get latest event for tracking number
     */
    public function getLatestEvent(string $trackingNumber): ?object
    {
        return DB::table('fedex_tracking_events')
            ->where('tracking_number', $trackingNumber)
            ->orderBy('event_timestamp', 'desc')
            ->first();
    }

    /**
     * Delete old tracking events
     */
    public function deleteOldEvents(int $daysToKeep = 90): int
    {
        return DB::table('fedex_tracking_events')
            ->where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }

    /**
     * Get tracking timeline
     */
    public function getTrackingTimeline(string $trackingNumber): array
    {
        $events = $this->getEventsByTrackingNumber($trackingNumber);
        
        $timeline = [];
        foreach ($events as $event) {
            $date = date('Y-m-d', strtotime($event->event_timestamp));
            
            if (!isset($timeline[$date])) {
                $timeline[$date] = [];
            }
            
            $timeline[$date][] = [
                'time' => date('H:i', strtotime($event->event_timestamp)),
                'type' => $event->event_type,
                'description' => $event->event_description,
                'location' => $event->location
            ];
        }
        
        return $timeline;
    }
}