<?php

declare(strict_types=1);

namespace Shopologic\Plugins\MultiCurrencyLocalization\Models;

use Shopologic\Core\Database\Model;

class Localization extends Model
{
    protected string $table = 'localizations';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'country_code',
        'country_name',
        'currency_code',
        'language_code',
        'locale_code',
        'timezone',
        'date_format',
        'time_format',
        'number_format',
        'decimal_separator',
        'thousands_separator',
        'currency_position',
        'currency_symbol_spacing',
        'rtl_support',
        'measurement_units',
        'address_format',
        'phone_format',
        'postal_code_format',
        'is_active',
        'priority',
        'metadata'
    ];

    protected array $casts = [
        'rtl_support' => 'boolean',
        'measurement_units' => 'json',
        'address_format' => 'json',
        'phone_format' => 'json',
        'postal_code_format' => 'json',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'metadata' => 'json'
    ];

    /**
     * Currency positions
     */
    const CURRENCY_BEFORE = 'before';
    const CURRENCY_AFTER = 'after';
    const CURRENCY_BEFORE_WITH_SPACE = 'before_space';
    const CURRENCY_AFTER_WITH_SPACE = 'after_space';

    /**
     * Measurement unit systems
     */
    const UNITS_METRIC = 'metric';
    const UNITS_IMPERIAL = 'imperial';
    const UNITS_MIXED = 'mixed';

    /**
     * Get currency
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * Get customer locations
     */
    public function customerLocations()
    {
        return $this->hasMany(CustomerLocation::class, 'country_code', 'country_code');
    }

    /**
     * Get regional pricing rules
     */
    public function regionalPricing()
    {
        return $this->hasMany(RegionalPricing::class, 'country_code', 'country_code');
    }

    /**
     * Get tax rules
     */
    public function taxRules()
    {
        return $this->hasMany(TaxRule::class, 'country_code', 'country_code');
    }

    /**
     * Scope active localizations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by country
     */
    public function scopeByCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Scope by language
     */
    public function scopeByLanguage($query, string $languageCode)
    {
        return $query->where('language_code', $languageCode);
    }

    /**
     * Scope by currency
     */
    public function scopeByCurrency($query, string $currencyCode)
    {
        return $query->where('currency_code', $currencyCode);
    }

    /**
     * Scope RTL languages
     */
    public function scopeRtl($query)
    {
        return $query->where('rtl_support', true);
    }

    /**
     * Format price according to localization settings
     */
    public function formatPrice(float $amount, string $currencySymbol = null): string
    {
        $symbol = $currencySymbol ?? $this->currency->symbol ?? $this->currency_code;
        
        // Format the number
        $formattedAmount = number_format(
            $amount,
            $this->currency->decimal_places ?? 2,
            $this->decimal_separator ?? '.',
            $this->thousands_separator ?? ','
        );
        
        // Apply currency position
        switch ($this->currency_position) {
            case self::CURRENCY_BEFORE:
                return $symbol . $formattedAmount;
            case self::CURRENCY_AFTER:
                return $formattedAmount . $symbol;
            case self::CURRENCY_BEFORE_WITH_SPACE:
                return $symbol . ' ' . $formattedAmount;
            case self::CURRENCY_AFTER_WITH_SPACE:
                return $formattedAmount . ' ' . $symbol;
            default:
                return $symbol . $formattedAmount;
        }
    }

    /**
     * Format number according to localization settings
     */
    public function formatNumber(float $number, int $decimals = 2): string
    {
        return number_format(
            $number,
            $decimals,
            $this->decimal_separator ?? '.',
            $this->thousands_separator ?? ','
        );
    }

    /**
     * Format date according to localization settings
     */
    public function formatDate(\DateTime $date): string
    {
        $format = $this->date_format ?? 'Y-m-d';
        return $date->format($format);
    }

    /**
     * Format time according to localization settings
     */
    public function formatTime(\DateTime $time): string
    {
        $format = $this->time_format ?? 'H:i:s';
        return $time->format($format);
    }

    /**
     * Format datetime according to localization settings
     */
    public function formatDateTime(\DateTime $datetime): string
    {
        $dateFormat = $this->date_format ?? 'Y-m-d';
        $timeFormat = $this->time_format ?? 'H:i:s';
        return $datetime->format($dateFormat . ' ' . $timeFormat);
    }

    /**
     * Get measurement unit for type
     */
    public function getMeasurementUnit(string $type): ?string
    {
        $units = $this->measurement_units ?? [];
        return $units[$type] ?? null;
    }

    /**
     * Set measurement unit for type
     */
    public function setMeasurementUnit(string $type, string $unit): void
    {
        $units = $this->measurement_units ?? [];
        $units[$type] = $unit;
        $this->measurement_units = $units;
        $this->save();
    }

    /**
     * Get address format template
     */
    public function getAddressFormat(): array
    {
        return $this->address_format ?? [
            'line1' => '{street_number} {street_name}',
            'line2' => '{apartment}',
            'line3' => '{city}, {state} {postal_code}',
            'line4' => '{country}'
        ];
    }

    /**
     * Format address according to localization
     */
    public function formatAddress(array $addressData): array
    {
        $format = $this->getAddressFormat();
        $formattedLines = [];
        
        foreach ($format as $line) {
            $formattedLine = $line;
            foreach ($addressData as $key => $value) {
                $formattedLine = str_replace('{' . $key . '}', $value, $formattedLine);
            }
            // Remove empty placeholders and extra spaces
            $formattedLine = preg_replace('/\{[^}]+\}/', '', $formattedLine);
            $formattedLine = preg_replace('/\s+/', ' ', trim($formattedLine));
            
            if (!empty($formattedLine)) {
                $formattedLines[] = $formattedLine;
            }
        }
        
        return $formattedLines;
    }

    /**
     * Get phone format pattern
     */
    public function getPhoneFormat(): array
    {
        return $this->phone_format ?? [
            'pattern' => '+{country_code} {area_code} {number}',
            'country_code' => '1',
            'area_code_length' => 3,
            'number_length' => 7
        ];
    }

    /**
     * Format phone number according to localization
     */
    public function formatPhoneNumber(string $phoneNumber): string
    {
        $format = $this->getPhoneFormat();
        $pattern = $format['pattern'] ?? '+{country_code} {area_code} {number}';
        
        // Remove non-numeric characters
        $digits = preg_replace('/\D/', '', $phoneNumber);
        
        // Apply formatting based on pattern
        // This is a simplified implementation
        if (strlen($digits) >= 10) {
            $countryCode = $format['country_code'] ?? '1';
            $areaCode = substr($digits, -10, 3);
            $number = substr($digits, -7);
            
            return str_replace(
                ['{country_code}', '{area_code}', '{number}'],
                [$countryCode, $areaCode, $number],
                $pattern
            );
        }
        
        return $phoneNumber;
    }

    /**
     * Get postal code format
     */
    public function getPostalCodeFormat(): array
    {
        return $this->postal_code_format ?? [
            'pattern' => '[0-9]{5}',
            'example' => '12345'
        ];
    }

    /**
     * Validate postal code
     */
    public function validatePostalCode(string $postalCode): bool
    {
        $format = $this->getPostalCodeFormat();
        $pattern = $format['pattern'] ?? '[0-9]{5}';
        
        return preg_match('/^' . $pattern . '$/', $postalCode) === 1;
    }

    /**
     * Get timezone object
     */
    public function getTimezone(): \DateTimeZone
    {
        return new \DateTimeZone($this->timezone ?? 'UTC');
    }

    /**
     * Convert UTC time to local time
     */
    public function convertToLocalTime(\DateTime $utcTime): \DateTime
    {
        $localTime = clone $utcTime;
        $localTime->setTimezone($this->getTimezone());
        return $localTime;
    }

    /**
     * Convert local time to UTC
     */
    public function convertToUtc(\DateTime $localTime): \DateTime
    {
        $utcTime = clone $localTime;
        $utcTime->setTimezone(new \DateTimeZone('UTC'));
        return $utcTime;
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        $metadata = $this->metadata ?? [];
        return $metadata[$key] ?? $default;
    }

    /**
     * Set metadata value
     */
    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * Get currency position label
     */
    public function getCurrencyPositionLabel(): string
    {
        $labels = [
            self::CURRENCY_BEFORE => 'Before (e.g., $100)',
            self::CURRENCY_AFTER => 'After (e.g., 100$)',
            self::CURRENCY_BEFORE_WITH_SPACE => 'Before with space (e.g., $ 100)',
            self::CURRENCY_AFTER_WITH_SPACE => 'After with space (e.g., 100 $)'
        ];
        
        return $labels[$this->currency_position] ?? 'Before';
    }

    /**
     * Get measurement unit system label
     */
    public function getMeasurementUnitSystemLabel(): string
    {
        $system = $this->getMetadata('unit_system', self::UNITS_METRIC);
        
        $labels = [
            self::UNITS_METRIC => 'Metric',
            self::UNITS_IMPERIAL => 'Imperial',
            self::UNITS_MIXED => 'Mixed'
        ];
        
        return $labels[$system] ?? 'Metric';
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        return [
            'id' => $this->id,
            'country_code' => $this->country_code,
            'country_name' => $this->country_name,
            'currency_code' => $this->currency_code,
            'currency_name' => $this->currency?->name,
            'currency_symbol' => $this->currency?->symbol,
            'language_code' => $this->language_code,
            'locale_code' => $this->locale_code,
            'timezone' => $this->timezone,
            'date_format' => $this->date_format,
            'time_format' => $this->time_format,
            'number_format' => [
                'decimal_separator' => $this->decimal_separator,
                'thousands_separator' => $this->thousands_separator
            ],
            'currency_position' => $this->currency_position,
            'currency_position_label' => $this->getCurrencyPositionLabel(),
            'rtl_support' => $this->rtl_support,
            'measurement_system' => $this->getMeasurementUnitSystemLabel(),
            'is_active' => $this->is_active,
            'priority' => $this->priority,
            'sample_price' => $this->formatPrice(1234.56),
            'sample_number' => $this->formatNumber(1234.56),
            'sample_date' => $this->formatDate(now()),
            'sample_time' => $this->formatTime(now())
        ];
    }

    /**
     * Find by country code
     */
    public static function findByCountry(string $countryCode): ?self
    {
        return self::byCountry($countryCode)->active()->first();
    }

    /**
     * Find by language code
     */
    public static function findByLanguage(string $languageCode): ?self
    {
        return self::byLanguage($languageCode)->active()->first();
    }

    /**
     * Get default localization
     */
    public static function getDefault(): ?self
    {
        return self::active()
            ->orderBy('priority', 'desc')
            ->first();
    }
}