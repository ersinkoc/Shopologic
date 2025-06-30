<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedex\Shipping;

use Shopologic\Core\Ecommerce\Shipping\ShippingMethodInterface;
use Shopologic\Core\Ecommerce\Shipping\ShippingRequest;
use Shopologic\Core\Ecommerce\Shipping\ShippingRate;
use Shopologic\Core\Ecommerce\Shipping\ShipmentResponse;
use Shopologic\Core\Ecommerce\Shipping\LabelResponse;
use Shopologic\Core\Ecommerce\Shipping\TrackingResponse;
use Shopologic\Core\Ecommerce\Shipping\AddressValidationResponse;
use Shopologic\Core\Ecommerce\Shipping\PickupRequest;
use Shopologic\Core\Ecommerce\Shipping\PickupResponse;
use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Models\CustomerAddress;
use Shopologic\Plugins\ShippingFedEx\Services\FedExApiClient;
use Shopologic\Plugins\ShippingFedEx\Services\FedExRateCalculator;
use Shopologic\Plugins\ShippingFedEx\Services\FedExLabelGenerator;
use Shopologic\Plugins\ShippingFedEx\Services\FedExTrackingService;
use Shopologic\Plugins\ShippingFedEx\Services\FedExAddressValidator;
use Shopologic\Plugins\ShippingFedEx\Repository\FedExShipmentRepository;

class FedExShippingMethod implements ShippingMethodInterface
{
    private FedExApiClient $apiClient;
    private FedExRateCalculator $rateCalculator;
    private FedExLabelGenerator $labelGenerator;
    private FedExTrackingService $trackingService;
    private FedExAddressValidator $addressValidator;
    private FedExShipmentRepository $shipmentRepository;
    private array $config;

    public function __construct(
        FedExApiClient $apiClient,
        FedExRateCalculator $rateCalculator,
        FedExLabelGenerator $labelGenerator,
        FedExTrackingService $trackingService,
        FedExAddressValidator $addressValidator,
        FedExShipmentRepository $shipmentRepository,
        array $config = []
    ) {
        $this->apiClient = $apiClient;
        $this->rateCalculator = $rateCalculator;
        $this->labelGenerator = $labelGenerator;
        $this->trackingService = $trackingService;
        $this->addressValidator = $addressValidator;
        $this->shipmentRepository = $shipmentRepository;
        $this->config = $config;
    }

    public function getId(): string
    {
        return 'fedex';
    }

    public function getName(): string
    {
        return 'FedEx';
    }

    public function getDescription(): string
    {
        return 'FedEx shipping services with real-time rates and tracking';
    }

    public function isAvailable(Order $order): bool
    {
        // Check if configured
        if (!$this->isConfigured()) {
            return false;
        }

        // Check if shipping address is provided
        if (!$order->shippingAddress) {
            return false;
        }

        // Check if destination is supported
        $supportedCountries = ['US', 'CA', 'MX']; // Add more as needed
        if (!in_array($order->shippingAddress->country_code, $supportedCountries)) {
            return false;
        }

        // Check if any enabled services are available
        $enabledServices = $this->config['enabled_services'] ?? [];
        return !empty($enabledServices);
    }

    public function calculateRates(ShippingRequest $request): array
    {
        try {
            // Check cache first
            $cacheKey = $this->generateRateCacheKey($request);
            $cachedRates = $this->shipmentRepository->getCachedRates($cacheKey);
            
            if ($cachedRates && $cachedRates->created_at->gt(now()->subHours(4))) {
                return $cachedRates->rates;
            }

            // Calculate rates using FedEx API
            $rates = $this->rateCalculator->calculateRates($request, $this->config);

            // Cache the rates
            $this->shipmentRepository->cacheRates($cacheKey, $rates);

            return $rates;

        } catch (\RuntimeException $e) {
            logger()->error('FedEx rate calculation failed', [
                'error' => $e->getMessage(),
                'from' => $request->getFromAddress()->postal_code,
                'to' => $request->getToAddress()->postal_code
            ]);
            return [];
        }
    }

    public function createShipment(Order $order, array $options = []): ShipmentResponse
    {
        try {
            // Prepare shipment data
            $shipmentData = $this->prepareShipmentData($order, $options);

            // Create shipment via FedEx API
            $fedexResponse = $this->apiClient->createShipment($shipmentData);

            // Save shipment to database
            $shipment = $this->shipmentRepository->create([
                'order_id' => $order->id,
                'tracking_number' => $fedexResponse['tracking_number'],
                'service_type' => $options['service_type'] ?? $this->extractServiceType($order->shipping_method),
                'master_tracking_number' => $fedexResponse['master_tracking_number'] ?? null,
                'shipment_digest' => $fedexResponse['shipment_digest'] ?? null,
                'label_data' => $fedexResponse['label_data'] ?? null,
                'rate' => $order->shipping_cost,
                'currency' => $order->currency,
                'status' => 'created',
                'metadata' => json_encode($fedexResponse['metadata'] ?? [])
            ]);

            // Update order with tracking number
            $order->tracking_number = $fedexResponse['tracking_number'];
            $order->save();

            return new ShipmentResponse(
                success: true,
                shipmentId: (string) $shipment->id,
                trackingNumber: $fedexResponse['tracking_number'],
                labelUrl: $fedexResponse['label_url'] ?? null,
                cost: $order->shipping_cost,
                estimatedDelivery: $fedexResponse['estimated_delivery'] ?? null
            );

        } catch (\RuntimeException $e) {
            logger()->error('FedEx shipment creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return new ShipmentResponse(
                success: false,
                error: $e->getMessage()
            );
        }
    }

    public function generateLabel(string $shipmentId): LabelResponse
    {
        try {
            $shipment = $this->shipmentRepository->find((int) $shipmentId);
            
            if (!$shipment) {
                throw new \Exception('Shipment not found');
            }

            // Generate label if not already generated
            if (!$shipment->label_data) {
                $labelData = $this->labelGenerator->generateLabel($shipment);
                
                $shipment->label_data = $labelData['data'];
                $shipment->label_format = $labelData['format'];
                $shipment->save();
            }

            return new LabelResponse(
                success: true,
                labelData: $shipment->label_data,
                format: $shipment->label_format ?? 'PDF',
                trackingNumber: $shipment->tracking_number
            );

        } catch (\RuntimeException $e) {
            return new LabelResponse(
                success: false,
                error: $e->getMessage()
            );
        }
    }

    public function trackShipment(string $trackingNumber): TrackingResponse
    {
        try {
            $trackingInfo = $this->trackingService->track($trackingNumber);

            return new TrackingResponse(
                success: true,
                trackingNumber: $trackingNumber,
                status: $trackingInfo['status'],
                statusDescription: $trackingInfo['status_description'],
                estimatedDelivery: $trackingInfo['estimated_delivery'] ?? null,
                actualDelivery: $trackingInfo['actual_delivery'] ?? null,
                currentLocation: $trackingInfo['current_location'] ?? null,
                events: $trackingInfo['events'] ?? []
            );

        } catch (\RuntimeException $e) {
            return new TrackingResponse(
                success: false,
                trackingNumber: $trackingNumber,
                error: $e->getMessage()
            );
        }
    }

    public function cancelShipment(string $shipmentId): bool
    {
        try {
            $shipment = $this->shipmentRepository->find((int) $shipmentId);
            
            if (!$shipment) {
                return false;
            }

            // Can only cancel if not picked up
            if (in_array($shipment->status, ['picked_up', 'in_transit', 'delivered'])) {
                return false;
            }

            // Cancel via FedEx API
            $result = $this->apiClient->cancelShipment($shipment->tracking_number);

            if ($result) {
                $shipment->status = 'cancelled';
                $shipment->cancelled_at = now();
                $shipment->save();
            }

            return $result;

        } catch (\RuntimeException $e) {
            logger()->error('FedEx shipment cancellation failed', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getAvailableServices(): array
    {
        $enabledServices = $this->config['enabled_services'] ?? [];
        $services = [];

        foreach ($enabledServices as $serviceCode) {
            $services[] = [
                'code' => $serviceCode,
                'name' => $this->getServiceName($serviceCode),
                'description' => $this->getServiceDescription($serviceCode),
                'domestic' => !str_contains($serviceCode, 'INTERNATIONAL'),
                'express' => str_contains($serviceCode, 'OVERNIGHT') || str_contains($serviceCode, 'PRIORITY')
            ];
        }

        return $services;
    }

    public function validateAddress(CustomerAddress $address): AddressValidationResponse
    {
        try {
            $result = $this->addressValidator->validate($address);

            return new AddressValidationResponse(
                valid: $result['valid'],
                suggestions: $result['suggestions'] ?? [],
                errors: $result['errors'] ?? [],
                confidence: $result['confidence'] ?? 0
            );

        } catch (\RuntimeException $e) {
            return new AddressValidationResponse(
                valid: false,
                errors: ['Failed to validate address: ' . $e->getMessage()]
            );
        }
    }

    public function schedulePickup(PickupRequest $request): PickupResponse
    {
        try {
            $pickupData = [
                'pickup_location' => $request->getPickupAddress(),
                'pickup_date' => $request->getPickupDate(),
                'ready_time' => $request->getReadyTime(),
                'close_time' => $request->getCloseTime(),
                'package_count' => $request->getPackageCount(),
                'total_weight' => $request->getTotalWeight(),
                'special_instructions' => $request->getSpecialInstructions()
            ];

            $result = $this->apiClient->schedulePickup($pickupData);

            return new PickupResponse(
                success: true,
                confirmationNumber: $result['confirmation_number'],
                pickupDate: $request->getPickupDate(),
                pickupCharge: $result['pickup_charge'] ?? 0
            );

        } catch (\RuntimeException $e) {
            return new PickupResponse(
                success: false,
                error: $e->getMessage()
            );
        }
    }

    private function isConfigured(): bool
    {
        return !empty($this->config['account_number']) &&
               !empty($this->config['meter_number']) &&
               !empty($this->config['key']) &&
               !empty($this->config['password']);
    }

    private function generateRateCacheKey(ShippingRequest $request): string
    {
        $key = sprintf(
            'fedex_rates_%s_%s_%s_%s',
            $request->getFromAddress()->postal_code,
            $request->getToAddress()->postal_code,
            $request->getTotalWeight(),
            md5(json_encode($request->getPackages()))
        );

        return $key;
    }

    private function prepareShipmentData(Order $order, array $options): array
    {
        return [
            'shipper' => $this->formatAddress($order->store->address ?? $this->getDefaultShipperAddress()),
            'recipient' => $this->formatAddress($order->shippingAddress),
            'service_type' => $options['service_type'] ?? $this->extractServiceType($order->shipping_method),
            'packaging_type' => $options['packaging_type'] ?? $this->config['default_packaging'],
            'dropoff_type' => $this->config['dropoff_type'],
            'packages' => $this->preparePackages($order, $options),
            'payment' => [
                'type' => 'SENDER',
                'account_number' => $this->config['account_number']
            ],
            'label_specification' => [
                'label_format_type' => $this->config['label_format'],
                'label_stock_type' => $this->config['label_stock_type']
            ],
            'special_services' => $this->prepareSpecialServices($order, $options)
        ];
    }

    private function formatAddress($address): array
    {
        return [
            'contact' => [
                'person_name' => $address->name,
                'company_name' => $address->company,
                'phone_number' => $address->phone
            ],
            'address' => [
                'street_lines' => array_filter([$address->line1, $address->line2]),
                'city' => $address->city,
                'state_or_province_code' => $address->state,
                'postal_code' => $address->postal_code,
                'country_code' => $address->country_code
            ]
        ];
    }

    private function preparePackages(Order $order, array $options): array
    {
        // If packages are provided in options, use them
        if (!empty($options['packages'])) {
            return $options['packages'];
        }

        // Otherwise, create a single package for the order
        return [[
            'weight' => [
                'units' => 'LB',
                'value' => $order->total_weight ?: 1.0
            ],
            'dimensions' => $options['dimensions'] ?? null,
            'customer_references' => [
                ['type' => 'CUSTOMER_REFERENCE', 'value' => $order->order_number]
            ]
        ]];
    }

    private function prepareSpecialServices(Order $order, array $options): array
    {
        $services = [];

        // Insurance
        if ($this->config['insurance_enabled'] && $order->total > 100) {
            $services['insurance'] = [
                'insured_value' => [
                    'currency' => $order->currency,
                    'amount' => $order->total
                ]
            ];
        }

        // Signature
        if ($this->config['signature_option'] !== 'NO_SIGNATURE') {
            $services['signature_option'] = $this->config['signature_option'];
        }

        // COD if applicable
        if ($order->payment_method === 'cod') {
            $services['cod'] = [
                'cod_collection_amount' => [
                    'currency' => $order->currency,
                    'amount' => $order->total
                ]
            ];
        }

        return $services;
    }

    private function extractServiceType(string $shippingMethod): string
    {
        // Extract FedEx service type from shipping method
        // e.g., "fedex_ground" -> "FEDEX_GROUND"
        $parts = explode('_', $shippingMethod);
        if (count($parts) > 1 && $parts[0] === 'fedex') {
            array_shift($parts);
            return strtoupper(implode('_', $parts));
        }

        return 'FEDEX_GROUND'; // Default
    }

    private function getServiceName(string $service): string
    {
        $names = [
            'FEDEX_GROUND' => 'FedEx Ground',
            'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
            'FEDEX_2_DAY' => 'FedEx 2Day',
            'FEDEX_2_DAY_AM' => 'FedEx 2Day A.M.',
            'STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
            'PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
            'FIRST_OVERNIGHT' => 'FedEx First Overnight',
            'INTERNATIONAL_ECONOMY' => 'FedEx International Economy',
            'INTERNATIONAL_PRIORITY' => 'FedEx International Priority'
        ];

        return $names[$service] ?? $service;
    }

    private function getServiceDescription(string $service): string
    {
        $descriptions = [
            'FEDEX_GROUND' => 'Economical ground delivery in 1-5 business days',
            'FEDEX_EXPRESS_SAVER' => 'Delivery in 3 business days',
            'FEDEX_2_DAY' => 'Delivery in 2 business days by 4:30 PM',
            'FEDEX_2_DAY_AM' => 'Delivery in 2 business days by 10:30 AM',
            'STANDARD_OVERNIGHT' => 'Next business day delivery by 3:00 PM',
            'PRIORITY_OVERNIGHT' => 'Next business day delivery by 10:30 AM',
            'FIRST_OVERNIGHT' => 'Next business day delivery, first delivery of the day',
            'INTERNATIONAL_ECONOMY' => 'International economy service in 2-5 business days',
            'INTERNATIONAL_PRIORITY' => 'International priority service in 1-3 business days'
        ];

        return $descriptions[$service] ?? '';
    }

    private function getDefaultShipperAddress(): CustomerAddress
    {
        // This would typically come from store configuration
        $address = new CustomerAddress();
        $address->name = 'Shopologic';
        $address->line1 = '123 Main St';
        $address->city = 'New York';
        $address->state = 'NY';
        $address->postal_code = '10001';
        $address->country_code = 'US';
        $address->phone = '555-123-4567';
        
        return $address;
    }
}