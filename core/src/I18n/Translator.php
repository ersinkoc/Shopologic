<?php

declare(strict_types=1);

namespace Shopologic\Core\I18n;

use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Events\EventDispatcherInterface;

/**
 * Translation service for multi-language support
 */
class Translator
{
    private CacheInterface $cache;
    private EventDispatcherInterface $eventDispatcher;
    private array $config;
    private string $locale;
    private string $fallbackLocale;
    private array $translations = [];
    private array $loaders = [];
    private array $loadedResources = [];

    public function __construct(
        CacheInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        array $config = []
    ) {
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->config = array_merge([
            'locale' => 'en',
            'fallback_locale' => 'en',
            'cache_ttl' => 3600,
            'translation_paths' => []
        ], $config);
        
        $this->locale = $this->config['locale'];
        $this->fallbackLocale = $this->config['fallback_locale'];
        
        $this->registerDefaultLoaders();
    }

    /**
     * Translate a message
     */
    public function translate(string $key, array $parameters = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        
        // Load translations for locale if not loaded
        $this->loadTranslations($locale);
        
        // Look for translation
        $translation = $this->findTranslation($key, $locale);
        
        // Apply parameters
        if (!empty($parameters)) {
            $translation = $this->replaceParameters($translation, $parameters);
        }
        
        // Apply filters
        $translation = $this->eventDispatcher->filter('translator.translate', $translation, [
            'key' => $key,
            'parameters' => $parameters,
            'locale' => $locale
        ]);
        
        return $translation;
    }

    /**
     * Translate with choice (pluralization)
     */
    public function choice(string $key, int $count, array $parameters = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        $parameters['count'] = $count;
        
        // Get translation
        $translation = $this->translate($key, [], $locale);
        
        // Apply pluralization
        $translation = $this->pluralize($translation, $count, $locale);
        
        // Apply parameters
        return $this->replaceParameters($translation, $parameters);
    }

    /**
     * Check if translation exists
     */
    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->locale;
        $this->loadTranslations($locale);
        
        return $this->findTranslation($key, $locale, false) !== $key;
    }

    /**
     * Get current locale
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set current locale
     */
    public function setLocale(string $locale): void
    {
        if ($this->locale !== $locale) {
            $this->locale = $locale;
            $this->eventDispatcher->dispatch('translator.locale_changed', [
                'old_locale' => $this->locale,
                'new_locale' => $locale
            ]);
        }
    }

    /**
     * Get fallback locale
     */
    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }

    /**
     * Set fallback locale
     */
    public function setFallbackLocale(string $locale): void
    {
        $this->fallbackLocale = $locale;
    }

    /**
     * Add translation path
     */
    public function addPath(string $path, string $namespace = 'default'): void
    {
        if (!isset($this->config['translation_paths'][$namespace])) {
            $this->config['translation_paths'][$namespace] = [];
        }
        
        $this->config['translation_paths'][$namespace][] = $path;
        
        // Clear loaded resources to force reload
        $this->loadedResources = [];
    }

    /**
     * Register a translation loader
     */
    public function addLoader(string $format, TranslationLoaderInterface $loader): void
    {
        $this->loaders[$format] = $loader;
    }

    /**
     * Add translations
     */
    public function addTranslations(array $translations, string $locale, string $domain = 'messages'): void
    {
        if (!isset($this->translations[$locale])) {
            $this->translations[$locale] = [];
        }
        
        if (!isset($this->translations[$locale][$domain])) {
            $this->translations[$locale][$domain] = [];
        }
        
        $this->translations[$locale][$domain] = array_merge(
            $this->translations[$locale][$domain],
            $translations
        );
    }

    /**
     * Get available locales
     */
    public function getAvailableLocales(): array
    {
        $locales = [];
        
        foreach ($this->config['translation_paths'] as $namespace => $paths) {
            foreach ($paths as $path) {
                if (is_dir($path)) {
                    foreach (scandir($path) as $dir) {
                        if ($dir !== '.' && $dir !== '..' && is_dir($path . '/' . $dir)) {
                            $locales[] = $dir;
                        }
                    }
                }
            }
        }
        
        return array_unique($locales);
    }

    /**
     * Format a date according to locale
     */
    public function formatDate(\DateTimeInterface $date, string $format = 'medium', ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        
        $formats = [
            'short' => $this->translate('date.format.short', [], $locale),
            'medium' => $this->translate('date.format.medium', [], $locale),
            'long' => $this->translate('date.format.long', [], $locale),
            'full' => $this->translate('date.format.full', [], $locale)
        ];
        
        $formatString = $formats[$format] ?? $format;
        
        return $date->format($formatString);
    }

    /**
     * Format a number according to locale
     */
    public function formatNumber(float $number, int $decimals = 0, ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        
        $symbols = $this->getNumberSymbols($locale);
        
        return number_format(
            $number,
            $decimals,
            $symbols['decimal_point'],
            $symbols['thousands_separator']
        );
    }

    /**
     * Format currency according to locale
     */
    public function formatCurrency(float $amount, string $currency, ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($amount, $currency);
    }

    // Private methods

    private function registerDefaultLoaders(): void
    {
        $this->addLoader('php', new Loaders\PhpFileLoader());
        $this->addLoader('json', new Loaders\JsonFileLoader());
        $this->addLoader('yaml', new Loaders\YamlFileLoader());
    }

    private function loadTranslations(string $locale): void
    {
        $cacheKey = 'translations_' . $locale;
        
        if (isset($this->loadedResources[$locale])) {
            return;
        }
        
        // Try cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $this->translations[$locale] = $cached;
            $this->loadedResources[$locale] = true;
            return;
        }
        
        // Load from files
        foreach ($this->config['translation_paths'] as $namespace => $paths) {
            foreach ($paths as $path) {
                $this->loadTranslationsFromPath($path, $locale, $namespace);
            }
        }
        
        // Cache loaded translations
        if (!empty($this->translations[$locale])) {
            $this->cache->set($cacheKey, $this->translations[$locale], $this->config['cache_ttl']);
        }
        
        $this->loadedResources[$locale] = true;
    }

    private function loadTranslationsFromPath(string $path, string $locale, string $namespace): void
    {
        $localePath = $path . '/' . $locale;
        
        if (!is_dir($localePath)) {
            return;
        }
        
        foreach (scandir($localePath) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $localePath . '/' . $file;
            if (!is_file($filePath)) {
                continue;
            }
            
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $domain = pathinfo($file, PATHINFO_FILENAME);
            
            if (isset($this->loaders[$extension])) {
                $translations = $this->loaders[$extension]->load($filePath);
                
                if ($namespace !== 'default') {
                    $domain = $namespace . '.' . $domain;
                }
                
                $this->addTranslations($translations, $locale, $domain);
            }
        }
    }

    private function findTranslation(string $key, string $locale, bool $fallback = true): string
    {
        // Parse key (domain.key format)
        $parts = explode('.', $key, 2);
        if (count($parts) === 2 && isset($this->translations[$locale][$parts[0]])) {
            $domain = $parts[0];
            $translationKey = $parts[1];
        } else {
            $domain = 'messages';
            $translationKey = $key;
        }
        
        // Look in current locale
        if (isset($this->translations[$locale][$domain][$translationKey])) {
            return $this->translations[$locale][$domain][$translationKey];
        }
        
        // Look in nested keys
        $value = $this->findNestedTranslation($locale, $domain, $translationKey);
        if ($value !== null) {
            return $value;
        }
        
        // Try fallback locale
        if ($fallback && $locale !== $this->fallbackLocale) {
            $this->loadTranslations($this->fallbackLocale);
            return $this->findTranslation($key, $this->fallbackLocale, false);
        }
        
        // Return key if not found
        return $key;
    }

    private function findNestedTranslation(string $locale, string $domain, string $key): ?string
    {
        if (!isset($this->translations[$locale][$domain])) {
            return null;
        }
        
        $keys = explode('.', $key);
        $value = $this->translations[$locale][$domain];
        
        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return is_string($value) ? $value : null;
    }

    private function replaceParameters(string $translation, array $parameters): string
    {
        foreach ($parameters as $key => $value) {
            $translation = str_replace(
                [':' . $key, '{' . $key . '}', '%' . $key . '%'],
                (string) $value,
                $translation
            );
        }
        
        return $translation;
    }

    private function pluralize(string $translation, int $count, string $locale): string
    {
        // Simple pluralization rules
        $parts = explode('|', $translation);
        
        if (count($parts) === 1) {
            return $parts[0];
        }
        
        // Get pluralization rule for locale
        $rule = $this->getPluralRule($count, $locale);
        
        return $parts[$rule] ?? $parts[0];
    }

    private function getPluralRule(int $count, string $locale): int
    {
        // Simplified plural rules for common languages
        switch ($locale) {
            case 'en':
            case 'de':
            case 'es':
            case 'it':
                return $count === 1 ? 0 : 1;
                
            case 'fr':
                return $count <= 1 ? 0 : 1;
                
            case 'ru':
                if ($count % 10 === 1 && $count % 100 !== 11) {
                    return 0;
                } elseif ($count % 10 >= 2 && $count % 10 <= 4 && ($count % 100 < 10 || $count % 100 >= 20)) {
                    return 1;
                } else {
                    return 2;
                }
                
            case 'ja':
            case 'zh':
            case 'ko':
                return 0; // No plural forms
                
            default:
                return $count === 1 ? 0 : 1;
        }
    }

    private function getNumberSymbols(string $locale): array
    {
        $symbols = [
            'en' => ['decimal_point' => '.', 'thousands_separator' => ','],
            'de' => ['decimal_point' => ',', 'thousands_separator' => '.'],
            'fr' => ['decimal_point' => ',', 'thousands_separator' => ' '],
            'es' => ['decimal_point' => ',', 'thousands_separator' => '.'],
            'it' => ['decimal_point' => ',', 'thousands_separator' => '.'],
            'pt' => ['decimal_point' => ',', 'thousands_separator' => '.'],
            'ru' => ['decimal_point' => ',', 'thousands_separator' => ' '],
            'ja' => ['decimal_point' => '.', 'thousands_separator' => ','],
            'zh' => ['decimal_point' => '.', 'thousands_separator' => ','],
            'ko' => ['decimal_point' => '.', 'thousands_separator' => ',']
        ];
        
        return $symbols[$locale] ?? $symbols['en'];
    }
}

/**
 * Helper function for translations
 */
function trans(string $key, array $parameters = [], ?string $locale = null): string
{
    return app(Translator::class)->translate($key, $parameters, $locale);
}

/**
 * Helper function for pluralization
 */
function trans_choice(string $key, int $count, array $parameters = [], ?string $locale = null): string
{
    return app(Translator::class)->choice($key, $count, $parameters, $locale);
}