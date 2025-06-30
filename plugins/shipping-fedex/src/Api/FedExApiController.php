<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedex\Api;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\JsonResponse;
use Shopologic\Core\Controller\ApiController;
use Shopologic\Core\Auth\Auth;
use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Models\CustomerAddress;
use Shopologic\Core\Ecommerce\Shipping\ShippingRequest;
use Shopologic\Core\Ecommerce\Shipping\PickupRequest;
use Shopologic\Plugins\ShippingFedEx\Shipping\FedExShippingMethod;
use Shopologic\Plugins\ShippingFedEx\Services\FedExTrackingService;

class FedExApiController extends ApiController
{
    private FedExShippingMethod $shippingMethod;
    private FedExTrackingService $trackingService;

    public function __construct(
        FedExShippingMethod $shippingMethod,
        FedExTrackingService $trackingService
    ) {
        $this->shippingMethod = $shippingMethod;
        $this->trackingService = $trackingService;
    }

    /**
     * Calculate shipping rates
     */
    public function calculateRates(Request $request): JsonResponse
    {
        try {
            $validated = $this->validate($request, [
                'from_address' => 'required|array',
                'to_address' => 'required|array',
                'packages' => 'required|array|min:1',
                'service_type' => 'string|nullable'
            ]);

            // Create address objects
            $fromAddress = $this->createAddress($validated['from_address']);
            $toAddress = $this->createAddress($validated['to_address']);

            // Create shipping request
            $shippingRequest = new ShippingRequest(
                $fromAddress,
                $toAddress,
                $validated['packages'],
                $validated['service_type'] ?? null
            );

            // Calculate rates
            $rates = $this->shippingMethod->calculateRates($shippingRequest);

            // Format response
            $formattedRates = array_map(function($rate) {
                return $rate->toArray();
            }, $rates);

            return $this->respondWithSuccess([
                'rates' => $formattedRates,
                'from' => $fromAddress->toArray(),
                'to' => $toAddress->toArray(),
                'package_count' => count($validated['packages'])
            ]);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 400);
        }
    }

    /**
     * Generate shipping label
     */
    public function generateLabel(Request $request): JsonResponse
    {
        try {
            // Verify permission
            if (!Auth::user()->can('shipping.fedex.create_label')) {
                return $this->respondWithError('Insufficient permissions', 403);
            }

            $validated = $this->validate($request, [
                'order_id' => 'required|integer',
                'service_type' => 'required|string',
                'packages' => 'array',
                'options' => 'array'
            ]);

            $order = Order::findOrFail($validated['order_id']);

            // Create shipment
            $response = $this->shippingMethod->createShipment($order, [
                'service_type' => $validated['service_type'],
                'packages' => $validated['packages'] ?? [],
                'options' => $validated['options'] ?? []
            ]);

            if (!$response->isSuccessful()) {
                return $this->respondWithError($response->getError(), 400);
            }

            // Generate label
            $labelResponse = $this->shippingMethod->generateLabel($response->getShipmentId());

            if (!$labelResponse->isSuccessful()) {
                return $this->respondWithError($labelResponse->getError(), 400);
            }

            return $this->respondWithSuccess([
                'shipment_id' => $response->getShipmentId(),
                'tracking_number' => $response->getTrackingNumber(),
                'label_format' => $labelResponse->getFormat(),
                'label_data' => $labelResponse->getLabelData(),
                'estimated_delivery' => $response->getEstimatedDelivery()
            ]);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Track shipment
     */
    public function trackShipment(Request $request, string $trackingNumber): JsonResponse
    {
        try {
            $response = $this->shippingMethod->trackShipment($trackingNumber);

            if (!$response->isSuccessful()) {
                return $this->respondWithError($response->getError(), 404);
            }

            return $this->respondWithSuccess([
                'tracking_number' => $response->getTrackingNumber(),
                'status' => $response->getStatus(),
                'status_description' => $response->getStatusDescription(),
                'estimated_delivery' => $response->getEstimatedDelivery(),
                'actual_delivery' => $response->getActualDelivery(),
                'current_location' => $response->getCurrentLocation(),
                'delivered' => $response->isDelivered(),
                'events' => $response->getEvents()
            ]);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Schedule pickup
     */
    public function schedulePickup(Request $request): JsonResponse
    {
        try {
            // Verify permission
            if (!Auth::user()->can('shipping.fedex.schedule_pickup')) {
                return $this->respondWithError('Insufficient permissions', 403);
            }

            $validated = $this->validate($request, [
                'pickup_address' => 'required|array',
                'pickup_date' => 'required|date|after:today',
                'ready_time' => 'required|string',
                'close_time' => 'required|string',
                'package_count' => 'required|integer|min:1',
                'total_weight' => 'required|numeric|min:0.1',
                'special_instructions' => 'string|nullable'
            ]);

            $pickupAddress = $this->createAddress($validated['pickup_address']);

            $pickupRequest = new PickupRequest(
                $pickupAddress,
                $validated['pickup_date'],
                $validated['ready_time'],
                $validated['close_time'],
                $validated['package_count'],
                $validated['total_weight'],
                $validated['special_instructions'] ?? null
            );

            $response = $this->shippingMethod->schedulePickup($pickupRequest);

            if (!$response->isSuccessful()) {
                return $this->respondWithError($response->getError(), 400);
            }

            return $this->respondWithSuccess([
                'confirmation_number' => $response->getConfirmationNumber(),
                'pickup_date' => $response->getPickupDate(),
                'pickup_charge' => $response->getPickupCharge()
            ]);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Validate address
     */
    public function validateAddress(Request $request): JsonResponse
    {
        try {
            $validated = $this->validate($request, [
                'line1' => 'required|string',
                'line2' => 'string|nullable',
                'city' => 'required|string',
                'state' => 'required|string',
                'postal_code' => 'required|string',
                'country_code' => 'required|string|size:2'
            ]);

            $address = new CustomerAddress($validated);
            $response = $this->shippingMethod->validateAddress($address);

            return $this->respondWithSuccess([
                'valid' => $response->isValid(),
                'confidence' => $response->getConfidence(),
                'suggestions' => $response->getSuggestions(),
                'errors' => $response->getErrors()
            ]);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Get available services
     */
    public function getServices(Request $request): JsonResponse
    {
        try {
            $services = $this->shippingMethod->getAvailableServices();

            return $this->respondWithSuccess([
                'services' => $services
            ]);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Get settings (admin only)
     */
    public function getSettings(Request $request): JsonResponse
    {
        try {
            // Verify admin permission
            if (!Auth::user()->can('shipping.fedex.configure')) {
                return $this->respondWithError('Insufficient permissions', 403);
            }

            $config = $this->shippingMethod->getConfiguration();
            
            // Remove sensitive data
            unset($config['account_number']);
            unset($config['meter_number']);
            unset($config['key']);
            unset($config['password']);

            return $this->respondWithSuccess($config);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Update settings (admin only)
     */
    public function updateSettings(Request $request): JsonResponse
    {
        try {
            // Verify admin permission
            if (!Auth::user()->can('shipping.fedex.configure')) {
                return $this->respondWithError('Insufficient permissions', 403);
            }

            $validated = $this->validate($request, [
                'account_number' => 'string',
                'meter_number' => 'string',
                'key' => 'string',
                'password' => 'string',
                'environment' => 'string|in:sandbox,production',
                'default_packaging' => 'string',
                'dropoff_type' => 'string',
                'enabled_services' => 'array',
                'insurance_enabled' => 'boolean',
                'signature_option' => 'string',
                'label_format' => 'string|in:PDF,PNG,ZPL',
                'label_stock_type' => 'string'
            ]);

            // Save configuration
            $plugin = $this->container->get('plugin.manager')->getPlugin('shipping-fedex');
            $plugin->updatePluginConfig($validated);

            return $this->respondWithSuccess(['message' => 'Settings updated successfully']);

        } catch (\RuntimeException $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }

    /**
     * Test connection (admin only)
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            // Verify admin permission
            if (!Auth::user()->can('shipping.fedex.configure')) {
                return $this->respondWithError('Insufficient permissions', 403);
            }

            // Test with a simple rate request
            $testRequest = new ShippingRequest(
                $this->createTestAddress('US'),
                $this->createTestAddress('US'),
                [['weight' => 1.0]]
            );

            $rates = $this->shippingMethod->calculateRates($testRequest);

            return $this->respondWithSuccess([
                'connected' => !empty($rates),
                'available_services' => count($rates),
                'environment' => $this->container->get('config')->get('fedex.environment', 'sandbox')
            ]);

        } catch (\RuntimeException $e) {
            return $this->respondWithError('Connection failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create address from array
     */
    private function createAddress(array $data): CustomerAddress
    {
        $address = new CustomerAddress();
        $address->line1 = $data['line1'] ?? '';
        $address->line2 = $data['line2'] ?? '';
        $address->city = $data['city'] ?? '';
        $address->state = $data['state'] ?? '';
        $address->postal_code = $data['postal_code'] ?? '';
        $address->country_code = $data['country_code'] ?? 'US';
        $address->name = $data['name'] ?? '';
        $address->company = $data['company'] ?? '';
        $address->phone = $data['phone'] ?? '';
        
        return $address;
    }

    /**
     * Create test address
     */
    private function createTestAddress(string $countryCode): CustomerAddress
    {
        $address = new CustomerAddress();
        
        if ($countryCode === 'US') {
            $address->line1 = '123 Test St';
            $address->city = 'New York';
            $address->state = 'NY';
            $address->postal_code = '10001';
            $address->country_code = 'US';
        } else {
            $address->line1 = '123 Test St';
            $address->city = 'Toronto';
            $address->state = 'ON';
            $address->postal_code = 'M5V 3A8';
            $address->country_code = 'CA';
        }
        
        return $address;
    }
}