<?php

declare(strict_types=1);

namespace Shopologic\Core\I18n\Locale;

/**
 * Locale information and utilities
 */
class Locale
{
    private static array $locales = [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'dir' => 'ltr',
            'region' => 'US',
            'language' => 'en',
            'date_format' => 'm/d/Y',
            'time_format' => 'h:i A',
            'first_day_of_week' => 0 // Sunday
        ],
        'es' => [
            'name' => 'Spanish',
            'native' => 'Español',
            'dir' => 'ltr',
            'region' => 'ES',
            'language' => 'es',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'first_day_of_week' => 1 // Monday
        ],
        'fr' => [
            'name' => 'French',
            'native' => 'Français',
            'dir' => 'ltr',
            'region' => 'FR',
            'language' => 'fr',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'first_day_of_week' => 1
        ],
        'de' => [
            'name' => 'German',
            'native' => 'Deutsch',
            'dir' => 'ltr',
            'region' => 'DE',
            'language' => 'de',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'first_day_of_week' => 1
        ],
        'it' => [
            'name' => 'Italian',
            'native' => 'Italiano',
            'dir' => 'ltr',
            'region' => 'IT',
            'language' => 'it',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'first_day_of_week' => 1
        ],
        'pt' => [
            'name' => 'Portuguese',
            'native' => 'Português',
            'dir' => 'ltr',
            'region' => 'PT',
            'language' => 'pt',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'first_day_of_week' => 1
        ],
        'ru' => [
            'name' => 'Russian',
            'native' => 'Русский',
            'dir' => 'ltr',
            'region' => 'RU',
            'language' => 'ru',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'first_day_of_week' => 1
        ],
        'ja' => [
            'name' => 'Japanese',
            'native' => '日本語',
            'dir' => 'ltr',
            'region' => 'JP',
            'language' => 'ja',
            'date_format' => 'Y/m/d',
            'time_format' => 'H:i',
            'first_day_of_week' => 0
        ],
        'zh' => [
            'name' => 'Chinese',
            'native' => '中文',
            'dir' => 'ltr',
            'region' => 'CN',
            'language' => 'zh',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'first_day_of_week' => 1
        ],
        'ko' => [
            'name' => 'Korean',
            'native' => '한국어',
            'dir' => 'ltr',
            'region' => 'KR',
            'language' => 'ko',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'first_day_of_week' => 0
        ],
        'ar' => [
            'name' => 'Arabic',
            'native' => 'العربية',
            'dir' => 'rtl',
            'region' => 'SA',
            'language' => 'ar',
            'date_format' => 'd/m/Y',
            'time_format' => 'h:i A',
            'first_day_of_week' => 6 // Saturday
        ],
        'he' => [
            'name' => 'Hebrew',
            'native' => 'עברית',
            'dir' => 'rtl',
            'region' => 'IL',
            'language' => 'he',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'first_day_of_week' => 0
        ]
    ];

    /**
     * Get all available locales
     */
    public static function getAvailable(): array
    {
        return self::$locales;
    }

    /**
     * Get locale info
     */
    public static function getInfo(string $locale): ?array
    {
        return self::$locales[$locale] ?? null;
    }

    /**
     * Check if locale exists
     */
    public static function exists(string $locale): bool
    {
        return isset(self::$locales[$locale]);
    }

    /**
     * Get locale name
     */
    public static function getName(string $locale): string
    {
        return self::$locales[$locale]['name'] ?? $locale;
    }

    /**
     * Get native name
     */
    public static function getNativeName(string $locale): string
    {
        return self::$locales[$locale]['native'] ?? $locale;
    }

    /**
     * Get text direction
     */
    public static function getDirection(string $locale): string
    {
        return self::$locales[$locale]['dir'] ?? 'ltr';
    }

    /**
     * Check if locale is RTL
     */
    public static function isRtl(string $locale): bool
    {
        return self::getDirection($locale) === 'rtl';
    }

    /**
     * Get date format
     */
    public static function getDateFormat(string $locale): string
    {
        return self::$locales[$locale]['date_format'] ?? 'Y-m-d';
    }

    /**
     * Get time format
     */
    public static function getTimeFormat(string $locale): string
    {
        return self::$locales[$locale]['time_format'] ?? 'H:i:s';
    }

    /**
     * Get datetime format
     */
    public static function getDateTimeFormat(string $locale): string
    {
        return self::getDateFormat($locale) . ' ' . self::getTimeFormat($locale);
    }

    /**
     * Get first day of week
     */
    public static function getFirstDayOfWeek(string $locale): int
    {
        return self::$locales[$locale]['first_day_of_week'] ?? 1;
    }

    /**
     * Format locale code for display
     */
    public static function format(string $locale): string
    {
        $info = self::getInfo($locale);
        if (!$info) {
            return $locale;
        }
        
        return sprintf('%s (%s)', $info['native'], $info['name']);
    }

    /**
     * Get locale from accept-language header
     */
    public static function fromAcceptLanguage(string $acceptLanguage, array $available = []): ?string
    {
        if (empty($available)) {
            $available = array_keys(self::$locales);
        }
        
        // Parse accept-language header
        $languages = [];
        $parts = explode(',', $acceptLanguage);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^([a-z]{2}(?:-[A-Z]{2})?)\s*(?:;\s*q\s*=\s*(1|0\.\d+))?$/i', $part, $matches)) {
                $lang = strtolower($matches[1]);
                $quality = isset($matches[2]) ? (float) $matches[2] : 1.0;
                $languages[$lang] = $quality;
            }
        }
        
        // Sort by quality
        arsort($languages);
        
        // Find best match
        foreach ($languages as $lang => $quality) {
            // Exact match
            if (in_array($lang, $available)) {
                return $lang;
            }
            
            // Language without region
            $langOnly = explode('-', $lang)[0];
            if (in_array($langOnly, $available)) {
                return $langOnly;
            }
        }
        
        return null;
    }
}