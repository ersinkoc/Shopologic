<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedEx\Services;

use Shopologic\Plugins\ShippingFedEx\Exceptions\FedExException;

/**
 * FedEx Web Services API Client
 * Implements FedEx API v1 without external dependencies
 */
class FedExApiClient
{
    private const API_BASE_SANDBOX = 'https://apis-sandbox.fedex.com';
    private const API_BASE_PRODUCTION = 'https://apis.fedex.com';
    
    private string $accountNumber;
    private string $meterNumber;
    private string $key;
    private string $password;
    private string $environment;
    private ?string $accessToken = null;
    private ?int $tokenExpiry = null;

    public function __construct(
        string $accountNumber,
        string $meterNumber,
        string $key,
        string $password,
        string $environment = 'sandbox'
    ) {
        $this->accountNumber = $accountNumber;
        $this->meterNumber = $meterNumber;
        $this->key = $key;
        $this->password = $password;
        $this->environment = $environment;
    }

    /**
     * Create a shipment
     */
    public function createShipment(array $shipmentData): array
    {
        $this->ensureAuthenticated();

        $response = $this->request('POST', '/ship/v1/shipments', [
            'labelResponseOptions' => 'URL_ONLY',
            'requestedShipment' => $shipmentData
        ]);

        if (!isset($response['output']['transactionShipments'][0])) {
            throw new FedExException('Invalid shipment response');
        }

        $shipment = $response['output']['transactionShipments'][0];

        return [
            'tracking_number' => $shipment['trackingNumber'] ?? null,
            'master_tracking_number' => $shipment['masterTrackingNumber'] ?? null,
            'service_type' => $shipment['serviceType'] ?? null,
            'label_url' => $shipment['pieceResponses'][0]['packageDocuments'][0]['url'] ?? null,
            'label_data' => $shipment['pieceResponses'][0]['packageDocuments'][0]['encodedLabel'] ?? null,
            'shipment_digest' => $shipment['completedShipmentDetail']['shipmentRating']['shipmentRateDetails'][0]['totalNetCharge'] ?? null,
            'estimated_delivery' => $shipment['completedShipmentDetail']['operationalDetail']['deliveryDate'] ?? null,
            'metadata' => $response['output'] ?? []
        ];
    }

    /**
     * Calculate shipping rates
     */
    public function getRates(array $rateRequest): array
    {
        $this->ensureAuthenticated();

        $response = $this->request('POST', '/rate/v1/rates/quotes', [
            'accountNumber' => ['value' => $this->accountNumber],
            'requestedShipment' => $rateRequest
        ]);

        $rates = [];
        foreach ($response['output']['rateReplyDetails'] ?? [] as $rateDetail) {
            $rates[] = [
                'service_type' => $rateDetail['serviceType'],
                'service_name' => $rateDetail['serviceName'] ?? $rateDetail['serviceType'],
                'packaging_type' => $rateDetail['packagingType'],
                'total_net_charge' => $rateDetail['ratedShipmentDetails'][0]['totalNetCharge'] ?? 0,
                'currency' => $rateDetail['ratedShipmentDetails'][0]['currency'] ?? 'USD',
                'transit_time' => $rateDetail['transitTime'] ?? null,
                'delivery_date' => $rateDetail['deliveryDate'] ?? null,
                'delivery_day' => $rateDetail['deliveryDayOfWeek'] ?? null
            ];
        }

        return $rates;
    }

    /**
     * Track a shipment
     */
    public function track(string $trackingNumber): array
    {
        $this->ensureAuthenticated();

        $response = $this->request('POST', '/track/v1/trackingnumbers', [
            'trackingInfo' => [
                [
                    'trackingNumberInfo' => [
                        'trackingNumber' => $trackingNumber
                    ]
                ]
            ],
            'includeDetailedScans' => true
        ]);

        if (!isset($response['output']['completeTrackResults'][0]['trackResults'][0])) {
            throw new FedExException('Tracking information not found');
        }

        $trackResult = $response['output']['completeTrackResults'][0]['trackResults'][0];

        return [
            'tracking_number' => $trackingNumber,
            'status' => $trackResult['latestStatusDetail']['code'] ?? null,
            'status_description' => $trackResult['latestStatusDetail']['description'] ?? null,
            'actual_delivery' => $trackResult['dateAndTimes']['actualDelivery'] ?? null,
            'estimated_delivery' => $trackResult['dateAndTimes']['estimatedDelivery'] ?? null,
            'current_location' => $this->formatLocation($trackResult['latestStatusDetail']['scanLocation'] ?? []),
            'service_type' => $trackResult['serviceDetail']['type'] ?? null,
            'weight' => $trackResult['packageDetails']['weight'] ?? null,
            'dimensions' => $trackResult['packageDetails']['dimensions'] ?? null,
            'events' => $this->formatTrackingEvents($trackResult['scanEvents'] ?? [])
        ];
    }

    /**
     * Cancel a shipment
     */
    public function cancelShipment(string $trackingNumber): bool
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->request('PUT', '/ship/v1/shipments/cancel', [
                'accountNumber' => ['value' => $this->accountNumber],
                'trackingNumber' => $trackingNumber
            ]);

            return $response['output']['cancelledShipment'] ?? false;

        } catch (FedExException $e) {
            logger()->error('FedEx shipment cancellation failed', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate an address
     */
    public function validateAddress(array $address): array
    {
        $this->ensureAuthenticated();

        $response = $this->request('POST', '/address/v1/addresses/resolve', [
            'addressesToValidate' => [$address]
        ]);

        $result = $response['output']['resolvedAddresses'][0] ?? [];

        return [
            'valid' => ($result['classification'] ?? '') === 'VALID',
            'classification' => $result['classification'] ?? 'UNKNOWN',
            'confidence' => $result['attributes']['POBoxClassification']['confidence'] ?? 0,
            'suggestions' => $result['resolvedAddress'] ?? [],
            'errors' => $result['customerMessages'] ?? []
        ];
    }

    /**
     * Schedule a pickup
     */
    public function schedulePickup(array $pickupData): array
    {
        $this->ensureAuthenticated();

        $response = $this->request('POST', '/pickup/v1/pickups', array_merge([
            'associatedAccountNumber' => ['value' => $this->accountNumber],
            'originDetail' => $pickupData['pickup_location'],
            'pickupDate' => $pickupData['pickup_date'],
            'pickupWindow' => [
                'readyTime' => $pickupData['ready_time'],
                'closeTime' => $pickupData['close_time']
            ],
            'packageCount' => $pickupData['package_count'],
            'totalWeight' => $pickupData['total_weight'],
            'carrierCode' => 'FDXE'
        ], $pickupData));

        return [
            'confirmation_number' => $response['output']['pickupConfirmationNumber'] ?? null,
            'location' => $response['output']['location'] ?? null,
            'pickup_charge' => $response['output']['pickupCharge'] ?? 0
        ];
    }

    /**
     * Get service availability
     */
    public function getServiceAvailability(array $request): array
    {
        $this->ensureAuthenticated();

        $response = $this->request('POST', '/availability/v1/transittimes', [
            'requestedShipment' => $request
        ]);

        return $response['output']['transitTimes'] ?? [];
    }

    /**
     * Upload shipment documents
     */
    public function uploadDocuments(array $documents): array
    {
        $this->ensureAuthenticated();

        $response = $this->request('POST', '/documentsubmission/v1/etds/upload', [
            'documents' => $documents
        ]);

        return $response['output'] ?? [];
    }

    /**
     * Ensure we have a valid access token
     */
    private function ensureAuthenticated(): void
    {
        if ($this->accessToken && $this->tokenExpiry && $this->tokenExpiry > time()) {
            return;
        }

        $this->authenticate();
    }

    /**
     * Authenticate with FedEx OAuth2
     */
    private function authenticate(): void
    {
        $response = $this->request('POST', '/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->key,
            'client_secret' => $this->password
        ], false);

        $this->accessToken = $response['access_token'] ?? null;
        $this->tokenExpiry = time() + ($response['expires_in'] ?? 3600) - 60; // Subtract 60 seconds for safety

        if (!$this->accessToken) {
            throw new FedExException('Failed to authenticate with FedEx API');
        }
    }

    /**
     * Make API request
     */
    private function request(string $method, string $endpoint, array $data = [], bool $authenticated = true): array
    {
        $url = $this->getApiBase() . $endpoint;
        
        $ch = curl_init();
        
        // Set basic options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Set headers
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-locale: en_US'
        ];
        
        if ($authenticated && $this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set method-specific options
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
                
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
                
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new FedExException('cURL error: ' . $error);
        }
        
        curl_close($ch);
        
        // Parse response
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new FedExException('Invalid JSON response from FedEx API');
        }
        
        // Handle errors
        if ($httpCode >= 400) {
            $this->handleApiError($data, $httpCode);
        }
        
        return $data;
    }

    /**
     * Get API base URL
     */
    private function getApiBase(): string
    {
        return $this->environment === 'production' 
            ? self::API_BASE_PRODUCTION 
            : self::API_BASE_SANDBOX;
    }

    /**
     * Handle API errors
     */
    private function handleApiError(array $response, int $httpCode): void
    {
        $errors = $response['errors'] ?? [];
        $message = 'FedEx API error';
        
        if (!empty($errors)) {
            $messages = array_map(function($error) {
                return $error['message'] ?? $error['code'] ?? 'Unknown error';
            }, $errors);
            $message = implode(', ', $messages);
        }
        
        throw new FedExException($message, $httpCode);
    }

    /**
     * Format location for tracking
     */
    private function formatLocation(array $location): ?string
    {
        if (empty($location)) {
            return null;
        }

        $parts = [];
        
        if (!empty($location['city'])) {
            $parts[] = $location['city'];
        }
        
        if (!empty($location['stateOrProvinceCode'])) {
            $parts[] = $location['stateOrProvinceCode'];
        }
        
        if (!empty($location['countryCode'])) {
            $parts[] = $location['countryCode'];
        }
        
        return implode(', ', $parts);
    }

    /**
     * Format tracking events
     */
    private function formatTrackingEvents(array $events): array
    {
        $formatted = [];
        
        foreach ($events as $event) {
            $formatted[] = [
                'timestamp' => $event['date'] ?? null,
                'status' => $event['eventType'] ?? null,
                'description' => $event['eventDescription'] ?? null,
                'location' => $this->formatLocation($event['scanLocation'] ?? []),
                'details' => $event['additionalDetails'] ?? null
            ];
        }
        
        return $formatted;
    }
}