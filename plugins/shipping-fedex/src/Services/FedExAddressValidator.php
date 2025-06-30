<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedex\Services;

use Shopologic\Core\Ecommerce\Models\CustomerAddress;

class FedExAddressValidator\n{
    private FedExApiClient $apiClient;

    public function __construct(FedExApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Validate an address
     */
    public function validate(CustomerAddress $address): array
    {
        try {
            $fedexAddress = $this->formatAddressForValidation($address);
            $result = $this->apiClient->validateAddress($fedexAddress);

            // Process validation result
            if ($result['valid']) {
                return [
                    'valid' => true,
                    'confidence' => $result['confidence'],
                    'suggestions' => [],
                    'errors' => []
                ];
            }

            // Format suggestions
            $suggestions = [];
            if (!empty($result['suggestions'])) {
                $suggestion = $result['suggestions'];
                $suggestions[] = $this->formatSuggestion($suggestion, $address);
            }

            return [
                'valid' => false,
                'confidence' => $result['confidence'] ?? 0,
                'suggestions' => $suggestions,
                'errors' => $result['errors'] ?? ['Address validation failed']
            ];

        } catch (\RuntimeException $e) {
            logger()->error('FedEx address validation failed', [
                'address' => $address->toArray(),
                'error' => $e->getMessage()
            ]);

            return [
                'valid' => false,
                'confidence' => 0,
                'suggestions' => [],
                'errors' => ['Address validation service unavailable']
            ];
        }
    }

    /**
     * Format address for FedEx validation
     */
    private function formatAddressForValidation(CustomerAddress $address): array
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
     * Format suggestion from FedEx response
     */
    private function formatSuggestion(array $suggestion, CustomerAddress $original): array
    {
        $formatted = [
            'line1' => $suggestion['streetLines'][0] ?? $original->line1,
            'line2' => $suggestion['streetLines'][1] ?? $original->line2,
            'city' => $suggestion['city'] ?? $original->city,
            'state' => $suggestion['stateOrProvinceCode'] ?? $original->state,
            'postal_code' => $suggestion['postalCode'] ?? $original->postal_code,
            'country_code' => $suggestion['countryCode'] ?? $original->country_code,
            'changes' => []
        ];

        // Identify what changed
        if ($formatted['line1'] !== $original->line1) {
            $formatted['changes'][] = 'street address';
        }
        if ($formatted['city'] !== $original->city) {
            $formatted['changes'][] = 'city';
        }
        if ($formatted['state'] !== $original->state) {
            $formatted['changes'][] = 'state';
        }
        if ($formatted['postal_code'] !== $original->postal_code) {
            $formatted['changes'][] = 'postal code';
        }

        return $formatted;
    }

    /**
     * Validate postal code format
     */
    public function validatePostalCode(string $postalCode, string $countryCode): bool
    {
        $patterns = [
            'US' => '/^\d{5}(-\d{4})?$/',
            'CA' => '/^[A-Z]\d[A-Z]\s?\d[A-Z]\d$/i',
            'MX' => '/^\d{5}$/',
            'GB' => '/^[A-Z]{1,2}\d{1,2}[A-Z]?\s?\d[A-Z]{2}$/i',
            'DE' => '/^\d{5}$/',
            'FR' => '/^\d{5}$/',
            'IT' => '/^\d{5}$/',
            'ES' => '/^\d{5}$/',
            'AU' => '/^\d{4}$/',
            'JP' => '/^\d{3}-?\d{4}$/'
        ];

        $pattern = $patterns[$countryCode] ?? null;
        
        if (!$pattern) {
            return true; // Allow unknown formats
        }

        return (bool) preg_match($pattern, $postalCode);
    }

    /**
     * Get state/province codes for a country
     */
    public function getStateProvinces(string $countryCode): array
    {
        // This would typically be loaded from a database or API
        $states = [
            'US' => [
                'AL' => 'Alabama',
                'AK' => 'Alaska',
                'AZ' => 'Arizona',
                'AR' => 'Arkansas',
                'CA' => 'California',
                'CO' => 'Colorado',
                'CT' => 'Connecticut',
                'DE' => 'Delaware',
                'FL' => 'Florida',
                'GA' => 'Georgia',
                'HI' => 'Hawaii',
                'ID' => 'Idaho',
                'IL' => 'Illinois',
                'IN' => 'Indiana',
                'IA' => 'Iowa',
                'KS' => 'Kansas',
                'KY' => 'Kentucky',
                'LA' => 'Louisiana',
                'ME' => 'Maine',
                'MD' => 'Maryland',
                'MA' => 'Massachusetts',
                'MI' => 'Michigan',
                'MN' => 'Minnesota',
                'MS' => 'Mississippi',
                'MO' => 'Missouri',
                'MT' => 'Montana',
                'NE' => 'Nebraska',
                'NV' => 'Nevada',
                'NH' => 'New Hampshire',
                'NJ' => 'New Jersey',
                'NM' => 'New Mexico',
                'NY' => 'New York',
                'NC' => 'North Carolina',
                'ND' => 'North Dakota',
                'OH' => 'Ohio',
                'OK' => 'Oklahoma',
                'OR' => 'Oregon',
                'PA' => 'Pennsylvania',
                'RI' => 'Rhode Island',
                'SC' => 'South Carolina',
                'SD' => 'South Dakota',
                'TN' => 'Tennessee',
                'TX' => 'Texas',
                'UT' => 'Utah',
                'VT' => 'Vermont',
                'VA' => 'Virginia',
                'WA' => 'Washington',
                'WV' => 'West Virginia',
                'WI' => 'Wisconsin',
                'WY' => 'Wyoming'
            ],
            'CA' => [
                'AB' => 'Alberta',
                'BC' => 'British Columbia',
                'MB' => 'Manitoba',
                'NB' => 'New Brunswick',
                'NL' => 'Newfoundland and Labrador',
                'NS' => 'Nova Scotia',
                'ON' => 'Ontario',
                'PE' => 'Prince Edward Island',
                'QC' => 'Quebec',
                'SK' => 'Saskatchewan',
                'NT' => 'Northwest Territories',
                'NU' => 'Nunavut',
                'YT' => 'Yukon'
            ]
        ];

        return $states[$countryCode] ?? [];
    }
}