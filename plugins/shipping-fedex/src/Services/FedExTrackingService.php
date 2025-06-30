<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedex\Services;

use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Plugins\ShippingFedEx\Repository\FedExTrackingRepository;
use Shopologic\Plugins\ShippingFedEx\Repository\FedExShipmentRepository;

class FedExTrackingService\n{
    private FedExApiClient $apiClient;
    private FedExTrackingRepository $trackingRepository;
    private FedExShipmentRepository $shipmentRepository;

    public function __construct(
        FedExApiClient $apiClient,
        FedExTrackingRepository $trackingRepository,
        FedExShipmentRepository $shipmentRepository
    ) {
        $this->apiClient = $apiClient;
        $this->trackingRepository = $trackingRepository;
        $this->shipmentRepository = $shipmentRepository;
    }

    /**
     * Track a shipment
     */
    public function track(string $trackingNumber): array
    {
        try {
            // Get tracking info from FedEx
            $trackingData = $this->apiClient->track($trackingNumber);

            // Save tracking events
            $this->saveTrackingEvents($trackingNumber, $trackingData['events'] ?? []);

            return $trackingData;

        } catch (\RuntimeException $e) {
            logger()->error('FedEx tracking failed', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);
            
            // Return cached data if available
            return $this->getCachedTrackingInfo($trackingNumber);
        }
    }

    /**
     * Start tracking an order
     */
    public function startTracking(Order $order): void
    {
        if (!$order->tracking_number) {
            return;
        }

        $shipment = $this->shipmentRepository->findByTrackingNumber($order->tracking_number);
        
        if (!$shipment) {
            $shipment = $this->shipmentRepository->create([
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'service_type' => $this->extractServiceType($order->shipping_method),
                'status' => 'shipped',
                'rate' => $order->shipping_cost,
                'currency' => $order->currency
            ]);
        }

        // Initial tracking update
        $this->updateShipmentTracking($shipment->id);
    }

    /**
     * Update tracking for active shipments
     */
    public function updateActiveShipments(): void
    {
        $activeShipments = $this->shipmentRepository->getActiveShipments();

        foreach ($activeShipments as $shipment) {
            try {
                $this->updateShipmentTracking($shipment->id);
            } catch (\RuntimeException $e) {
                logger()->error('Failed to update shipment tracking', [
                    'shipment_id' => $shipment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Update tracking for a specific shipment
     */
    public function updateShipmentTracking(int $shipmentId): void
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        
        if (!$shipment || !$shipment->tracking_number) {
            return;
        }

        $trackingData = $this->track($shipment->tracking_number);

        // Update shipment status
        $status = $this->mapTrackingStatus($trackingData['status'] ?? '');
        
        if ($status !== $shipment->status) {
            $shipment->status = $status;
            
            if ($status === 'delivered') {
                $shipment->delivered_at = $trackingData['actual_delivery'];
            }
            
            $shipment->save();

            // Trigger event
            event('shipping.status_changed', [
                'shipment' => $shipment,
                'old_status' => $shipment->status,
                'new_status' => $status,
                'tracking_data' => $trackingData
            ]);
        }
    }

    /**
     * Get tracking info for an order
     */
    public function getTrackingInfo(string $trackingNumber): array
    {
        // Try to get fresh data
        try {
            return $this->track($trackingNumber);
        } catch (\RuntimeException $e) {
            // Fall back to cached data
            return $this->getCachedTrackingInfo($trackingNumber);
        }
    }

    /**
     * Save tracking events
     */
    private function saveTrackingEvents(string $trackingNumber, array $events): void
    {
        foreach ($events as $event) {
            $this->trackingRepository->createOrUpdate([
                'tracking_number' => $trackingNumber,
                'event_timestamp' => $event['timestamp'],
                'event_type' => $event['status'],
                'event_description' => $event['description'],
                'location' => $event['location'],
                'details' => json_encode($event['details'] ?? [])
            ]);
        }
    }

    /**
     * Get cached tracking info
     */
    private function getCachedTrackingInfo(string $trackingNumber): array
    {
        $events = $this->trackingRepository->getEventsByTrackingNumber($trackingNumber);
        
        if ($events->isEmpty()) {
            return [
                'tracking_number' => $trackingNumber,
                'status' => 'unknown',
                'events' => []
            ];
        }

        $latestEvent = $events->first();
        
        return [
            'tracking_number' => $trackingNumber,
            'status' => $latestEvent->event_type,
            'status_description' => $latestEvent->event_description,
            'current_location' => $latestEvent->location,
            'events' => $events->map(function ($event) {
                return [
                    'timestamp' => $event->event_timestamp,
                    'status' => $event->event_type,
                    'description' => $event->event_description,
                    'location' => $event->location,
                    'details' => json_decode($event->details, true)
                ];
            })->toArray()
        ];
    }

    /**
     * Map FedEx tracking status to internal status
     */
    private function mapTrackingStatus(string $fedexStatus): string
    {
        $mapping = [
            'PU' => 'picked_up',
            'OC' => 'origin_scan',
            'IT' => 'in_transit',
            'DE' => 'out_for_delivery',
            'DL' => 'delivered',
            'CA' => 'cancelled',
            'RS' => 'return_to_sender',
            'EX' => 'exception'
        ];

        return $mapping[$fedexStatus] ?? 'in_transit';
    }

    /**
     * Extract service type from shipping method
     */
    private function extractServiceType(string $shippingMethod): string
    {
        $parts = explode('_', $shippingMethod);
        if (count($parts) > 1 && $parts[0] === 'fedex') {
            array_shift($parts);
            return strtoupper(implode('_', $parts));
        }

        return 'FEDEX_GROUND';
    }

    /**
     * Subscribe to tracking updates via webhook
     */
    public function subscribeToUpdates(string $trackingNumber): bool
    {
        try {
            $response = $this->apiClient->request('POST', '/track/v1/notifications', [
                'trackingNumber' => $trackingNumber,
                'notificationDetail' => [
                    'notificationType' => 'ON_DELIVERY',
                    'emailDetail' => [
                        'emailAddress' => config('mail.from.address'),
                        'notificationEventType' => ['ON_DELIVERY', 'ON_EXCEPTION']
                    ]
                ]
            ]);

            return true;

        } catch (\RuntimeException $e) {
            logger()->error('Failed to subscribe to tracking updates', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}