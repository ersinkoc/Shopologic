<?php

declare(strict_types=1);

namespace Shopologic\Core\I18n\Locale;

use Shopologic\Core\Session\SessionManager;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\I18n\Translator;

/**
 * Manages locale selection and switching
 */
class LocaleManager
{
    private SessionManager $session;
    private EventDispatcherInterface $eventDispatcher;
    private Translator $translator;
    private string $currentLocale;
    private string $defaultLocale;
    private array $availableLocales;

    public function __construct(
        SessionManager $session,
        EventDispatcherInterface $eventDispatcher,
        Translator $translator,
        array $config = []
    ) {
        $this->session = $session;
        $this->eventDispatcher = $eventDispatcher;
        $this->translator = $translator;
        
        $this->defaultLocale = $config['default_locale'] ?? 'en';
        $this->availableLocales = $config['available_locales'] ?? ['en'];
        
        $this->initializeLocale();
    }

    /**
     * Get current locale
     */
    public function getCurrentLocale(): string
    {
        return $this->currentLocale;
    }

    /**
     * Set current locale
     */
    public function setCurrentLocale(string $locale): bool
    {
        if (!$this->isAvailable($locale)) {
            return false;
        }
        
        $oldLocale = $this->currentLocale;
        $this->currentLocale = $locale;
        
        // Update translator
        $this->translator->setLocale($locale);
        
        // Store in session
        $this->session->set('locale', $locale);
        
        // Set PHP locale
        $this->setPhpLocale($locale);
        
        // Trigger event
        $this->eventDispatcher->dispatch('locale.changed', [
            'old_locale' => $oldLocale,
            'new_locale' => $locale
        ]);
        
        return true;
    }

    /**
     * Get default locale
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Get available locales
     */
    public function getAvailableLocales(): array
    {
        return $this->availableLocales;
    }

    /**
     * Check if locale is available
     */
    public function isAvailable(string $locale): bool
    {
        return in_array($locale, $this->availableLocales);
    }

    /**
     * Detect locale from request
     */
    public function detectFromRequest(\Shopologic\Core\Http\Request $request): ?string
    {
        // Check URL parameter
        $locale = $request->get('locale');
        if ($locale && $this->isAvailable($locale)) {
            return $locale;
        }
        
        // Check accept-language header
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $locale = Locale::fromAcceptLanguage($acceptLanguage, $this->availableLocales);
            if ($locale) {
                return $locale;
            }
        }
        
        return null;
    }

    /**
     * Get locale info
     */
    public function getLocaleInfo(string $locale): ?array
    {
        return Locale::getInfo($locale);
    }

    /**
     * Get locale name
     */
    public function getLocaleName(string $locale): string
    {
        return Locale::getName($locale);
    }

    /**
     * Get native locale name
     */
    public function getNativeLocaleName(string $locale): string
    {
        return Locale::getNativeName($locale);
    }

    /**
     * Check if current locale is RTL
     */
    public function isRtl(): bool
    {
        return Locale::isRtl($this->currentLocale);
    }

    /**
     * Get date format for current locale
     */
    public function getDateFormat(): string
    {
        return Locale::getDateFormat($this->currentLocale);
    }

    /**
     * Get time format for current locale
     */
    public function getTimeFormat(): string
    {
        return Locale::getTimeFormat($this->currentLocale);
    }

    /**
     * Format date according to current locale
     */
    public function formatDate(\DateTimeInterface $date, string $format = 'medium'): string
    {
        return $this->translator->formatDate($date, $format, $this->currentLocale);
    }

    /**
     * Format number according to current locale
     */
    public function formatNumber(float $number, int $decimals = 0): string
    {
        return $this->translator->formatNumber($number, $decimals, $this->currentLocale);
    }

    /**
     * Build locale switcher data
     */
    public function getLocaleSwitcherData(): array
    {
        $data = [];
        
        foreach ($this->availableLocales as $locale) {
            $info = Locale::getInfo($locale);
            if ($info) {
                $data[] = [
                    'code' => $locale,
                    'name' => $info['name'],
                    'native' => $info['native'],
                    'current' => $locale === $this->currentLocale,
                    'url' => $this->getLocaleSwitchUrl($locale)
                ];
            }
        }
        
        return $data;
    }

    // Private methods

    private function initializeLocale(): void
    {
        // Check session
        $locale = $this->session->get('locale');
        
        if ($locale && $this->isAvailable($locale)) {
            $this->currentLocale = $locale;
            return;
        }
        
        // Check store locale
        $store = app('current_store');
        if ($store && $store->locale && $this->isAvailable($store->locale)) {
            $this->currentLocale = $store->locale;
            return;
        }
        
        // Use default
        $this->currentLocale = $this->defaultLocale;
    }

    private function setPhpLocale(string $locale): void
    {
        $info = Locale::getInfo($locale);
        if (!$info) {
            return;
        }
        
        // Build locale strings
        $localeStrings = [
            $info['language'] . '_' . $info['region'] . '.UTF-8',
            $info['language'] . '_' . $info['region'],
            $info['language'] . '.UTF-8',
            $info['language']
        ];
        
        // Try to set locale
        setlocale(LC_ALL, ...$localeStrings);
        setlocale(LC_NUMERIC, 'C'); // Keep numeric in C locale for consistency
    }

    private function getLocaleSwitchUrl(string $locale): string
    {
        $request = app('request');
        $currentUrl = $request->getUri();
        
        // Parse URL
        $parts = parse_url($currentUrl);
        $query = [];
        
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        
        $query['locale'] = $locale;
        $parts['query'] = http_build_query($query);
        
        return $this->buildUrl($parts);
    }

    private function buildUrl(array $parts): string
    {
        $url = '';
        
        if (isset($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
        }
        
        if (isset($parts['host'])) {
            $url .= $parts['host'];
        }
        
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        
        if (isset($parts['path'])) {
            $url .= $parts['path'];
        }
        
        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }
        
        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }
        
        return $url;
    }
}