<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedex\Services;

use Shopologic\Core\Ecommerce\Shipping\ShippingRequest;
use Shopologic\Core\Ecommerce\Shipping\ShippingRate;
use Shopologic\Plugins\ShippingFedEx\Exceptions\FedExException;

class FedExRateCalculator\n{
    private FedExApiClient $apiClient;

    public function __construct(FedExApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Calculate shipping rates for a request
     */
    public function calculateRates(ShippingRequest $request, array $config): array
    {
        try {
            $rateRequest = $this->buildRateRequest($request, $config);
            $fedexRates = $this->apiClient->getRates($rateRequest);
            
            return $this->formatRates($fedexRates, $config);

        } catch (FedExException $e) {
            logger()->error('FedEx rate calculation failed', [
                'error' => $e->getMessage(),
                'request' => $request
            ]);
            return [];
        }
    }

    /**
     * Build FedEx rate request
     */
    private function buildRateRequest(ShippingRequest $request, array $config): array
    {
        $packages = $this->preparePackages($request->getPackages());

        return [
            'shipper' => $this->formatAddress($request->getFromAddress()),
            'recipient' => $this->formatAddress($request->getToAddress()),
            'pickupType' => $config['dropoff_type'] ?? 'REGULAR_PICKUP',
            'serviceType' => $request->getServiceType(), // null to get all services
            'packagingType' => $config['default_packaging'] ?? 'YOUR_PACKAGING',
            'rateRequestType' => ['ACCOUNT', 'LIST'],
            'requestedPackageLineItems' => $packages,
            'customsClearanceDetail' => $this->getCustomsDetail($request)
        ];
    }

    /**
     * Prepare packages for rate request
     */
    private function preparePackages(array $packages): array
    {
        $fedexPackages = [];
        
        foreach ($packages as $index => $package) {
            $fedexPackage = [
                'sequenceNumber' => $index + 1,
                'groupPackageCount' => 1,
                'weight' => [
                    'value' => $package['weight'] ?? 1.0,
                    'units' => $package['weight_unit'] ?? 'LB'
                ]
            ];

            // Add dimensions if provided
            if (!empty($package['dimensions'])) {
                $fedexPackage['dimensions'] = [
                    'length' => $package['dimensions']['length'] ?? 0,
                    'width' => $package['dimensions']['width'] ?? 0,
                    'height' => $package['dimensions']['height'] ?? 0,
                    'units' => $package['dimensions']['unit'] ?? 'IN'
                ];
            }

            // Add insurance if specified
            if (!empty($package['insured_value'])) {
                $fedexPackage['insuredValue'] = [
                    'amount' => $package['insured_value'],
                    'currency' => $package['currency'] ?? 'USD'
                ];
            }

            $fedexPackages[] = $fedexPackage;
        }

        return $fedexPackages;
    }

    /**
     * Format address for FedEx
     */
    private function formatAddress($address): array
    {
        return [
            'streetLines' => array_filter([
                $address->line1,
                $address->line2
            ]),
            'city' => $address->city,
            'stateOrProvinceCode' => $address->state,
            'postalCode' => $address->postal_code,
            'countryCode' => $address->country_code
        ];
    }

    /**
     * Get customs detail if international
     */
    private function getCustomsDetail(ShippingRequest $request): ?array
    {
        $fromCountry = $request->getFromAddress()->country_code;
        $toCountry = $request->getToAddress()->country_code;
        
        // If domestic, no customs needed
        if ($fromCountry === $toCountry) {
            return null;
        }

        // Basic customs detail for rating
        return [
            'dutiesPayment' => [
                'paymentType' => 'RECIPIENT'
            ],
            'commodities' => [[
                'description' => 'General Merchandise',
                'weight' => [
                    'value' => $request->getTotalWeight(),
                    'units' => 'LB'
                ],
                'quantity' => $request->getPackageCount(),
                'customsValue' => [
                    'amount' => $request->getOption('customs_value', 100.00),
                    'currency' => 'USD'
                ]
            ]]
        ];
    }

    /**
     * Format FedEx rates to standard shipping rates
     */
    private function formatRates(array $fedexRates, array $config): array
    {
        $rates = [];
        $enabledServices = $config['enabled_services'] ?? [];

        foreach ($fedexRates as $fedexRate) {
            // Skip if service not enabled
            if (!empty($enabledServices) && !in_array($fedexRate['service_type'], $enabledServices)) {
                continue;
            }

            $rate = new ShippingRate(
                serviceCode: 'fedex_' . strtolower($fedexRate['service_type']),
                serviceName: $this->getServiceName($fedexRate['service_type']),
                cost: (float) $fedexRate['total_net_charge'],
                currency: $fedexRate['currency'],
                estimatedDays: $this->parseTransitTime($fedexRate['transit_time']),
                estimatedDeliveryDate: $fedexRate['delivery_date'],
                metadata: [
                    'carrier' => 'FedEx',
                    'service_type' => $fedexRate['service_type'],
                    'packaging_type' => $fedexRate['packaging_type'],
                    'delivery_day' => $fedexRate['delivery_day'] ?? null
                ]
            );

            $rates[] = $rate;
        }

        // Sort by cost
        usort($rates, function($a, $b) {
            return $a->getCost() <=> $b->getCost();
        });

        return $rates;
    }

    /**
     * Parse transit time to days
     */
    private function parseTransitTime(?string $transitTime): ?int
    {
        if (!$transitTime) {
            return null;
        }

        // FedEx transit times like "ONE_DAY", "TWO_DAYS", etc.
        $mapping = [
            'ONE_DAY' => 1,
            'TWO_DAYS' => 2,
            'THREE_DAYS' => 3,
            'FOUR_DAYS' => 4,
            'FIVE_DAYS' => 5,
            'SIX_DAYS' => 6,
            'SEVEN_DAYS' => 7,
            'EIGHT_DAYS' => 8,
            'NINE_DAYS' => 9,
            'TEN_DAYS' => 10
        ];

        return $mapping[$transitTime] ?? null;
    }

    /**
     * Get service name
     */
    private function getServiceName(string $serviceType): string
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

        return $names[$serviceType] ?? $serviceType;
    }
}